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

    /**
     * Create a new message instance.
     */
    public function __construct($data, $invoicePdf)
    {
        $this->data = $data;
        $this->invoicePdf = $invoicePdf;
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
        return new Content(
            view: 'emails.invoice',
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

        if (! empty($this->invoicePdf)) {
            $attachments[] = Attachment::fromData(fn () => $this->invoicePdf, 'invoice.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
