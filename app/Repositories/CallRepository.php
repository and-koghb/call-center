<?php

namespace App\Repositories;

use App\Models\Call;
use App\Enums\CallStatus;

class CallRepository
{
    public function find(int $id): ?Call
    {
        return Call::find($id);
    }

    public function createIncoming(string $phone): Call
    {
        return Call::create([
            'phone' => $phone,
            'client_id' => null,
            'operator_id' => null,
            'status' => CallStatus::NEW,
            'finished_at' => null,
        ]);
    }

    public function updateToAssigned(Call $call, ?int $clientId, int $operatorId): bool
    {
        return $call->update([
            'client_id' => $clientId,
            'operator_id' => $operatorId,
            'status' => CallStatus::ASSIGNED,
        ]);
    }

    public function updateStatus(Call $call, CallStatus $status): bool
    {
        return $call->update(['status' => $status]);
    }
}
