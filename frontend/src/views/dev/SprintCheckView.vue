<script setup lang="ts">
import { ref, computed, watch } from 'vue'

// ── Dev 快速登入（寫入 localStorage 讓路由守衛放行） ──────
const isDevLoggedIn = ref(!!localStorage.getItem('auth_token'))
const devRole = ref<'basic' | 'paid' | 'suspended'>('basic')
const devRoleLabel = ref(getDevRoleLabel())

function getDevRoleLabel(): string {
  if (!localStorage.getItem('auth_token')) return ''
  if (localStorage.getItem('is_suspended') === 'true') return '停權'
  return `Lv.${localStorage.getItem('member_level') ?? '?'}`
}

function devLogin() {
  const roleMap = {
    basic:     { level: '1', suspended: 'false' },
    paid:      { level: '3', suspended: 'false' },
    suspended: { level: '1', suspended: 'true' },
  }
  const cfg = roleMap[devRole.value]
  localStorage.setItem('auth_token', 'dev-mock-token')
  localStorage.setItem('member_level', cfg.level)
  localStorage.setItem('is_suspended', cfg.suspended)
  isDevLoggedIn.value = true
  devRoleLabel.value = getDevRoleLabel()
}

function devLogout() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('member_level')
  localStorage.removeItem('is_suspended')
  isDevLoggedIn.value = false
  devRoleLabel.value = ''
}

// ── 檢核項目定義 ──────────────────────────────────────────
interface CheckItem {
  id: string
  label: string
  link?: string
}

interface CheckGroup {
  title: string
  items: CheckItem[]
}

const groups: CheckGroup[] = [
  {
    title: 'S3-01 探索頁',
    items: [
      { id: 's3-01-load',     label: '頁面能正常載入',                     link: '#/app/explore' },
      { id: 's3-01-skeleton',  label: '骨架屏 shimmer 動畫出現' },
      { id: 's3-01-cards',     label: '用戶卡片列表顯示' },
      { id: 's3-01-tags',      label: '地區標籤切換（點台北/台中/高雄）' },
      { id: 's3-01-search',    label: '搜尋框輸入有反應' },
      { id: 's3-01-infinite',  label: '捲到底部自動載入下一頁' },
      { id: 's3-01-filter',    label: '篩選漏斗可以開啟 Bottom Sheet' },
    ],
  },
  {
    title: 'S3-02 UserCard',
    items: [
      { id: 's3-02-height',   label: '卡片高度 88px' },
      { id: 's3-02-avatar',   label: '頭像左側顯示' },
      { id: 's3-02-dot',      label: '線上狀態點（綠/灰）' },
      { id: 's3-02-credit',   label: '誠信等級徽章顯示正確顏色' },
      { id: 's3-02-fav',      label: '收藏按鈕點擊有動畫' },
    ],
  },
  {
    title: 'S3-03 篩選 Bottom Sheet',
    items: [
      { id: 's3-03-slide',    label: '從底部滑入動畫' },
      { id: 's3-03-age',      label: '年齡滑桿可拖動' },
      { id: 's3-03-gender',   label: '性別 Radio 可選' },
      { id: 's3-03-credit',   label: '誠信分數 Chip 可選' },
      { id: 's3-03-city',     label: '地區 Checkbox 多選' },
      { id: 's3-03-apply',    label: '套用後漏斗出現紅點' },
      { id: 's3-03-reset',    label: '重設後紅點消失' },
    ],
  },
  {
    title: 'S3-04 ProfileView',
    items: [
      { id: 's3-04-load',     label: '頁面能正常載入',                     link: '#/app/profiles/1' },
      { id: 's3-04-gallery',   label: '圖片輪播區顯示' },
      { id: 's3-04-info',      label: '基本資訊顯示' },
      { id: 's3-04-fav',       label: '收藏按鈕可點' },
      { id: 's3-04-msg',       label: '傳送訊息按鈕（非付費會員應彈出升級 Modal）' },
      { id: 's3-04-bio',       label: '個人簡介超過4行顯示「展開」' },
    ],
  },
  {
    title: 'S3-05 VerifyBadge',
    items: [
      { id: 's3-05-email',    label: 'Email 徽章藍色' },
      { id: 's3-05-phone',    label: '手機徽章綠色' },
      { id: 's3-05-advanced', label: '進階驗證徽章橙色' },
    ],
  },
  {
    title: 'S3-06 VerifyView',
    items: [
      { id: 's3-06-load',     label: '頁面能正常載入',                     link: '#/app/settings/verify' },
      { id: 's3-06-send',     label: '發送驗證碼按鈕' },
      { id: 's3-06-cooldown', label: '60秒倒數計時' },
      { id: 's3-06-advanced', label: '進階驗證入口顯示' },
    ],
  },
  {
    title: 'S3-07 SuspendedView',
    items: [
      { id: 's3-07-load',     label: '頁面能正常載入',                     link: '#/suspended' },
      { id: 's3-07-reason',   label: '停權原因說明顯示' },
      { id: 's3-07-appeal',   label: '申訴按鈕可點' },
      { id: 's3-07-no-nav',   label: '底部 BottomNav 不顯示' },
    ],
  },
]

const totalItems = groups.reduce((sum, g) => sum + g.items.length, 0)

// ── 勾選狀態（localStorage 持久化） ──────────────────────
type CheckState = 'none' | 'pass' | 'fail'

const STORAGE_KEY = 'sprint3-check-state'

function loadState(): Record<string, CheckState> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

const states = ref<Record<string, CheckState>>(loadState())

watch(states, (val) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
}, { deep: true })

function getState(id: string): CheckState {
  return states.value[id] ?? 'none'
}

function cycle(id: string) {
  const order: CheckState[] = ['none', 'pass', 'fail']
  const cur = getState(id)
  const next = order[(order.indexOf(cur) + 1) % order.length]
  states.value[id] = next
}

function resetAll() {
  states.value = {}
}

// ── 統計 ──────────────────────────────────────────────────
const passCount = computed(() =>
  Object.values(states.value).filter(s => s === 'pass').length
)

const failCount = computed(() =>
  Object.values(states.value).filter(s => s === 'fail').length
)

function groupPassCount(group: CheckGroup): number {
  return group.items.filter(i => getState(i.id) === 'pass').length
}
</script>

<template>
  <div class="check-page">
    <!-- Header -->
    <header class="check-header">
      <div class="check-header__left">
        <h1 class="check-header__title">Sprint 3 檢核清單</h1>
        <p class="check-header__sub">點擊圓圈切換：未測 → 通過 → 失敗</p>
      </div>
      <div class="check-header__right">
        <div class="check-progress">
          <span class="check-progress__pass">{{ passCount }}</span>
          <span class="check-progress__sep">/</span>
          <span class="check-progress__total">{{ totalItems }}</span>
          <span class="check-progress__label">通過</span>
        </div>
        <span v-if="failCount > 0" class="check-progress__fail-tag">
          {{ failCount }} 失敗
        </span>
        <button class="check-reset" @click="resetAll" title="全部重設">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
          </svg>
        </button>
      </div>
    </header>

    <!-- Dev Login Panel -->
    <div class="dev-login">
      <div class="dev-login__status">
        <span
          class="dev-login__dot"
          :class="isDevLoggedIn ? 'dev-login__dot--on' : 'dev-login__dot--off'"
        />
        <span class="dev-login__text">
          {{ isDevLoggedIn ? '已登入（dev mock）' : '未登入 — 需登入才能測試 /app/* 頁面' }}
        </span>
      </div>
      <div class="dev-login__actions">
        <template v-if="!isDevLoggedIn">
          <select v-model="devRole" class="dev-login__select">
            <option value="basic">基本會員（Lv.1）</option>
            <option value="paid">付費會員（Lv.3）</option>
            <option value="suspended">停權帳號</option>
          </select>
          <button class="dev-login__btn dev-login__btn--login" @click="devLogin">
            Dev 登入
          </button>
        </template>
        <template v-else>
          <span class="dev-login__role">{{ devRoleLabel }}</span>
          <button class="dev-login__btn dev-login__btn--logout" @click="devLogout">
            登出
          </button>
        </template>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="check-bar">
      <div
        class="check-bar__fill check-bar__fill--pass"
        :style="{ width: `${(passCount / totalItems) * 100}%` }"
      />
      <div
        class="check-bar__fill check-bar__fill--fail"
        :style="{ width: `${(failCount / totalItems) * 100}%` }"
      />
    </div>

    <!-- Groups -->
    <div class="check-groups">
      <section v-for="group in groups" :key="group.title" class="check-group">
        <div class="check-group__header">
          <h2 class="check-group__title">{{ group.title }}</h2>
          <span class="check-group__count">
            {{ groupPassCount(group) }}/{{ group.items.length }}
          </span>
        </div>

        <div
          v-for="item in group.items"
          :key="item.id"
          class="check-item"
          :class="{
            'check-item--pass': getState(item.id) === 'pass',
            'check-item--fail': getState(item.id) === 'fail',
          }"
        >
          <!-- 狀態圓圈 -->
          <button
            class="check-item__circle"
            :class="{
              'check-item__circle--pass': getState(item.id) === 'pass',
              'check-item__circle--fail': getState(item.id) === 'fail',
            }"
            @click="cycle(item.id)"
            :aria-label="`${item.label} - 目前狀態：${
              getState(item.id) === 'pass' ? '通過' :
              getState(item.id) === 'fail' ? '失敗' : '未測試'
            }`"
          >
            <!-- pass: checkmark -->
            <svg v-if="getState(item.id) === 'pass'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            <!-- fail: x -->
            <svg v-else-if="getState(item.id) === 'fail'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>

          <!-- 說明文字 -->
          <span class="check-item__label">{{ item.label }}</span>

          <!-- 連結按鈕 -->
          <a
            v-if="item.link"
            :href="item.link"
            class="check-item__link"
            target="_blank"
            :title="`前往 ${item.link}`"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            前往
          </a>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.check-page {
  min-height: 100dvh;
  background: #F8F9FB;
  padding-bottom: 40px;
}

/* ── Header ────────────────────────────────────────────────── */
.check-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 24px 20px 16px;
  background: #fff;
  border-bottom: 1px solid #F1F5F9;
  gap: 12px;
  flex-wrap: wrap;
}

.check-header__title {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
}

.check-header__sub {
  font-size: 12px;
  color: #94A3B8;
  margin-top: 4px;
}

.check-header__right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.check-progress {
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.check-progress__pass {
  font-size: 28px;
  font-weight: 800;
  color: #22C55E;
  font-variant-numeric: tabular-nums;
}

.check-progress__sep {
  font-size: 18px;
  color: #CBD5E1;
  margin: 0 1px;
}

.check-progress__total {
  font-size: 18px;
  font-weight: 600;
  color: #94A3B8;
  font-variant-numeric: tabular-nums;
}

.check-progress__label {
  font-size: 12px;
  color: #94A3B8;
  margin-left: 4px;
}

.check-progress__fail-tag {
  font-size: 11px;
  font-weight: 600;
  color: #EF4444;
  background: #FEF2F2;
  padding: 3px 8px;
  border-radius: 6px;
}

.check-reset {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #E2E8F0;
  background: #fff;
  color: #94A3B8;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}

.check-reset:hover {
  color: #EF4444;
  border-color: #FECACA;
  background: #FEF2F2;
}

/* ── Dev Login Panel ───────────────────────────────────────── */
.dev-login {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 20px;
  background: #FFFBEB;
  border-bottom: 1px solid #FDE68A;
  flex-wrap: wrap;
}

.dev-login__status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dev-login__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dev-login__dot--on { background: #22C55E; }
.dev-login__dot--off { background: #EF4444; }

.dev-login__text {
  font-size: 12px;
  color: #92400E;
  font-weight: 500;
}

.dev-login__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dev-login__select {
  height: 30px;
  padding: 0 8px;
  border-radius: 6px;
  border: 1px solid #FDE68A;
  background: #fff;
  font-size: 12px;
  color: #92400E;
  cursor: pointer;
}

.dev-login__btn {
  height: 30px;
  padding: 0 14px;
  border-radius: 6px;
  border: none;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.dev-login__btn--login {
  background: #F0294E;
  color: #fff;
}

.dev-login__btn--login:hover {
  background: #D01A3C;
}

.dev-login__btn--logout {
  background: #fff;
  color: #64748B;
  border: 1px solid #E2E8F0;
}

.dev-login__btn--logout:hover {
  background: #F8FAFC;
}

.dev-login__role {
  font-size: 12px;
  font-weight: 600;
  color: #065F46;
  background: #ECFDF5;
  padding: 4px 10px;
  border-radius: 6px;
}

/* ── Progress Bar ──────────────────────────────────────────── */
.check-bar {
  height: 4px;
  background: #E2E8F0;
  display: flex;
  overflow: hidden;
}

.check-bar__fill {
  height: 100%;
  transition: width 0.3s ease;
}

.check-bar__fill--pass {
  background: #22C55E;
}

.check-bar__fill--fail {
  background: #EF4444;
}

/* ── Groups ────────────────────────────────────────────────── */
.check-groups {
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.check-group {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #F1F5F9;
  overflow: hidden;
}

.check-group__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px 10px;
  border-bottom: 1px solid #F8FAFC;
}

.check-group__title {
  font-size: 14px;
  font-weight: 700;
  color: #0F172A;
}

.check-group__count {
  font-size: 12px;
  font-weight: 600;
  color: #94A3B8;
  font-variant-numeric: tabular-nums;
}

/* ── Item ──────────────────────────────────────────────────── */
.check-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-bottom: 0.5px solid #F8FAFC;
  transition: background 0.15s;
}

.check-item:last-child {
  border-bottom: none;
}

.check-item--pass {
  background: #F0FDF4;
}

.check-item--fail {
  background: #FEF2F2;
}

/* ── Circle ────────────────────────────────────────────────── */
.check-item__circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #D1D5DB;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s;
  padding: 0;
}

.check-item__circle:hover {
  border-color: #94A3B8;
}

.check-item__circle--pass {
  background: #22C55E;
  border-color: #22C55E;
  color: #fff;
}

.check-item__circle--fail {
  background: #EF4444;
  border-color: #EF4444;
  color: #fff;
}

/* ── Label ─────────────────────────────────────────────────── */
.check-item__label {
  flex: 1;
  font-size: 13px;
  color: #334155;
  line-height: 1.5;
  min-width: 0;
}

.check-item--pass .check-item__label {
  color: #166534;
}

.check-item--fail .check-item__label {
  color: #991B1B;
}

/* ── Link ──────────────────────────────────────────────────── */
.check-item__link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #F0294E;
  text-decoration: none;
  padding: 4px 10px;
  border-radius: 6px;
  background: #FFF0F3;
  white-space: nowrap;
  flex-shrink: 0;
  transition: background 0.15s;
}

.check-item__link:hover {
  background: #FFE4EA;
}
</style>
