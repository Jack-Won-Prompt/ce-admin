<?php

namespace App\Mail;

use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminInvitation $invitation,
        public User $inviter,
        public string $personalMessage = ''
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[CE Admin] 관리자 시스템 초대');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-invitation');
    }
}
