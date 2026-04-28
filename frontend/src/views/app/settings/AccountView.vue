<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import AvatarCropModal from '@/components/common/AvatarCropModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useImageUpload } from '@/composables/useImageUpload'
import { default as apiClient } from '@/api/client'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const { isUploading, uploadAvatar: _uploadAvatar, uploadPhoto, error: uploadError } = useImageUpload()

// ── 表單資料 ──────────────────────────────────────────────
const form = ref({
  nickname: '',
  birthDate: '',
  location: '',
  height: null as number | null,
  weight: null as number | null,
  job: '',
  education: '',
  introduction: '',
  // F27 profile fields
  style: '' as '' | 'fresh' | 'sweet' | 'sexy' | 'intellectual' | 'sporty',
  datingBudget: '' as '' | 'casual' | 'moderate' | 'generous' | 'luxury' | 'undisclosed',
  datingFrequency: '' as '' | 'occasional' | 'weekly' | 'flexible',
  datingType: [] as string[],
  relationshipGoal: '' as '' | 'short_term' | 'long_term' | 'open' | 'undisclosed',
  smoking: '' as '' | 'never' | 'sometimes' | 'often',
  drinking: '' as '' | 'never' | 'social' | 'often',
  carOwner: null as boolean | null,
  availability: [] as string[],
})

// F27 選項字典 — 前端顯示用
const DATING_TYPE_OPTIONS = [
  { value: 'dining', label: '餐敘' },
  { value: 'travel', label: '旅遊' },
  { value: 'companion', label: '陪伴' },
  { value: 'mentorship', label: '指導' },
  { value: 'undisclosed', label: '不透露' },
]
const AVAILABILITY_OPTIONS = [
  { value: 'weekday_day', label: '平日白天' },
  { value: 'weekday_night', label: '平日晚上' },
  { value: 'weekend', label: '週末' },
  { value: 'flexible', label: '彈性配合' },
]

function toggleInArray(arr: string[], value: string) {
  const idx = arr.indexOf(value)
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(value)
}

const avatarUrl = ref<string | null>(null)
const avatarSlots = ref<string[]>([])
const showCropModal = ref(false)
const cropSrc = ref<string | null>(null)
const photos = ref<string[]>([])
const isDirty = ref(false)
const isSaving = ref(false)

const introLength = computed(() => form.value.introduction.length)
const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 2)

// F40 會員狀態卡片
const subscriptionInfo = computed(() => {
  const u = authStore.user as any
  return u?.subscription ?? null
})
const stealthStatusLabel = computed(() => {
  const u = authStore.user as any
  if (!u?.stealth_until) return '未啟用'
  const end = new Date(u.stealth_until).getTime()
  const left = end - Date.now()
  if (left <= 0) return '未啟用'
  const h = Math.floor(left / 3600000)
  const m = Math.floor((left % 3600000) / 60000)
  return `剩餘 ${h}h ${m}m`
})
function daysColor(days: number | null): string {
  if (days === null || days === undefined) return '#6B7280'
  if (days <= 7) return '#F0294E'
  if (days <= 30) return '#F59E0B'
  return '#10B981'
}
function formatDateYMD(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

// 隱私設定
const stealthMode = ref(false)
const hideLastActive = ref(false)
const readReceipt = ref(true)

// F22 全域免打擾
const dnd = ref({ dndEnabled: false, dndStart: '22:00', dndEnd: '08:00' })
const isDndSaving = ref(false)

// F42 隱身模式
import { useStealth } from '@/composables/useStealth'
const stealth = useStealth()
const showStealthConfirm = ref(false)
const showInsufficientModal = ref(false)
const insufficientInfo = ref<{ required: number; current: number } | null>(null)

async function handleStealthToggle() {
  if (stealth.status.value?.isActive) {
    // 已啟用 → 關閉
    try {
      await stealth.deactivate()
      uiStore.showToast('已關閉隱身', 'success')
    } catch { uiStore.showToast('關閉失敗', 'error') }
    return
  }

  // VIP 直接啟用、非 VIP 先確認
  if (stealth.status.value?.isVipFree) {
    const res = await stealth.activate()
    if (res.ok) uiStore.showToast('VIP 免費啟用隱身', 'success')
    return
  }
  showStealthConfirm.value = true
}

async function confirmStealthActivate() {
  showStealthConfirm.value = false
  const res = await stealth.activate()
  if (res.ok) {
    uiStore.showToast(`已啟用隱身，扣除 ${res.pointsDeducted} 點`, 'success')
  } else if (res.reason === 'insufficient_points') {
    insufficientInfo.value = { required: res.required, current: res.current }
    showInsufficientModal.value = true
  } else {
    uiStore.showToast(res.message, 'error')
  }
}

function goTopUp() {
  showInsufficientModal.value = false
  router.push('/app/shop?tab=points')
}

const CITIES = [
  '台北市', '新北市', '桃園市', '台中市', '台南市', '高雄市',
  '基隆市', '新竹市', '嘉義市', '新竹縣', '苗栗縣', '彰化縣',
  '南投縣', '雲林縣', '嘉義縣', '屏東縣', '宜蘭縣', '花蓮縣',
  '台東縣', '澎湖縣', '金門縣', '連江縣',
]

onMounted(async () => {
  await loadProfile()
  await loadAvatarSlots()
  await loadDnd()
  await stealth.fetchStatus()
})

async function loadDnd() {
  try {
    const { getDnd } = await import('@/api/dnd')
    const s = await getDnd()
    dnd.value = {
      dndEnabled: s.dndEnabled,
      dndStart: s.dndStart ?? '22:00',
      dndEnd: s.dndEnd ?? '08:00',
    }
  } catch { /* ignore */ }
}

async function persistDnd() {
  if (isDndSaving.value) return
  isDndSaving.value = true
  try {
    const { updateDnd } = await import('@/api/dnd')
    await updateDnd({
      dndEnabled: dnd.value.dndEnabled,
      dndStart: dnd.value.dndEnabled ? dnd.value.dndStart : null,
      dndEnd: dnd.value.dndEnabled ? dnd.value.dndEnd : null,
    })
    uiStore.showToast(dnd.value.dndEnabled ? '已啟用免打擾' : '已關閉免打擾', 'success')
  } catch {
    uiStore.showToast('免打擾設定儲存失敗', 'error')
  } finally {
    isDndSaving.value = false
  }
}

function handleDndToggle() {
  dnd.value.dndEnabled = !dnd.value.dndEnabled
  persistDnd()
}

function handleDndTimeChange() {
  if (dnd.value.dndEnabled) persistDnd()
}

async function loadAvatarSlots() {
  try {
    const res = await apiClient.get('/users/me/avatars')
    avatarSlots.value = res.data.data.slots ?? []
    if (res.data.data.current_avatar) avatarUrl.value = res.data.data.current_avatar
  } catch { /* ignore */ }
}

async function loadProfile() {
  try {
    const res = await (await import('@/api/client')).default.get('/users/me/settings')
    const p = res.data.data.profile
    form.value = {
      nickname: p.nickname ?? '',
      birthDate: p.birth_date ?? '',
      location: p.city ?? '',
      height: p.height ?? null,
      weight: p.weight ?? null,
      job: p.job ?? '',
      education: p.education ?? '',
      introduction: p.introduction ?? '',
      style: (p.style ?? '') as typeof form.value.style,
      datingBudget: (p.dating_budget ?? '') as typeof form.value.datingBudget,
      datingFrequency: (p.dating_frequency ?? '') as typeof form.value.datingFrequency,
      datingType: Array.isArray(p.dating_type) ? p.dating_type : [],
      relationshipGoal: (p.relationship_goal ?? '') as typeof form.value.relationshipGoal,
      smoking: (p.smoking ?? '') as typeof form.value.smoking,
      drinking: (p.drinking ?? '') as typeof form.value.drinking,
      carOwner: typeof p.car_owner === 'boolean' ? p.car_owner : null,
      availability: Array.isArray(p.availability) ? p.availability : [],
    }
    avatarUrl.value = p.avatar_url ?? null
    isDirty.value = false
  } catch {
    // Fallback: use auth store data
    if (authStore.user) {
      form.value.nickname = authStore.user.nickname
      avatarUrl.value = authStore.user.avatar
    }
  }
}

watch(form, () => { isDirty.value = true }, { deep: true })

// ── 頭像上傳 ──────────────────────────────────────────────
const avatarInput = ref<HTMLInputElement | null>(null)

function triggerAvatarUpload() {
  avatarInput.value?.click()
}

function handleAvatarChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => {
    cropSrc.value = ev.target?.result as string
    showCropModal.value = true
  }
  reader.readAsDataURL(file)
  // Reset input so same file can be re-selected
  if (avatarInput.value) avatarInput.value.value = ''
}

async function handleCropConfirm(blob: Blob) {
  showCropModal.value = false
  const formData = new FormData()
  formData.append('photo', blob, 'avatar.jpg')
  formData.append('set_active', '1')

  try {
    const res = await apiClient.post('/users/me/avatars', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    avatarSlots.value = res.data.data.slots ?? []
    avatarUrl.value = res.data.data.current_avatar
    uiStore.showToast('頭像已更新', 'success')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: { message?: string } } } }
    uiStore.showToast(e?.response?.data?.error?.message ?? '上傳失敗', 'error')
  }
}

async function switchAvatar(url: string) {
  try {
    await apiClient.patch('/users/me/avatars/active', { url })
    avatarUrl.value = url
  } catch { /* ignore */ }
}

async function deleteAvatarSlot(url: string) {
  try {
    const res = await apiClient.delete('/users/me/avatars', { data: { url } })
    avatarSlots.value = res.data.data.slots ?? []
    avatarUrl.value = res.data.data.current_avatar
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: { message?: string } } } }
    uiStore.showToast(e?.response?.data?.error?.message ?? '刪除失敗', 'error')
  }
}

// ── 相冊上傳 ──────────────────────────────────────────────
const photoInput = ref<HTMLInputElement | null>(null)

function triggerPhotoUpload() {
  if (photos.value.length >= 3) {
    uiStore.showToast('最多 3 張相冊照片', 'warning')
    return
  }
  photoInput.value?.click()
}

async function handlePhotoChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const result = await uploadPhoto(file)
  if (result) {
    photos.value.push(result.url)
    isDirty.value = true
  } else if (uploadError.value) {
    uiStore.showToast(uploadError.value, 'error')
  }
}

function removePhoto(index: number) {
  photos.value.splice(index, 1)
  isDirty.value = true
}

// ── 儲存 ──────────────────────────────────────────────────
async function saveProfile() {
  if (!isDirty.value) return
  isSaving.value = true
  try {
    // Map frontend field names to backend column names
    await apiClient.patch('/users/me', {
      nickname: form.value.nickname,
      location: form.value.location,
      height: form.value.height,
      weight: form.value.weight,
      occupation: form.value.job,
      education: form.value.education,
      bio: form.value.introduction,
      // F27 profile fields（空字串轉 null，讓後端 validate nullable 通過）
      style: form.value.style || null,
      dating_budget: form.value.datingBudget || null,
      dating_frequency: form.value.datingFrequency || null,
      dating_type: form.value.datingType.length ? form.value.datingType : null,
      relationship_goal: form.value.relationshipGoal || null,
      smoking: form.value.smoking || null,
      drinking: form.value.drinking || null,
      car_owner: form.value.carOwner,
      availability: form.value.availability.length ? form.value.availability : null,
    })
    uiStore.showToast('資料已更新', 'success')
    isDirty.value = false
  } catch {
    uiStore.showToast('儲存失敗', 'error')
  } finally {
    isSaving.value = false
  }
}

// ── 隱私 Toggle ──────────────────────────────────────────
function handlePrivacyToggle(setting: 'stealth' | 'hideActive' | 'readReceipt') {
  if (!isPaid.value) {
    router.push('/app/shop')
    return
  }
  if (setting === 'stealth') stealthMode.value = !stealthMode.value
  else if (setting === 'hideActive') hideLastActive.value = !hideLastActive.value
  else readReceipt.value = !readReceipt.value
}

// ── 登出 ──────────────────────────────────────────────────
async function handleLogout() {
  try { await import('@/api/auth').then(m => m.logout()) } catch { /* ignore */ }
  authStore.logout()
  router.push('/login')
}

// ── 設定快捷連結 ──────────────────────────────────────────
const settingsLinks = [
  { label: '會員方案', path: '/app/shop', highlight: true },
  { label: '身份驗證', path: '/app/settings/verify' },
  { label: '修改密碼', path: '/app/settings/change-password' },
  { label: '訂閱管理', path: '/app/settings/subscription' },
  { label: '封鎖名單', path: '/app/settings/blocked' },
  { label: '刪除帳號', path: '/app/settings/delete-account', danger: true },
]
</script>

<template>
  <AvatarCropModal
    v-if="showCropModal && cropSrc"
    :src="cropSrc"
    @confirm="handleCropConfirm"
    @cancel="showCropModal = false"
  />

  <AppLayout title="個人資料">
    <template #topbar-right>
      <button
        class="save-btn"
        :class="{ 'save-btn--active': isDirty }"
        :disabled="!isDirty || isSaving"
        @click="saveProfile"
      >
        {{ isSaving ? '儲存中...' : '儲存' }}
      </button>
    </template>

    <!-- 手機未驗證警示卡 -->
    <div v-if="authStore.user && !authStore.user.phone_verified" class="phone-warning-card">
      <span>📱 您尚未完成手機驗證，目前為 Lv0，部分功能受限</span>
      <RouterLink to="/app/settings/verify" class="phone-warning-link">立即驗證 →</RouterLink>
    </div>

    <div class="account-page">
      <!-- F40：我的會員狀態卡片 -->
      <section class="member-status">
        <h3 class="section-label">👤 我的會員狀態</h3>
        <div class="status-row">
          <span class="status-row__label">會員等級</span>
          <span class="status-row__value">
            Lv{{ authStore.user?.membership_level ?? 0 }}
            <span v-if="(authStore.user?.membership_level ?? 0) >= 3" class="member-tag member-tag--paid">付費會員 💎</span>
            <span v-else-if="(authStore.user?.membership_level ?? 0) >= 2" class="member-tag member-tag--adv">進階驗證</span>
            <span v-else-if="(authStore.user?.membership_level ?? 0) >= 1" class="member-tag">驗證會員</span>
            <span v-else class="member-tag member-tag--basic">一般會員</span>
          </span>
        </div>
        <div class="status-row">
          <span class="status-row__label">訂閱方案</span>
          <span v-if="subscriptionInfo" class="status-row__value">
            {{ subscriptionInfo.plan_name }}
          </span>
          <button v-else class="status-row__upgrade" @click="router.push('/app/shop')">尚未訂閱・升級 →</button>
        </div>
        <div v-if="subscriptionInfo" class="status-row">
          <span class="status-row__label">到期時間</span>
          <span class="status-row__value">
            {{ formatDateYMD(subscriptionInfo.expires_at) }}
            <span :style="{ color: daysColor(subscriptionInfo.days_remaining), fontWeight: 600 }">（剩餘 {{ subscriptionInfo.days_remaining }} 天）</span>
          </span>
        </div>
        <div class="status-row status-row--divider">
          <span class="status-row__label">💎 我的點數</span>
          <span class="status-row__value">
            {{ authStore.user?.points_balance ?? 0 }} 點
            <button class="status-row__action" @click="router.push('/app/shop?tab=points')">儲值</button>
          </span>
        </div>
        <div class="status-row">
          <span class="status-row__label">🕶 隱身模式</span>
          <span class="status-row__value">{{ stealthStatusLabel }}</span>
        </div>
      </section>

      <!-- F42 隱身模式控制 -->
      <section class="stealth-section">
        <div class="stealth-head">
          <h3 class="section-label">🕶 隱身模式</h3>
          <span v-if="stealth.status.value?.isActive" class="stealth-active-dot">● 啟用中</span>
        </div>

        <p class="stealth-desc">
          啟用後：<br>
          • 不出現在其他用戶的搜尋結果<br>
          • 瀏覽他人資料不留訪客紀錄
        </p>

        <!-- 已啟用 -->
        <template v-if="stealth.status.value?.isActive">
          <div class="stealth-countdown">
            <span class="stealth-countdown__label">到期：</span>
            <span class="stealth-countdown__value">{{ formatDateYMD(stealth.status.value.stealthUntil) }} {{ new Date(stealth.status.value.stealthUntil!).toLocaleTimeString('zh-TW',{hour:'2-digit',minute:'2-digit'}) }}</span>
          </div>
          <div class="stealth-countdown">
            <span class="stealth-countdown__label">倒數：</span>
            <span class="stealth-countdown__value stealth-countdown__timer">{{ stealth.countdown.value }}</span>
          </div>
          <div class="stealth-actions">
            <button class="stealth-btn stealth-btn--extend" @click="handleStealthToggle">
              {{ stealth.status.value.isVipFree ? `延長 ${stealth.status.value.durationHours}h（VIP 免費）` : `延長 ${stealth.status.value.durationHours}h（${stealth.status.value.cost} 點）` }}
            </button>
            <button class="stealth-btn stealth-btn--close" @click="stealth.deactivate(); uiStore.showToast('已關閉隱身','success')">
              提前關閉
            </button>
          </div>
        </template>

        <!-- 未啟用 -->
        <template v-else-if="stealth.status.value">
          <div v-if="stealth.status.value.isVipFree" class="stealth-vip-row">
            <button class="stealth-btn stealth-btn--primary" @click="handleStealthToggle">
              啟用隱身 {{ stealth.status.value.durationHours }}h
            </button>
            <span class="stealth-vip-tag">VIP 免費 💎</span>
          </div>
          <template v-else>
            <button class="stealth-btn stealth-btn--primary" @click="handleStealthToggle">
              啟用隱身 {{ stealth.status.value.durationHours }}h（{{ stealth.status.value.cost }} 點）
            </button>
            <p class="stealth-balance">目前餘額：{{ stealth.status.value.currentBalance }} 點</p>
            <p class="stealth-upsell">
              或 <a @click="router.push('/app/shop')">升級 VIP 享免費隱身 →</a>
            </p>
          </template>
        </template>
      </section>

      <!-- 隱身確認 Modal -->
      <div v-if="showStealthConfirm" class="modal-overlay" @click="showStealthConfirm = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">啟用隱身模式</h3>
          <div class="modal-card__meta">
            <div>消費：<strong>{{ stealth.status.value?.cost }} 點</strong></div>
            <div>持續：<strong>{{ stealth.status.value?.durationHours }} 小時</strong></div>
            <div>目前餘額：{{ stealth.status.value?.currentBalance }} 點</div>
            <div>啟用後餘額：{{ (stealth.status.value?.currentBalance ?? 0) - (stealth.status.value?.cost ?? 0) }} 點</div>
          </div>
          <p class="modal-card__note">
            效果：不出現在搜尋結果、瀏覽他人不留訪客紀錄<br>
            <span style="color:#EF4444;">提前關閉不退點</span>
          </p>
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="showStealthConfirm = false">取消</button>
            <button class="btn-primary" @click="confirmStealthActivate">確認啟用</button>
          </div>
        </div>
      </div>

      <!-- 餘額不足 Modal -->
      <div v-if="showInsufficientModal" class="modal-overlay" @click="showInsufficientModal = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">點數不足</h3>
          <div class="modal-card__meta">
            <div>需要：<strong>{{ insufficientInfo?.required }} 點</strong></div>
            <div>目前：<strong>{{ insufficientInfo?.current }} 點</strong></div>
          </div>
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="showInsufficientModal = false">取消</button>
            <button class="btn-primary" @click="goTopUp">前往儲值</button>
          </div>
        </div>
      </div>

      <!-- 頭像區塊 -->
      <section class="avatar-section">
        <div class="avatar-wrap" @click="triggerAvatarUpload">
          <img
            v-if="avatarUrl"
            :src="avatarUrl"
            alt="頭像"
            class="avatar-img"
          />
          <div v-else class="avatar-placeholder">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-3.87 3.13-7 7-7s7 3.13 7 7"/></svg>
          </div>
          <div v-if="isUploading" class="avatar-loading">
            <div class="spinner" />
          </div>
          <div class="avatar-edit-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="white" stroke="none"><path d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1z"/></svg>
          </div>
        </div>
        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden-input" @change="handleAvatarChange" />
        <span class="avatar-hint">點擊更換頭像（最多 3 個）</span>

        <!-- 頭像槽位 -->
        <div class="avatar-slots">
          <div
            v-for="(slotUrl, i) in avatarSlots"
            :key="i"
            class="avatar-slot"
            :class="{ 'avatar-slot--active': slotUrl === avatarUrl }"
          >
            <img :src="slotUrl" alt="頭像" @click="switchAvatar(slotUrl)" />
            <button class="avatar-slot__delete" @click.stop="deleteAvatarSlot(slotUrl)">×</button>
            <div v-if="slotUrl === avatarUrl" class="avatar-slot__badge">使用中</div>
          </div>
          <div
            v-if="avatarSlots.length < 3"
            class="avatar-slot avatar-slot--empty"
            @click="triggerAvatarUpload"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </div>
        </div>
      </section>

      <!-- 表單 -->
      <section class="form-section">
        <div class="field">
          <label class="field__label">暱稱</label>
          <input v-model="form.nickname" type="text" class="field__input" maxlength="20" placeholder="2-20 個字元" />
        </div>

        <div class="field">
          <label class="field__label">生日</label>
          <input :value="form.birthDate" type="date" class="field__input field__input--disabled" disabled />
          <span class="field__hint">生日無法修改</span>
        </div>

        <div class="field">
          <label class="field__label">地區</label>
          <select v-model="form.location" class="field__input">
            <option value="" disabled>請選擇</option>
            <option v-for="city in CITIES" :key="city" :value="city">{{ city }}</option>
          </select>
        </div>

        <div class="field-row">
          <div class="field field--half">
            <label class="field__label">身高 (cm)</label>
            <input v-model.number="form.height" type="number" class="field__input" min="100" max="250" placeholder="cm" />
          </div>
          <div class="field field--half">
            <label class="field__label">體重 (kg)</label>
            <input v-model.number="form.weight" type="number" class="field__input" min="30" max="200" placeholder="kg" />
          </div>
        </div>

        <div class="field">
          <label class="field__label">職業</label>
          <input v-model="form.job" type="text" class="field__input" placeholder="例：軟體工程師" />
        </div>

        <div class="field">
          <label class="field__label">學歷</label>
          <select v-model="form.education" class="field__input">
            <option value="">請選擇學歷</option>
            <option value="high_school">高中 / 高職</option>
            <option value="associate">專科</option>
            <option value="bachelor">大學</option>
            <option value="master">碩士</option>
            <option value="phd">博士</option>
            <option value="other">其他</option>
          </select>
        </div>

        <div class="field">
          <label class="field__label">自我介紹</label>
          <textarea
            v-model="form.introduction"
            class="field__textarea"
            maxlength="300"
            rows="4"
            placeholder="介紹一下自己..."
          />
          <span class="field__counter">{{ introLength }} / 300</span>
        </div>
      </section>

      <!-- 外貌風格（F27） -->
      <section class="form-section">
        <h3 class="section-label">外貌風格（選填）</h3>
        <div class="field">
          <label class="field__label">自我風格</label>
          <select v-model="form.style" class="field__input">
            <option value="">不指定</option>
            <option value="fresh">清新</option>
            <option value="sweet">甜美</option>
            <option value="sexy">性感</option>
            <option value="intellectual">知性</option>
            <option value="sporty">運動</option>
          </select>
        </div>
      </section>

      <!-- 約會偏好（F27） -->
      <section class="form-section">
        <h3 class="section-label">約會偏好（選填）</h3>
        <div class="field">
          <label class="field__label">約會預算</label>
          <select v-model="form.datingBudget" class="field__input">
            <option value="">不指定</option>
            <option value="casual">輕鬆小聚</option>
            <option value="moderate">質感約會</option>
            <option value="generous">高品質體驗</option>
            <option value="luxury">頂級享受</option>
            <option value="undisclosed">不透露</option>
          </select>
        </div>

        <div class="field">
          <label class="field__label">見面頻率</label>
          <select v-model="form.datingFrequency" class="field__input">
            <option value="">不指定</option>
            <option value="occasional">偶爾見面</option>
            <option value="weekly">每週約會</option>
            <option value="flexible">看心情</option>
          </select>
        </div>

        <div class="field">
          <label class="field__label">約會類型（可複選）</label>
          <div class="chip-group">
            <label
              v-for="opt in DATING_TYPE_OPTIONS"
              :key="opt.value"
              class="chip"
              :class="{ 'chip--active': form.datingType.includes(opt.value) }"
            >
              <input
                type="checkbox"
                class="chip__input"
                :checked="form.datingType.includes(opt.value)"
                @change="toggleInArray(form.datingType, opt.value); isDirty = true"
              />
              {{ opt.label }}
            </label>
          </div>
        </div>

        <div class="field">
          <label class="field__label">關係期望</label>
          <select v-model="form.relationshipGoal" class="field__input">
            <option value="">不指定</option>
            <option value="short_term">短期約會</option>
            <option value="long_term">長期穩定</option>
            <option value="open">開放探索</option>
            <option value="undisclosed">不透露</option>
          </select>
        </div>
      </section>

      <!-- 生活資訊（F27） -->
      <section class="form-section">
        <h3 class="section-label">生活資訊（選填）</h3>
        <div class="field-row">
          <div class="field field--half">
            <label class="field__label">抽菸</label>
            <select v-model="form.smoking" class="field__input">
              <option value="">不指定</option>
              <option value="never">從不</option>
              <option value="sometimes">偶爾</option>
              <option value="often">經常</option>
            </select>
          </div>
          <div class="field field--half">
            <label class="field__label">飲酒</label>
            <select v-model="form.drinking" class="field__input">
              <option value="">不指定</option>
              <option value="never">從不</option>
              <option value="social">社交場合</option>
              <option value="often">經常</option>
            </select>
          </div>
        </div>

        <div class="field">
          <div class="car-owner-row">
            <div>
              <span class="field__label">有自備車</span>
              <span class="field__hint">選填，不指定時隱藏</span>
            </div>
            <button
              type="button"
              class="toggle-sm"
              :class="{ 'toggle-sm--on': form.carOwner === true }"
              @click="form.carOwner = form.carOwner === true ? null : true; isDirty = true"
            >
              <span class="toggle-sm__dot" />
            </button>
          </div>
        </div>

        <div class="field">
          <label class="field__label">可約時段（可複選）</label>
          <div class="chip-group">
            <label
              v-for="opt in AVAILABILITY_OPTIONS"
              :key="opt.value"
              class="chip"
              :class="{ 'chip--active': form.availability.includes(opt.value) }"
            >
              <input
                type="checkbox"
                class="chip__input"
                :checked="form.availability.includes(opt.value)"
                @change="toggleInArray(form.availability, opt.value); isDirty = true"
              />
              {{ opt.label }}
            </label>
          </div>
        </div>
      </section>

      <!-- 隱私設定 -->
      <section class="privacy-section">
        <h3 class="section-label">隱私設定</h3>

        <div class="privacy-row" @click="handlePrivacyToggle('stealth')">
          <div class="privacy-row__left">
            <span class="privacy-row__label">隱身模式</span>
            <span class="privacy-row__desc">瀏覽他人資料不留足跡</span>
          </div>
          <div class="privacy-row__right">
            <svg v-if="!isPaid" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <button
              v-else
              class="toggle-sm"
              :class="{ 'toggle-sm--on': stealthMode }"
            >
              <span class="toggle-sm__dot" />
            </button>
          </div>
        </div>

        <div class="privacy-row" @click="handlePrivacyToggle('hideActive')">
          <div class="privacy-row__left">
            <span class="privacy-row__label">隱藏上線時間</span>
            <span class="privacy-row__desc">不顯示最後上線時間</span>
          </div>
          <div class="privacy-row__right">
            <svg v-if="!isPaid" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <button
              v-else
              class="toggle-sm"
              :class="{ 'toggle-sm--on': hideLastActive }"
            >
              <span class="toggle-sm__dot" />
            </button>
          </div>
        </div>

        <div class="privacy-row" @click="handlePrivacyToggle('readReceipt')">
          <div class="privacy-row__left">
            <span class="privacy-row__label">已讀回執</span>
            <span class="privacy-row__desc">對方可看到你是否已讀</span>
          </div>
          <div class="privacy-row__right">
            <svg v-if="!isPaid" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <button
              v-else
              class="toggle-sm"
              :class="{ 'toggle-sm--on': readReceipt }"
            >
              <span class="toggle-sm__dot" />
            </button>
          </div>
        </div>

        <p v-if="!isPaid" class="privacy-note">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          付費會員限定功能
        </p>
      </section>

      <!-- 免打擾設定（F22 Part B） -->
      <section class="privacy-section">
        <h3 class="section-label">免打擾模式</h3>

        <div class="privacy-row" @click="handleDndToggle">
          <div class="privacy-row__left">
            <span class="privacy-row__label">啟用免打擾</span>
            <span class="privacy-row__desc">在指定時段內不發送推播通知</span>
          </div>
          <div class="privacy-row__right">
            <button class="toggle-sm" :class="{ 'toggle-sm--on': dnd.dndEnabled }">
              <span class="toggle-sm__dot" />
            </button>
          </div>
        </div>

        <div v-if="dnd.dndEnabled" class="dnd-time-row">
          <div class="dnd-time-field">
            <label for="dnd-start" class="dnd-time-label">開始時間</label>
            <input
              id="dnd-start"
              v-model="dnd.dndStart"
              type="time"
              class="dnd-time-input"
              @change="handleDndTimeChange"
            />
          </div>
          <div class="dnd-time-field">
            <label for="dnd-end" class="dnd-time-label">結束時間</label>
            <input
              id="dnd-end"
              v-model="dnd.dndEnd"
              type="time"
              class="dnd-time-input"
              @change="handleDndTimeChange"
            />
          </div>
        </div>

        <p v-if="dnd.dndEnabled" class="privacy-note">
          若結束時間早於開始時間（如 22:00 → 08:00），代表跨午夜的時段。
        </p>
      </section>

      <!-- 設定連結 -->
      <section class="links-section">
        <h3 class="section-label">帳號設定</h3>
        <div
          v-for="link in settingsLinks"
          :key="link.path"
          class="link-row"
          :class="{ 'link-row--danger': link.danger }"
          @click="router.push(link.path)"
        >
          <span>{{ link.label }}</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </div>
      </section>

      <!-- 登出 -->
      <section class="settings-section" style="margin-top: 24px;">
        <button class="logout-btn" @click="handleLogout">登出</button>
      </section>
    </div>
  </AppLayout>
</template>

<style>
/* ── 共用 Modal（F42 隱身 + 餘額不足）────────────── */
.modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:200; display:flex; align-items:center; justify-content:center; padding:20px; animation:fade-in 0.15s ease; }
.modal-card { width:100%; max-width:380px; background:#fff; border-radius:16px; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.15); }
.modal-card__title { font-size:17px; font-weight:700; color:#111827; margin:0 0 12px; }
.modal-card__meta { font-size:14px; color:#374151; line-height:1.9; margin-bottom:12px; padding:12px; background:#F9FAFB; border-radius:10px; }
.modal-card__meta strong { color:#F0294E; }
.modal-card__note { font-size:12px; color:#6B7280; line-height:1.6; margin:0 0 16px; }
.modal-card__actions { display:flex; gap:10px; }
.modal-card__actions .btn-secondary,.modal-card__actions .btn-primary { flex:1; height:44px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none; }
.modal-card__actions .btn-secondary { background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB; }
.modal-card__actions .btn-primary { background:#F0294E; color:#fff; }
.modal-card__actions .btn-primary:hover { background:#D01A3C; }
@keyframes fade-in { from { opacity:0; } to { opacity:1; } }

/* ── F42 隱身模式區塊 ──────────────────────────── */
.stealth-section { background:#fff; border:1px solid #F1F5F9; border-radius:14px; padding:16px; margin-bottom:16px; }
.stealth-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.stealth-head .section-label { margin:0; font-size:14px; font-weight:700; color:#111827; }
.stealth-active-dot { font-size:12px; color:#F0294E; font-weight:600; }
.stealth-desc { font-size:12px; color:#6B7280; line-height:1.6; margin:8px 0 14px; padding:10px; background:#F9FAFB; border-radius:8px; }
.stealth-countdown { display:flex; justify-content:space-between; padding:4px 0; font-size:13px; }
.stealth-countdown__label { color:#6B7280; }
.stealth-countdown__value { color:#111827; font-weight:500; }
.stealth-countdown__timer { font-variant-numeric:tabular-nums; color:#F0294E; font-weight:700; font-size:15px; }
.stealth-actions { display:flex; gap:8px; margin-top:12px; }
.stealth-btn { flex:1; height:44px; border:none; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.15s; }
.stealth-btn--primary { background:#F0294E; color:#fff; width:100%; }
.stealth-btn--primary:hover { background:#D01A3C; }
.stealth-btn--extend { background:#F0294E; color:#fff; }
.stealth-btn--close { background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB; }
.stealth-vip-row { display:flex; align-items:center; gap:12px; }
.stealth-vip-tag { flex-shrink:0; padding:4px 12px; background:#FEF3C7; color:#92400E; border-radius:9999px; font-size:12px; font-weight:600; }
.stealth-balance { font-size:12px; color:#9CA3AF; margin-top:8px; text-align:center; }
.stealth-upsell { font-size:12px; color:#6B7280; margin-top:4px; text-align:center; }
.stealth-upsell a { color:#F0294E; cursor:pointer; font-weight:500; }

/* ── F40 我的會員狀態卡片 ──────────────────────────── */
.member-status { background:#fff; border:1px solid #F1F5F9; border-radius:14px; padding:16px; margin-bottom:16px; }
.member-status .section-label { font-size:14px; font-weight:700; color:#111827; margin:0 0 12px; }
.status-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:13px; }
.status-row--divider { border-top:1px solid #F3F4F6; margin-top:8px; padding-top:12px; }
.status-row__label { color:#6B7280; }
.status-row__value { color:#111827; font-weight:500; display:flex; align-items:center; gap:8px; }
.status-row__upgrade { background:none; border:none; color:#F0294E; font-weight:600; cursor:pointer; font-size:13px; padding:0; }
.status-row__action { padding:4px 10px; background:#F0294E; color:#fff; border:none; border-radius:9999px; font-size:11px; font-weight:600; cursor:pointer; margin-left:8px; }
.member-tag { display:inline-flex; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; background:#F3F4F6; color:#6B7280; }
.member-tag--paid { background:#FEF3C7; color:#92400E; }
.member-tag--adv { background:#DBEAFE; color:#1E40AF; }
.member-tag--basic { background:#F3F4F6; color:#6B7280; }

.account-page { padding: 16px; padding-bottom: 100px; }

/* ── Save Button ── */
.save-btn { padding: 6px 16px; border-radius: 8px; border: none; background: #E5E7EB; color: #9CA3AF; font-size: 14px; font-weight: 600; cursor: not-allowed; }
.save-btn--active { background: #F0294E; color: white; cursor: pointer; }
.save-btn--active:hover { background: #D01A3C; }

/* ── F27 Chip 多選（約會類型 / 可約時段） ── */
.chip-group { display:flex; flex-wrap:wrap; gap:8px; }
.chip { display:inline-flex; align-items:center; justify-content:center; height:36px; padding:0 14px; border:1.5px solid #E5E7EB; border-radius:9999px; background:#F9FAFB; color:#374151; font-size:13px; font-weight:500; cursor:pointer; transition:all 0.15s; user-select:none; }
.chip:hover { border-color:#D1D5DB; }
.chip--active { border-color:#F0294E; background:#FFE4EA; color:#F0294E; }
.chip__input { position:absolute; opacity:0; pointer-events:none; }

.car-owner-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 14px; background:#F9FAFB; border:1.5px solid #E5E7EB; border-radius:10px; }

/* ── DND 免打擾時段 ── */
.dnd-time-row { display:flex; gap:12px; padding:12px 16px; background:#F9FAFB; border-radius:10px; margin:4px 0 8px; }
.dnd-time-field { flex:1; display:flex; flex-direction:column; gap:6px; }
.dnd-time-label { font-size:12px; color:#6B7280; font-weight:500; }
.dnd-time-input { height:40px; border:1.5px solid #E5E7EB; border-radius:8px; padding:0 12px; font-size:15px; color:#111827; background:#FFFFFF; outline:none; font-family:inherit; }
.dnd-time-input:focus { border-color:#F0294E; }

/* ── Avatar ── */
.avatar-section { text-align: center; margin-bottom: 24px; }
.avatar-wrap { width: 96px; height: 96px; border-radius: 50%; overflow: hidden; margin: 0 auto 8px; position: relative; cursor: pointer; border: 3px solid #F0294E; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder { width: 100%; height: 100%; background: #F3F4F6; display: flex; align-items: center; justify-content: center; }
.avatar-loading { position: absolute; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; }
.spinner { width: 24px; height: 24px; border: 3px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.avatar-edit-badge { position: absolute; bottom: 2px; right: 2px; width: 24px; height: 24px; border-radius: 50%; background: #F0294E; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
.avatar-hint { font-size: 12px; color: #9CA3AF; }
.hidden-input { display: none; }

/* ── Avatar Slots ── */
.avatar-slots { display: flex; gap: 12px; justify-content: center; margin-top: 12px; }
.avatar-slot { position: relative; width: 64px; height: 64px; border-radius: 12px; overflow: hidden; cursor: pointer; border: 2px solid #E5E7EB; }
.avatar-slot--active { border-color: #F0294E; }
.avatar-slot img { width: 100%; height: 100%; object-fit: cover; }
.avatar-slot__delete { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; border-radius: 50%; background: rgba(0,0,0,0.5); color: white; border: none; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.avatar-slot__badge { position: absolute; bottom: 0; left: 0; right: 0; background: #F0294E; color: white; font-size: 9px; text-align: center; padding: 1px; }
.avatar-slot--empty { background: #F3F4F6; display: flex; align-items: center; justify-content: center; border-style: dashed; }

/* ── Photos ── */
.photo-row { display: flex; gap: 8px; justify-content: center; margin-top: 12px; }
.photo-thumb { width: 72px; height: 72px; border-radius: 10px; overflow: hidden; position: relative; }
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-remove { position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; border: none; font-size: 14px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.photo-add { width: 72px; height: 72px; border-radius: 10px; border: 2px dashed #D1D5DB; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.photo-add:hover { border-color: #F0294E; }

/* ── Form ── */
.form-section { margin-bottom: 24px; }
.field { margin-bottom: 16px; }
.field--half { flex: 1; }
.field-row { display: flex; gap: 12px; }
.field__label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px; }
.field__input { width: 100%; height: 48px; border-radius: 10px; border: 1.5px solid #E5E7EB; padding: 0 16px; font-size: 15px; color: #111827; background: white; }
.field__input:focus { outline: none; border-color: #F0294E; box-shadow: 0 0 0 3px rgba(240,41,78,0.12); }
.field__input--disabled { background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; }
.field__hint { font-size: 11px; color: #9CA3AF; margin-top: 2px; display: block; }
.field__textarea { width: 100%; border-radius: 10px; border: 1.5px solid #E5E7EB; padding: 12px 16px; font-size: 15px; color: #111827; resize: none; font-family: inherit; }
.field__textarea:focus { outline: none; border-color: #F0294E; box-shadow: 0 0 0 3px rgba(240,41,78,0.12); }
.field__counter { display: block; text-align: right; font-size: 12px; color: #9CA3AF; margin-top: 4px; font-variant-numeric: tabular-nums; }

/* ── Privacy ── */
.privacy-section { background: white; border-radius: 14px; border: 1px solid #F1F5F9; padding: 16px; margin-bottom: 20px; }
.section-label { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px; }
.privacy-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 0.5px solid #F1F5F9; cursor: pointer; }
.privacy-row:last-of-type { border-bottom: none; }
.privacy-row__left { display: flex; flex-direction: column; gap: 1px; }
.privacy-row__label { font-size: 14px; font-weight: 500; color: #111827; }
.privacy-row__desc { font-size: 12px; color: #9CA3AF; }
.privacy-row__right { display: flex; align-items: center; }
.privacy-note { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #9CA3AF; margin-top: 8px; }

.toggle-sm { width: 40px; height: 22px; border-radius: 11px; border: none; background: #E5E7EB; position: relative; cursor: pointer; transition: background 0.2s; padding: 0; }
.toggle-sm--on { background: #22C55E; }
.toggle-sm__dot { position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: white; transition: transform 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.toggle-sm--on .toggle-sm__dot { transform: translateX(18px); }

/* ── Links ── */
.links-section { background: white; border-radius: 14px; border: 1px solid #F1F5F9; padding: 4px 16px; margin-bottom: 24px; }
.link-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 0.5px solid #F1F5F9; cursor: pointer; font-size: 14px; color: #374151; }
.link-row:last-child { border-bottom: none; }
.link-row--danger { color: #EF4444; }
.link-row:active { opacity: 0.7; }
.logout-btn { width: 100%; height: 48px; border-radius: 10px; border: 1.5px solid #EF4444; background: transparent; color: #EF4444; font-size: 15px; font-weight: 600; cursor: pointer; }
.logout-btn:active { background: #FEF2F2; }
/* ── 手機未驗證警示卡 ── */
.phone-warning-card { background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 10px; padding: 12px 16px; margin: 16px; display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 14px; color: #92400E; }
.phone-warning-link { color: #92400E; font-weight: 600; text-decoration: underline; flex-shrink: 0; }
</style>
