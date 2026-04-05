<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerOrderController extends Controller
{
    // Add new order by customer
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id'              => 'required|exists:trips,id',
            'order_type'           => 'required|in:titip-beli,kirim',
            'name'                 => 'required|string|max:255',
            'description'          => 'required|string|max:1000',
            'weight'               => 'required|numeric|min:0.1',
            'quantity'             => 'nullable|integer|min:1',
            'item_price'           => 'nullable|numeric|min:0',
            'photo'                => 'nullable|image|max:5120',
            'notes'                => 'nullable|string|max:500',
            'recipient_name'       => 'nullable|required_if:order_type,kirim|string|max:255',
            'recipient_phone'      => 'nullable|required_if:order_type,kirim|string|max:20',
            'destination_address'  => 'nullable|required_if:order_type,kirim|string|max:1000',
        ], [
            'trip_id.required'             => 'Perjalanan wajib dipilih.',
            'trip_id.exists'               => 'Perjalanan tidak ditemukan.',
            'name.required'                => 'Nama barang wajib diisi.',
            'description.required'         => 'Deskripsi barang wajib diisi.',
            'weight.required'              => 'Berat barang wajib diisi.',
            'weight.min'                   => 'Berat minimal 0.1 kg.',
            'recipient_name.required_if'   => 'Nama penerima wajib diisi untuk pengiriman.',
            'recipient_phone.required_if'  => 'Nomor telepon penerima wajib diisi untuk pengiriman.',
            'destination_address.required_if' => 'Alamat penerima wajib diisi untuk pengiriman.',
        ]);

        $customer = $request->user();

        $trip = Trip::with(['traveler', 'pickups', 'collections'])
            ->where('status', 'active')
            ->findOrFail($validated['trip_id']);

        $remaining = $trip->capacity - $trip->used_capacity;
        if ($validated['weight'] > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Kapasitas tidak cukup. Tersisa {$remaining} kg.",
            ], 422);
        }

        $weight       = $validated['weight'];
        $pricePerKg   = $trip->price;
        $shippingCost = $weight * $pricePerKg;

        $itemPrice = 0;
        $quantity  = 1;
        if ($validated['order_type'] === 'titip-beli') {
            $itemPrice = $validated['item_price'] ?? 0;
            $quantity  = $validated['quantity'] ?? 1;
        }

        $itemTotal  = $itemPrice * $quantity;
        $totalPrice = $shippingCost + $itemTotal;

        $imagePath = null;
        if ($request->hasFile('photo')) {
            $imagePath = $request->file('photo')->store('orders', 'public');
        }

        $pickupPoint     = $trip->pickups->first();
        $collectionPoint = $trip->collections->first();
        $sku             = 'ORD-' . strtoupper(Str::random(8));

        $transaction = Transaction::create([
            'traveler_id'         => $trip->traveler_id,
            'customer_id'         => $customer->id,
            'trip_id'             => $trip->id,
            'pickup_point_id'     => $pickupPoint?->id,
            'collection_point_id' => $validated['order_type'] === 'kirim' ? $collectionPoint?->id : null,
            'sku'                 => $sku,
            'order_type'          => $validated['order_type'],
            'name'                => $validated['name'],
            'description'         => $validated['description'],
            'arrival_date'        => $trip->estimated_arrival_at?->toDateString(),
            'quantity'            => $quantity,
            'item_price'          => $validated['order_type'] === 'titip-beli' ? $itemPrice : null,
            'destination_address' => $validated['destination_address'] ?? null,
            'notes'               => $validated['notes'] ?? null,
            'weight'              => $weight,
            'commission'          => 0,
            'shipping_price'      => $shippingCost,
            'price'               => $totalPrice,
            'image'               => $imagePath,
            'recipient_name'      => $validated['recipient_name'] ?? null,
            'recipient_phone'     => $validated['recipient_phone'] ?? null,
            'status'              => 'pending',
            'price_confirmed'     => false,
        ]);

        $trip->increment('used_capacity', $weight);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibuat.',
            'data'    => ['id' => $transaction->id, 'sku' => $transaction->sku],
        ], 201);
    }

    // All order 
    public function index(Request $request)
    {
        $orders = Transaction::where('customer_id', $request->user()->id)
            ->with([
                'traveler:id,name,profile_photo',
                'trip:id,city,destination',
                'orderProcess',
            ])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    // Show detail order
    public function show(Request $request, $id)
    {
        $order = Transaction::where('customer_id', $request->user()->id)
            ->with([
                'traveler:id,name,phone,email,profile_photo,city',
                'traveler.payoutAccounts',
                'trip:id,city,destination,departure_at,estimated_arrival_at,price',
                'pickupPoint',
                'collectionPoint',
                'orderProcess',
                'payment',
                'rating',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    /**
     * Customer upload bukti pembayaran
     * Hanya untuk titip-beli yang sudah price_confirmed
     */
    public function uploadPayment(Request $request, $id)
    {
        $order = Transaction::where('customer_id', $request->user()->id)
            ->where('status', 'on_progress')
            ->where('order_type', 'titip-beli')
            ->where('price_confirmed', true)
            ->whereNull('paid_at')
            ->findOrFail($id);

        $request->validate([
            'payment_proof' => 'required|image|max:5120',
        ], [
            'payment_proof.required' => 'Bukti pembayaran wajib diupload.',
            'payment_proof.image'    => 'File harus berupa gambar.',
        ]);

        $path = $request->file('payment_proof')->store('payments', 'public');

        $order->update([
            'payment_proof' => $path,
            'paid_at'       => now(),
        ]);

        // Update order_process status
        $order->orderProcess?->update(['status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diupload.',
        ]);
    }

    // Cancelled order by customer (pending only)
    public function cancel(Request $request, $id)
    {
        $order = Transaction::where('customer_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $order->update(['status' => 'cancelled']);
        $order->trip?->decrement('used_capacity', $order->weight);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibatalkan.',
        ]);
    }
}