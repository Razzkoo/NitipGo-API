<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\OrderProcess;
use App\Models\Trip;
use Illuminate\Http\Request;

class TravelerOrderController extends Controller
{
    // All order
    public function index(Request $request)
    {
        $traveler = $request->user();

        $query = Transaction::where('traveler_id', $traveler->id)
            ->with([
                'customer:id,name,phone,email,profile_photo,address',
                'trip:id,code,city,destination,departure_at,estimated_arrival_at,price',
                'pickupPoint:id,name,address,pickup_time,map_url',
                'collectionPoint:id,name,address,collections_time,map_url',
                'orderProcess',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->trip_id);
        }

        $orders = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    // Show detail order
    public function show(Request $request, $id)
    {
        $order = Transaction::where('traveler_id', $request->user()->id)
            ->with([
                'customer:id,name,phone,email,profile_photo,address',
                'trip:id,code,city,destination,departure_at,estimated_arrival_at,price',
                'pickupPoint:id,name,address,pickup_time,map_url',
                'collectionPoint:id,name,address,collections_time,map_url',
                'orderProcess',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    // Accept order by traveler
    public function accept(Request $request, $id)
    {
        $order = Transaction::where('traveler_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $order->update(['status' => 'on_progress']);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil diterima.',
        ]);
    }

    // Reject order by traveler + reason
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = Transaction::where('traveler_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $order->update([
            'status' => 'cancelled',
            'notes'  => $request->reason,
        ]);

        $order->trip?->decrement('used_capacity', $order->weight);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil ditolak.',
        ]);
    }

    /**
     * Update harga barang (HANYA titip-beli & on_progress)
     * Upload struk + harga final → customer harus bayar
     */
    public function updatePrice(Request $request, $id)
    {
        $order = Transaction::where('traveler_id', $request->user()->id)
            ->where('status', 'on_progress')
            ->where('order_type', 'titip-beli')
            ->findOrFail($id);

        $validated = $request->validate([
            'item_price'    => 'required|numeric|min:0',
            'receipt_photo' => 'required|image|max:5120',
            'price_notes'   => 'nullable|string|max:500',
        ], [
            'item_price.required'    => 'Harga barang wajib diisi.',
            'receipt_photo.required' => 'Foto struk wajib diupload.',
        ]);

        $receiptPath = $request->file('receipt_photo')->store('receipts', 'public');

        $originalItemPrice = $order->item_price;
        $newItemPrice      = $validated['item_price'];
        $newItemTotal      = $newItemPrice * $order->quantity;
        $newTotalPrice     = $order->shipping_price + $newItemTotal;

        OrderProcess::updateOrCreate(
            ['transaction_id' => $order->id],
            [
                'original_item_price' => $originalItemPrice,
                'updated_item_price'  => $newItemPrice,
                'updated_total_price' => $newTotalPrice,
                'receipt_photo'       => $receiptPath,
                'price_notes'         => $validated['price_notes'] ?? null,
                'status'              => 'waiting_payment',
            ]
        );

        $order->update([
            'item_price'      => $newItemPrice,
            'price'           => $newTotalPrice,
            'price_confirmed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Harga diperbarui. Menunggu pembayaran customer.',
        ]);
    }

    /**
     * Update status: on_progress → on_the_way → finished
     * titip-beli: harus price_confirmed + paid_at sebelum on_the_way
     * kirim: langsung bisa on_the_way
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:on_progress,on_the_way,finished',
        ]);

        $order = Transaction::where('traveler_id', $request->user()->id)
            ->whereIn('status', ['on_progress', 'on_the_way'])
            ->findOrFail($id);

        $allowedTransitions = [
            'on_progress' => 'on_the_way',
            'on_the_way'  => 'finished',
        ];

        $expectedNext = $allowedTransitions[$order->status] ?? null;
        if ($request->status !== $expectedNext) {
            return response()->json([
                'success' => false,
                'message' => "Status hanya bisa diubah ke '{$expectedNext}'.",
            ], 422);
        }

        // Cek pembayaran untuk titip-beli
        if ($order->status === 'on_progress' && $request->status === 'on_the_way') {
            if ($order->order_type === 'titip-beli') {
                if (!$order->price_confirmed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Update harga dan upload struk terlebih dahulu.',
                    ], 422);
                }
                if (!$order->paid_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Menunggu pembayaran dari customer.',
                    ], 422);
                }
            }
        }

        $order->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status order berhasil diperbarui.',
        ]);
    }

    public function byTrip(Request $request, $tripId)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->findOrFail($tripId);

        $orders = Transaction::where('trip_id', $trip->id)
            ->with(['customer:id,name,phone,profile_photo', 'orderProcess'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id'              => $order->id,
                    'sku'             => $order->sku,
                    'customer'        => $order->customer?->name ?? 'Unknown',
                    'phone'           => $order->customer?->phone ?? '-',
                    'avatar'          => $order->customer?->profile_photo,
                    'item'            => $order->name,
                    'description'     => $order->description,
                    'order_type'      => $order->order_type,
                    'weight'          => $order->weight . ' kg',
                    'price'           => 'Rp ' . number_format($order->price, 0, ',', '.'),
                    'status'          => $order->status,
                    'price_confirmed' => $order->price_confirmed,
                    'paid_at'         => $order->paid_at,
                    'created_at'      => $order->created_at->format('d M Y H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }
}