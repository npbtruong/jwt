<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // event_id is the idempotency key. The unique index is the DB-level
            // backstop: even if Redis were flushed, a duplicate insert throws.
            $table->string('event_id')->unique();

            $table->string('ota_reservation_id')->index();
            $table->string('property_id')->index();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->date('check_in');
            $table->date('check_out');
            $table->string('room_type');
            $table->decimal('total_amount', 14, 2);
            $table->string('currency', 3);
            $table->string('status')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
