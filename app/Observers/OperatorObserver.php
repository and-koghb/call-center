<?php

namespace App\Observers;

use App\Models\Operator;
use App\Enums\OperatorStatus;
use Illuminate\Support\Facades\Redis;

class OperatorObserver
{
    public function saved(Operator $operator): void
    {
        if ($operator->isDirty('status') || $operator->isDirty('deleted_at') || $operator->wasRecentlyCreated) {

            if ($operator->status === OperatorStatus::AVAILABLE && is_null($operator->deleted_at)) {

                $score = $operator->last_call_at
                    ? $operator->last_call_at->timestamp
                    : (time() - 86400);

                Redis::zadd('operators:available', $score, $operator->id);
            } else {
                Redis::zrem('operators:available', $operator->id);
            }
        }
    }

    public function deleted(Operator $operator): void
    {
        Redis::zrem('operators:available', $operator->id);
    }
}
