<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one test OTA client and PRINTS the raw secret once.
 *
 * The raw secret is never persisted (only its bcrypt hash), so this seeder is
 * the only place you'll ever see it — copy it into your curl calls.
 */
final class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = 'booking_com_client';
        $rawSecret = 's3cr3t_raw_value';

        Client::updateOrCreate(
            ['client_id' => $clientId],
            [
                'client_secret' => Hash::make($rawSecret),
                'name' => 'Booking.com',
            ],
        );

        $this->command?->newLine();
        $this->command?->info('================ Test OTA client ================');
        $this->command?->info("  client_id:     {$clientId}");
        $this->command?->info("  client_secret: {$rawSecret}   (raw — shown once)");
        $this->command?->info('=================================================');
        $this->command?->newLine();
    }
}
