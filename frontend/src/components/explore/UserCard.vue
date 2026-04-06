<script setup lang="ts">
import { computed } from 'vue'
import type { ExploreUser } from '@/types/explore'

interface Props {
  user: ExploreUser
}

const props = defineProps<Props>()

const emit = defineEmits<{
  click: [userId: number]
  favorite: [userId: number]
}>()

// 誠信分數等級（對外只顯示等級名稱，不顯示具體分數）
const creditLevel = computed(() => {
  const score = props.user.creditScore
  if (score >= 91) return { label: '頂級', class: 'credit--top' }
  if (score >= 61) return { label: '優質', class: 'credit--good' }
  if (score >= 31) return { label: '普通', class: 'credit--normal' }
  return { label: '受限', class: 'credit--restricted' }
})

// 驗證徽章
const badges = computed(() => {
  const list: { key: string; label: string; icon: string }[] = []
  if (props.user.emailVerified)    list.push({ key: 'email',    label: 'Email',    icon: '✉' })
  if (props.user.phoneVerified)    list.push({ key: 'phone',    label: '手機',     icon: '☎' })
  if (props.user.advancedVerified) list.push({ key: 'advanced', label: '進階驗證', icon: '✓' })
  return list
})

// 上次上線文字
const lastActiveText = computed(() => {
  if (props.user.isOnline) return null
  const lastActive = props.user.lastActiveAt
  if (!lastActive) return '最近上線'
  const diff = Date.now() - new Date(lastActive).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${mins} 分鐘前`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} 小時前`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} 天前`
  return '7 天前以上'
})

function handleFavorite(event: Event) {
  event.stopPropagation()
  emit('favorite', props.user.id)
}
</script>

<template>
  <article
    class="user-card"
    role="button"
    tabindex="0"
    :aria-label="`${user.nickname}，${user.age}歲，${user.location}`"
    @click="emit('click', user.id)"
    @keydown.enter="emit('click', user.id)"
    @keydown.space.prevent="emit('click', user.id)"
  >
    <!-- 左側：頭像 + 線上狀態 -->
    <div class="user-card__avatar-wrap">
      <img
        :src="user.avatar || '/assets/default-avatar.webp'"
        :alt="`${user.nickname} 的頭像`"
        class="user-card__avatar"
        loading="lazy"
        width="56"
        height="56"
      />
      <!-- 線上狀態圓點 -->
      <span
        class="user-card__online-dot"
        :class="user.isOnline ? 'user-card__online-dot--online' : 'user-card__online-dot--offline'"
        :aria-label="user.isOnline ? '目前在線' : `最後上線：${lastActiveText}`"
      />
    </div>

    <!-- 中間：基本資訊 -->
    <div class="user-card__info">
      <!-- 第一行：暱稱 -->
      <p class="user-card__nickname">
        {{ user.nickname }}
      </p>
      <!-- 第二行：年齡・地區 -->
      <p class="user-card__meta">
        {{ user.age }} 歲
        <span class="user-card__meta-dot">·</span>
        {{ user.location }}
      </p>
      <!-- 第三行：驗證徽章 -->
      <div v-if="badges.length" class="user-card__badges" aria-label="驗證狀態">
        <span
          v-for="badge in badges"
          :key="badge.key"
          class="user-card__badge"
          :class="`user-card__badge--${badge.key}`"
          :title="badge.label"
        >
          {{ badge.icon }}{{ badge.label }}
        </span>
      </div>
    </div>

    <!-- 右側：誠信分數 + 收藏 -->
    <div class="user-card__right">
      <!-- 誠信等級徽章 -->
      <span
        class="user-card__credit"
        :class="creditLevel.class"
        aria-label="`誠信等級：${creditLevel.label}`"
      >
        {{ creditLevel.label }}
      </span>
      <!-- 收藏按鈕 -->
      <button
        class="user-card__fav-btn"
        :class="{ 'user-card__fav-btn--active': user.isFavorited }"
        :aria-label="user.isFavorited ? '取消收藏' : '加入收藏'"
        :aria-pressed="user.isFavorited"
        @click="handleFavorite"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          :fill="user.isFavorited ? '#F0294E' : 'none'"
          :stroke="user.isFavorited ? '#F0294E' : '#94A3B8'"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
      </button>
    </div>
  </article>
</template>

<style scoped>
/* ── 卡片容器 ─────────────────────────────────────────────── */
.user-card {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 88px;
  padding: 0 4px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  margin-bottom: 8px;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  outline: none;
  /* 觸控最小目標 */
  min-height: 88px;
}

.user-card:hover {
  transform: scale(0.99);
  box-shadow: 0 4px 12px rgba(0,0,0,0.09);
}

.user-card:active {
  transform: scale(0.97);
}

.user-card:focus-visible {
  box-shadow: 0 0 0 3px rgba(240,41,78,0.2);
}

/* ── 頭像 ─────────────────────────────────────────────────── */
.user-card__avatar-wrap {
  position: relative;
  flex-shrink: 0;
  margin-left: 12px;
}

.user-card__avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
  background: #F1F5F9;
  display: block;
}

.user-card__online-dot {
  position: absolute;
  top: 1px;
  right: 1px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid #fff;
}

.user-card__online-dot--online {
  background: #22C55E;
}

.user-card__online-dot--offline {
  background: #CBD5E1;
}

/* ── 資訊區 ───────────────────────────────────────────────── */
.user-card__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.user-card__nickname {
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}

.user-card__meta {
  font-size: 13px;
  color: #64748B;
  display: flex;
  align-items: center;
  gap: 4px;
}

.user-card__meta-dot {
  color: #CBD5E1;
}

/* ── 驗證徽章 ─────────────────────────────────────────────── */
.user-card__badges {
  display: flex;
  gap: 4px;
  flex-wrap: nowrap;
  overflow: hidden;
}

.user-card__badge {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 10px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
}

.user-card__badge--email {
  background: #EFF6FF;
  color: #3B82F6;
}

.user-card__badge--phone {
  background: #F0FDF4;
  color: #22C55E;
}

.user-card__badge--advanced {
  background: #FFF7ED;
  color: #F97316;
}

/* ── 右側欄 ───────────────────────────────────────────────── */
.user-card__right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  margin-right: 12px;
  flex-shrink: 0;
}

/* ── 誠信等級 ─────────────────────────────────────────────── */
.user-card__credit {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 6px;
  letter-spacing: 0.3px;
}

/* 頂級：金色特效 */
.credit--top {
  background: linear-gradient(135deg, #FDE68A, #FCD34D);
  color: #92400E;
  box-shadow: 0 0 0 1px #FDE68A;
}

/* 優質：綠色 */
.credit--good {
  background: #F0FDF4;
  color: #15803D;
  border: 1px solid #BBF7D0;
}

/* 普通：灰藍 */
.credit--normal {
  background: #F1F5F9;
  color: #475569;
}

/* 受限：紅 */
.credit--restricted {
  background: #FEF2F2;
  color: #DC2626;
  border: 1px solid #FECACA;
}

/* ── 收藏按鈕 ─────────────────────────────────────────────── */
.user-card__fav-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: #F8FAFC;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s, transform 0.1s;
}

.user-card__fav-btn:active {
  transform: scale(0.88);
}

.user-card__fav-btn--active {
  background: #FFF0F3;
}

.user-card__fav-btn--active svg {
  animation: heart-pop 0.3s cubic-bezier(0.17, 0.89, 0.32, 1.49);
}

@keyframes heart-pop {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.35); }
  100% { transform: scale(1); }
}
</style>
