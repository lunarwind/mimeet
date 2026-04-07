<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, type AuthUser } from '@/stores/auth'
import { getCreditLevel, CreditLevelLabel } from '@/types/user'

const router = useRouter()
const authStore = useAuthStore()

// ── Dev 身份定義 ──────────────────────────────────────────
interface DevIdentity {
  key: string
  label: string
  description: string
  user: AuthUser
  memberLevel: string
  suspended: string
}

const DEV_IDENTITIES: DevIdentity[] = [
  {
    key: 'lv1',
    label: 'Lv1 驗證會員',
    description: '普通誠信，僅Email驗證',
    user: {
      id: 99, email: 'lv1@test.com', nickname: '測試會員A', avatar: null,
      gender: 'male', status: 'active', credit_score: 45, membership_level: 1, verified: '1',
    },
    memberLevel: '1', suspended: 'false',
  },
  {
    key: 'lv2',
    label: 'Lv2 進階驗證',
    description: '優質誠信，三種驗證',
    user: {
      id: 98, email: 'lv2@test.com', nickname: '測試會員B', avatar: null,
      gender: 'female', status: 'active', credit_score: 78, membership_level: 2, verified: '2',
    },
    memberLevel: '2', suspended: 'false',
  },
  {
    key: 'lv3',
    label: 'Lv3 付費會員',
    description: '頂級誠信，全功能解鎖',
    user: {
      id: 97, email: 'lv3@test.com', nickname: '測試會員C', avatar: null,
      gender: 'male', status: 'active', credit_score: 95, membership_level: 3, verified: '3',
    },
    memberLevel: '3', suspended: 'false',
  },
  {
    key: 'limited',
    label: '受限用戶',
    description: '誠信 15 分，功能受限',
    user: {
      id: 96, email: 'limited@test.com', nickname: '測試會員D', avatar: null,
      gender: 'male', status: 'active', credit_score: 15, membership_level: 1, verified: '1',
    },
    memberLevel: '1', suspended: 'false',
  },
  {
    key: 'suspended',
    label: '已停權',
    description: '誠信 0 分，跳轉 /suspended',
    user: {
      id: 95, email: 'banned@test.com', nickname: '測試會員E', avatar: null,
      gender: 'male', status: 'suspended', credit_score: 0, membership_level: 0, verified: '0',
    },
    memberLevel: '0', suspended: 'true',
  },
]

const isDevLoggedIn = ref(!!localStorage.getItem('auth_token'))
const activeIdentityKey = ref<string | null>(localStorage.getItem('dev_identity_key'))

const activeIdentity = computed(() =>
  DEV_IDENTITIES.find(i => i.key === activeIdentityKey.value) ?? null
)

const activeCreditLabel = computed(() => {
  if (!activeIdentity.value) return ''
  return CreditLevelLabel[getCreditLevel(activeIdentity.value.user.credit_score)]
})

function switchIdentity(identity: DevIdentity) {
  localStorage.setItem('auth_token', 'dev-mock-token')
  localStorage.setItem('member_level', identity.memberLevel)
  localStorage.setItem('is_suspended', identity.suspended)
  localStorage.setItem('dev_identity_key', identity.key)
  authStore.setDevUser(identity.user)
  isDevLoggedIn.value = true
  activeIdentityKey.value = identity.key
}

function devLogout() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('member_level')
  localStorage.removeItem('is_suspended')
  localStorage.removeItem('dev_identity_key')
  authStore.logout()
  isDevLoggedIn.value = false
  activeIdentityKey.value = null
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
      { id: 's3-03-age',      label: '年齡滑桿可拖動，拖動時顯示圓點位置' },
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
      { id: 's3-04-msg',       label: '傳送訊息按鈕' },
      { id: 's3-04-bio',       label: '個人簡介超過80字顯示「展開」' },
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
  {
    title: 'S3-08 AppealView',
    items: [
      { id: 's3-08-load',     label: '頁面能正常載入',                     link: '#/suspended/appeal' },
      { id: 's3-08-fields',   label: '表單欄位顯示（原因/佐證/上傳圖片）' },
      { id: 's3-08-counter',  label: '字數計數器運作（輸入文字確認數字更新）' },
      { id: 's3-08-success',  label: '送出後顯示案號畫面' },
      { id: 's3-08-format',   label: '案號格式正確（APPEAL-開頭）' },
    ],
  },
  {
    title: 'S3-09 useProfile',
    items: [
      { id: 's3-09-load',     label: 'ProfileView 能正常載入用戶資料',      link: '#/app/profiles/1' },
      { id: 's3-09-cache',    label: '60秒快取運作（連續進出同一 Profile，Network 只打一次 API）' },
      { id: 's3-09-fav',      label: '收藏/取消收藏狀態切換正確' },
      { id: 's3-09-update',   label: '資料更新後畫面即時反映' },
    ],
  },
  {
    title: 'S3-10 useInfiniteScroll',
    items: [
      { id: 's3-10-scroll',   label: 'ExploreView 無限滾動正常（捲到底自動載入）', link: '#/app/explore' },
      { id: 's3-10-pages',    label: '第1頁20筆、第2頁20筆、第3頁10筆（共50筆）' },
      { id: 's3-10-end',      label: '載入完畢顯示「已顯示全部 50 位用戶」' },
      { id: 's3-10-reset',    label: 'reset() 正常運作（切換篩選後列表重置）' },
    ],
  },
  {
    title: '停權體驗（切換身份5 測試）',
    items: [
      { id: 'sus-redirect',  label: '切換為「身份5 已停權」後點任意 /app/* 連結 → 立即跳轉 /suspended', link: '#/app/explore' },
      { id: 'sus-blur',      label: '/suspended 頁面背景有模糊虛景效果' },
      { id: 'sus-score',     label: '誠信分數顯示正確（應為 0）' },
      { id: 'sus-bar',       label: '分數進度條為紅色' },
      { id: 'sus-appeal',    label: '「提出申訴」按鈕導向 /suspended/appeal',   link: '#/suspended/appeal' },
      { id: 'sus-logout',    label: '「登出」按鈕清除 session 並跳至 /login' },
    ],
  },
  // ═══════════════════════════════════════════════════════════
  //  Sprint 4
  // ═══════════════════════════════════════════════════════════
  {
    title: 'S4-01 AppLayout + BottomNav + TopBar',
    items: [
      { id: 's4-01-bottomnav',   label: '探索頁底部顯示 BottomNav（5 Tab）',          link: '#/app/explore' },
      { id: 's4-01-tab-active',  label: '當前頁面 Tab 為紅色高亮' },
      { id: 's4-01-tab-explore', label: '點「探索」Tab 切換至 /app/explore' },
      { id: 's4-01-tab-msg',     label: '點「訊息」Tab 切換至 /app/messages' },
      { id: 's4-01-tab-date',    label: '點「約會」Tab 切換至 /app/dates' },
      { id: 's4-01-tab-notify',  label: '點「通知」Tab 切換至 /app/notifications' },
      { id: 's4-01-tab-me',      label: '點「我的」Tab 切換至 /app/settings' },
      { id: 's4-01-topbar',      label: 'TopBar 元件可複用（有 title / showBack / right slot）' },
      { id: 's4-01-safe-area',   label: 'BottomNav 有 safe-area-inset-bottom 留白' },
    ],
  },
  {
    title: 'S4-02 AccountView（帳號設定）',
    items: [
      { id: 's4-02-load',     label: '頁面能正常載入',                           link: '#/app/settings' },
      { id: 's4-02-avatar',   label: '頭像 + 暱稱 + Email 顯示' },
      { id: 's4-02-badges',   label: '驗證徽章正確顯示（依身份等級）' },
      { id: 's4-02-credit',   label: '誠信分數 + 進度條 + 等級標籤顯示' },
      { id: 's4-02-verify',   label: '身份驗證入口可點，跳至 /app/settings/verify', link: '#/app/settings/verify' },
      { id: 's4-02-blocked',  label: '封鎖名單入口可點' },
      { id: 's4-02-reports',  label: '問題回報入口可點' },
      { id: 's4-02-delete',   label: '刪除帳號入口可點' },
      { id: 's4-02-logout',   label: '登出按鈕清除 session 並跳至 /login' },
    ],
  },
  {
    title: 'S4-03 BlockedView（封鎖名單）',
    items: [
      { id: 's4-03-load',     label: '頁面能正常載入',                           link: '#/app/settings/blocked' },
      { id: 's4-03-back',     label: 'TopBar 有返回按鈕，可回上一頁' },
      { id: 's4-03-list',     label: '封鎖名單列表顯示（頭像 + 暱稱 + 封鎖日期）' },
      { id: 's4-03-unblock',  label: '「解除封鎖」按鈕點擊後用戶從列表移除' },
      { id: 's4-03-empty',    label: '解除全部後顯示空狀態' },
    ],
  },
  {
    title: 'S4-04 DeleteAccountView（刪除帳號）',
    items: [
      { id: 's4-04-load',     label: '頁面能正常載入',                           link: '#/app/settings/delete-account' },
      { id: 's4-04-warning',  label: '紅色警告 Banner 顯示「7天冷靜期」說明' },
      { id: 's4-04-reason',   label: '刪除原因 Radio 可選（5 個選項）' },
      { id: 's4-04-password', label: '密碼輸入框可輸入' },
      { id: 's4-04-submit',   label: '送出後顯示冷靜期等待畫面（含預計刪除日期）' },
      { id: 's4-04-cancel',   label: '「取消刪除」按鈕點擊後顯示成功恢復畫面' },
    ],
  },
  {
    title: 'S4-05 NotificationsView（通知中心）',
    items: [
      { id: 's4-05-load',      label: '頁面能正常載入',                          link: '#/app/notifications' },
      { id: 's4-05-skeleton',   label: '載入中顯示骨架屏' },
      { id: 's4-05-list',       label: '通知列表顯示（icon + 內容 + 時間）' },
      { id: 's4-05-unread',     label: '未讀通知有黃色背景 + 紅色圓點' },
      { id: 's4-05-tap',        label: '點擊通知標記已讀（黃色消失）' },
      { id: 's4-05-mark-all',   label: '「全部已讀」按鈕一次標記所有已讀' },
      { id: 's4-05-scroll',     label: '無限滾動載入更多通知' },
    ],
  },
  {
    title: 'S4-06 ShopView（會員商城）',
    items: [
      { id: 's4-06-load',     label: '頁面能正常載入',                          link: '#/app/shop' },
      { id: 's4-06-plans',    label: '三個方案卡片顯示（月/季/年）' },
      { id: 's4-06-select',   label: '點擊方案卡片切換選中狀態（紅框）' },
      { id: 's4-06-popular',  label: '季費方案有「最受歡迎」標籤' },
      { id: 's4-06-subscribe',label: '「立即訂閱」按鈕可點' },
      { id: 's4-06-trial',    label: '體驗價入口可點，跳至 /app/shop/trial',     link: '#/app/shop/trial' },
      { id: 's4-06-upgrade',  label: '從 /app/messages 跳轉來時顯示升級提示 Banner' },
    ],
  },
  {
    title: 'S4-07 TrialView（體驗訂閱）',
    items: [
      { id: 's4-07-load',     label: '頁面能正常載入',                          link: '#/app/shop/trial' },
      { id: 's4-07-hero',     label: '漸層 Hero 卡片顯示 NT$ 199 / 30天' },
      { id: 's4-07-features', label: '體驗內容清單顯示（5 項）' },
      { id: 's4-07-notice',   label: '注意事項黃色方塊顯示' },
      { id: 's4-07-purchase', label: '購買按鈕點擊後顯示成功畫面' },
    ],
  },
  {
    title: 'S4-08 MessagesView（聊天列表）',
    items: [
      { id: 's4-08-load',     label: '頁面能正常載入（需 Lv2+ 身份）',            link: '#/app/messages' },
      { id: 's4-08-skeleton',  label: '載入中顯示骨架屏' },
      { id: 's4-08-list',      label: '對話列表顯示（頭像 + 暱稱 + 最後訊息 + 時間）' },
      { id: 's4-08-online',    label: '在線用戶有綠色圓點' },
      { id: 's4-08-badge',     label: '未讀訊息有紅色數字 Badge' },
      { id: 's4-08-click',     label: '點擊對話可進入 ChatView' },
      { id: 's4-08-guard',     label: '切換身份1（Lv1）→ 進入 /app/messages 被攔截至 /app/shop' },
    ],
  },
  {
    title: 'S4-09 ChatView（聊天對話）',
    items: [
      { id: 's4-09-load',     label: '頁面能正常載入',                          link: '#/app/messages/1' },
      { id: 's4-09-topbar',    label: 'TopBar 顯示對方頭像 + 暱稱 + 在線狀態' },
      { id: 's4-09-messages',  label: '歷史訊息正確顯示（左=對方，右=自己）' },
      { id: 's4-09-send',      label: '輸入訊息並送出，氣泡出現在右側' },
      { id: 's4-09-reply',     label: '送出後 1-3 秒對方自動回覆' },
      { id: 's4-09-scroll',    label: '新訊息自動捲到底部' },
      { id: 's4-09-no-nav',    label: 'BottomNav 不顯示（hideBottomNav）' },
    ],
  },
  {
    title: 'S4-10 ReportsView（問題回報）',
    items: [
      { id: 's4-10-load',     label: '頁面能正常載入',                          link: '#/app/reports' },
      { id: 's4-10-type',      label: '回報類型 Chip 可切換（一般/系統/匿名）' },
      { id: 's4-10-reason',    label: '具體原因下拉選單依類型動態更新' },
      { id: 's4-10-textarea',  label: '詳細說明文字框 + 字數計數器' },
      { id: 's4-10-submit',    label: '送出後顯示案號成功畫面' },
      { id: 's4-10-history',   label: '「歷史紀錄」切換後顯示過往案件列表' },
      { id: 's4-10-reply',     label: '已處理案件顯示管理員回覆（綠色區塊）' },
    ],
  },
]

const totalItems = groups.reduce((sum, g) => sum + g.items.length, 0)

// ── 勾選狀態（localStorage 持久化） ──────────────────────
type CheckState = 'none' | 'pass' | 'fail'
const STORAGE_KEY = 'sprint-check-state'

function loadState(): Record<string, CheckState> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch { return {} }
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

function resetAll() { states.value = {} }

function goTest(link: string) {
  // link 格式為 '#/app/explore'，去掉 '#' 前綴後用 router.push 導航
  const path = link.startsWith('#') ? link.slice(1) : link
  router.push(path)
}

const passCount = computed(() => Object.values(states.value).filter(s => s === 'pass').length)
const failCount = computed(() => Object.values(states.value).filter(s => s === 'fail').length)

function groupPassCount(group: CheckGroup): number {
  return group.items.filter(i => getState(i.id) === 'pass').length
}
</script>

<template>
  <div class="check-page">
    <!-- Header -->
    <header class="check-header">
      <div class="check-header__left">
        <h1 class="check-header__title">Sprint 3 & 4 檢核清單</h1>
        <p class="check-header__sub">點擊圓圈切換：未測 → 通過 → 失敗</p>
      </div>
      <div class="check-header__right">
        <div class="check-progress">
          <span class="check-progress__pass">{{ passCount }}</span>
          <span class="check-progress__sep">/</span>
          <span class="check-progress__total">{{ totalItems }}</span>
          <span class="check-progress__label">通過</span>
        </div>
        <span v-if="failCount > 0" class="check-progress__fail-tag">{{ failCount }} 失敗</span>
        <button class="check-reset" @click="resetAll" title="全部重設">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
          </svg>
        </button>
      </div>
    </header>

    <!-- Identity Switcher -->
    <div class="id-switcher">
      <div class="id-switcher__label">
        <span class="id-switcher__dot" :class="isDevLoggedIn ? 'id-switcher__dot--on' : 'id-switcher__dot--off'" />
        <span v-if="activeIdentity" class="id-switcher__current">
          {{ activeIdentity.label }}
          <span class="id-switcher__credit" :class="`id-switcher__credit--${getCreditLevel(activeIdentity.user.credit_score)}`">
            {{ activeCreditLabel }}
          </span>
        </span>
        <span v-else class="id-switcher__hint">選擇測試身份</span>
      </div>
      <div class="id-switcher__btns">
        <button
          v-for="identity in DEV_IDENTITIES"
          :key="identity.key"
          class="id-btn"
          :class="{ 'id-btn--active': activeIdentityKey === identity.key }"
          :title="identity.description"
          @click="switchIdentity(identity)"
        >
          {{ identity.label }}
        </button>
        <button v-if="isDevLoggedIn" class="id-btn id-btn--logout" @click="devLogout">登出</button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="check-bar">
      <div class="check-bar__fill check-bar__fill--pass" :style="{ width: `${(passCount / totalItems) * 100}%` }" />
      <div class="check-bar__fill check-bar__fill--fail" :style="{ width: `${(failCount / totalItems) * 100}%` }" />
    </div>

    <!-- Groups -->
    <div class="check-groups">
      <section v-for="group in groups" :key="group.title" class="check-group">
        <div class="check-group__header">
          <h2 class="check-group__title">{{ group.title }}</h2>
          <span class="check-group__count">{{ groupPassCount(group) }}/{{ group.items.length }}</span>
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
          <button
            class="check-item__circle"
            :class="{
              'check-item__circle--pass': getState(item.id) === 'pass',
              'check-item__circle--fail': getState(item.id) === 'fail',
            }"
            @click="cycle(item.id)"
            :aria-label="`${item.label} - ${getState(item.id) === 'pass' ? '通過' : getState(item.id) === 'fail' ? '失敗' : '未測試'}`"
          >
            <svg v-if="getState(item.id) === 'pass'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <svg v-else-if="getState(item.id) === 'fail'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
          <span class="check-item__label">{{ item.label }}</span>
          <button v-if="item.link" class="check-item__link" @click="goTest(item.link!)" :title="`前往 ${item.link}`">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            前往
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.check-page { min-height: 100dvh; background: #F8F9FB; padding-bottom: 40px; }

/* ── Header ────────────────────────────────────────────────── */
.check-header { display:flex; align-items:flex-start; justify-content:space-between; padding:24px 20px 16px; background:#fff; border-bottom:1px solid #F1F5F9; gap:12px; flex-wrap:wrap; }
.check-header__title { font-size:20px; font-weight:700; color:#0F172A; }
.check-header__sub { font-size:12px; color:#94A3B8; margin-top:4px; }
.check-header__right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
.check-progress { display:flex; align-items:baseline; gap:2px; }
.check-progress__pass { font-size:28px; font-weight:800; color:#22C55E; font-variant-numeric:tabular-nums; }
.check-progress__sep { font-size:18px; color:#CBD5E1; margin:0 1px; }
.check-progress__total { font-size:18px; font-weight:600; color:#94A3B8; font-variant-numeric:tabular-nums; }
.check-progress__label { font-size:12px; color:#94A3B8; margin-left:4px; }
.check-progress__fail-tag { font-size:11px; font-weight:600; color:#EF4444; background:#FEF2F2; padding:3px 8px; border-radius:6px; }
.check-reset { width:32px; height:32px; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#94A3B8; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.check-reset:hover { color:#EF4444; border-color:#FECACA; background:#FEF2F2; }

/* ── Identity Switcher ─────────────────────────────────────── */
.id-switcher { padding:12px 20px; background:#FFFBEB; border-bottom:1px solid #FDE68A; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.id-switcher__label { display:flex; align-items:center; gap:8px; }
.id-switcher__dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.id-switcher__dot--on { background:#22C55E; }
.id-switcher__dot--off { background:#EF4444; }
.id-switcher__current { font-size:13px; font-weight:600; color:#92400E; display:flex; align-items:center; gap:6px; }
.id-switcher__hint { font-size:12px; color:#92400E; }
.id-switcher__credit { font-size:10px; font-weight:700; padding:2px 7px; border-radius:9999px; }
.id-switcher__credit--top { background:linear-gradient(135deg,#FDE68A,#FCD34D); color:#92400E; }
.id-switcher__credit--good { background:#ECFDF5; color:#065F46; }
.id-switcher__credit--normal { background:#EFF6FF; color:#1E40AF; }
.id-switcher__credit--low { background:#FEF2F2; color:#991B1B; }
.id-switcher__btns { display:flex; gap:6px; flex-wrap:wrap; }
.id-btn { height:28px; padding:0 10px; border-radius:6px; border:1px solid #E2E8F0; background:#fff; font-size:11px; font-weight:600; color:#475569; cursor:pointer; white-space:nowrap; transition:all 0.15s; }
.id-btn:hover { border-color:#F0294E; color:#F0294E; }
.id-btn--active { background:#F0294E; border-color:#F0294E; color:#fff; }
.id-btn--logout { border-color:#E2E8F0; color:#64748B; background:#fff; }
.id-btn--logout:hover { border-color:#EF4444; color:#EF4444; }

/* ── Progress Bar ──────────────────────────────────────────── */
.check-bar { height:4px; background:#E2E8F0; display:flex; overflow:hidden; }
.check-bar__fill { height:100%; transition:width 0.3s ease; }
.check-bar__fill--pass { background:#22C55E; }
.check-bar__fill--fail { background:#EF4444; }

/* ── Groups ────────────────────────────────────────────────── */
.check-groups { padding:12px 16px; display:flex; flex-direction:column; gap:12px; }
.check-group { background:#fff; border-radius:14px; border:1px solid #F1F5F9; overflow:hidden; }
.check-group__header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px 10px; border-bottom:1px solid #F8FAFC; }
.check-group__title { font-size:14px; font-weight:700; color:#0F172A; }
.check-group__count { font-size:12px; font-weight:600; color:#94A3B8; font-variant-numeric:tabular-nums; }

/* ── Item ──────────────────────────────────────────────────── */
.check-item { display:flex; align-items:center; gap:10px; padding:10px 16px; border-bottom:0.5px solid #F8FAFC; transition:background 0.15s; }
.check-item:last-child { border-bottom:none; }
.check-item--pass { background:#F0FDF4; }
.check-item--fail { background:#FEF2F2; }

.check-item__circle { width:28px; height:28px; border-radius:50%; border:2px solid #D1D5DB; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; padding:0; }
.check-item__circle:hover { border-color:#94A3B8; }
.check-item__circle--pass { background:#22C55E; border-color:#22C55E; color:#fff; }
.check-item__circle--fail { background:#EF4444; border-color:#EF4444; color:#fff; }

.check-item__label { flex:1; font-size:13px; color:#334155; line-height:1.5; min-width:0; }
.check-item--pass .check-item__label { color:#166534; }
.check-item--fail .check-item__label { color:#991B1B; }

.check-item__link { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#F0294E; border:none; padding:4px 10px; border-radius:6px; background:#FFF0F3; white-space:nowrap; flex-shrink:0; cursor:pointer; transition:background 0.15s; }
.check-item__link:hover { background:#FFE4EA; }
.check-item__link:active { transform:scale(0.95); }
</style>
