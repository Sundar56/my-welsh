<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    private $data;
    private $invoicePdf;
    private $lang;

    /**
     * Create a new message instance.
     */
    public function __construct($data, $invoicePdf, $lang)
    {
        $this->data = $data;
        $this->invoicePdf = $invoicePdf;
        $this->lang = $lang;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Send Invoice Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->lang === 'cy'
            ? 'emails.invoice_cy'
            : 'emails.invoice';

        return new Content(
            view: $view,
            with: [
                'data' => $this->data,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if (! is_null($this->invoicePdf) && $this->invoicePdf !== '') {
            $attachments[] = Attachment::fromData(fn () => $this->invoicePdf, 'invoice.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
