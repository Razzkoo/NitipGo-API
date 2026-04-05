<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    // Admin: read only route trips
    public function routes(Request $request)
    {
        $routes = DB::table('trips')
            ->select(
                'city',
                'destination',
                DB::raw('COUNT(*) as total_trips'),
                DB::raw('COUNT(DISTINCT traveler_id) as travelers'),
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_trips")
            )
            ->groupBy('city', 'destination')
            ->orderByDesc('total_trips')
            ->get()
            ->map(function ($row) {
                return [
                    'fromCity'     => $row->city,
                    'toCity'       => $row->destination,
                    'total_trips'  => (int) $row->total_trips,
                    'travelers'    => (int) $row->travelers,
                    'active_trips' => (int) $row->active_trips,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $routes,
        ]);
    }

    // Public/Customer : list traveler available trios
    public function available(Request $request)
    {
        $query = Trip::with(['traveler:id,name,phone,profile_photo,city', 'pickups', 'collections'])
            ->where('status', 'active')
            ->whereDate('departure_at', '>=', now()->toDateString());

        // Filter city
        if ($request->filled('from')) {
            $query->where('city', 'like', '%' . $request->from . '%');
        }

        // Filter destination
        if ($request->filled('to')) {
            $query->where('destination', 'like', '%' . $request->to . '%');
        }

        // Filter date
        if ($request->filled('date')) {
            $query->whereDate('departure_at', $request->date);
        }

        $trips = $query->orderBy('departure_at', 'asc')
            ->get()
            ->map(function ($trip) {
                $remaining = $trip->capacity - $trip->used_capacity;

                return [
                    'id'           => $trip->id,
                    'code'         => $trip->code,
                    'from'         => $trip->city,
                    'to'           => $trip->destination,
                    'date'         => $trip->departure_at->format('Y-m-d'),
                    'displayDate'  => $trip->departure_at->format('d M Y'),
                    'departureTime' => $trip->departure_at->format('H:i'),
                    'arrivalDate'  => $trip->estimated_arrival_at?->format('d M Y'),
                    'arrivalTime'  => $trip->estimated_arrival_at?->format('H:i'),
                    'capacity'     => $remaining > 0 ? "{$remaining} kg tersisa" : "Penuh",
                    'capacityRaw'  => $remaining,
                    'pricePerKg'   => $trip->price,
                    'price'        => 'Rp ' . number_format($trip->price, 0, ',', '.') . '/kg',
                    'notes'        => $trip->description,
                    'traveler'     => [
                        'id'     => $trip->traveler->id,
                        'name'   => $trip->traveler->name,
                        'phone'  => $trip->traveler->phone,
                        'photo'  => $trip->traveler->profile_photo,
                        'city'   => $trip->traveler->city,
                    ],
                    'pickup'       => $trip->pickups->first() ? [
                        'name'    => $trip->pickups->first()->name,
                        'address' => $trip->pickups->first()->address,
                        'time'    => $trip->pickups->first()->pickup_time
                                        ? \Carbon\Carbon::parse($trip->pickups->first()->pickup_time)->format('H:i')
                                        : null,
                        'mapUrl'  => $trip->pickups->first()->map_url,
                    ] : null,
                    'collection'   => $trip->collections->first() ? [
                        'name'    => $trip->collections->first()->name,
                        'address' => $trip->collections->first()->address,
                        'time'    => $trip->collections->first()->collections_time
                                        ? \Carbon\Carbon::parse($trip->collections->first()->collections_time)->format('H:i')
                                        : null,
                        'mapUrl'  => $trip->collections->first()->map_url,
                    ] : null,
                ];
            });

        // List
        $cities = Trip::where('status', 'active')
            ->where('departure_at', '>=', now())
            ->select('city', 'destination')
            ->get()
            ->flatMap(fn($t) => [$t->city, $t->destination])
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $trips,
            'cities'  => $cities,
        ]);
    }

    // Customer: Detail trip for customer
    public function show(Request $request, $id)
    {
        $trip = Trip::with([
            'traveler:id,name,phone,email,profile_photo,city,province',
            'pickups',
            'collections',
        ])
        ->where('status', 'active')
        ->findOrFail($id);

        $remaining = $trip->capacity - $trip->used_capacity;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $trip->id,
                'code'          => $trip->code,
                'from'          => $trip->city,
                'to'            => $trip->destination,
                'date'          => $trip->departure_at->format('d M Y'),
                'time'          => $trip->departure_at->format('H:i') . ' WIB',
                'arrivalDate'   => $trip->estimated_arrival_at?->format('d M Y'),
                'arrivalTime'   => $trip->estimated_arrival_at?->format('H:i') . ' WIB',
                'capacity'      => $remaining > 0 ? "{$remaining} kg tersisa" : "Penuh",
                'totalCapacity' => "{$trip->capacity} kg",
                'capacityRaw'   => $remaining,
                'price'         => 'Rp ' . number_format($trip->price, 0, ',', '.') . '/kg',
                'pricePerKg'    => $trip->price,
                'notes'         => $trip->description,
                'traveler'      => [
                    'id'       => $trip->traveler->id,
                    'name'     => $trip->traveler->name,
                    'phone'    => $trip->traveler->phone,
                    'email'    => $trip->traveler->email,
                    'photo'    => $trip->traveler->profile_photo,
                    'city'     => $trip->traveler->city,
                    'province' => $trip->traveler->province,
                ],
                'pickup'     => $trip->pickups->first() ? [
                    'name'    => $trip->pickups->first()->name,
                    'address' => $trip->pickups->first()->address,
                    'time'    => $trip->pickups->first()->pickup_time
                                    ? \Carbon\Carbon::parse($trip->pickups->first()->pickup_time)->format('H:i')
                                    : null,
                    'mapUrl'  => $trip->pickups->first()->map_url,
                ] : null,
                'collection' => $trip->collections->first() ? [
                    'name'    => $trip->collections->first()->name,
                    'address' => $trip->collections->first()->address,
                    'time'    => $trip->collections->first()->collections_time
                                    ? \Carbon\Carbon::parse($trip->collections->first()->collections_time)->format('H:i')
                                    : null,
                    'mapUrl'  => $trip->collections->first()->map_url,
                ] : null,
            ],
        ]);
    }
}