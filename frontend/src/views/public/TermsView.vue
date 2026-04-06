<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
function goBack() { router.back() }
</script>

<template>
  <div class="static-root">
    <header class="static-topbar">
      <button class="back-btn" @click="goBack">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <span class="page-title">使用者條款</span>
      <div class="placeholder" />
    </header>

    <div class="static-content">
      <p class="last-updated">最後更新：2026年4月</p>

      <section>
        <h2>1. 接受條款</h2>
        <p>歡迎使用 MiMeet（以下稱「本平台」）。使用本平台即表示您同意本使用者條款（以下稱「本條款」）。若您不同意本條款，請勿使用本平台。</p>
      </section>

      <section>
        <h2>2. 服務資格</h2>
        <p>使用本平台需符合以下條件：</p>
        <ul>
          <li>年滿 18 歲</li>
          <li>具備完整法律行為能力</li>
          <li>未曾因違反本條款而被永久封禁</li>
          <li>遵守中華民國相關法律規定</li>
        </ul>
      </section>

      <section>
        <h2>3. 帳號責任</h2>
        <p>您有責任：</p>
        <ul>
          <li>提供真實、正確的個人資料</li>
          <li>妥善保管帳號密碼，不得讓他人使用您的帳號</li>
          <li>對帳號內的所有活動負責</li>
          <li>發現帳號遭未授權使用時，立即通知本平台</li>
        </ul>
      </section>

      <section>
        <h2>4. 禁止行為</h2>
        <p>使用本平台時，您不得：</p>
        <ul>
          <li>發布虛假、誤導或詐騙性內容</li>
          <li>騷擾、恐嚇或威脅其他用戶</li>
          <li>散布色情、暴力或歧視性內容</li>
          <li>以商業目的發送廣告或垃圾訊息</li>
          <li>使用自動化工具或機器人操作帳號</li>
          <li>嘗試破解、入侵或干擾本平台系統</li>
          <li>蒐集其他用戶的個人資料</li>
          <li>冒充他人或本平台工作人員</li>
          <li>在平台外進行金錢交易</li>
        </ul>
      </section>

      <section>
        <h2>5. 誠信分數系統</h2>
        <p>本平台採用獨特的誠信分數系統評估用戶可信度。分數將根據您在平台上的行為自動調整。分數過低將限制您的功能使用，降至 0 分將導致帳號暫停。本平台保留調整分數規則的權利。</p>
      </section>

      <section>
        <h2>6. 內容規範</h2>
        <p>您上傳的個人照片及文字內容須符合以下規範：</p>
        <ul>
          <li>必須為本人真實照片，禁止使用他人照片</li>
          <li>禁止裸露、色情或暗示性圖片</li>
          <li>禁止包含個人聯絡方式（電話、Line、WeChat 等）</li>
          <li>禁止廣告宣傳或商業性內容</li>
          <li>本平台有權在不另行通知的情況下移除違規內容</li>
        </ul>
      </section>

      <section>
        <h2>7. 付費服務</h2>
        <p>本平台提供付費會員方案。關於費用與退款：</p>
        <ul>
          <li>所有費用以新台幣計算，含稅</li>
          <li>訂閱費用一經支付原則上不予退還</li>
          <li>因系統錯誤導致的誤扣款將全額退還</li>
          <li>本平台保留調整定價的權利，變更前將提前通知</li>
        </ul>
      </section>

      <section>
        <h2>8. 帳號終止</h2>
        <p>本平台在以下情形可終止您的帳號：</p>
        <ul>
          <li>違反本條款</li>
          <li>誠信分數降至 0</li>
          <li>長期未使用（超過 12 個月）</li>
          <li>依法律或主管機關要求</li>
        </ul>
        <p>您也可隨時申請刪除帳號，申請後有 7 天冷靜期。</p>
      </section>

      <section>
        <h2>9. 免責聲明</h2>
        <p>本平台僅提供交友媒合服務，不對以下事項負責：</p>
        <ul>
          <li>用戶間交流、見面所產生的任何糾紛</li>
          <li>用戶提供不實資料所造成的損害</li>
          <li>第三方服務中斷或故障</li>
          <li>不可抗力事件導致的服務中斷</li>
        </ul>
      </section>

      <section>
        <h2>10. 準據法與管轄</h2>
        <p>本條款依中華民國法律解釋與執行。因本條款產生的爭議，以台灣台北地方法院為第一審管轄法院。</p>
      </section>

      <div class="placeholder-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 8v4M12 16h.01"/>
        </svg>
        此為暫定 Placeholder 內容，正式上線前需由法律顧問審閱並替換為正式版本。
      </div>
    </div>
  </div>
</template>

<style scoped>
.static-root {
  min-height: 100svh; background: #fff;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
}
.static-topbar {
  position: sticky; top: 0; z-index: 10;
  background: #fff; border-bottom: 1px solid #E5E7EB;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; height: 52px;
}
.back-btn {
  width: 40px; height: 40px; border-radius: 10px;
  background: transparent; border: none;
  display: flex; align-items: center; justify-content: center;
  color: #6B7280; cursor: pointer; transition: background 0.2s;
}
.back-btn:hover { background: #F3F4F6; }
.page-title { font-size: 16px; font-weight: 600; color: #111827; }
.placeholder { width: 40px; }
.static-content { max-width: 680px; margin: 0 auto; padding: 24px 20px 60px; }
.last-updated { font-size: 12px; color: #9CA3AF; margin-bottom: 28px; }
section { margin-bottom: 28px; }
h2 { font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 10px; padding-bottom: 8px; border-bottom: 1px solid #F3F4F6; }
p { font-size: 14px; color: #374151; line-height: 1.75; margin: 0 0 10px; }
ul { padding-left: 20px; margin: 8px 0; }
li { font-size: 14px; color: #374151; line-height: 1.75; margin-bottom: 4px; }
.placeholder-notice {
  display: flex; align-items: flex-start; gap: 8px;
  background: #FFFBEB; border: 1px solid #FDE68A;
  border-radius: 10px; padding: 12px 14px;
  font-size: 12px; color: #92400E; margin-top: 32px; line-height: 1.6;
}
.placeholder-notice svg { flex-shrink: 0; margin-top: 1px; color: #F59E0B; }
</style>
