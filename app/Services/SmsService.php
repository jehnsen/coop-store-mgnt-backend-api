<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private const SEMAPHORE_API_URL = 'https://api.semaphore.co/api/v4/messages';

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $senderName = 'COOP'
    ) {}

    /**
     * Send an SMS via Semaphore and log the result.
     *
     * @return SmsLog
     */
    public function send(
        int    $storeId,
        int    $userId,
        string $recipientPhone,
        string $recipientName,
        string $message,
        string $type = 'reminder'
    ): SmsLog {
        $log = SmsLog::create([
            'store_id'       => $storeId,
            'user_id'        => $userId,
            'recipient_phone' => $recipientPhone,
            'recipient_name' => $recipientName,
            'message'        => $message,
            'type'           => $type,
            'status'         => 'pending',
            'provider'       => 'semaphore',
        ]);

        if (empty($this->apiKey)) {
            $log->update([
                'status'        => 'failed',
                'error_message' => 'SEMAPHORE_API_KEY is not configured.',
            ]);

            Log::warning('SmsService: SEMAPHORE_API_KEY not set. SMS not sent.', [
                'recipient' => $recipientPhone,
                'type'      => $type,
            ]);

            return $log;
        }

        try {
            $response = Http::timeout(15)->post(self::SEMAPHORE_API_URL, [
                'apikey'     => $this->apiKey,
                'number'     => $recipientPhone,
                'message'    => $message,
                'sendername' => $this->senderName,
            ]);

            $body = $response->json();

            if ($response->successful() && ! empty($body)) {
                $first = is_array($body) ? ($body[0] ?? $body) : $body;
                $log->update([
                    'status'              => 'sent',
                    'provider_message_id' => $first['message_id'] ?? null,
                    'provider_response'   => json_encode($body),
                    'credits_used'        => $first['credit_cost'] ?? 1,
                    'sent_at'             => now(),
                ]);
            } else {
                $log->update([
                    'status'            => 'failed',
                    'provider_response' => json_encode($body),
                    'error_message'     => 'Semaphore returned a non-success response.',
                ]);
            }
        } catch (\Exception $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('SmsService: Exception while sending SMS.', [
                'recipient' => $recipientPhone,
                'error'     => $e->getMessage(),
            ]);
        }

        return $log;
    }
}
