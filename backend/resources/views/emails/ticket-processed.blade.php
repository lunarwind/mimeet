<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <title>
    @if ($isAppeal && $newStatus === 'resolved')
      您的申訴處理結果通知
    @elseif ($isAppeal && $newStatus === 'dismissed')
      您的申訴未通過
    @else
      您的回報已處理
    @endif
  </title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 20px;">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;">

        {{-- Header --}}
        <tr><td style="background:#F0294E;padding:24px 32px;">
          <span style="color:#fff;font-size:22px;font-weight:700;">MiMeet</span>
        </td></tr>

        {{-- Body --}}
        <tr><td style="padding:40px 32px;">
          <p style="font-size:16px;color:#111827;margin:0 0 8px;">嗨，{{ $nickname }}！</p>

          @if ($isAppeal && $newStatus === 'resolved')
            <p style="font-size:14px;color:#6B7280;margin:0 0 16px;">
              您於 {{ $submittedAt }} 提交的申訴已通過審核。
            </p>

            {{-- 警示框：核准 ≠ 解停（Q7 決策核心） --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
              <tr><td style="background:#FFFBEB;border-left:3px solid #F59E0B;padding:14px 16px;border-radius:4px;">
                <p style="font-size:13px;color:#92400E;margin:0 0 6px;font-weight:600;">⚠️ 請注意：申訴核准 ≠ 帳號自動恢復</p>
                <p style="font-size:13px;color:#78350F;margin:0;line-height:1.6;">
                  如需恢復帳號使用，請：<br>
                  • 聯繫客服 (<a href="mailto:support@mimeet.online" style="color:#F0294E;">support@mimeet.online</a>)<br>
                  • 或等待管理員後續處理
                </p>
              </td></tr>
            </table>

            @if (!empty($adminReply))
              <p style="font-size:14px;color:#374151;margin:0 0 8px;font-weight:600;">管理員回覆：</p>
              <p style="font-size:14px;color:#6B7280;margin:0 0 24px;line-height:1.6;white-space:pre-wrap;">{{ $adminReply }}</p>
            @endif

          @elseif ($isAppeal && $newStatus === 'dismissed')
            <p style="font-size:14px;color:#6B7280;margin:0 0 16px;">
              您於 {{ $submittedAt }} 提交的申訴經審核未獲通過。
            </p>

            <p style="font-size:14px;color:#374151;margin:0 0 8px;font-weight:600;">理由：</p>
            <p style="font-size:14px;color:#6B7280;margin:0 0 24px;line-height:1.6;white-space:pre-wrap;">{{ $adminReply }}</p>

            <p style="font-size:13px;color:#9CA3AF;margin:0 0 24px;">
              如有疑問，請聯繫客服 (<a href="mailto:support@mimeet.online" style="color:#F0294E;">support@mimeet.online</a>)。
            </p>

          @else
            {{-- 通用 ticket 處理（type ≠ appeal） --}}
            <p style="font-size:14px;color:#6B7280;margin:0 0 24px;">
              您於 {{ $submittedAt }} 提交的回報已完成處理。
            </p>

            @if (!empty($adminReply))
              <p style="font-size:14px;color:#374151;margin:0 0 8px;font-weight:600;">管理員回覆：</p>
              <p style="font-size:14px;color:#6B7280;margin:0 0 24px;line-height:1.6;white-space:pre-wrap;">{{ $adminReply }}</p>
            @endif
          @endif

          <p style="font-size:13px;color:#9CA3AF;margin:0;">
            案件編號：A{{ str_pad($ticket->id, 9, '0', STR_PAD_LEFT) }}
          </p>
        </td></tr>

        {{-- Footer --}}
        <tr><td style="background:#F9FAFB;padding:20px 32px;border-top:1px solid #E5E7EB;">
          <p style="font-size:12px;color:#9CA3AF;margin:0;">您收到此信是因為您在 MiMeet 有帳號。</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
