<?php

namespace App\Mail;

use App\Models\Reservations\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $hotel
     * @param  'confirmed'|'changed'|'cancelled'  $kind
     */
    public function __construct(public Reservation $reservation, public array $hotel, public string $kind = 'confirmed') {}

    public function envelope(): Envelope
    {
        $what = ['confirmed' => 'Booking confirmed', 'changed' => 'New dates confirmed', 'cancelled' => 'Booking cancelled'][$this->kind];

        return new Envelope(subject: "{$what} · {$this->reservation->reference()} · {$this->hotel['hotel_name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-confirmed', with: [
            'manageUrl' => rtrim(config('app.frontend_url'), '/').'/book/'.$this->reservation->reference().'?key='.urlencode((string) $this->reservation->guest_key),
        ]);
    }
}
