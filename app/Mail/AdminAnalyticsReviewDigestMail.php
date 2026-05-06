<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

class AdminAnalyticsReviewDigestMail extends Mailable
{
    /**
     * @param array<string, mixed> $report
     */
    public function __construct(public array $report)
    {
    }

    public function envelope(): Envelope
    {
        $owner = (string) ($this->report['owner'] ?? 'growth');
        $alerts = count((array) ($this->report['conversionAlerts'] ?? []));

        return new Envelope(
            subject: sprintf('SkyblockHub admin review (%s) - %d alerts', $owner, $alerts),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-analytics-review',
        );
    }
}