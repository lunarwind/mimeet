<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestMail extends Mailable
{
    public function build(): static
    {
        return $this->subject('【MiMeet】後台 Email 設定測試')
            ->html('<h2>MiMeet Email 測試成功</h2><p>您的 SMTP 設定正確。此為後台發送的測試信件。</p>');
    }
}
