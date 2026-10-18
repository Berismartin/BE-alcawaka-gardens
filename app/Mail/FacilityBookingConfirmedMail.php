<?php

namespace App\Mail;

use App\Models\Facilities\FacilityBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FacilityBookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $hotel */
    public function __construct(public FacilityBooking $booking, public array $hotel) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->booking->facility->name} booked · {$this->booking->reference()} · {$this->hotel['hotel_name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.facility-booking-confirmed', with: [
            'viewUrl' => rtrim(config('app.frontend_url'), '/').'/facilities/booking/'.$this->booking->id.'?key='.urlencode((string) $this->booking->guest_key),
        ]);
    }
}
