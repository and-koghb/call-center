<?php

namespace Tests\Feature;

use App\Contracts\TelephonyClientInterface;
use App\Enums\CallStatus;
use App\Enums\OperatorStatus;
use App\Models\Call;
use App\Models\Operator;
use App\Jobs\ProcessIncomingCallJob;
use App\Models\User;
use App\Services\CallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class IncomingCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function testSuccessfullyRoutesCallToAvailableOperator()
    {
        $user = User::create([
            'name' => 'Pavel Petrov',
            'email' => 'operator1@example.com',
            'password' => bcrypt('password'),
        ]);

        $operator = Operator::create([
            'user_id' => $user->id,
            'status' => OperatorStatus::AVAILABLE,
            'deleted_at' => Operator::getLiveTimestamp(),
        ]);

        $call = Call::create([
            'phone' => '+79991112233',
            'status' => CallStatus::NEW
        ]);
        $this->mock(TelephonyClientInterface::class, function (MockInterface $mock) use ($call, $operator) {
            $mock->shouldReceive('sendCallAssigned')
                ->once()
                ->with(Mockery::on(fn($c) => $c->id === $call->id), $operator->id);
            });

        $this->mock(\App\Repositories\OperatorRepository::class, function (MockInterface $mock) use ($operator) {
            $mock->shouldReceive('popMostAvailableId')->once()->andReturn($operator->id);

            $mock->shouldReceive('tryLockAndBindBusy')->once()->with($operator->id)->andReturn($operator);
        });

        $jobMock = $this->getMockBuilder(ProcessIncomingCallJob::class)
            ->setConstructorArgs([$call->id])
            ->onlyMethods(['release'])
            ->getMock();

        $jobMock->expects($this->never())->method('release');

        $callService = app(CallService::class);
        $callService->routeCallToOperator($call->id, $jobMock);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'operator_id' => $operator->id,
            'status' => CallStatus::IN_PROGRESS,
        ]);
    }

    public function testDoesNotRouteCallToSoftDeletedOperator()
    {
        $user = User::create([
            'name' => 'Fired Employee',
            'email' => 'operator2@example.com',
            'password' => bcrypt('password'),
        ]);

        $operator = Operator::create([
            'user_id' => $user->id,
            'status' => OperatorStatus::AVAILABLE,
            'deleted_at' => now()->format('Y-m-d H:i:s'),
        ]);

        Redis::connection()->zadd('operators:available', now()->timestamp, $operator->id);

        $call = Call::create(['phone' => '+79991112233', 'status' => CallStatus::NEW]);

        $this->mock(TelephonyClientInterface::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendCallAssigned');
        });

        $jobMock = $this->getMockBuilder(ProcessIncomingCallJob::class)
            ->setConstructorArgs([$call->id])
            ->onlyMethods(['release'])
            ->getMock();

        $jobMock->expects($this->once())->method('release')->with(5);

        app(CallService::class)->routeCallToOperator($call->id, $jobMock);
    }

    public function testReturnsToQueueWhenNoOperatorsAvailable()
    {
        $call = Call::create([
            'phone' => '+79991112233',
            'status' => CallStatus::NEW
        ]);

        $this->mock(TelephonyClientInterface::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendCallAssigned');
        });

        $this->mock(\App\Repositories\OperatorRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('popMostAvailableId')->once()->andReturn(null);
        });

        $jobMock = $this->getMockBuilder(ProcessIncomingCallJob::class)
            ->setConstructorArgs([$call->id])
            ->onlyMethods(['release'])
            ->getMock();

        $jobMock->expects($this->once())
            ->method('release')
            ->with(5);

        $callService = app(CallService::class);
        $callService->routeCallToOperator($call->id, $jobMock);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => CallStatus::NEW,
            'operator_id' => null,
        ]);
    }
}
