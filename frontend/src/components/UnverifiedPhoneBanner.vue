<template>
  <div v-if="show" class="banner-unverified" role="alert">
    <span class="banner-icon">📱</span>
    <span class="banner-text">
      您尚未完成手機驗證，部分功能受限。
      <a class="banner-link" @click="goVerify">立即驗證 →</a>
    </span>
    <button class="banner-close" @click="dismiss" aria-label="關閉提醒">×</button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const dismissedThisSession = ref(false)

const show = computed(() =>
  !!authStore.user &&
  !authStore.user.phone_verified &&
  !dismissedThisSession.value,
)

function goVerify() {
  router.push('/app/settings/verify')
}

function dismiss() {
  // session 內隱藏；重整或重登後仍出現（持續提醒設計）
  dismissedThisSession.value = true
}
</script>

<style scoped>
.banner-unverified {
  background: linear-gradient(90deg, #FEF3C7 0%, #FDE68A 100%);
  border-bottom: 1px solid #F59E0B;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #92400E;
  position: sticky;
  top: 0;
  z-index: 100;
}
.banner-icon { font-size: 16px; flex-shrink: 0; }
.banner-text { flex: 1; line-height: 1.4; }
.banner-link {
  color: #92400E;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
  margin-left: 4px;
}
.banner-link:hover { color: #78350F; }
.banner-close {
  background: none;
  border: none;
  color: #92400E;
  font-size: 20px;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  flex-shrink: 0;
}
.banner-close:hover { opacity: 0.7; }

@media (max-width: 480px) {
  .banner-unverified { padding: 8px 12px; font-size: 13px; }
}
</style>
