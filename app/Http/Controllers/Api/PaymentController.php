<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected MidtransService $midtrans;

    public function __construct(MidtransService $midtrans)
    {
        $this->midtrans = $midtrans;
    }

    /**
     * Buat pembayaran Midtrans (setelah traveler terima order)
     */
    public function createPayment(Request $request, $transactionId)
    {
        $customer = $request->user();

        $transaction = Transaction::where('customer_id', $customer->id)
            ->where('status', 'on_progress')
            ->with('trip')
            ->findOrFail($transactionId);

        // Cek payment pending yang sudah ada
        $existingPayment = Payment::where('transaction_id', $transaction->id)
            ->where('payment_status', 'pending')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'success' => true,
                'message' => 'Gunakan token pembayaran yang sudah ada.',
                'data'    => [
                    'snap_token' => $existingPayment->snap_token,
                    'snap_url'   => $this->midtrans->getSnapUrl(),
                    'client_key' => $this->midtrans->getClientKey(),
                    'payment_id' => $existingPayment->id,
                ],
            ]);
        }

        $grossAmount = (int) $transaction->price;

        // Item details
        $items = [];
        $items[] = [
            'id'       => 'SHIPPING-' . $transaction->id,
            'price'    => (int) $transaction->shipping_price,
            'quantity' => 1,
            'name'     => 'Ongkir ' . ($transaction->trip->city ?? '') . ' → ' . ($transaction->trip->destination ?? ''),
        ];

        if ($transaction->order_type === 'titip-beli' && $transaction->item_price > 0) {
            $items[] = [
                'id'       => 'ITEM-' . $transaction->id,
                'price'    => (int) $transaction->item_price,
                'quantity' => $transaction->quantity,
                'name'     => Str::limit($transaction->name, 50),
            ];
        }

        $midtransOrderId  = 'NTG-' . $transaction->id . '-' . time();
        $paymentReference = 'PAY-' . strtoupper(Str::random(12));

        try {
            $params = $this->midtrans->buildOrderPaymentParams(
                $midtransOrderId,
                $grossAmount,
                [
                    'name'  => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                $items
            );

            $snapToken = $this->midtrans->createSnapToken($params);

            $payment = Payment::create([
                'transaction_id'    => $transaction->id,
                'user_id'           => $customer->id,
                'traveler_id'       => $transaction->traveler_id,
                'snap_token'        => $snapToken,
                'midtrans_order_id' => $midtransOrderId,
                'amount'            => $grossAmount,
                'fee'               => 0,
                'total_paid'        => $grossAmount,
                'payment_status'    => 'pending',
                'payment_reference' => $paymentReference,
                'expired_at'        => now()->addHours(24),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token pembayaran berhasil dibuat.',
                'data'    => [
                    'snap_token' => $snapToken,
                    'snap_url'   => $this->midtrans->getSnapUrl(),
                    'client_key' => $this->midtrans->getClientKey(),
                    'payment_id' => $payment->id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans create token error: ' . $e->getMessage());

            $message = 'Gagal membuat pembayaran. Silakan coba lagi.';
            if (config('app.debug') && str_contains($e->getMessage(), '401')) {
                $message = 'Midtrans authentication failed: Server key tidak valid. Periksa MIDTRANS_SERVER_KEY di .env';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'debug'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cek status pembayaran
     */
    public function checkStatus(Request $request, $transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)
            ->latest()
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_status'  => $payment->payment_status,
                'payment_type'    => $payment->payment_type,
                'payment_channel' => $payment->payment_channel,
                'paid_at'         => $payment->paid_at,
                'amount'          => $payment->amount,
                'snap_token'      => $payment->payment_status === 'pending' ? $payment->snap_token : null,
                'snap_url'        => $payment->payment_status === 'pending' ? $this->midtrans->getSnapUrl() : null,
                'client_key'      => $this->midtrans->getClientKey(),
            ],
        ]);
    }

    /**
     * Midtrans Webhook Notification
     * URL: POST /api/midtrans/notification (tanpa auth)
     */
    public function handleNotification(Request $request)
    {
        try {
            $notification = $this->midtrans->handleNotification();

            $orderId           = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus       = $notification->fraud_status ?? 'accept';
            $paymentType       = $notification->payment_type ?? null;

            $payment = Payment::where('midtrans_order_id', $orderId)->first();

            if (!$payment) {
                Log::warning("Midtrans notification: payment not found for {$orderId}");
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Simpan detail Midtrans
            $payment->midtrans_transaction_id = $notification->transaction_id ?? null;
            $payment->payment_type            = $paymentType;
            $payment->payment_channel         = $this->resolveChannel($notification);
            $payment->va_number               = $this->resolveVaNumber($notification);

            // Update status
            if ($transactionStatus === 'capture') {
                $payment->payment_status = ($fraudStatus === 'accept') ? 'paid' : 'failed';
            } elseif ($transactionStatus === 'settlement') {
                $payment->payment_status = 'paid';
            } elseif (in_array($transactionStatus, ['cancel', 'deny'])) {
                $payment->payment_status = 'failed';
            } elseif ($transactionStatus === 'expire') {
                $payment->payment_status = 'expired';
            } elseif ($transactionStatus === 'pending') {
                $payment->payment_status = 'pending';
            }

            // Pembayaran berhasil
            if ($payment->payment_status === 'paid' && !$payment->paid_at) {
                $payment->paid_at = now();
                $this->onPaymentSuccess($payment);
            }

            if (in_array($payment->payment_status, ['failed', 'expired'])) {
                $this->onPaymentFailed($payment);
            }

            $payment->save();

            return response()->json(['message' => 'OK']);
        } catch (\Exception $e) {
            Log::error('Midtrans notification error: ' . $e->getMessage());
            return response()->json(['message' => 'Error'], 500);
        }
    }

    /**
     * Pembayaran berhasil → update order process + tambah saldo traveler
     */
    private function onPaymentSuccess(Payment $payment): void
    {
        $transaction = $payment->transaction;
        if (!$transaction) return;

        // Update order_process
        $orderProcess = $transaction->orderProcess;
        if ($orderProcess) {
            $orderProcess->update([
                'step'    => 'paid',
                'paid_at' => now(),
            ]);
        }

        // 100% masuk saldo traveler
        if ($transaction->traveler_id) {
            DB::table('travelers')
                ->where('id', $transaction->traveler_id)
                ->increment('balance', $payment->amount);
        }
    }

    private function onPaymentFailed(Payment $payment): void
    {
        // Customer bisa coba bayar lagi (buat snap token baru)
    }

    private function resolveChannel($notification): ?string
    {
        if (isset($notification->va_numbers[0]->bank)) {
            return $notification->va_numbers[0]->bank;
        }
        if (isset($notification->permata_va_number)) {
            return 'permata';
        }
        if (isset($notification->bill_key)) {
            return 'mandiri_bill';
        }
        return $notification->payment_type ?? null;
    }

    private function resolveVaNumber($notification): ?string
    {
        if (isset($notification->va_numbers[0]->va_number)) {
            return $notification->va_numbers[0]->va_number;
        }
        if (isset($notification->permata_va_number)) {
            return $notification->permata_va_number;
        }
        if (isset($notification->bill_key)) {
            return $notification->bill_key;
        }
        return null;
    }

    /**
 * Sync status pembayaran dari Midtrans (dipanggil setelah Snap close)
 */
    public function syncStatus(Request $request, $transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)
            ->latest()
            ->firstOrFail();

        if ($payment->payment_status === 'paid') {
            return response()->json(['success' => true, 'data' => ['payment_status' => 'paid']]);
        }

        if (!$payment->midtrans_order_id) {
            return response()->json(['success' => false, 'message' => 'No midtrans order'], 400);
        }

        try {
            // Query langsung ke Midtrans API
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');

            $status = \Midtrans\Transaction::status($payment->midtrans_order_id);

            $transactionStatus = $status->transaction_status ?? null;
            $fraudStatus = $status->fraud_status ?? 'accept';
            $paymentType = $status->payment_type ?? null;

            // Update payment details
            $payment->midtrans_transaction_id = $status->transaction_id ?? null;
            $payment->payment_type = $paymentType;
            $payment->payment_channel = $this->resolveChannelFromStatus($status);
            $payment->va_number = $this->resolveVaNumberFromStatus($status);

            if ($transactionStatus === 'capture') {
                $payment->payment_status = ($fraudStatus === 'accept') ? 'paid' : 'failed';
            } elseif ($transactionStatus === 'settlement') {
                $payment->payment_status = 'paid';
            } elseif (in_array($transactionStatus, ['cancel', 'deny'])) {
                $payment->payment_status = 'failed';
            } elseif ($transactionStatus === 'expire') {
                $payment->payment_status = 'expired';
            }

            if ($payment->payment_status === 'paid' && !$payment->paid_at) {
                $payment->paid_at = now();
                $this->onPaymentSuccess($payment);
            }

            $payment->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_status' => $payment->payment_status,
                    'payment_type'   => $payment->payment_type,
                    'paid_at'        => $payment->paid_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans sync status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal sync status pembayaran.',
            ], 500);
        }
    }

    private function resolveChannelFromStatus($status): ?string
    {
        if (isset($status->va_numbers[0]->bank)) return $status->va_numbers[0]->bank;
        if (isset($status->permata_va_number)) return 'permata';
        if (isset($status->bill_key)) return 'mandiri_bill';
        return $status->payment_type ?? null;
    }

    private function resolveVaNumberFromStatus($status): ?string
    {
        if (isset($status->va_numbers[0]->va_number)) return $status->va_numbers[0]->va_number;
        if (isset($status->permata_va_number)) return $status->permata_va_number;
        if (isset($status->bill_key)) return $status->bill_key;
        return null;
    }


    // Admin list transactions
    public function adminIndex(Request $request)
    {
        $query = Payment::with([
            'transaction:id,customer_id,traveler_id,trip_id,order_type,name',
            'transaction.customer:id,name',
            'transaction.traveler:id,name,city',
            'transaction.trip:id,city,destination',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('midtrans_order_id', 'like', '%' . $request->search . '%')
                ->orWhereHas('transaction.customer', fn($q2) =>
                    $q2->where('name', 'like', '%' . $request->search . '%'))
                ->orWhereHas('transaction.traveler', fn($q2) =>
                    $q2->where('name', 'like', '%' . $request->search . '%'));
            });
        }

        $payments = $query->paginate(20);

        $payments->getCollection()->transform(fn($p) => [
            'id'              => $p->id,
            'orderId'         => $p->midtrans_order_id ?? 'PAY-' . $p->id,
            'customer'        => $p->transaction?->customer?->name ?? '-',
            'traveler'        => $p->transaction?->traveler?->name ?? '-',
            'route'           => $p->transaction?->trip
                ? $p->transaction->trip->city . ' → ' . $p->transaction->trip->destination
                : '-',
            'amount'          => $p->amount,
            'paymentStatus'   => $p->payment_status,   // pending/paid/failed/expired
            'paymentChannel'  => $p->payment_channel ?? $p->payment_type ?? '-',
            'paymentType'     => $p->payment_type ?? '-',
            'orderType'       => $p->transaction?->order_type ?? '-',
            'date'            => $p->created_at->format('d M Y'),
            'paidAt'          => $p->paid_at?->format('d M Y H:i'),
        ]);

        // Stats
        $stats = \DB::table('payments')->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN payment_status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN payment_status IN ("failed","expired") THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as total_volume
        ')->first();

        return response()->json([
            'success' => true,
            'data'    => $payments,
            'stats'   => [
                'total'        => (int) $stats->total,
                'paid'         => (int) $stats->paid,
                'pending'      => (int) $stats->pending,
                'failed'       => (int) $stats->failed,
                'total_volume' => (float) $stats->total_volume,
            ],
        ]);
    }
}