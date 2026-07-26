<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Client;

interface ClientRepositoryInterface
{
    /**
     * Look up a client by its public client_id. Null when not found.
     */
    public function findByClientId(string $clientId): ?Client;
}
