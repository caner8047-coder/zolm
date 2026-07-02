<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrendyolBoosterDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) ($this->payload['subject'] ?? 'ZOLM Trendyol Booster Özeti'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketplace.trendyol-booster-digest',
            with: [
                'payload' => $this->payload,
            ],
        );
    }
}
