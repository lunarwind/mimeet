<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
function goBack() { router.back() }

interface HelpTopic {
  title: string
  desc: string
  to: string
  icon: string
}

const topics: HelpTopic[] = [
  {
    title: '防詐指南',
    desc: '了解常見詐騙手法與自保方式，讓每次相遇都安全。',
    to: '/anti-fraud',
    icon: '🛡️',
  },
]
</script>

<template>
  <div class="static-root">
    <header class="static-topbar">
      <button class="back-btn" @click="goBack">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
      </button>
      <span class="page-title">幫助中心</span>
      <div class="placeholder" />
    </header>

    <div class="static-content">
      <p class="intro">
        歡迎來到 MiMeet 幫助中心。這裡您可以找到關於平台使用的常見問題與安全提示。
      </p>

      <div class="topic-list">
        <router-link
          v-for="t in topics"
          :key="t.to"
          :to="t.to"
          class="topic-card"
        >
          <div class="topic-icon">{{ t.icon }}</div>
          <div class="topic-body">
            <div class="topic-title">{{ t.title }}</div>
            <div class="topic-desc">{{ t.desc }}</div>
          </div>
          <div class="topic-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>
        </router-link>
      </div>

      <div class="contact-note">
        <p>找不到答案？請來信 <a href="mailto:service@mimeet.club">service@mimeet.club</a>，我們將儘速回覆。</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.static-root {
  min-height: 100svh;
  background: #fff;
  font-family: 'Noto Sans TC', -apple-system, sans-serif;
}

.static-topbar {
  position: sticky; top: 0; z-index: 10;
  background: #fff; border-bottom: 1px solid #E5E7EB;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; height: 52px;
}
.back-btn {
  width: 40px; height: 40px; border-radius: 10px;
  background: transparent; border: none;
  display: flex; align-items: center; justify-content: center;
  color: #6B7280; cursor: pointer; transition: background 0.2s;
}
.back-btn:hover { background: #F3F4F6; }
.page-title { font-size: 16px; font-weight: 600; color: #111827; }
.placeholder { width: 40px; }

.static-content {
  max-width: 680px;
  margin: 0 auto;
  padding: 24px 20px 60px;
}
.intro {
  font-size: 14px; color: #6B7280;
  line-height: 1.75; margin: 0 0 24px;
}

.topic-list {
  display: flex; flex-direction: column; gap: 12px;
}
.topic-card {
  display: flex; align-items: center; gap: 14px;
  padding: 16px;
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
}
.topic-card:hover {
  border-color: #F0294E;
  box-shadow: 0 4px 12px rgba(240,41,78,0.08);
  transform: translateY(-1px);
}
.topic-icon {
  font-size: 24px;
  width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: #FFF5F7;
  border-radius: 10px;
  flex-shrink: 0;
}
.topic-body { flex: 1; min-width: 0; }
.topic-title {
  font-size: 15px; font-weight: 600;
  color: #111827; margin-bottom: 2px;
}
.topic-desc {
  font-size: 13px; color: #6B7280;
  line-height: 1.5;
}
.topic-arrow {
  color: #9CA3AF;
  flex-shrink: 0;
}

.contact-note {
  margin-top: 32px;
  padding: 16px;
  background: #F9F9FB;
  border-radius: 10px;
  font-size: 13px;
  color: #6B7280;
  line-height: 1.6;
  text-align: center;
}
.contact-note a {
  color: #F0294E;
  text-decoration: none;
  font-weight: 600;
}
.contact-note a:hover {
  text-decoration: underline;
}
</style>
