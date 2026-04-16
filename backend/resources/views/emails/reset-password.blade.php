<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"><title>密碼重設</title></head>
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
          <p style="font-size:14px;color:#6B7280;margin:0 0 32px;">我們收到了您的密碼重設請求，請點擊下方按鈕設定新密碼：</p>
          <div style="text-align:center;margin:0 0 32px;">
            <a href="{{ $resetUrl }}" style="display:inline-block;background:#F0294E;color:#fff;font-size:16px;font-weight:700;padding:14px 40px;border-radius:10px;text-decoration:none;">
              重設密碼
            </a>
          </div>
          <p style="font-size:13px;color:#9CA3AF;margin:0 0 16px;">此連結 60 分鐘內有效，若您未申請密碼重設，請忽略此信件。</p>
          <p style="font-size:12px;color:#D1D5DB;margin:0;word-break:break-all;">若按鈕無法點擊，請複製以下連結至瀏覽器：<br>{{ $resetUrl }}</p>
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
