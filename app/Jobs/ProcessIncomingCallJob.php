<?php

namespace App\Jobs;

use App\Services\CallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        protected int $callId
    ) {}

    public function retryUntil(): \DateTime
    {
        return now()->addSeconds(60);
    }

    public function handle(CallService $callService): void
    {
        $callService->routeCallToOperator($this->callId, $this);
    }

    public function failed(\Throwable $exception): void
    {
        app(CallService::class)->handleFailedRouting($this->callId);
    }
}
