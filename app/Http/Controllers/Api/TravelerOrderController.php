<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\OrderProcess;
use App\Models\Trip;
use Illuminate\Http\Request;

class TravelerOrderController extends Controller
{
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
                'payment:id,transaction_id,payment_status,paid_at,amount',
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

    public function show(Request $request, $id)
    {
        $order = Transaction::where('traveler_id', $request->user()->id)
            ->with([
                'customer:id,name,phone,email,profile_photo,address',
                'trip:id,code,city,destination,departure_at,estimated_arrival_at,price',
                'pickupPoint:id,name,address,pickup_time,map_url',
                'collectionPoint:id,name,address,collections_time,map_url',
                'orderProcess',
                'payment:id,transaction_id,payment_status,paid_at,amount,payment_type,payment_channel',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    /**
     * Traveler accept order → status on_progress
     * Create OrderProcess → step waiting_payment
     */
    public function accept(Request $request, $id)
    {
        $order = Transaction::where('traveler_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $order->update(['status' => 'on_progress']);

        // Create order process
        OrderProcess::updateOrCreate(
            ['transaction_id' => $order->id],
            [
                'step'        => 'waiting_payment',
                'accepted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Order diterima. Menunggu pembayaran customer.',
        ]);
    }

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
        ]);

        OrderProcess::updateOrCreate(
            ['transaction_id' => $order->id],
            [
                'step'          => 'cancelled',
                'cancelled_at'  => now(),
                'cancel_reason' => $request->reason,
            ]
        );

        $order->trip?->decrement('used_capacity', $order->weight);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil ditolak.',
        ]);
    }

    /**
     * Update status: on_progress → on_the_way → finished
     * on_progress → on_the_way: HANYA jika sudah bayar via Midtrans
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:on_the_way,finished',
        ]);

        $order = Transaction::where('traveler_id', $request->user()->id)
            ->whereIn('status', ['on_progress', 'on_the_way'])
            ->with(['payment', 'orderProcess'])
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

        // Cek pembayaran Midtrans sebelum mulai kirim
        if ($order->status === 'on_progress' && $request->status === 'on_the_way') {
            $payment = $order->payment;
            if (!$payment || $payment->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer belum melakukan pembayaran.',
                ], 422);
            }
        }

        $order->update(['status' => $request->status]);

        // Update order process
        if ($request->status === 'on_the_way') {
            $order->orderProcess?->update([
                'step'      => 'in_delivery',
                'shipped_at' => now(),
            ]);
        }

        if ($request->status === 'finished') {
            $order->orderProcess?->update([
                'step'         => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status order berhasil diperbarui.',
        ]);
    }

    // Update price (titip-beli only)
    public function updatePrice(Request $request, $id)
    {
        $request->validate([
            'item_price'    => 'required|numeric|min:0',
            'receipt_photo' => 'nullable|image|max:2048',
            'price_notes'   => 'nullable|string|max:500',
        ]);

        $order = Transaction::where('traveler_id', $request->user()->id)
            ->where('status', 'on_progress')
            ->findOrFail($id);

        // Upload struk
        $receiptFile = $request->file('receipt_photo');

        if (!$receiptFile) {
            return response()->json([
                'success' => false,
                'message' => 'Foto struk tidak ditemukan',
            ], 422);
        }

        $receiptPath = $receiptFile->store('receipts', 'public');

        // Hitung total baru
        $itemPrice = (int) $request->input('item_price');
        $originalItemPrice = $order->item_price;
        $totalPrice = ((int)$itemPrice * (int)$order->quantity) + (int)$order->shipping_price;

        // Update order
        $order->update([
            'item_price'      => $itemPrice,
            'price'           => $totalPrice,
            'price_confirmed' => true,
        ]);

        // Update order process
        OrderProcess::updateOrCreate(
            ['transaction_id' => $order->id],
            [
                'original_item_price' => $originalItemPrice,
                'updated_item_price'  => $itemPrice,
                'updated_total_price' => $totalPrice,
                'receipt_photo'       => $receiptPath,
                'price_notes'         => $request->price_notes,
                'step'                => 'waiting_payment',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Harga berhasil diperbarui, menunggu pembayaran customer',
        ]);
    }

    public function byTrip(Request $request, $tripId)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->findOrFail($tripId);

        $orders = Transaction::where('trip_id', $trip->id)
            ->with(['customer:id,name,phone,profile_photo', 'payment:id,transaction_id,payment_status,paid_at'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id'             => $order->id,
                    'sku'            => $order->sku,
                    'customer'       => $order->customer?->name ?? 'Unknown',
                    'phone'          => $order->customer?->phone ?? '-',
                    'avatar'         => $order->customer?->profile_photo,
                    'item'           => $order->name,
                    'description'    => $order->description,
                    'order_type'     => $order->order_type,
                    'weight'         => $order->weight . ' kg',
                    'price'          => 'Rp ' . number_format($order->price, 0, ',', '.'),
                    'status'         => $order->status,
                    'payment_status' => $order->payment?->payment_status ?? null,
                    'created_at'     => $order->created_at->format('d M Y H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }
}