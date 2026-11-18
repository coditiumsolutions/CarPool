<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationCodeMail extends Mailable
{
    public function __construct(public readonly string $code, public readonly int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' password reset code');
    }

    public function content(): Content
    {
        $app = e(config('app.name'));

        return new Content(htmlString: "<p>Your {$app} password reset code is:</p>"
            ."<p style=\"font-size:24px;letter-spacing:4px\"><strong>{$this->code}</strong></p>"
            ."<p>It expires in {$this->minutes} minutes. If you did not request it, ignore this email.</p>");
    }
}
