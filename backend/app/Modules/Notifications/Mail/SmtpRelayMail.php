<?php

namespace App\Modules\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * SmtpEmailProvider::send()'s own envelope -- a real Mailable, not
 * Mail::raw(), deliberately: Illuminate\Support\Testing\Fakes\MailFake's
 * own raw() implementation is a no-op (it only records Mailable
 * instances), a real gap found by running Mail::assertSentCount() and
 * getting zero rather than assuming Mail::fake() covers every Mail
 * facade method uniformly.
 */
class SmtpRelayMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)->html($this->bodyText);
    }
}
