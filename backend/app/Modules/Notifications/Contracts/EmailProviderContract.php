<?php

namespace App\Modules\Notifications\Contracts;

/**
 * The capability contract every Email-category Provider implements
 * (docs/adr/0019-integration-platform-architecture.md Decision 1: "a
 * capability contract per vendor category"). One contract, many vendors
 * -- adding SES or Sendgrid alongside SmtpEmailProvider is a new class
 * implementing this same interface, never a change to it.
 */
interface EmailProviderContract
{
    public function send(string $to, string $subject, string $body): bool;
}
