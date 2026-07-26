<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ReservationReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reservation confirmed — {$this->reservation->ota_reservation_id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-received',
            with: ['reservation' => $this->reservation],
        );
    }
}
