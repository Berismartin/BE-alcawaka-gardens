<?php

namespace App\Mail;

use App\Domains\Payments\PaymentLink\Services\PaymentLinkService;
use App\Models\Payments\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The invoice email: what is owed, a "Pay now" button to /pay/{token}, and for room bookings the invoice PDF. */
class PaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $hotel */
    public function __construct(public PaymentLink $link, public array $hotel) {}

    public function envelope(): Envelope
    {
        $ref = $this->link->reservation?->reference() ?? $this->link->facilityBooking?->facility?->name;

        return new Envelope(subject: "Invoice · {$ref} · {$this->hotel['hotel_name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-link', with: [
            'payNow' => PaymentLinkService::payNow($this->link),
            'due' => PaymentLinkService::due($this->link->owner()),
            'url' => $this->link->url(),
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->link->reservation) {
            return [];
        }
        $invoice = new ReceiptMail($this->link->reservation, $this->hotel); // the same PDF as "Email invoice" on the folio

        return [Attachment::fromData(fn () => $invoice->pdf(), $invoice->filename())->withMime('application/pdf')];
    }
}
