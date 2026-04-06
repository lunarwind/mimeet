<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const userNickname = computed(() => authStore.user?.nickname ?? '用戶')

function goToAppeal() {
  router.push('/suspended/appeal')
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="suspended-view">
    <!-- 圖示 -->
    <div class="suspended-icon" aria-hidden="true">
      <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
        <circle cx="40" cy="40" r="36" fill="#FEF2F2" stroke="#FECACA" stroke-width="2"/>
        <circle cx="40" cy="40" r="24" fill="#FEE2E2"/>
        <path
          d="M40 28v12"
          stroke="#EF4444"
          stroke-width="3"
          stroke-linecap="round"
        />
        <circle cx="40" cy="48" r="2" fill="#EF4444"/>
      </svg>
    </div>

    <!-- 標題 -->
    <h1 class="suspended-title">帳號已暫停使用</h1>
    <p class="suspended-subtitle">{{ userNickname }}，您的帳號目前處於停權狀態</p>

    <!-- 說明卡片 -->
    <div class="suspended-card">
      <h2 class="suspended-card__title">停權原因</h2>
      <p class="suspended-card__text">
        您的帳號因違反平台使用條款而被暫時停權。在停權期間，您將無法使用平台的任何功能。
      </p>

      <h2 class="suspended-card__title suspended-card__title--mt">停權期間限制</h2>
      <ul class="suspended-card__list">
        <li>無法瀏覽其他用戶資料</li>
        <li>無法發送或接收訊息</li>
        <li>無法使用探索與配對功能</li>
        <li>無法發布動態</li>
      </ul>
    </div>

    <!-- 申訴說明 -->
    <div class="suspended-appeal-info">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
      </svg>
      <p>如果您認為此停權決定有誤，可以提交申訴，我們將在 3 個工作天內回覆。</p>
    </div>

    <!-- 操作按鈕 -->
    <div class="suspended-actions">
      <button class="suspended-actions__btn suspended-actions__btn--primary" @click="goToAppeal">
        提交申訴
      </button>
      <button class="suspended-actions__btn suspended-actions__btn--secondary" @click="handleLogout">
        登出
      </button>
    </div>

    <!-- 底部連結 -->
    <div class="suspended-footer">
      <p class="suspended-footer__text">如有任何疑問，請聯繫客服</p>
      <a class="suspended-footer__link" href="mailto:support@mimeet.tw">support@mimeet.tw</a>
    </div>
  </div>
</template>

<style scoped>
.suspended-view {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100dvh;
  background: #F9F9FB;
  padding: 60px 24px 40px;
  text-align: center;
}

/* ── Icon ──────────────────────────────────────────────────── */
.suspended-icon {
  margin-bottom: 24px;
  animation: shake 0.6s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-6px); }
  40% { transform: translateX(6px); }
  60% { transform: translateX(-4px); }
  80% { transform: translateX(4px); }
}

/* ── Title ─────────────────────────────────────────────────── */
.suspended-title {
  font-size: 22px;
  font-weight: 700;
  color: #0F172A;
  margin-bottom: 8px;
}

.suspended-subtitle {
  font-size: 14px;
  color: #64748B;
  margin-bottom: 28px;
}

/* ── Card ──────────────────────────────────────────────────── */
.suspended-card {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #F1F5F9;
  padding: 20px;
  text-align: left;
  margin-bottom: 16px;
}

.suspended-card__title {
  font-size: 14px;
  font-weight: 600;
  color: #0F172A;
  margin-bottom: 8px;
}

.suspended-card__title--mt {
  margin-top: 16px;
}

.suspended-card__text {
  font-size: 13px;
  color: #64748B;
  line-height: 1.7;
}

.suspended-card__list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.suspended-card__list li {
  font-size: 13px;
  color: #64748B;
  line-height: 1.8;
  padding-left: 16px;
  position: relative;
}

.suspended-card__list li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 10px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #EF4444;
}

/* ── Appeal Info ────────────────────────────────────────────── */
.suspended-appeal-info {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  width: 100%;
  max-width: 400px;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 24px;
  text-align: left;
}

.suspended-appeal-info svg {
  flex-shrink: 0;
  margin-top: 1px;
}

.suspended-appeal-info p {
  font-size: 12px;
  color: #1E40AF;
  line-height: 1.6;
  margin: 0;
}

/* ── Actions ───────────────────────────────────────────────── */
.suspended-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  max-width: 400px;
}

.suspended-actions__btn {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: none;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.suspended-actions__btn:active {
  transform: scale(0.97);
}

.suspended-actions__btn--primary {
  background: #F0294E;
  color: #fff;
}

.suspended-actions__btn--primary:active {
  background: #D01A3C;
}

.suspended-actions__btn--secondary {
  background: #fff;
  color: #64748B;
  border: 1.5px solid #E2E8F0;
}

/* ── Footer ────────────────────────────────────────────────── */
.suspended-footer {
  margin-top: 32px;
}

.suspended-footer__text {
  font-size: 12px;
  color: #94A3B8;
  margin-bottom: 4px;
}

.suspended-footer__link {
  font-size: 12px;
  color: #F0294E;
  text-decoration: none;
  font-weight: 500;
}
</style>
