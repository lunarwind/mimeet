<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * D.3：Ticket 處理結果 email 通知（情境 2 — suspended/auto_suspended user）。
 *
 * 三種內容變體：
 * 1. type=appeal + status=resolved → 「申訴已核准」+ 明確說明「核准 ≠ 解停」
 * 2. type=appeal + status=dismissed → 「申訴未通過」+ 駁回理由
 * 3. type≠appeal + 任一終態 → 通用「回報已處理」
 *
 * 經 queue 派發（ShouldQueue），不阻塞 admin UI。
 */
class TicketProcessedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Report $ticket,
        public readonly string $newStatus,      // 'resolved' | 'dismissed'
        public readonly string $adminReply,
        public readonly bool $isAppeal,
        public readonly string $reporterNickname,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->isAppeal && $this->newStatus === 'resolved' => '【MiMeet】您的申訴處理結果通知',
            $this->isAppeal && $this->newStatus === 'dismissed' => '【MiMeet】您的申訴未通過',
            default => '【MiMeet】您的回報已處理',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-processed',
            with: [
                'ticket' => $this->ticket,
                'newStatus' => $this->newStatus,
                'adminReply' => $this->adminReply,
                'isAppeal' => $this->isAppeal,
                'nickname' => $this->reporterNickname,
                'submittedAt' => $this->ticket->created_at?->format('Y-m-d H:i') ?? '',
            ],
        );
    }
}
