<?php
/**
 * Payment Gateway — Fawaterak Integration
 * ========================================
 * Handles communication with Fawaterak API v2.
 */
require_once __DIR__ . '/payment-config.php';

class HmsPaymentGateway
{
    private const API_BASE = 'https://app.fawaterk.com/api/v2';

    public static function verifyCallback(array $data): array
    {
        if (HMS_PAYMENT_DEMO_MODE) {
            return [
                'success' => true,
                'appointment_id' => (int)($data['apid'] ?? 0),
                'transaction_id' => $data['ref'] ?? 'DEMO_' . time(),
                'channel' => $data['channel'] ?? 'demo',
            ];
        }

        // Fawaterak Webhook
        $status = $data['invoice_status'] ?? '';
        $invoiceId = $data['invoice_id'] ?? '';
        $apptId = (int)($data['custom_ref'] ?? 0);

        if ($status === 'paid' && $apptId > 0) {
            return [
                'success' => true,
                'appointment_id' => $apptId,
                'transaction_id' => (string)$invoiceId,
                'channel' => 'fawaterak',
            ];
        }

        return ['success' => false, 'error' => 'Payment not paid or invalid data'];
    }

    public static function createPayment(array $params): array
    {
        $channel = $params['channel'] ?? '';

        if ($channel === 'instapay') {
            return self::createInstapayPayment($params);
        }

        if (HMS_PAYMENT_DEMO_MODE) {
            return self::createDemoPayment($params);
        }

        return self::createFawaterakPayment($params);
    }

    private static function createFawaterakPayment(array $params): array
    {
        $channelId = ($params['channel'] === 'fawry') ? FAWATERAK_FAWRY_ID : FAWATERAK_WALLET_ID;
        
        $payload = [
            'cartTotal' => (int)$params['amount'],
            'currency' => HMS_CURRENCY,
            'customer' => [
                'first_name' => $params['patient_name'] ?: 'Patient',
                'last_name' => 'HMS',
                'email' => $params['patient_email'] ?: 'patient@example.com',
                'phone' => $params['patient_phone'] ?: '01000000000',
            ],
            'cartItems' => [
                [
                    'name' => 'Medical Reservation #' . $params['appointment_id'],
                    'price' => (int)$params['amount'],
                    'quantity' => 1
                ]
            ],
            'payment_method_id' => (int)$channelId,
            'returnUrl' => HMS_BASE_URL . '/modules/patient/calender.php',
            'callbackUrl' => HMS_BASE_URL . '/includes/payment-callback.php',
            'custom_ref' => (string)$params['appointment_id'],
        ];

        $response = self::httpPost(self::API_BASE . '/createInvoiceLink', $payload);

        if ($response && isset($response['status']) && $response['status'] === 'success') {
            return [
                'success' => true,
                'redirect_url' => $response['data']['url'] ?? '',
                'invoice_id' => $response['data']['invoice_id'] ?? '',
            ];
        }

        return ['success' => false, 'error' => $response['message'] ?? 'Fawaterak request failed'];
    }

    private static function createDemoPayment(array $params): array
    {
        $ref = 'DEMO_' . strtoupper(substr(md5(uniqid()), 0, 10));
        $channel = $params['channel'] ?? 'fawry';

        $queryParams = [
            'apid' => $params['appointment_id'],
            'ref' => $ref,
            'amount' => $params['amount'],
            'channel' => $channel,
        ];

        if ($channel === 'fawry') {
            $queryParams['fawry_ref'] = '988' . rand(100000, 999999);
        }

        return [
            'success' => true,
            'demo' => true,
            'redirect_url' => HMS_BASE_URL . '/includes/payment-demo.php?' . http_build_query($queryParams),
        ];
    }

    private static function createInstapayPayment(array $params): array
    {
        return [
            'success' => true,
            'manual' => true,
            'redirect_url' => HMS_BASE_URL . '/modules/patient/pay-deposit.php?apid=' . $params['appointment_id'] . '&channel=instapay',
        ];
    }

    private static function httpPost(string $url, array $body): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . FAWATERAK_API_KEY
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }
}
