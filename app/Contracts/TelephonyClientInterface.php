<?php

namespace App\Contracts;

use App\Models\Call;

interface TelephonyClientInterface
{
    public function sendCallAssigned(Call $call, int $operatorId): void;
}
