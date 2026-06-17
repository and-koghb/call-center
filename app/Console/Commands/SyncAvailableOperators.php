<?php

namespace App\Console\Commands;

use App\Enums\OperatorStatus;
use App\Models\Operator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SyncAvailableOperators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'operators:redis-sync-operators';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync available operators into Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting operator cache warmup in Redis...');

        Redis::del('operators:available');

        Operator::where('status', OperatorStatus::AVAILABLE)
            ->where('deleted_at', Operator::getLiveTimestamp())
            ->chunk(100, function ($operators) {
                foreach ($operators as $operator) {
                    $score = $operator->last_call_at
                        ? $operator->last_call_at->timestamp
                        : (time() - 86400);

                    Redis::zadd('operators:available', $score, $operator->id);
                }
            });

        $count = Redis::zcard('operators:available');
        $this->info("Successfully added operators to Redis: {$count}");

        return Command::SUCCESS;
    }
}
