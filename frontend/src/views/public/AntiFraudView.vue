<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
function goBack() { router.back() }

const warningItems = [
  {
    icon: 'money',
    title: '索取金錢或匯款',
    desc: '對方以各種理由（急病、投資、機票費）要求您匯款或轉帳，無論金額大小，請勿輕易轉帳給未曾見面的網友。',
  },
  {
    icon: 'gift',
    title: '要求購買禮品卡或點數',
    desc: '要求您購買超商禮品卡、遊戲點數並提供序號，此為常見詐騙手法，本平台官方絕不會要求此操作。',
  },
  {
    icon: 'invest',
    title: '投資或虛擬貨幣邀約',
    desc: '以高獲利為誘餌，邀請您投資特定平台或購買虛擬貨幣，請特別提高警覺，此類詐騙損失金額往往極高。',
  },
  {
    icon: 'photo',
    title: '索取個人私密照片',
    desc: '對方要求您傳送私密照片或影片，可能用於日後勒索。請絕對不要向陌生網友傳送任何私密內容。',
  },
  {
    icon: 'identity',
    title: '身份不符或過於完美',
    desc: '照片過於完美（疑似模特兒或明星照），或自稱在國外（軍人、工程師、醫生），卻始終無法視訊見面，請提高警覺。',
  },
  {
    icon: 'urgency',
    title: '製造緊迫感或情感壓力',
    desc: '突然表達強烈愛意，或以情感勒索方式要求您做某些事，正常交友不會有如此急迫的情感發展。',
  },
]
</script>

<template>
  <div class="static-root">
    <header class="static-topbar">
      <button class="back-btn" @click="goBack">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <span class="page-title">防詐指南</span>
      <div class="placeholder" />
    </header>

    <div class="static-content">
      <p class="last-updated">最後更新：2026年4月</p>

      <!-- 重要警示橫幅 -->
      <div class="alert-banner">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div>
          <strong>重要提醒</strong>
          <p>MiMeet 官方人員絕不會要求您匯款、購買點數或提供個人銀行資訊。如遇可疑情況，請立即透過「問題回報」功能通報本平台。</p>
        </div>
      </div>

      <section>
        <h2>常見詐騙手法</h2>
        <div class="warning-list">
          <div v-for="(item, i) in warningItems" :key="i" class="warning-item">
            <div class="warning-icon">
              <svg v-if="item.icon === 'money'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v2m0 8v2m-4-7h2a2 2 0 0 0 0-4H9a2 2 0 0 1 0-4h2m1 0v-1m0 9v1"/></svg>
              <svg v-else-if="item.icon === 'gift'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
              <svg v-else-if="item.icon === 'invest'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
              <svg v-else-if="item.icon === 'photo'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <svg v-else-if="item.icon === 'identity'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div class="warning-body">
              <strong>{{ item.title }}</strong>
              <p>{{ item.desc }}</p>
            </div>
          </div>
        </div>
      </section>

      <section>
        <h2>自我保護原則</h2>
        <div class="tip-list">
          <div class="tip-item">
            <div class="tip-num">01</div>
            <div>
              <strong>先視訊再見面</strong>
              <p>約定見面前，務必先進行視訊通話，確認對方真實身份與外貌。</p>
            </div>
          </div>
          <div class="tip-item">
            <div class="tip-num">02</div>
            <div>
              <strong>公開場所第一次見面</strong>
              <p>第一次與網友見面請選擇人多的公共場所，並告知親友見面地點與時間。</p>
            </div>
          </div>
          <div class="tip-item">
            <div class="tip-num">03</div>
            <div>
              <strong>不輕易透露個人資訊</strong>
              <p>不要過早透露住家地址、工作單位、財務狀況等敏感個人資訊。</p>
            </div>
          </div>
          <div class="tip-item">
            <div class="tip-num">04</div>
            <div>
              <strong>善用平台舉報功能</strong>
              <p>遇到可疑對象，請立即點擊用戶頁面的「檢舉」按鈕向平台通報。</p>
            </div>
          </div>
          <div class="tip-item">
            <div class="tip-num">05</div>
            <div>
              <strong>受騙後立即報警</strong>
              <p>若遭遇詐騙或損失財物，請立即撥打 165 反詐騙專線或向警察局報案。</p>
            </div>
          </div>
        </div>
      </section>

      <section>
        <h2>MiMeet 誠信分數保障</h2>
        <p>MiMeet 的誠信分數系統會追蹤用戶在平台上的行為。誠信分數較高的用戶代表：</p>
        <ul>
          <li>完成多重身份驗證（Email、手機、進階驗證）</li>
          <li>約會紀錄良好，無爽約紀錄</li>
          <li>未曾收到有效的違規檢舉</li>
          <li>在平台上持續保持誠信行為</li>
        </ul>
        <p>我們建議您優先考慮誠信分數在 61 分以上（優質或頂級）的用戶進行互動。</p>
      </section>

      <section>
        <h2>求助資源</h2>
        <div class="resource-list">
          <div class="resource-item">
            <strong>165 反詐騙專線</strong>
            <span>24 小時諮詢與通報</span>
          </div>
          <div class="resource-item">
            <strong>110 警察報案</strong>
            <span>遭受詐騙或人身安全受威脅</span>
          </div>
          <div class="resource-item">
            <strong>MiMeet 問題回報</strong>
            <span>平台內「問題回報」功能</span>
          </div>
        </div>
      </section>

      <div class="placeholder-notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 8v4M12 16h.01"/>
        </svg>
        此為暫定 Placeholder 內容，正式上線前需由平台運營人員更新為完整版本。
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
.last-updated { font-size: 12px; color: #9CA3AF; margin-bottom: 20px; }

/* Alert Banner */
.alert-banner {
  display: flex; gap: 12px; align-items: flex-start;
  background: #FEF2F2; border: 1px solid #FECACA;
  border-radius: 12px; padding: 14px 16px;
  margin-bottom: 28px;
}
.alert-banner svg { color: #EF4444; flex-shrink: 0; margin-top: 2px; }
.alert-banner strong { font-size: 14px; color: #991B1B; display: block; margin-bottom: 4px; }
.alert-banner p { font-size: 13px; color: #7F1D1D; line-height: 1.6; margin: 0; }

section { margin-bottom: 28px; }
h2 { font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 14px; padding-bottom: 8px; border-bottom: 1px solid #F3F4F6; }
p { font-size: 14px; color: #374151; line-height: 1.75; margin: 0 0 10px; }
ul { padding-left: 20px; margin: 8px 0; }
li { font-size: 14px; color: #374151; line-height: 1.75; margin-bottom: 4px; }

/* Warning List */
.warning-list { display: flex; flex-direction: column; gap: 12px; }
.warning-item {
  display: flex; gap: 14px; align-items: flex-start;
  background: #FFF5F7; border: 1px solid #FFE4EA;
  border-radius: 12px; padding: 14px;
}
.warning-icon {
  width: 40px; height: 40px; min-width: 40px; border-radius: 10px;
  background: #fff; border: 1px solid #FFE4EA;
  display: flex; align-items: center; justify-content: center;
  color: #F0294E;
}
.warning-body strong { font-size: 14px; color: #111827; display: block; margin-bottom: 4px; }
.warning-body p { font-size: 13px; color: #6B7280; line-height: 1.6; margin: 0; }

/* Tips */
.tip-list { display: flex; flex-direction: column; gap: 14px; }
.tip-item { display: flex; gap: 14px; align-items: flex-start; }
.tip-num {
  font-size: 13px; font-weight: 700; color: #F0294E;
  min-width: 28px; height: 28px;
  background: #FFF5F7; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  margin-top: 1px;
}
.tip-item strong { font-size: 14px; color: #111827; display: block; margin-bottom: 4px; }
.tip-item p { font-size: 13px; color: #6B7280; line-height: 1.6; margin: 0; }

/* Resources */
.resource-list { display: flex; flex-direction: column; gap: 10px; }
.resource-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 16px; background: #F9F9FB;
  border-radius: 10px; border: 1px solid #E5E7EB;
}
.resource-item strong { font-size: 14px; color: #111827; }
.resource-item span { font-size: 12px; color: #6B7280; }

/* Placeholder notice */
.placeholder-notice {
  display: flex; align-items: flex-start; gap: 8px;
  background: #FFFBEB; border: 1px solid #FDE68A;
  border-radius: 10px; padding: 12px 14px;
  font-size: 12px; color: #92400E; margin-top: 32px; line-height: 1.6;
}
.placeholder-notice svg { flex-shrink: 0; margin-top: 1px; color: #F59E0B; }
</style>
