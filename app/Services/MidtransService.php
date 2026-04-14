<?php

namespace App\Services;

use Midtrans\Snap;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey    = config('midtrans.server_key');
        Config::$clientKey    = config('midtrans.client_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized  = config('midtrans.is_sanitized');
        Config::$is3ds        = config('midtrans.is_3ds');
    }

    public function createSnapToken(array $params): string
    {
        return Snap::getSnapToken($params);
    }

    public function buildOrderPaymentParams(
        string $orderId,
        int $grossAmount,
        array $customer,
        array $items
    ): array {
        return [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customer['name'] ?? 'Customer',
                'email'      => $customer['email'] ?? '',
                'phone'      => $customer['phone'] ?? '',
            ],
            'item_details' => $items,
            'callbacks' => [
                'finish' => config('app.frontend_url', 'http://localhost:5173') . '/orders',
            ],
        ];
    }

    public function handleNotification(): Notification
    {
        return new Notification();
    }

    public function getClientKey(): string
    {
        return config('midtrans.client_key');
    }

    public function getSnapUrl(): string
    {
        return config('midtrans.snap_url');
    }
}