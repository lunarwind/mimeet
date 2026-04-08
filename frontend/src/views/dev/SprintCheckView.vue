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
    title: 'S4-01 MessagesView',
    items: [
      { id: 's4-01-load',     label: '頁面正常載入（用身份3 Lv3測試）',               link: '#/app/messages' },
      { id: 's4-01-height',   label: 'ChatCard 高度 72px' },
      { id: 's4-01-bold',     label: '未讀對話暱稱加粗 + neutral-50 背景' },
      { id: 's4-01-search',   label: '搜尋欄輸入有反應（過濾暱稱/訊息）' },
      { id: 's4-01-empty',    label: '空狀態顯示聊天泡泡插圖 + 探索按鈕' },
    ],
  },
  {
    title: 'S4-02/03 ChatView + MessageBubble',
    items: [
      { id: 's4-02-load',     label: '點擊任一對話進入聊天頁',                       link: '#/app/messages/1' },
      { id: 's4-02-self',     label: '自己訊息靠右，#F0294E 背景，白色文字' },
      { id: 's4-02-other',    label: '對方訊息靠左，#F1F5F9 背景，深色文字' },
      { id: 's4-02-scroll',   label: '進入時自動捲至最底部' },
      { id: 's4-02-status',   label: '已讀/未讀狀態顯示於訊息右下' },
      { id: 's4-02-date',     label: '日期分隔符顯示（今天/昨天/日期）' },
    ],
  },
  {
    title: 'S4-04 ChatInput',
    items: [
      { id: 's4-04-grow',     label: '輸入框自動增高（貼入多行文字）' },
      { id: 's4-04-enter',    label: 'Enter 送出，Shift+Enter 換行' },
      { id: 's4-04-disabled', label: '無內容時送出按鈕灰色' },
    ],
  },
  {
    title: 'S4-05/06 useChat + 未讀 Badge',
    items: [
      { id: 's4-05-mock',     label: 'Mock 模式每 3-8 秒收到假訊息' },
      { id: 's4-05-badge',    label: 'BottomNav 訊息 icon 未讀數更新' },
      { id: 's4-05-clear',    label: '進入對話後未讀數歸零' },
    ],
  },
  {
    title: 'S4-07/08 DatesView + DateCard',
    items: [
      { id: 's4-07-load',     label: '頁面正常載入（用身份3測試）',                   link: '#/app/dates' },
      { id: 's4-07-tabs',     label: 'Tab 切換（待接受/進行中/已完成）' },
      { id: 's4-07-gradient', label: 'DateCard 漸層背景（#F0294E → #C0203E）顯示' },
      { id: 's4-07-countdown',label: '進行中約會倒數計時運作' },
      { id: 's4-07-accept',   label: '待接受約會有「接受」+「拒絕」按鈕' },
    ],
  },
  {
    title: 'S4-09 QRCodeDisplay',
    items: [
      { id: 's4-09-qr',       label: 'QR 圖示顯示' },
      { id: 's4-09-timer',    label: '倒數計時正確（分:秒格式）' },
      { id: 's4-09-expired',  label: '過期後顯示「已過期」+ 重新產生按鈕' },
    ],
  },
  {
    title: 'S4-10 QR 掃碼頁',
    items: [
      { id: 's4-10-load',     label: '頁面正常載入',                                 link: '#/app/dates/scan' },
      { id: 's4-10-manual',   label: '開發環境顯示手動輸入欄位' },
      { id: 's4-10-mock',     label: '「模擬驗證（成功）」顯示成功畫面 + 分數' },
      { id: 's4-10-denied',   label: '相機授權拒絕時顯示 fallback UI' },
    ],
  },
  {
    title: 'S4 身份切換驗證',
    items: [
      { id: 's4-id-lv1',      label: '身份1 Lv1 → 進入 #/app/messages 被擋至 /app/shop', link: '#/app/messages' },
      { id: 's4-id-lv3',      label: '身份3 Lv3 → 所有 S4 頁面完整可用',              link: '#/app/messages' },
      { id: 's4-id-sus',      label: '身份5 停權 → 進入 /app/* 跳轉 /suspended',      link: '#/app/messages' },
    ],
  },
  {
    title: 'S4-11 真機測試',
    items: [
      { id: 's4-11-ip',       label: 'dev/check 頁面顯示區網 IP' },
      { id: 's4-11-mobile',   label: '手機瀏覽器能開啟 dev/check 頁面' },
      { id: 's4-11-camera',   label: 'QR 掃碼頁在手機上能請求相機權限' },
    ],
  },
  // ═══════════════════════════════════════════════════════════
  //  Sprint 5
  // ═══════════════════════════════════════════════════════════
  {
    title: 'S5-01 ShopView',
    items: [
      { id: 's5-01-load',     label: '頁面正常載入',                                     link: '#/app/shop' },
      { id: 's5-01-cards',    label: '4 個方案卡片顯示（週/月/季/年）' },
      { id: 's5-01-member',   label: '身份3 Lv3：頂部顯示「我的會員」區塊' },
      { id: 's5-01-nomember', label: '身份1 Lv1：不顯示我的會員區塊' },
      { id: 's5-01-trial',    label: '新手體驗入口（身份1顯示，身份3不顯示）' },
      { id: 's5-01-modal',    label: '點擊方案顯示確認 Modal' },
    ],
  },
  {
    title: 'S5-02 TrialView',
    items: [
      { id: 's5-02-load',     label: '頁面正常載入',                                     link: '#/app/shop/trial' },
      { id: 's5-02-price',    label: 'NT$199 價格顯示' },
      { id: 's5-02-used',     label: '身份3 已購買過：顯示已使用提示' },
    ],
  },
  {
    title: 'S5-03/04 AccountView + 隱私設定',
    items: [
      { id: 's5-03-load',     label: '頁面正常載入',                                     link: '#/app/settings' },
      { id: 's5-03-avatar',   label: '頭像點擊觸發上傳' },
      { id: 's5-03-fields',   label: '表單欄位正確顯示' },
      { id: 's5-03-birthday', label: '生日欄位 disabled' },
      { id: 's5-03-counter',  label: '字數計數器運作' },
      { id: 's5-04-lock',     label: '身份1 Lv1：隱私 Toggle 顯示鎖頭' },
      { id: 's5-04-toggle',   label: '身份3 Lv3：隱私 Toggle 正常可操作' },
      { id: 's5-03-save',     label: '儲存後顯示 Toast' },
    ],
  },
  {
    title: 'S5-05 訂閱管理',
    items: [
      { id: 's5-05-load',     label: '頁面正常載入',                                     link: '#/app/settings/subscription' },
      { id: 's5-05-plan',     label: '身份3：顯示目前方案 + 到期日' },
      { id: 's5-05-empty',    label: '身份1：顯示「目前沒有有效訂閱」' },
      { id: 's5-05-cancel',   label: '取消訂閱流程（身份3測試）' },
    ],
  },
  {
    title: 'S5-06 NotificationsView',
    items: [
      { id: 's5-06-load',     label: '頁面正常載入',                                     link: '#/app/notifications' },
      { id: 's5-06-mock',     label: '10 筆 Mock 通知顯示' },
      { id: 's5-06-unread',   label: '未讀通知有左側紅線' },
      { id: 's5-06-click',    label: '點擊通知跳轉對應頁面' },
      { id: 's5-06-readall',  label: '全部已讀按鈕' },
    ],
  },
  {
    title: 'S5-07 BlockedView',
    items: [
      { id: 's5-07-load',     label: '頁面正常載入',                                     link: '#/app/settings/blocked' },
      { id: 's5-07-list',     label: '封鎖列表顯示' },
      { id: 's5-07-unblock',  label: '解除封鎖確認 Modal' },
      { id: 's5-07-empty',    label: '空狀態顯示' },
    ],
  },
  {
    title: 'S5-08 ReportsView',
    items: [
      { id: 's5-08-load',     label: '頁面正常載入',                                     link: '#/app/reports' },
      { id: 's5-08-types',    label: '5 種回報類型 Radio 卡片' },
      { id: 's5-08-upload',   label: '截圖上傳（最多 3 張）' },
      { id: 's5-08-ticket',   label: '送出後顯示案號' },
      { id: 's5-08-history',  label: '回報歷史頁',                                       link: '#/app/reports/history' },
    ],
  },
  {
    title: 'S5-09 DeleteAccountView',
    items: [
      { id: 's5-09-load',     label: '頁面正常載入',                                     link: '#/app/settings/delete-account' },
      { id: 's5-09-check',    label: '勾選確認框才能進行' },
      { id: 's5-09-input',    label: '輸入「DELETE」才啟用刪除按鈕' },
      { id: 's5-09-countdown',label: '確認 Modal 倒數 5 秒' },
      { id: 's5-09-flow',     label: '身份3 測試刪除流程（Mock 模式不真刪）' },
    ],
  },
  {
    title: 'S5-10 usePayment',
    items: [
      { id: 's5-10-paid',     label: '身份3：isPaid = true，isExpiringSoon 依到期日' },
      { id: 's5-10-free',     label: '身份1：isPaid = false' },
      { id: 's5-10-plans',    label: 'fetchPlans 回傳 4 個方案' },
    ],
  },
  {
    title: 'S5-11 useImageUpload',
    items: [
      { id: 's5-11-spinner',  label: '上傳頭像顯示 spinner' },
      { id: 's5-11-size',     label: '超過 5MB 顯示錯誤訊息' },
      { id: 's5-11-format',   label: '格式不符顯示錯誤訊息' },
      { id: 's5-11-mock',     label: 'Mock 模式 1.5 秒後回傳假 URL' },
    ],
  },
  // ═══════════════════════════════════════════════════════════
  //  Sprint 6
  // ═══════════════════════════════════════════════════════════
  {
    title: 'S6-01/02/03 Admin 初始化 + Layout + 登入',
    items: [
      { id: 's6-01-open',     label: 'http://localhost:3001/#/admin/login 能正常開啟' },
      { id: 's6-03-mock',     label: 'Mock 登入（admin@mimeet.tw / password）成功' },
      { id: 's6-03-redirect', label: '登入後跳轉 /admin/members' },
      { id: 's6-02-sidebar',  label: 'Sidebar 選單顯示' },
      { id: 's6-02-super',    label: 'super_admin 登入：看到所有選單' },
      { id: 's6-02-cs',       label: 'cs 角色（cs@mimeet.tw / password）：只看到 Ticket 回報' },
    ],
  },
  {
    title: 'S6-04/05 會員列表 + 詳情',
    items: [
      { id: 's6-04-load',     label: '/admin/members 顯示 50 筆資料' },
      { id: 's6-04-search',   label: '搜尋暱稱有反應' },
      { id: 's6-04-credit',   label: '篩選誠信等級有反應' },
      { id: 's6-05-click',    label: '點擊會員進入詳情頁' },
      { id: 's6-05-tabs',     label: '4 個 Tab 都能切換' },
      { id: 's6-05-modal',    label: '調整分數 Modal 能開啟' },
    ],
  },
  {
    title: 'S6-06 Ticket 管理',
    items: [
      { id: 's6-06-load',     label: '/admin/tickets 正常顯示' },
      { id: 's6-06-tabs',     label: 'Tab 三狀態切換' },
      { id: 's6-06-drawer',   label: '點擊查看詳情 Drawer 滑入' },
      { id: 's6-06-status',   label: '狀態變更按鈕可操作' },
    ],
  },
  {
    title: 'S6-07 支付記錄',
    items: [
      { id: 's6-07-load',     label: '/admin/payments 正常顯示' },
      { id: 's6-07-cards',    label: '4 張統計卡片顯示' },
      { id: 's6-07-filter',   label: '日期篩選有反應' },
    ],
  },
  {
    title: 'S6-08 系統設定',
    items: [
      { id: 's6-08-load',     label: '/admin/settings/system 正常顯示' },
      { id: 's6-08-cs',       label: 'cs 角色無法進入（顯示無權限）' },
      { id: 's6-08-save',     label: '修改數值後儲存有反應' },
    ],
  },
  {
    title: 'S6-09 Auth API',
    items: [
      { id: 's6-09-register', label: 'POST /api/v1/auth/register 回傳正確' },
      { id: 's6-09-login',    label: 'POST /api/v1/auth/login 回傳 user + cookie' },
      { id: 's6-09-me',       label: 'GET /api/v1/auth/me 需登入才能存取' },
      { id: 's6-09-suspended',label: '停權用戶登入回傳 403' },
    ],
  },
  {
    title: 'S6-10 User API',
    items: [
      { id: 's6-10-search',   label: 'GET /api/v1/users/search 回傳列表' },
      { id: 's6-10-me',       label: 'GET /api/v1/users/me 回傳個人資料' },
      { id: 's6-10-update',   label: 'PATCH /api/v1/users/me 更新成功' },
      { id: 's6-10-birth',    label: '嘗試修改 birth_date 回傳 422' },
    ],
  },
  {
    title: 'S6-11 Subscription API',
    items: [
      { id: 's6-11-plans',    label: 'GET /api/v1/subscriptions/plans 回傳方案' },
      { id: 's6-11-me',       label: 'GET /api/v1/subscriptions/me 回傳訂閱資料' },
      { id: 's6-11-guard',    label: '/api/v1/chats 需 Lv2（Lv1 應回傳 403）' },
    ],
  },
  {
    title: 'S6-12 CORS / CSRF',
    items: [
      { id: 's6-12-cors',     label: '前台登入不出現 CORS 錯誤' },
      { id: 's6-12-cookie',   label: 'Cookie 正確設定' },
      { id: 's6-12-csrf',     label: 'CSRF Token 附加正確' },
    ],
  },
  {
    title: 'S6-13 WebSocket',
    items: [
      { id: 's6-13-ws',       label: 'DevTools Network WS 看到連線建立' },
      { id: 's6-13-mock',     label: 'Mock 模式每 3-8 秒收到假訊息' },
      { id: 's6-13-unread',   label: '進入聊天頁未讀數歸零' },
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
  const path = link.startsWith('#') ? link.slice(1) : link
  router.push(path)
}

// ── 區網 IP（真機測試用） ────────────────────────────────
const lanUrl = ref('')
if (import.meta.env.DEV) {
  lanUrl.value = window.location.origin.replace('localhost', window.location.hostname)
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
        <h1 class="check-header__title">Sprint 3-6 檢核清單</h1>
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

    <!-- 真機測試提示 -->
    <div v-if="lanUrl" class="device-info">
      <h3 class="device-info__title">📱 真機測試</h3>
      <p class="device-info__url">區網位址：<strong>{{ lanUrl }}/#/dev/check</strong></p>
      <div class="device-info__notes">
        <p><b>iOS Safari：</b>getUserMedia 需 HTTPS（localhost 除外）。建議使用 <code>npx vite --https</code> 或安裝 <code>@vitejs/plugin-basic-ssl</code>。</p>
        <p><b>Android Chrome：</b>HTTP 區網 IP 下 getUserMedia 會被拒絕。同樣需要 HTTPS 或使用 Chrome devtools port forwarding（<code>chrome://inspect</code>）。</p>
        <p><b>替代方案：</b>QR 掃碼頁在 DEV 環境提供「手動輸入代碼」fallback，不需要相機也能測試驗證流程。</p>
      </div>
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

/* ── Device Info ──────────────────────────────────────────── */
.device-info { margin:12px 16px 0; padding:14px 16px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:12px; }
.device-info__title { font-size:14px; font-weight:700; color:#1E40AF; margin:0 0 6px; }
.device-info__url { font-size:13px; color:#1E40AF; margin:0 0 10px; word-break:break-all; }
.device-info__notes { display:flex; flex-direction:column; gap:6px; }
.device-info__notes p { font-size:11px; color:#1E40AF; line-height:1.5; margin:0; }
.device-info__notes code { background:rgba(59,130,246,0.1); padding:1px 4px; border-radius:3px; font-size:10px; }
</style>
