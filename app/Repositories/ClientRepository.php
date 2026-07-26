<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;

final class ClientRepository implements ClientRepositoryInterface
{
    public function findByClientId(string $clientId): ?Client
    {
        return Client::query()
            ->where('client_id', $clientId)
            ->first();
    }
}
