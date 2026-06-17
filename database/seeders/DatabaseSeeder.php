<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Call;
use App\Enums\CallStatus;
use App\Enums\OperatorStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Redis;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // @todo remove later
        $this->insertInitialDummyData();
    }

    private function insertInitialDummyData()
    {
        Redis::del('operators:available');

        User::factory()->count(15)->create();

        $operators = Operator::factory()->count(10)->create(['status' => OperatorStatus::OFFLINE]);

        $clients = Client::factory()->count(45)->create();
        Client::factory()->count(5)->deleted()->create();

        $this->insertFinishedAndMissedCalls($clients, $operators);

        $this->updateLastCallTimes($operators);

        $shuffledOperators = $operators->shuffle();

        $this->setAvailableOperators($shuffledOperators);

        $this->insertInProgressCalls($shuffledOperators, $clients);

        $this->setRestOperatorStatuses($shuffledOperators);
    }

    private function insertFinishedAndMissedCalls($clients, $operators)
    {
        $startTime = now()->subDays(7);

        for ($i = 0; $i < 140; $i++) {
            $startTime = $startTime->addMinutes(rand(30, 120));
            $randomClient = $clients->random();

            $roll = rand(1, 100);

            if ($roll <= 80) {
                $randomOperator = $operators->random();
                Call::factory()->success()->create([
                    'phone' => $randomClient->phone,
                    'client_id' => $randomClient->id,
                    'operator_id' => $randomOperator->id,
                    'created_at' => $startTime,
                ]);
            } else {
                Call::factory()->missed()->create([
                    'phone' => $randomClient->phone,
                    'client_id' => $randomClient->id,
                    'created_at' => $startTime,
                    'finished_at' => $startTime,
                ]);
            }
        }
    }

    private function updateLastCallTimes($operators)
    {
        foreach ($operators as $operator) {
            $lastFinishedCall = Call::where('operator_id', $operator->id)
                ->where('status', CallStatus::SUCCESS)
                ->orderBy('finished_at', 'desc')
                ->first();

            if ($lastFinishedCall) {
                $operator->last_call_at = $lastFinishedCall->finished_at;
                $operator->saveQuietly();
            }
        }
    }

    private function setAvailableOperators($shuffledOperators)
    {
        $availableOperators = $shuffledOperators->slice(0, 3);
        foreach ($availableOperators as $operator) {
            $operator->status = OperatorStatus::AVAILABLE;
            $operator->save();
        }
    }

    private function insertInProgressCalls($shuffledOperators, $clients)
    {
        $busyOperators = $shuffledOperators->slice(3, 3);
        $uniqueClientsForBusyOperators = $clients->shuffle()->slice(0, 3);

        foreach ($busyOperators->values() as $i => $operator) {
            $client = $uniqueClientsForBusyOperators->values()[$i];

            $operator->status = OperatorStatus::BUSY;
            $operator->saveQuietly();

            Call::factory()->inProgress()->create([
                'phone' => $client->phone,
                'client_id' => $client->id,
                'operator_id' => $operator->id,
                'created_at' => now()->subMinutes(rand(1, 10)),
                'finished_at' => null,
            ]);
        }
    }

    private function setRestOperatorStatuses($shuffledOperators)
    {
        $restOperators = $shuffledOperators->slice(6, 4);
        foreach ($restOperators as $i => $operator) {
            $operator->status = $i % 2 == 0 ? OperatorStatus::BREAK : OperatorStatus::OFFLINE;
            $operator->saveQuietly();
        }
    }
}
