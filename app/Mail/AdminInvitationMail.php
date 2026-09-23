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
        /* 제목은 메시지 관리에서 고친다 (2026-09-23 지시) */
        return new Envelope(subject: \App\Models\MessageTemplate::문구(
            'admin_invitation_subject', [], '[CE Admin] 관리자 시스템 초대', 'email'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-invitation');
    }
}
