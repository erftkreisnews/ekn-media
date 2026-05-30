<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class EventSuggestionsDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\EventSuggestion>  $suggestions
     */
    public function __construct(
        public Collection $suggestions,
        public string $reviewUrl
    ) {}

    public function envelope(): Envelope
    {
        $count = max(1, $this->suggestions->count());

        return new Envelope(
            subject: 'Neue Veranstaltungs-Vorschläge ('.$count.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event-suggestions-digest',
        );
    }
}
