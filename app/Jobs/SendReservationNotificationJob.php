<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ReservationReceivedMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the reservation-received notification email.
 *
 * This is the ASYNC half of the ingest flow — it runs on the dedicated
 * `notifications` Redis queue, on a separate worker container, so slow SMTP
 * never blocks the webhook's 200 response.
 *
 * Retry policy ($tries / $backoff / $timeout) is entirely config-driven.
 */
final class SendReservationNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Max attempts before the job is sent to failed_jobs. */
    public int $tries;

    /** Hard per-attempt timeout in seconds. */
    public int $timeout;

    public function __construct(
        public readonly Reservation $reservation,
    ) {
        // Pin to the named queue the worker listens on, and pull retry policy
        // from config so operators tune it via env without code changes.
        $this->onQueue((string) config('notifications.queue'));
        $this->tries = (int) config('notifications.tries');
        $this->timeout = (int) config('notifications.timeout');
    }

    /**
     * Growing delay (seconds) between retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('notifications.backoff');
    }

    public function handle(): void
    {
        Mail::to($this->reservation->guest_email)
            ->send(new ReservationReceivedMail($this->reservation));

        Log::info('reservation.notification.sent', [
            'reservation_id' => $this->reservation->id,
            'event_id' => $this->reservation->event_id,
            'guest_email' => $this->reservation->guest_email,
        ]);
    }

    /**
     * Terminal failure hook: runs after the last retry. Laravel also records
     * the job in the failed_jobs table; here we add a structured log line.
     */
    public function failed(Throwable $e): void
    {
        Log::error('reservation.notification.failed', [
            'reservation_id' => $this->reservation->id,
            'event_id' => $this->reservation->event_id,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
