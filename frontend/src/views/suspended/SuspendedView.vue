<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getCurrentAppeal, type CurrentAppeal } from '@/api/appeals'

const router = useRouter()
const authStore = useAuthStore()

const creditScore = computed(() => authStore.user?.credit_score ?? 0)
const maxScore = 120
const scorePercent = computed(() => Math.max(0, Math.min(100, (creditScore.value / maxScore) * 100)))
const suspendReason = computed(() => {
  if (creditScore.value <= 0) return '您的帳號因誠信分數歸零已被系統自動停權'
  return '您的帳號因違反使用條款已被暫停'
})

// ── 進行中申訴 ─────────────────────────────────────────────
const currentAppeal = ref<CurrentAppeal | null>(null)
const appealLoaded = ref(false)
const hasPendingAppeal = computed(() => currentAppeal.value?.status === 'pending')

const submittedDateLabel = computed(() => {
  const iso = currentAppeal.value?.submitted_at
  if (!iso) return ''
  const d = new Date(iso)
  return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
})

// ── 分數動畫 ──────────────────────────────────────────────
const animatedScore = ref(0)

onMounted(async () => {
  // 分數動畫
  const target = creditScore.value
  if (target > 0) {
    const duration = 1000
    const start = performance.now()
    const animate = (now: number) => {
      const elapsed = now - start
      const progress = Math.min(elapsed / duration, 1)
      const eased = 1 - Math.pow(1 - progress, 3)
      animatedScore.value = Math.round(eased * target)
      if (progress < 1) requestAnimationFrame(animate)
    }
    requestAnimationFrame(animate)
  }

  // 載入進行中申訴狀態（API 失敗時靜默 fallback 為「無申訴」UI）
  try {
    currentAppeal.value = await getCurrentAppeal()
  } catch {
    currentAppeal.value = null
  } finally {
    appealLoaded.value = true
  }
})

function goToAppeal() {
  if (hasPendingAppeal.value) return
  router.push('/suspended/appeal')
}

function handleLogout() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('member_level')
  localStorage.removeItem('is_suspended')
  localStorage.removeItem('dev_identity_key')
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="suspended-page">
    <!-- 模糊背景：假 ExploreView -->
    <div class="suspended-bg" aria-hidden="true">
      <!-- 假 TopBar -->
      <div class="fake-topbar">
        <div class="fake-topbar__title" />
        <div class="fake-topbar__btn" />
      </div>
      <!-- 假搜尋框 -->
      <div class="fake-search" />
      <!-- 假標籤列 -->
      <div class="fake-tags">
        <span v-for="n in 5" :key="n" class="fake-tag" />
      </div>
      <!-- 假 UserCard x6 -->
      <div v-for="n in 6" :key="n" class="fake-card">
        <div class="fake-card__avatar" />
        <div class="fake-card__body">
          <div class="fake-card__line fake-card__line--name" />
          <div class="fake-card__line fake-card__line--sub" />
          <div class="fake-card__line fake-card__line--badges" />
        </div>
        <div class="fake-card__right">
          <div class="fake-card__badge" />
          <div class="fake-card__heart" />
        </div>
      </div>
    </div>

    <!-- 深色遮罩 -->
    <div class="suspended-overlay" />

    <!-- 中央卡片 -->
    <div class="suspended-card">
      <!-- ① 警示圖示 -->
      <div class="suspended-card__icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>

      <!-- ② 標題 -->
      <h1 class="suspended-card__title">帳號已暫停使用</h1>

      <!-- ③ 誠信分數 -->
      <div class="suspended-card__score">
        <span class="suspended-card__score-label">您的誠信分數</span>
        <div class="suspended-card__score-row">
          <span class="suspended-card__score-value">{{ animatedScore }}</span>
          <span class="suspended-card__score-max">/ {{ maxScore }}</span>
        </div>
        <div class="suspended-card__bar">
          <div
            class="suspended-card__bar-fill"
            :style="{ width: `${scorePercent}%` }"
          />
        </div>
      </div>

      <!-- ④ 停權原因 -->
      <p class="suspended-card__reason">{{ suspendReason }}</p>

      <!-- ⑤ 分隔線 -->
      <hr class="suspended-card__divider" />

      <!-- ⑥ 進行中申訴 / 說明文字 -->
      <div v-if="hasPendingAppeal" class="suspended-card__appeal-status">
        <div class="suspended-card__appeal-status-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <div class="suspended-card__appeal-status-body">
          <div class="suspended-card__appeal-status-title">已有申訴審核中</div>
          <div class="suspended-card__appeal-status-meta">
            案號 {{ currentAppeal?.ticket_no }}
            <span v-if="submittedDateLabel"> · {{ submittedDateLabel }}</span>
          </div>
          <div class="suspended-card__appeal-status-desc">
            審核通常於 3-5 個工作天內完成，結果將以 Email 通知您。
          </div>
        </div>
      </div>
      <p v-else class="suspended-card__desc">
        如您認為此停權有誤，可提出申訴。<br />
        我們將於 3-5 個工作天內審核，<br />
        審核結果將以 Email 通知您。
      </p>

      <!-- ⑦ 按鈕 -->
      <button
        class="suspended-card__btn suspended-card__btn--primary"
        :disabled="hasPendingAppeal || !appealLoaded"
        @click="goToAppeal"
      >
        {{ hasPendingAppeal ? '申訴審核中' : '提出申訴' }}
      </button>
      <button class="suspended-card__btn suspended-card__btn--secondary" @click="handleLogout">
        登出
      </button>
    </div>
  </div>
</template>

<style scoped>
.suspended-page {
  position: fixed;
  inset: 0;
  overflow: hidden;
}

/* ── 模糊背景層 ────────────────────────────────────────────── */
.suspended-bg {
  position: absolute;
  inset: 0;
  background: #F8F9FB;
  filter: blur(8px);
  opacity: 0.25;
  pointer-events: none;
  padding: 0 16px;
  animation: fade-in 0.3s ease;
}

@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 0.25; }
}

/* 假 TopBar */
.fake-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  padding: 0 4px;
}

.fake-topbar__title {
  width: 60px;
  height: 20px;
  background: #CBD5E1;
  border-radius: 6px;
}

.fake-topbar__btn {
  width: 36px;
  height: 36px;
  background: #E2E8F0;
  border-radius: 10px;
}

/* 假搜尋框 */
.fake-search {
  height: 44px;
  background: #E2E8F0;
  border-radius: 9999px;
  margin: 8px 0;
}

/* 假標籤列 */
.fake-tags {
  display: flex;
  gap: 8px;
  padding: 8px 0;
}

.fake-tag {
  width: 56px;
  height: 28px;
  background: #E2E8F0;
  border-radius: 9999px;
}

.fake-tag:first-child {
  background: #F0294E;
  opacity: 0.4;
}

/* 假 UserCard */
.fake-card {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 88px;
  background: #fff;
  border-radius: 14px;
  padding: 0 12px;
  margin-bottom: 8px;
}

.fake-card__avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #E2E8F0;
  flex-shrink: 0;
}

.fake-card__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.fake-card__line {
  height: 10px;
  background: #E2E8F0;
  border-radius: 5px;
}

.fake-card__line--name { width: 55%; }
.fake-card__line--sub { width: 40%; }
.fake-card__line--badges { width: 65%; height: 16px; }

.fake-card__right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.fake-card__badge {
  width: 36px;
  height: 18px;
  background: #E2E8F0;
  border-radius: 6px;
}

.fake-card__heart {
  width: 28px;
  height: 28px;
  background: #F1F5F9;
  border-radius: 50%;
}

/* ── 深色遮罩 ──────────────────────────────────────────────── */
.suspended-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.85);
  animation: fade-in-overlay 0.3s ease;
}

@keyframes fade-in-overlay {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* ── 中央卡片 ──────────────────────────────────────────────── */
.suspended-card {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: calc(100% - 48px);
  max-width: 380px;
  background: #fff;
  border-radius: 20px;
  padding: 32px 24px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  animation: slide-up-card 0.4s 0.1s ease-out both;
}

@keyframes slide-up-card {
  from { opacity: 0; transform: translate(-50%, calc(-50% + 20px)); }
  to { opacity: 1; transform: translate(-50%, -50%); }
}

/* ① 圖示 */
.suspended-card__icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #EF4444;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  box-shadow: 0 4px 16px rgba(239,68,68,0.3);
}

/* ② 標題 */
.suspended-card__title {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  margin-bottom: 20px;
}

/* ③ 分數 */
.suspended-card__score {
  width: 100%;
  background: #FEF2F2;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.suspended-card__score-label {
  font-size: 12px;
  color: #64748B;
  display: block;
  margin-bottom: 6px;
}

.suspended-card__score-row {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 4px;
  margin-bottom: 10px;
}

.suspended-card__score-value {
  font-size: 36px;
  font-weight: 800;
  color: #EF4444;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.suspended-card__score-max {
  font-size: 16px;
  color: #94A3B8;
  font-weight: 500;
}

.suspended-card__bar {
  width: 100%;
  height: 6px;
  background: #E2E8F0;
  border-radius: 3px;
  overflow: hidden;
}

.suspended-card__bar-fill {
  height: 100%;
  background: #EF4444;
  border-radius: 3px;
  transition: width 1s ease-out;
}

/* ④ 原因 */
.suspended-card__reason {
  font-size: 13px;
  color: #64748B;
  line-height: 1.6;
  margin-bottom: 16px;
}

/* ⑤ 分隔 */
.suspended-card__divider {
  width: 100%;
  border: none;
  border-top: 1px solid #F1F5F9;
  margin: 0 0 16px;
}

/* ⑥ 說明 */
.suspended-card__desc {
  font-size: 12px;
  color: #94A3B8;
  line-height: 1.7;
  margin-bottom: 20px;
}

/* ⑦ 按鈕 */
.suspended-card__btn {
  width: 100%;
  height: 46px;
  border-radius: 10px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.suspended-card__btn:active {
  transform: scale(0.97);
}

.suspended-card__btn + .suspended-card__btn {
  margin-top: 8px;
}

.suspended-card__btn--primary {
  background: #F0294E;
  color: #fff;
}

.suspended-card__btn--primary:active {
  background: #D01A3C;
}

.suspended-card__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.suspended-card__btn:disabled:active {
  transform: none;
}

/* ── 進行中申訴狀態卡片 ───────────────────────────────────── */
.suspended-card__appeal-status {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 20px;
  text-align: left;
  width: 100%;
  box-sizing: border-box;
}

.suspended-card__appeal-status-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.suspended-card__appeal-status-body {
  flex: 1;
  min-width: 0;
}

.suspended-card__appeal-status-title {
  font-size: 14px;
  font-weight: 600;
  color: #1E40AF;
  margin-bottom: 2px;
}

.suspended-card__appeal-status-meta {
  font-size: 12px;
  color: #1E40AF;
  font-variant-numeric: tabular-nums;
  margin-bottom: 4px;
}

.suspended-card__appeal-status-desc {
  font-size: 12px;
  color: #1E40AF;
  line-height: 1.5;
}

.suspended-card__btn--secondary {
  background: #fff;
  color: #64748B;
  border: 1.5px solid #E2E8F0;
}

.suspended-card__btn--secondary:active {
  background: #F8FAFC;
}
</style>
