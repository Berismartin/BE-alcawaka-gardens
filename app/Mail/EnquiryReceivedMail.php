<?php

namespace App\Mail;

use App\Models\Content\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the hotel's email (Settings): a new website enquiry. "Reply" goes straight to the guest. */
class EnquiryReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        $what = $this->enquiry->request_type
            ? 'Booking '.($this->enquiry->request_type === 'cancel' ? 'cancellation' : 'change').' request'
            : ($this->enquiry->event_type ? "{$this->enquiry->event_type} enquiry" : 'New enquiry');

        return new Envelope(
            subject: "{$what} from {$this->enquiry->name}",
            replyTo: $this->enquiry->email ? [new Address($this->enquiry->email, $this->enquiry->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enquiry-received');
    }
}
