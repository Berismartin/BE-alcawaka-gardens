<?php

namespace App\Mail;

use App\Models\Reservations\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The itemised bill. The same view is the email body and the PDF (an invoice while money is due). */
class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $hotel */
    public function __construct(public Reservation $reservation, public array $hotel) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->title()} · {$this->reservation->reference()} · {$this->hotel['hotel_name']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt', with: ['title' => $this->title()]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf(), $this->filename())->withMime('application/pdf')];
    }

    public function pdf(): string
    {
        return Pdf::setOption('isFontSubsettingEnabled', true)->loadView('mail.receipt', ['reservation' => $this->reservation, 'hotel' => $this->hotel, 'title' => $this->title(), 'pdf' => true])->output();
    }

    public function filename(): string
    {
        return "{$this->title()}-{$this->reservation->reference()}.pdf";
    }

    private function title(): string
    {
        return $this->reservation->balance() > 0 ? 'invoice' : 'receipt';
    }
}
