<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EmailLogService
{
    public function logEmail(array $data, string $status, ?string $errorMessage)
    {
        $logPayload = [
            'request' => $data,
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => now()->toIso8601String(),
        ];

        Log::channel('email')->info('Log email dibuat', $logPayload);
    }
}
