<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
function goBack() { router.back() }
</script>

<template>
  <div class="static-root">

    <!-- TopBar -->
    <header class="static-topbar">
      <button class="back-btn" @click="goBack">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <span class="page-title">隱私權政策</span>
      <div class="placeholder" />
    </header>

    <!-- 內容 -->
    <div class="static-content">

      <p class="last-updated">最後更新：2026年4月</p>

      <section>
        <h2>1. 資料收集</h2>
        <p>MiMeet（以下稱「本平台」）依據中華民國個人資料保護法，收集並處理您的個人資料。我們收集的資訊包括但不限於：</p>
        <ul>
          <li>基本資料：姓名、性別、生日、聯絡方式</li>
          <li>帳號資料：Email、手機號碼、加密後的密碼</li>
          <li>使用者上傳內容：個人照片、個人簡介</li>
          <li>使用記錄：登入時間、瀏覽記錄、互動紀錄</li>
          <li>裝置資訊：IP 位址、瀏覽器類型、作業系統</li>
        </ul>
      </section>

      <section>
        <h2>2. 資料使用目的</h2>
        <p>本平台收集您的個人資料，用於以下目的：</p>
        <ul>
          <li>提供交友配對及相關服務</li>
          <li>維護帳號安全與身份驗證</li>
          <li>計算及顯示誠信分數</li>
          <li>改善平台功能與使用者體驗</li>
          <li>發送服務相關通知與行銷資訊（經您同意後）</li>
          <li>依法律規定配合主管機關查核</li>
        </ul>
      </section>

      <section>
        <h2>3. 資料分享</h2>
        <p>本平台不會將您的個人資料出售予第三方。在以下情形下，我們可能分享您的資料：</p>
        <ul>
          <li>經您明確同意</li>
          <li>履行法律義務或回應政府機關要求</li>
          <li>保護本平台、用戶或公眾的安全與權益</li>
          <li>與本平台服務提供商合作（如雲端儲存、支付處理），且受保密義務約束</li>
        </ul>
      </section>

      <section>
        <h2>4. 資料刪除與保留</h2>
        <p>當您主動刪除帳號時，系統將進入 7 天冷靜期，期間您可隨時取消刪除。冷靜期結束後，您的帳號將被去識別化處理（暱稱、Email、手機號碼等個人資訊將被清除或匿名化）。</p>
        <p>為保障平台安全與防範網路詐騙，當您主動刪除訊息、照片或申請刪除帳號後，相關資料將於系統備份中保留 30 天以供安全審核或檢警調閱，期滿後將永久銷毀。</p>
        <p>進階驗證所上傳的自拍照將依法規要求保留 1 年，以備日後身份查核之需。</p>
        <p>具體保留項目：</p>
        <ul>
          <li>已刪除的聊天訊息：保留 30 天後永久銷毀</li>
          <li>已刪除的照片：移至隔離區保留 30 天後永久銷毀</li>
          <li>帳號去識別化後的使用記錄：保留 30 天後永久銷毀</li>
          <li>進階驗證照片：保留 1 年</li>
        </ul>
      </section>

      <section>
        <h2>5. 您的權利</h2>
        <p>依個人資料保護法，您享有以下權利：</p>
        <ul>
          <li>查詢及閱覽本平台持有的您的個人資料</li>
          <li>請求製給複製本</li>
          <li>請求補充或更正不正確的資料</li>
          <li>請求停止蒐集、處理或利用</li>
          <li>請求刪除（惟法律另有規定者除外）</li>
        </ul>
        <p>如需行使上述權利，請透過平台內的「問題回報」功能或來信至 privacy@mimeet.club 聯繫我們。</p>
      </section>

      <section>
        <h2>6. Cookie 使用</h2>
        <p>本平台使用 Cookie 及類似技術維持登入狀態、記住您的偏好設定，並分析使用行為以改善服務。您可透過瀏覽器設定管理 Cookie，但停用 Cookie 可能影響部分功能的正常運作。</p>
      </section>

      <section>
        <h2>7. 未成年人保護</h2>
        <p>本平台嚴禁 18 歲以下人士註冊使用。如發現未成年人帳號，本平台將立即終止其服務並刪除相關資料。</p>
      </section>

      <section>
        <h2>8. 政策變更</h2>
        <p>本平台保留隨時修改本隱私權政策的權利。重大變更將透過平台通知或 Email 告知您。繼續使用本平台即表示您接受修改後的政策。</p>
      </section>

      <section>
        <h2>9. 聯絡我們</h2>
        <p>如有任何隱私相關問題，請聯絡：</p>
        <p class="contact-info">
          MiMeet 個人資料保護專員<br>
          Email：privacy@mimeet.club<br>
          地址：台灣（詳細地址待補）
        </p>
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
  min-height: 100svh;
  background: #fff;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
}

/* TopBar */
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
.page-title {
  font-size: 16px; font-weight: 600; color: #111827;
}
.placeholder { width: 40px; }

/* Content */
.static-content {
  max-width: 680px;
  margin: 0 auto;
  padding: 24px 20px 60px;
}
.last-updated {
  font-size: 12px; color: #9CA3AF;
  margin-bottom: 28px;
}

section {
  margin-bottom: 28px;
}
h2 {
  font-size: 16px; font-weight: 600;
  color: #111827; margin: 0 0 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid #F3F4F6;
}
p {
  font-size: 14px; color: #374151;
  line-height: 1.75; margin: 0 0 10px;
}
ul {
  padding-left: 20px; margin: 8px 0;
}
li {
  font-size: 14px; color: #374151;
  line-height: 1.75; margin-bottom: 4px;
}
.contact-info {
  background: #F9F9FB;
  border-radius: 10px;
  padding: 14px 16px;
  font-size: 13px;
  line-height: 1.8;
}

/* Placeholder notice */
.placeholder-notice {
  display: flex; align-items: flex-start; gap: 8px;
  background: #FFFBEB; border: 1px solid #FDE68A;
  border-radius: 10px; padding: 12px 14px;
  font-size: 12px; color: #92400E;
  margin-top: 32px; line-height: 1.6;
}
.placeholder-notice svg { flex-shrink: 0; margin-top: 1px; color: #F59E0B; }
</style>
