<?php

namespace App\Repositories;

use App\Models\Operator;
use App\Enums\OperatorStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OperatorRepository
{
    public function popMostAvailableId(): ?int
    {
        $popped = Redis::zpopmin('operators:available', 1);
        return $popped ? (int) array_key_first($popped) : null;
    }

    public function tryLockAndBindBusy(int $operatorId): ?Operator
    {
        return DB::transaction(function () use ($operatorId) {
            $operator = Operator::where('id', $operatorId)
                ->where('status', OperatorStatus::AVAILABLE)
                ->lockForUpdate()
                ->first();

            if (!$operator) {
                return null;
            }

            $operator->status = OperatorStatus::BUSY;
            $operator->save();

            return $operator;
        });
    }

    public function releaseToAvailable(int $operatorId): void
    {
        Operator::where('id', $operatorId)->update([
            'status' => OperatorStatus::AVAILABLE
        ]);

        $timestamp = now()->timestamp;
        \Illuminate\Support\Facades\Redis::zadd('operators:available', $timestamp, $operatorId);
    }
}
