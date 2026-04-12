<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VerificationMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $nickname,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('【MiMeet】Email 驗證碼')
            ->html("
                <div style='font-family: sans-serif; max-width: 480px; margin: 0 auto; padding: 32px;'>
                    <h2 style='color: #F0294E;'>MiMeet Email 驗證</h2>
                    <p>Hi {$this->nickname}，</p>
                    <p>您的驗證碼為：</p>
                    <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center; padding: 16px; background: #F9F9FB; border-radius: 10px; margin: 16px 0;'>
                        {$this->code}
                    </div>
                    <p style='color: #6B7280; font-size: 14px;'>驗證碼 10 分鐘內有效，請勿將驗證碼透露給他人。</p>
                    <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 24px 0;'>
                    <p style='color: #9CA3AF; font-size: 12px;'>此郵件由 MiMeet 系統自動發送，請勿回覆。</p>
                </div>
            ");
    }
}
