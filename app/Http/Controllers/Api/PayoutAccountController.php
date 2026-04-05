<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutAccountController extends Controller
{
    // index traveler
    public function index(Request $request)
    {
        $accounts = PayoutAccount::where('traveler_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    // create payment account
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payout_type'    => ['required', Rule::in(['bank', 'e_wallet'])],
            'provider'       => ['required', Rule::in(['bca', 'bni', 'mandiri', 'ovo', 'dana', 'gopay'])],
            'account_name'   => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
        ]);

        $travelerId = $request->user()->id;

        // Checked
        $exists = PayoutAccount::where('traveler_id', $travelerId)
            ->where('provider', $validated['provider'])
            ->where('account_number', $validated['account_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening dengan provider dan nomor ini sudah terdaftar.',
            ], 409);
        }

        // default no account
        $hasDefault = PayoutAccount::where('traveler_id', $travelerId)
            ->where('is_default', true)
            ->exists();

        $account = PayoutAccount::create([
            'traveler_id'    => $travelerId,
            'payout_type'    => $validated['payout_type'],
            'provider'       => $validated['provider'],
            'account_name'   => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'is_default'     => !$hasDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil ditambahkan.',
            'data'    => $account,
        ], 201);
    }

    // Delete payment account
    public function destroy(Request $request, $id)
    {
        $account = PayoutAccount::where('id', $id)
            ->where('traveler_id', $request->user()->id)
            ->firstOrFail();

        $wasDefault = $account->is_default;
        $account->delete();

        if ($wasDefault) {
            $first = PayoutAccount::where('traveler_id', $request->user()->id)->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil dihapus.',
        ]);
    }

    // Set payment account
    public function setDefault(Request $request, $id)
    {
        $travelerId = $request->user()->id;

        $account = PayoutAccount::where('id', $id)
            ->where('traveler_id', $travelerId)
            ->firstOrFail();

        // Reset default
        PayoutAccount::where('traveler_id', $travelerId)
            ->update(['is_default' => false]);

        $account->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Rekening utama berhasil diubah.',
        ]);
    }
}