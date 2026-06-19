<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Jobs\ProcessIncomingCallJob;
use App\Models\Call;
use App\Repositories\CallRepository;
use App\Repositories\ClientRepository;
use App\Repositories\OperatorRepository;
use App\Contracts\TelephonyClientInterface;
use Illuminate\Support\Facades\Log;

class CallService
{
    public function __construct(
        protected CallRepository $callRepository,
        protected ClientRepository $clientRepository,
        protected OperatorRepository $operatorRepository,
        protected TelephonyClientInterface $telephonyClient
    ) {}

    public function registerIncomingCall(string $phone): void
    {
        $call = $this->callRepository->createIncoming($phone);

        ProcessIncomingCallJob::dispatch($call->id)->onQueue('calls');
    }

    public function routeCallToOperator(int $callId, ProcessIncomingCallJob $job): void
    {
        $call = $this->callRepository->find($callId);
        if (!$call) {
            return;
        }

        if ($call->status === CallStatus::NEW) {
            $operatorFound = $this->handleNewCall($call);

            if (!$operatorFound) {
                $job->release(5);
                return;
            }
        }

        if ($call->status === CallStatus::ASSIGNED) {
            $this->handleAssignedCall($call);
        }

        return;
    }

    protected function handleNewCall(Call $call): bool
    {
        $client = $this->clientRepository->findByPhone($call->phone);
        $clientId = $client ? $client->id : null;

        $operatorId = $this->operatorRepository->popMostAvailableId();

        if (!$operatorId) {
            if ($clientId) {
                $call->update(['client_id' => $clientId]);
            }
            Log::warning("No operators available in Redis for call ID: {$call->id}");
            return false;
        }

        $operator = $this->operatorRepository->tryLockAndBindBusy($operatorId);

        if (!$operator) {
            return false;
        }

        $this->callRepository->updateToAssigned($call, $clientId, $operatorId);

        return true;
    }

    protected function handleAssignedCall(Call $call): void
    {
        $this->telephonyClient->sendCallAssigned($call, $call->operator_id);

        $this->callRepository->updateStatus($call, CallStatus::IN_PROGRESS);

        Log::info('Call successfully assigned and routed to telephony', [
            'call_id' => $call->id,
            'operator_id' => $call->operator_id
        ]);
    }

    public function handleFailedRouting(int $callId): void
    {
        $call = $this->callRepository->find($callId);

        if ($call && $call->status === CallStatus::ASSIGNED) {
            if ($call->operator_id) {
                $this->operatorRepository->releaseToAvailable($call->operator_id);
            }
            $this->callRepository->updateStatus($call, CallStatus::FAILED);

            Log::error("Call routing fatally failed. Resources released.", ['call_id' => $callId]);
        }
    }
}
