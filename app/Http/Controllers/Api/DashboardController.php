<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WithdrawRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Traveler dashboard
    public function traveler(Request $request)
    {
        $traveler = $request->user();

        // Stats
        $totalTrips = $traveler->trips()->count();

        $totalOrdersFinished = $traveler->transactions()
            ->where('status', 'finished')
            ->count();

        $totalIncome = Payment::where('traveler_id', $traveler->id)
            ->where('payment_status', 'paid')
            ->sum('amount');
        
        $incomeThisMonth = Payment::where('traveler_id', $traveler->id)
            ->where('payment_status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $totalWithdraw = WithdrawRequest::where('traveler_id', $traveler->id)
            ->whereIn('withdraw_status', ['approved', 'paid'])
            ->sum('amount');

        $balance = (float) $totalIncome - (float) $totalWithdraw;

        $avgRating = round($traveler->ratings()->avg('rating') ?? 0, 1);

        // Upcoming trips (active, departure >= today, max 2)
        $upcomingTrips = $traveler->trips()
            ->where('status', 'active')
            ->whereDate('departure_at', '>=', now()->toDateString())
            ->withCount('transactions')
            ->with('transactions:id,trip_id,weight,status')
            ->orderBy('departure_at', 'asc')
            ->limit(2)
            ->get()
            ->map(function ($trip) {
                $actualUsed = $trip->transactions
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('weight');

                $capacityPercent = $trip->capacity > 0
                    ? round(($actualUsed / $trip->capacity) * 100)
                    : 0;

                return [
                    'id'              => $trip->id,
                    'from'            => $trip->city,
                    'to'              => $trip->destination,
                    'date'            => $trip->departure_at->format('d M Y'),
                    'departureTime'   => $trip->departure_at->format('H:i'),
                    'orders'          => $trip->transactions_count,
                    'capacity'        => "{$actualUsed}/{$trip->capacity} kg",
                    'capacityPercent' => $capacityPercent,
                    'status'          => $trip->status,
                ];
            });

        // Pending orders (status pending, max 5)
        $pendingOrders = $traveler->transactions()
            ->where('status', 'pending')
            ->with('customer:id,name,phone,profile_photo', 'trip:id,city,destination')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id'       => $order->id,
                    'sku'      => $order->sku,
                    'customer' => $order->customer?->name ?? 'Unknown',
                    'item'     => $order->name,
                    'weight'   => $order->weight . ' kg',
                    'price'    => 'Rp ' . number_format($order->price, 0, ',', '.'),
                    'route'    => ($order->trip?->city ?? '?') . ' → ' . ($order->trip?->destination ?? '?'),
                    'order_type' => $order->order_type,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'total_trips'     => $totalTrips,
                    'orders_finished' => $totalOrdersFinished,
                    'balance'         => $balance,
                    'rating'          => $avgRating,
                    'income_this_month' => (float) $incomeThisMonth,
                ],
                'upcoming_trips'  => $upcomingTrips,
                'pending_orders'  => $pendingOrders,
            ],
        ]);
    }
}