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
  introduction: '',
})

const avatarUrl = ref<string | null>(null)
const avatarSlots = ref<string[]>([])
const showCropModal = ref(false)
const cropSrc = ref<string | null>(null)
const photos = ref<string[]>([])
const isDirty = ref(false)
const isSaving = ref(false)

const introLength = computed(() => form.value.introduction.length)
const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 3)

// 隱私設定
const stealthMode = ref(false)
const hideLastActive = ref(false)
const readReceipt = ref(true)

const CITIES = [
  '台北市', '新北市', '桃園市', '台中市', '台南市', '高雄市',
  '基隆市', '新竹市', '嘉義市', '新竹縣', '苗栗縣', '彰化縣',
  '南投縣', '雲林縣', '嘉義縣', '屏東縣', '宜蘭縣', '花蓮縣',
  '台東縣', '澎湖縣', '金門縣', '連江縣',
]

onMounted(async () => {
  await loadProfile()
  await loadAvatarSlots()
})

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
      introduction: p.introduction ?? '',
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
  } catch (err: any) {
    uiStore.showToast(err.response?.data?.error?.message ?? '上傳失敗', 'error')
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
  } catch (err: any) {
    uiStore.showToast(err.response?.data?.error?.message ?? '刪除失敗', 'error')
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
      occupation: form.value.job,
      bio: form.value.introduction,
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

    <div class="account-page">
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
.account-page { padding: 16px; padding-bottom: 100px; }

/* ── Save Button ── */
.save-btn { padding: 6px 16px; border-radius: 8px; border: none; background: #E5E7EB; color: #9CA3AF; font-size: 14px; font-weight: 600; cursor: not-allowed; }
.save-btn--active { background: #F0294E; color: white; cursor: pointer; }
.save-btn--active:hover { background: #D01A3C; }

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
</style>
