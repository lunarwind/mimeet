<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import VerifyBadge from '@/components/common/VerifyBadge.vue'
import { blockUser } from '@/api/users'
import { getCreditLevel, CreditLevelLabel } from '@/types/user'
import { useAuthStore } from '@/stores/auth'
import { useProfile } from '@/composables/useProfile'
import { useDateInviteFromProfile } from '@/composables/useDateInviteFromProfile'
import DateInviteBottomSheet from '@/components/date/DateInviteBottomSheet.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { profile, isLoading, error, fetchProfile, toggleFavorite: doToggleFavorite } = useProfile()

// 約會邀請
const userId = computed(() => Number(route.params.id))
const {
  isEligibleToInvite,
  isLoading: dateInviteLoading,
  showBottomSheet,
  form: dateForm,
  successMessage,
  handleInviteClick,
  handleSubmit: doDateSubmit,
  handleCancel: handleDateCancel,
} = useDateInviteFromProfile(
  () => userId.value,
  () => profile.value?.membership_level ?? 0,
)
const bioExpanded = ref(false)
const currentPhotoIndex = ref(0)
const showMoreMenu = ref(false)

// ── 計算屬性 ──────────────────────────────────────────────
const creditLevel = computed(() => {
  if (!profile.value) return null
  const level = getCreditLevel(profile.value.credit_score)
  return { level, label: CreditLevelLabel[level], class: `credit--${level}` }
})

const allPhotos = computed(() => {
  if (!profile.value) return []
  const photos = profile.value.photos ?? []
  if (photos.length > 0) return photos.map(p => p.url)
  if (profile.value.avatar) return [profile.value.avatar]
  return ['/assets/default-avatar.webp']
})

const bioNeedsTruncate = computed(() =>
  (profile.value?.introduction?.length ?? 0) > 80
)

const displayBio = computed(() => {
  const bio = profile.value?.introduction
  if (!bio) return null
  if (bioExpanded.value || !bioNeedsTruncate.value) return bio
  return bio.slice(0, 80) + '…'
})

const lastActiveText = computed(() => {
  if (!profile.value) return ''
  if (profile.value.online_status === 'online') return '目前在線'
  const last = profile.value.last_active_at
  if (!last) return '最近上線'
  const diff = Date.now() - new Date(last).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${mins} 分鐘前上線`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} 小時前上線`
  const days = Math.floor(hours / 24)
  return `${days} 天前上線`
})

const isSelf = computed(() => authStore.user?.id === userId.value)

// ── 載入 ──────────────────────────────────────────────────
onMounted(() => {
  fetchProfile(userId.value)
})

// ── 圖片輪播 ──────────────────────────────────────────────
function prevPhoto() {
  if (currentPhotoIndex.value > 0) currentPhotoIndex.value--
}

function nextPhoto() {
  if (currentPhotoIndex.value < allPhotos.value.length - 1) currentPhotoIndex.value++
}

// ── 操作 ──────────────────────────────────────────────────
const favoriteLoading = ref(false)

async function toggleFavorite() {
  if (!profile.value || favoriteLoading.value) return
  favoriteLoading.value = true
  try {
    await doToggleFavorite(profile.value.id)
  } finally {
    favoriteLoading.value = false
  }
}

async function sendMessage() {
  if (!profile.value) return
  try {
    const { getOrCreateConversation } = await import('@/api/chat')
    const conversationId = await getOrCreateConversation(profile.value.id)
    if (conversationId) {
      router.push(`/app/messages/${conversationId}`)
    }
  } catch (err: any) {
    const msg = err.response?.data?.message ?? err.response?.data?.error?.message ?? '無法開啟對話'
    const { useUiStore } = await import('@/stores/ui')
    useUiStore().showToast(msg, 'error')
  }
}

async function toggleBlock() {
  if (!profile.value) return
  if (profile.value.is_blocked) {
    await blockUser(profile.value.id)
  } else {
    await blockUser(profile.value.id)
  }
  showMoreMenu.value = false
}

function goBack() {
  router.back()
}
</script>

<template>
  <div class="profile-view">
    <!-- TopBar -->
    <header class="profile-topbar">
      <button class="profile-topbar__back" @click="goBack" aria-label="返回">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <h1 class="profile-topbar__title">{{ profile?.nickname ?? '個人資料' }}</h1>
      <button
        v-if="!isSelf && profile"
        class="profile-topbar__more"
        @click="showMoreMenu = !showMoreMenu"
        aria-label="更多操作"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
        </svg>
      </button>
      <div v-else class="profile-topbar__more-placeholder" />

      <!-- 更多選單 -->
      <div v-if="showMoreMenu" class="profile-more-menu">
        <button class="profile-more-menu__item profile-more-menu__item--danger" @click="toggleBlock">
          {{ profile?.is_blocked ? '解除封鎖' : '封鎖此用戶' }}
        </button>
        <button class="profile-more-menu__item" @click="showMoreMenu = false">取消</button>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="profile-loading">
      <div class="profile-loading__photo" />
      <div class="profile-loading__info">
        <div class="profile-loading__line profile-loading__line--lg" />
        <div class="profile-loading__line profile-loading__line--md" />
        <div class="profile-loading__line profile-loading__line--sm" />
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="profile-error">
      <p>{{ error }}</p>
      <button class="profile-error__btn" @click="goBack">返回</button>
    </div>

    <!-- Content -->
    <template v-else-if="profile">
      <!-- 圖片輪播區 -->
      <section class="profile-gallery">
        <div class="profile-gallery__viewport">
          <img
            :src="allPhotos[currentPhotoIndex]"
            :alt="`${profile.nickname} 的照片 ${currentPhotoIndex + 1}`"
            class="profile-gallery__img"
          />
          <!-- 左右切換觸控區 -->
          <button
            v-if="currentPhotoIndex > 0"
            class="profile-gallery__nav profile-gallery__nav--prev"
            @click="prevPhoto"
            aria-label="上一張"
          />
          <button
            v-if="currentPhotoIndex < allPhotos.length - 1"
            class="profile-gallery__nav profile-gallery__nav--next"
            @click="nextPhoto"
            aria-label="下一張"
          />
          <!-- 線上狀態 -->
          <span
            class="profile-gallery__status"
            :class="profile.online_status === 'online' ? 'profile-gallery__status--online' : 'profile-gallery__status--offline'"
          >
            {{ profile.online_status === 'online' ? '在線' : lastActiveText }}
          </span>
        </div>
        <!-- 圖片指示點 -->
        <div v-if="allPhotos.length > 1" class="profile-gallery__dots">
          <span
            v-for="(_, i) in allPhotos"
            :key="i"
            class="profile-gallery__dot"
            :class="{ 'profile-gallery__dot--active': i === currentPhotoIndex }"
            @click="currentPhotoIndex = i"
          />
        </div>
      </section>

      <!-- 基本資訊區 -->
      <section class="profile-info">
        <div class="profile-info__header">
          <h2 class="profile-info__nickname">{{ profile.nickname }}</h2>
          <span
            v-if="creditLevel"
            class="profile-info__credit"
            :class="creditLevel.class"
          >
            {{ creditLevel.label }}
          </span>
        </div>
        <p class="profile-info__meta">
          {{ profile.age }} 歲
          <span class="profile-info__meta-dot">·</span>
          {{ profile.location }}
        </p>
        <!-- 驗證徽章 -->
        <div class="profile-info__badges">
          <VerifyBadge type="email" :verified="profile.email_verified" />
          <VerifyBadge type="phone" :verified="profile.phone_verified" />
          <VerifyBadge type="advanced" :verified="profile.advanced_verified" />
        </div>
        <!-- 數據摘要 -->
        <div class="profile-info__stats">
          <div v-if="profile.height" class="profile-info__stat">
            <span class="profile-info__stat-value">{{ profile.height }}cm</span>
            <span class="profile-info__stat-label">身高</span>
          </div>
          <div v-if="profile.job" class="profile-info__stat">
            <span class="profile-info__stat-value">{{ profile.job }}</span>
            <span class="profile-info__stat-label">職業</span>
          </div>
          <div v-if="profile.education" class="profile-info__stat">
            <span class="profile-info__stat-value">{{ profile.education }}</span>
            <span class="profile-info__stat-label">學歷</span>
          </div>
        </div>
      </section>

      <!-- 操作按鈕行 -->
      <section v-if="!isSelf" class="profile-actions">
        <button
          class="profile-actions__btn profile-actions__btn--primary"
          @click="sendMessage"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          傳送訊息
        </button>
        <button
          v-if="isEligibleToInvite"
          class="profile-actions__btn profile-actions__btn--secondary"
          :disabled="dateInviteLoading"
          @click="handleInviteClick"
        >
          <span v-if="dateInviteLoading" class="profile-actions__spinner" />
          <span v-else>📅 邀請約會</span>
        </button>
        <button
          class="profile-actions__btn"
          :class="profile.is_favorited ? 'profile-actions__btn--fav-active' : 'profile-actions__btn--secondary'"
          @click="toggleFavorite"
          :disabled="favoriteLoading"
        >
          <svg width="18" height="18" viewBox="0 0 24 24"
            :fill="profile.is_favorited ? '#F0294E' : 'none'"
            :stroke="profile.is_favorited ? '#F0294E' : 'currentColor'"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          >
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
          {{ profile.is_favorited ? '已收藏' : '收藏' }}
        </button>
      </section>

      <!-- 個人簡介（可摺疊） -->
      <section v-if="profile.introduction" class="profile-bio">
        <h3 class="profile-bio__title">關於我</h3>
        <p class="profile-bio__text">{{ displayBio }}</p>
        <button
          v-if="bioNeedsTruncate"
          class="profile-bio__toggle"
          @click="bioExpanded = !bioExpanded"
        >
          {{ bioExpanded ? '收起' : '展開全部' }}
        </button>
      </section>

      <!-- 動態縮圖區 -->
      <section v-if="profile.photos.length > 1" class="profile-photos">
        <h3 class="profile-photos__title">相冊</h3>
        <div class="profile-photos__grid">
          <img
            v-for="photo in profile.photos"
            :key="photo.id"
            :src="photo.url"
            :alt="`${profile.nickname} 的照片`"
            class="profile-photos__thumb"
            loading="lazy"
          />
        </div>
      </section>
    </template>

    <!-- 約會邀請 Bottom Sheet -->
    <DateInviteBottomSheet
      v-if="showBottomSheet && profile"
      :target-nickname="profile.nickname"
      :form="dateForm"
      :is-loading="dateInviteLoading"
      @update:form="dateForm = $event"
      @submit="doDateSubmit(profile.nickname)"
      @cancel="handleDateCancel"
    />

    <!-- 成功 Toast -->
    <Transition name="toast">
      <div v-if="successMessage" class="profile-toast">{{ successMessage }}</div>
    </Transition>
  </div>
</template>

<style scoped>
.profile-view {
  background: #F9F9FB;
  min-height: 100dvh;
  padding-bottom: 32px;
}

/* ── TopBar ────────────────────────────────────────────────── */
.profile-topbar {
  position: sticky;
  top: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  height: 56px;
  padding: 0 12px;
  background: #fff;
  border-bottom: 0.5px solid #E8ECF0;
}

.profile-topbar__back,
.profile-topbar__more {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.profile-topbar__back:active,
.profile-topbar__more:active {
  background: #F1F5F9;
}

.profile-topbar__title {
  flex: 1;
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  color: #0F172A;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-topbar__more-placeholder {
  width: 40px;
  flex-shrink: 0;
}

/* ── More Menu ─────────────────────────────────────────────── */
.profile-more-menu {
  position: absolute;
  top: 52px;
  right: 12px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  overflow: hidden;
  z-index: 30;
  min-width: 160px;
}

.profile-more-menu__item {
  width: 100%;
  padding: 14px 20px;
  border: none;
  background: transparent;
  font-size: 14px;
  font-weight: 500;
  color: #334155;
  text-align: left;
  cursor: pointer;
}

.profile-more-menu__item:active {
  background: #F8FAFC;
}

.profile-more-menu__item--danger {
  color: #EF4444;
}

/* ── Loading ───────────────────────────────────────────────── */
.profile-loading {
  padding: 0;
}

.profile-loading__photo {
  width: 100%;
  height: 360px;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.profile-loading__info {
  padding: 20px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.profile-loading__line {
  height: 14px;
  border-radius: 7px;
  background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.profile-loading__line--lg { width: 50%; height: 20px; }
.profile-loading__line--md { width: 35%; }
.profile-loading__line--sm { width: 60%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Error ─────────────────────────────────────────────────── */
.profile-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px 32px;
  color: #64748B;
  font-size: 15px;
}

.profile-error__btn {
  height: 44px;
  padding: 0 24px;
  border-radius: 10px;
  border: 1.5px solid #E2E8F0;
  background: #fff;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}

/* ── Gallery ───────────────────────────────────────────────── */
.profile-gallery__viewport {
  position: relative;
  width: 100%;
  height: 360px;
  overflow: hidden;
  background: #E2E8F0;
}

.profile-gallery__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.profile-gallery__nav {
  position: absolute;
  top: 0;
  width: 40%;
  height: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
}

.profile-gallery__nav--prev { left: 0; }
.profile-gallery__nav--next { right: 0; }

.profile-gallery__status {
  position: absolute;
  bottom: 12px;
  left: 12px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  backdrop-filter: blur(8px);
}

.profile-gallery__status--online {
  background: rgba(34, 197, 94, 0.2);
  color: #16A34A;
  border: 1px solid rgba(34, 197, 94, 0.3);
}

.profile-gallery__status--offline {
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
}

.profile-gallery__dots {
  display: flex;
  justify-content: center;
  gap: 6px;
  padding: 10px 0;
}

.profile-gallery__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #CBD5E1;
  cursor: pointer;
  transition: all 0.2s;
}

.profile-gallery__dot--active {
  background: #F0294E;
  width: 18px;
  border-radius: 3px;
}

/* ── Info ──────────────────────────────────────────────────── */
.profile-info {
  padding: 16px 16px 0;
}

.profile-info__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.profile-info__nickname {
  font-size: 22px;
  font-weight: 700;
  color: #0F172A;
}

.profile-info__credit {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 9999px;
  letter-spacing: 0.3px;
}

.credit--top {
  background: linear-gradient(135deg, #FDE68A, #FCD34D);
  color: #92400E;
  box-shadow: 0 0 0 1px #FDE68A;
}

.credit--good {
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
}

.credit--normal {
  background: #EFF6FF;
  color: #1E40AF;
  border: 1px solid #BFDBFE;
}

.credit--low {
  background: #FEF2F2;
  color: #991B1B;
  border: 1px solid #FECACA;
}

.profile-info__meta {
  font-size: 14px;
  color: #64748B;
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 10px;
}

.profile-info__meta-dot {
  color: #CBD5E1;
}

.profile-info__badges {
  display: flex;
  gap: 6px;
  margin-bottom: 14px;
}

.profile-info__stats {
  display: flex;
  gap: 0;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #F1F5F9;
  overflow: hidden;
}

.profile-info__stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 8px;
}

.profile-info__stat + .profile-info__stat {
  border-left: 1px solid #F1F5F9;
}

.profile-info__stat-value {
  font-size: 14px;
  font-weight: 600;
  color: #0F172A;
}

.profile-info__stat-label {
  font-size: 11px;
  color: #94A3B8;
  margin-top: 2px;
}

/* ── Actions ───────────────────────────────────────────────── */
.profile-actions {
  display: flex;
  gap: 10px;
  padding: 16px;
}

.profile-actions__btn {
  flex: 1;
  height: 48px;
  border-radius: 10px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.15s;
}

.profile-actions__btn:active {
  transform: scale(0.97);
}

.profile-actions__btn--primary {
  background: #F0294E;
  color: #fff;
}

.profile-actions__btn--primary:active {
  background: #D01A3C;
}

.profile-actions__btn--secondary {
  background: transparent;
  color: #374151;
  border: 1.5px solid #E5E7EB;
}
.profile-actions__btn--secondary:hover {
  background: #F9FAFB;
  border-color: #D1D5DB;
}

.profile-actions__btn--fav-active {
  background: #FFF0F3;
  color: #F0294E;
  border: 1.5px solid #FECDD3;
}

/* ── Bio ───────────────────────────────────────────────────── */
.profile-bio {
  padding: 0 16px;
  margin-top: 16px;
}

.profile-bio__title {
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
  margin-bottom: 8px;
}

.profile-bio__text {
  font-size: 14px;
  color: #475569;
  line-height: 1.7;
  white-space: pre-wrap;
}

.profile-bio__toggle {
  border: none;
  background: transparent;
  color: #F0294E;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 0;
  margin-top: 4px;
}

/* ── Photos Grid ───────────────────────────────────────────── */
.profile-photos {
  padding: 0 16px;
  margin-top: 20px;
}

.profile-photos__title {
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
  margin-bottom: 10px;
}

.profile-photos__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  border-radius: 12px;
  overflow: hidden;
}

.profile-photos__thumb {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  background: #F1F5F9;
  display: block;
}

/* ── 約會邀請按鈕 spinner ── */
.profile-actions__spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid #D1D5DB;
  border-top-color: #374151;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── 成功 Toast ── */
.profile-toast {
  position: fixed;
  bottom: 80px;
  left: 50%;
  transform: translateX(-50%);
  background: #065F46;
  color: #fff;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  z-index: 2000;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.toast-enter-active { animation: toastIn 0.3s ease-out; }
.toast-leave-active { animation: toastIn 0.3s ease-in reverse; }
@keyframes toastIn {
  from { opacity: 0; transform: translateX(-50%) translateY(20px); }
  to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* ── Tablet (768px+) ─────────────────────────────────────── */
@media (min-width: 768px) {
  .profile-view { max-width: 720px; margin: 0 auto; }
}
@media (min-width: 1024px) {
  .profile-view { max-width: 800px; }
}
@media (min-width: 1440px) {
  .profile-view { max-width: 960px; }
}
</style>
