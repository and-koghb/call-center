<?php

namespace App\Repositories;

use App\Models\Client;

class ClientRepository
{
    public function findByPhone(string $phone): ?Client
    {
        return Client::where('phone', $phone)->first();
    }
}
