<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const goHomeLabel = computed(() => (authStore.isLoggedIn ? '回到探索頁' : '回到首頁'))

function goHome() {
  router.push(authStore.isLoggedIn ? '/app/explore' : '/')
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    goHome()
  }
}
</script>

<template>
  <div class="notfound-root">
    <div class="notfound-card">
      <div class="notfound-badge">404</div>

      <h1 class="notfound-title">找不到這個頁面</h1>

      <p class="notfound-desc">
        您輸入的網址可能有誤，或該頁面已被移除。<br />
        讓我們帶你回到正確的地方。
      </p>

      <div class="notfound-actions">
        <button class="btn-primary" @click="goHome">{{ goHomeLabel }}</button>
        <button class="btn-ghost" @click="goBack">返回上一頁</button>
      </div>

      <div class="notfound-brand">
        <span class="brand-dot" />
        <span class="brand-text">MiMeet</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notfound-root {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 20px;
  background: #F9F9FB;
  background-image:
    radial-gradient(circle at 15% 20%, rgba(240, 41, 78, 0.08) 0%, transparent 55%),
    radial-gradient(circle at 85% 80%, rgba(240, 41, 78, 0.06) 0%, transparent 55%);
}

.notfound-card {
  width: 100%;
  max-width: 480px;
  background: #FFFFFF;
  border-radius: 20px;
  padding: 48px 28px 36px;
  text-align: center;
  box-shadow: 0 10px 30px -10px rgba(17, 24, 39, 0.12), 0 4px 12px -4px rgba(17, 24, 39, 0.06);
}

.notfound-badge {
  display: inline-block;
  font-family: 'Inter', system-ui, sans-serif;
  font-size: 72px;
  font-weight: 700;
  line-height: 1;
  letter-spacing: -2px;
  background: linear-gradient(135deg, #F0294E 0%, #A80F2C 100%);
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 16px;
}

.notfound-title {
  font-size: 22px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px;
  letter-spacing: -0.5px;
}

.notfound-desc {
  font-size: 15px;
  line-height: 1.6;
  color: #6B7280;
  margin: 0 0 32px;
}

.notfound-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 32px;
}

.btn-primary {
  width: 100%;
  height: 48px;
  border: none;
  border-radius: 10px;
  background: #F0294E;
  color: #FFFFFF;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
}

.btn-primary:hover {
  background: #D01A3C;
}

.btn-primary:active {
  transform: scale(0.97);
}

.btn-ghost {
  width: 100%;
  height: 48px;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  background: transparent;
  color: #374151;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.btn-ghost:hover {
  background: #F9FAFB;
  border-color: #D1D5DB;
}

.notfound-brand {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #9CA3AF;
  letter-spacing: 0.5px;
}

.brand-dot {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: #F0294E;
}

.brand-text {
  font-weight: 500;
}

@media (min-width: 768px) {
  .notfound-card {
    max-width: 520px;
    padding: 64px 48px 48px;
  }

  .notfound-badge {
    font-size: 96px;
  }

  .notfound-title {
    font-size: 26px;
  }

  .notfound-desc {
    font-size: 16px;
    margin-bottom: 40px;
  }

  .notfound-actions {
    flex-direction: row-reverse;
    justify-content: center;
  }

  .btn-primary,
  .btn-ghost {
    width: auto;
    min-width: 160px;
    padding: 0 28px;
  }
}
</style>
