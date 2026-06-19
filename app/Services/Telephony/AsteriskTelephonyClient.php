<?php

namespace App\Services\Telephony;

use App\Contracts\TelephonyClientInterface;
use App\Models\Call;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsteriskTelephonyClient implements TelephonyClientInterface
{
    public function sendCallAssigned(Call $call, int $operatorId): void
    {
        $response = Http::timeout((int) config('services.asterisk.timeout'))
            ->withToken(config('services.asterisk.token'))
            ->post(config('services.asterisk.url') . '/channels/bridge', [
                'channel_id'  => $call->phone,
                'operator_id' => $operatorId,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Asterisk API returned status: " . $response->status());
        }

        Log::debug("Asterisk bridge triggered successfully", [
            'call_id' => $call->id,
            'operator_id' => $operatorId
        ]);
    }
}
