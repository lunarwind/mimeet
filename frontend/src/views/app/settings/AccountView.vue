<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import TopBar from '@/components/layout/TopBar.vue'
import VerifyBadge from '@/components/common/VerifyBadge.vue'
import { getCreditLevel, CreditLevelLabel } from '@/types/user'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)

const creditLevel = computed(() => {
  if (!user.value) return null
  const level = getCreditLevel(user.value.credit_score)
  return { level, label: CreditLevelLabel[level] }
})

const membershipLabel = computed(() => {
  const lvl = user.value?.membership_level ?? 0
  if (lvl >= 3) return '付費會員'
  if (lvl >= 2) return '進階驗證'
  if (lvl >= 1) return '基礎會員'
  return '未驗證'
})

const verifiedLevel = computed(() => user.value?.verified ?? '0')

function goTo(name: string) {
  router.push({ name })
}

function handleLogout() {
  authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="account-view">
    <TopBar title="我的" />

    <div class="account-body">
      <!-- Profile Header -->
      <section class="profile-header">
        <div class="profile-header__avatar-wrap">
          <img
            :src="user?.avatar || 'https://i.pravatar.cc/150?img=0'"
            alt="頭像"
            class="profile-header__avatar"
          />
          <span class="profile-header__level-badge" :class="`profile-header__level-badge--${creditLevel?.level}`">
            {{ membershipLabel }}
          </span>
        </div>
        <div class="profile-header__info">
          <h2 class="profile-header__name">{{ user?.nickname || '未設定' }}</h2>
          <p class="profile-header__email">{{ user?.email || '' }}</p>
          <div class="profile-header__badges">
            <VerifyBadge v-if="verifiedLevel >= '1'" type="email" />
            <VerifyBadge v-if="verifiedLevel >= '2'" type="phone" />
            <VerifyBadge v-if="verifiedLevel >= '3'" type="advanced" />
          </div>
        </div>
      </section>

      <!-- 誠信分數 -->
      <section class="section-card" v-if="user">
        <div class="credit-row">
          <span class="credit-row__label">誠信分數</span>
          <span class="credit-row__score" :class="`credit-row__score--${creditLevel?.level}`">
            {{ user.credit_score }}
          </span>
          <span class="credit-row__level" :class="`credit-row__level--${creditLevel?.level}`">
            {{ creditLevel?.label }}
          </span>
        </div>
        <div class="credit-bar">
          <div
            class="credit-bar__fill"
            :class="`credit-bar__fill--${creditLevel?.level}`"
            :style="{ width: `${user.credit_score}%` }"
          />
        </div>
      </section>

      <!-- 帳號驗證 -->
      <section class="section-card">
        <h3 class="section-card__title">帳號驗證</h3>
        <div class="menu-item" @click="goTo('settings-verify')">
          <div class="menu-item__left">
            <svg class="menu-item__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>身份驗證</span>
          </div>
          <div class="menu-item__right">
            <span class="menu-item__hint" :class="verifiedLevel >= '3' ? 'menu-item__hint--done' : ''">
              {{ verifiedLevel >= '3' ? '已完成' : '前往驗證' }}
            </span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
          </div>
        </div>
      </section>

      <!-- 設定選項 -->
      <section class="section-card">
        <h3 class="section-card__title">設定</h3>

        <div class="menu-item" @click="goTo('settings-blocked')">
          <div class="menu-item__left">
            <svg class="menu-item__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
            <span>封鎖名單</span>
          </div>
          <div class="menu-item__right">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
          </div>
        </div>

        <div class="menu-item" @click="goTo('reports')">
          <div class="menu-item__left">
            <svg class="menu-item__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>
            </svg>
            <span>問題回報</span>
          </div>
          <div class="menu-item__right">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
          </div>
        </div>
      </section>

      <!-- 危險操作 -->
      <section class="section-card">
        <div class="menu-item menu-item--danger" @click="goTo('settings-delete-account')">
          <div class="menu-item__left">
            <svg class="menu-item__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            <span>刪除帳號</span>
          </div>
          <div class="menu-item__right">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
          </div>
        </div>
      </section>

      <!-- 登出按鈕 -->
      <button class="logout-btn" @click="handleLogout">登出</button>

      <!-- 版本資訊 -->
      <p class="version-text">MiMeet v1.0.0</p>
    </div>
  </div>
</template>

<style scoped>
.account-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.account-body {
  flex: 1;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Profile Header ───────────────────────────���────────────── */
.profile-header {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #fff;
  border-radius: 14px;
  padding: 20px 16px;
  border: 1px solid #F1F5F9;
}

.profile-header__avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.profile-header__avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  background: #F1F5F9;
}

.profile-header__level-badge {
  position: absolute;
  bottom: -4px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 9px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 9999px;
  white-space: nowrap;
  border: 1.5px solid #fff;
}

.profile-header__level-badge--top { background: #FFFBEB; color: #92400E; }
.profile-header__level-badge--good { background: #ECFDF5; color: #065F46; }
.profile-header__level-badge--normal { background: #EFF6FF; color: #1E40AF; }
.profile-header__level-badge--low { background: #FEF2F2; color: #991B1B; }

.profile-header__info {
  flex: 1;
  min-width: 0;
}

.profile-header__name {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.profile-header__email {
  font-size: 13px;
  color: #6B7280;
  margin-top: 2px;
}

.profile-header__badges {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}

/* ── Credit Score ────────────────────────��─────────────────── */
.credit-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.credit-row__label {
  font-size: 13px;
  color: #6B7280;
}

.credit-row__score {
  font-size: 20px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.credit-row__score--top { color: #92400E; }
.credit-row__score--good { color: #065F46; }
.credit-row__score--normal { color: #1E40AF; }
.credit-row__score--low { color: #991B1B; }

.credit-row__level {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 9999px;
}

.credit-row__level--top { background: #FFFBEB; color: #92400E; }
.credit-row__level--good { background: #ECFDF5; color: #065F46; }
.credit-row__level--normal { background: #EFF6FF; color: #1E40AF; }
.credit-row__level--low { background: #FEF2F2; color: #991B1B; }

.credit-bar {
  height: 6px;
  background: #E5E7EB;
  border-radius: 3px;
  margin-top: 10px;
  overflow: hidden;
}

.credit-bar__fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.8s ease-out;
}

.credit-bar__fill--top { background: linear-gradient(90deg, #FDE68A, #F59E0B); }
.credit-bar__fill--good { background: #10B981; }
.credit-bar__fill--normal { background: #3B82F6; }
.credit-bar__fill--low { background: #EF4444; }

/* ── Section Card ───────────────────────��──────────────────── */
.section-card {
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  border: 1px solid #F1F5F9;
}

.section-card__title {
  font-size: 13px;
  font-weight: 600;
  color: #6B7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  padding: 0 4px;
}

/* ── Menu Item ─────────────────────────────────────────────── */
.menu-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 4px;
  cursor: pointer;
  border-bottom: 0.5px solid #F3F4F6;
  transition: background 0.15s;
}

.menu-item:last-child {
  border-bottom: none;
}

.menu-item:active {
  background: #F9FAFB;
}

.menu-item__left {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15px;
  font-weight: 500;
  color: #111827;
}

.menu-item__icon {
  color: #6B7280;
  flex-shrink: 0;
}

.menu-item--danger .menu-item__left {
  color: #DC2626;
}

.menu-item--danger .menu-item__icon {
  color: #DC2626;
}

.menu-item__right {
  display: flex;
  align-items: center;
  gap: 4px;
}

.menu-item__hint {
  font-size: 13px;
  color: #F0294E;
  font-weight: 500;
}

.menu-item__hint--done {
  color: #10B981;
}

/* ── Logout ────────────────────────────────────────────────── */
.logout-btn {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  border: 1.5px solid #E5E7EB;
  background: #fff;
  font-size: 15px;
  font-weight: 600;
  color: #6B7280;
  cursor: pointer;
  transition: all 0.15s;
}

.logout-btn:active {
  background: #F9FAFB;
  transform: scale(0.98);
}

.version-text {
  text-align: center;
  font-size: 11px;
  color: #CBD5E1;
  padding: 8px 0 24px;
}
</style>
