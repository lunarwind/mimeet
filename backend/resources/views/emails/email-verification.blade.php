<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"><title>Email 驗證</title></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 20px;">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;">
        <!-- Header -->
        <tr><td style="background:#F0294E;padding:24px 32px;">
          <span style="color:#fff;font-size:22px;font-weight:700;">MiMeet</span>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:40px 32px;">
          <p style="font-size:16px;color:#111827;margin:0 0 8px;">嗨，{{ $nickname }}！</p>
          <p style="font-size:14px;color:#6B7280;margin:0 0 32px;">感謝您註冊 MiMeet，請輸入以下驗證碼完成信箱驗證：</p>
          <div style="background:#FFF1F3;border:2px dashed #F0294E;border-radius:12px;padding:24px;text-align:center;margin:0 0 32px;">
            <span style="font-size:36px;font-weight:700;color:#F0294E;letter-spacing:8px;">{{ $code }}</span>
            <p style="font-size:12px;color:#9CA3AF;margin:12px 0 0;">驗證碼 10 分鐘內有效，請勿洩漏給他人</p>
          </div>
          <p style="font-size:13px;color:#9CA3AF;margin:0;">若您未進行此操作，請忽略此信件。</p>
        </td></tr>
        <!-- Footer -->
        <tr><td style="background:#F9FAFB;padding:20px 32px;border-top:1px solid #E5E7EB;">
          <p style="font-size:12px;color:#9CA3AF;margin:0;">您收到此信是因為您在 MiMeet 有帳號。</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
