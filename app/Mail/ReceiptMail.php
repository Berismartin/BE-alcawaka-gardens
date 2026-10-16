<?php

namespace App\Mail;

use App\Models\Reservations\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $hotel */
    public function __construct(public Reservation $reservation, public array $hotel) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your receipt · {$this->reservation->reference()} · {$this->hotel['hotel_name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt');
    }
}
