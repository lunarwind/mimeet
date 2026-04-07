<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const scrollY = ref(0)
const heroVisible = ref(false)
const featuresVisible = ref(false)
const ctaVisible = ref(false)

function goRegister() {
  router.push('/register')
}

function goLogin() {
  router.push('/login')
}

function handleScroll() {
  scrollY.value = window.scrollY
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  // 觸發進場動畫
  setTimeout(() => { heroVisible.value = true }, 100)
  setTimeout(() => { featuresVisible.value = true }, 400)
  setTimeout(() => { ctaVisible.value = true }, 700)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})

const features = [
  {
    icon: 'qr',
    title: 'QR碼約會驗證',
    desc: '見面當下掃描 QR 碼，雙方確認出席並獲得誠信加分，讓每一次相遇都有記錄。',
  },
  {
    icon: 'shield',
    title: '誠信分數系統',
    desc: '動態評估每位用戶的可信度，低分者自動限制功能，確保社群品質。',
  },
  {
    icon: 'verify',
    title: '多重身份驗證',
    desc: 'Email、手機、進階照片與信用卡三層驗證，讓每位成員都是真實存在的人。',
  },
]
</script>

<template>
  <div class="landing-root">
    <!-- ───────── Topbar ───────── -->
    <header class="topbar" :class="{ scrolled: scrollY > 40 }">
      <div class="topbar-inner">
        <span class="brand-logo">MiMeet</span>
        <div class="topbar-actions">
          <button class="btn-ghost-sm" @click="goLogin">登入</button>
          <button class="btn-primary-sm" @click="goRegister">立即加入</button>
        </div>
      </div>
    </header>

    <!-- ───────── Hero Section ───────── -->
    <section class="hero-section">
      <div class="hero-bg-gradient" />
      <div class="hero-overlay-pattern" />

      <div class="hero-content" :class="{ visible: heroVisible }">
        <div class="hero-text-block">
          <div class="hero-badge">
            <span class="hero-badge-dot" />
            台灣高端交友平台
          </div>
          <h1 class="hero-title">找到你值得<br>信賴的<em>另一半</em></h1>
          <p class="hero-subtitle">
            誠信分數系統，讓每一次相遇都真實可靠
          </p>
          <div class="hero-cta-row">
            <button class="btn-cta-main" @click="goRegister">
              立即免費加入
              <span class="btn-arrow">→</span>
            </button>
            <button class="btn-cta-ghost" @click="goLogin">
              已有帳號？登入
            </button>
          </div>
          <div class="hero-trust-row">
            <div class="trust-item">
              <span class="trust-num">5,000+</span>
              <span class="trust-label">認證會員</span>
            </div>
            <div class="trust-divider" />
            <div class="trust-item">
              <span class="trust-num">98%</span>
              <span class="trust-label">真實照片</span>
            </div>
            <div class="trust-divider" />
            <div class="trust-item">
              <span class="trust-num">3層</span>
              <span class="trust-label">身份驗證</span>
            </div>
          </div>
        </div>

        <!-- Hero illustration -->
        <div class="hero-illustration">
          <div class="phone-mockup">
            <div class="phone-screen">
              <div class="mock-topbar">
                <span class="mock-logo">MiMeet</span>
                <div class="mock-avatar-row">
                  <div class="mock-avatar av1" />
                  <div class="mock-avatar av2" />
                </div>
              </div>
              <div class="mock-profile-card">
                <div class="mock-profile-photo" />
                <div class="mock-credit-badge">
                  <span class="credit-dot" />
                  誠信 92 分
                </div>
                <div class="mock-profile-info">
                  <div class="mock-name-bar" />
                  <div class="mock-detail-bar short" />
                </div>
                <div class="mock-action-row">
                  <div class="mock-btn like">♥</div>
                  <div class="mock-btn msg">✉</div>
                </div>
              </div>
              <div class="mock-qr-strip">
                <div class="mock-qr-icon">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <path d="M14 14h2v2h-2zM18 14h3M14 18h2M18 18h3M18 20v2M14 20h2"/>
                  </svg>
                </div>
                <span class="mock-qr-text">QR 碼約會驗證</span>
                <div class="mock-qr-dot" />
              </div>
            </div>
          </div>
          <!-- Floating badges -->
          <div class="float-badge fb1">
            <span class="fb-icon">✓</span> 已驗證身份
          </div>
          <div class="float-badge fb2">
            <span class="fb-score">91</span> 誠信頂級
          </div>
        </div>
      </div>
    </section>

    <!-- ───────── Features Section ───────── -->
    <section class="features-section" :class="{ visible: featuresVisible }">
      <div class="features-inner">
        <div class="section-label">核心功能</div>
        <h2 class="section-title">為什麼選擇 MiMeet？</h2>
        <div class="features-grid">
          <div
            v-for="(f, i) in features"
            :key="i"
            class="feature-card"
            :style="{ animationDelay: `${i * 120}ms` }"
          >
            <div class="feature-icon-wrap">
              <!-- QR icon -->
              <svg v-if="f.icon === 'qr'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <path d="M14 14h2v2h-2zM18 14h3M14 18h2M18 18h3M18 20v2M14 20h2"/>
              </svg>
              <!-- Shield icon -->
              <svg v-if="f.icon === 'shield'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L4 6v6c0 5.25 3.5 10.15 8 11.5C16.5 22.15 20 17.25 20 12V6L12 2z"/>
                <path d="M9 12l2 2 4-4"/>
              </svg>
              <!-- Verify icon -->
              <svg v-if="f.icon === 'verify'" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0z"/>
                <path d="M9 12l2 2 4-4"/>
              </svg>
            </div>
            <h3 class="feature-title">{{ f.title }}</h3>
            <p class="feature-desc">{{ f.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ───────── How It Works Section ───────── -->
    <section class="how-section">
      <div class="how-inner">
        <div class="section-label light">使用流程</div>
        <h2 class="section-title light">三步驟開始你的旅程</h2>
        <div class="steps-row">
          <div class="step-item">
            <div class="step-num">01</div>
            <div class="step-content">
              <h3>建立帳號</h3>
              <p>填寫基本資料，完成 Email 與手機驗證</p>
            </div>
          </div>
          <div class="step-connector" />
          <div class="step-item">
            <div class="step-num">02</div>
            <div class="step-content">
              <h3>探索配對</h3>
              <p>瀏覽已驗證的真實成員，發送訊息開始對話</p>
            </div>
          </div>
          <div class="step-connector" />
          <div class="step-item">
            <div class="step-num">03</div>
            <div class="step-content">
              <h3>QR碼見面</h3>
              <p>安排見面時掃描 QR 碼，雙方獲得誠信加分</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ───────── Final CTA Section ───────── -->
    <section class="cta-section" :class="{ visible: ctaVisible }">
      <div class="cta-inner">
        <div class="cta-bg-shape" />
        <h2 class="cta-title">準備好開始了嗎？</h2>
        <p class="cta-sub">立即免費註冊，加入台灣最真實可信的交友平台</p>
        <button class="btn-cta-large" @click="goRegister">
          免費立即加入
          <span class="btn-arrow-lg">→</span>
        </button>
        <p class="cta-login-hint">
          已有帳號？
          <button class="inline-link" @click="goLogin">直接登入</button>
        </p>
      </div>
    </section>

    <!-- ───────── Footer ───────── -->
    <footer class="landing-footer">
      <div class="footer-inner">
        <span class="footer-brand">MiMeet</span>
        <span class="footer-copy">© 2026 MiMeet. 台灣高端交友平台</span>
        <div class="footer-links">
          <a href="#/privacy">隱私權政策</a>
          <span>·</span>
          <a href="#/terms">使用者條款</a>
          <span>·</span>
          <a href="#/anti-fraud">聯絡我們</a>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* ─── CSS Variables ─────────────────────────────────── */
:root {
  --primary: #F0294E;
  --primary-dark: #D01A3C;
  --primary-light: #FFF5F7;
  --primary-50: #FFE4EA;
  --gold: #F59E0B;
  --text-primary: #111827;
  --text-secondary: #6B7280;
  --text-muted: #9CA3AF;
  --surface: #F9F9FB;
  --card: #FFFFFF;
  --border: #E5E7EB;
  --hero-gradient-start: #F0294E;
  --hero-gradient-end: #A80F2C;
}

/* ─── Reset / Base ─────────────────────────────────── */
.landing-root {
  font-family: 'Noto Sans TC', -apple-system, BlinkMacSystemFont, sans-serif;
  color: var(--text-primary);
  background: #fff;
  overflow-x: hidden;
}

/* ─── Topbar ─────────────────────────────────────────── */
.topbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  padding: 0 20px;
  height: 60px;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  transition: box-shadow 0.3s;
}
.topbar.scrolled {
  box-shadow: 0 2px 16px rgba(0,0,0,0.06);
}
.topbar-inner {
  max-width: 1100px;
  margin: 0 auto;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.brand-logo {
  font-family: 'Noto Serif TC', serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--primary);
  letter-spacing: -0.5px;
}
.topbar-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}
.btn-ghost-sm {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text-secondary);
  padding: 7px 18px;
  border-radius: 24px;
  font-size: 14px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}
.btn-ghost-sm:hover {
  background: var(--surface);
  border-color: var(--text-secondary);
}
.btn-primary-sm {
  background: var(--primary);
  border: none;
  color: #fff;
  padding: 7px 18px;
  border-radius: 24px;
  font-size: 14px;
  cursor: pointer;
  font-family: inherit;
  font-weight: 600;
  transition: all 0.2s;
}
.btn-primary-sm:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

/* ─── Hero Section ─────────────────────────────────── */
.hero-section {
  position: relative;
  min-height: 100svh;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: #fff;
}
.hero-bg-gradient {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 600px 500px at 85% 90%, #FFF0F3 0%, transparent 60%),
    radial-gradient(ellipse 400px 300px at 5% 10%, #FFF8F5 0%, transparent 55%);
  z-index: 0;
}
.hero-overlay-pattern {
  position: absolute;
  inset: 0;
  z-index: 1;
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='0.8' fill='rgba(240,41,78,0.06)'/%3E%3C/svg%3E");
}

.hero-content {
  position: relative;
  z-index: 2;
  max-width: 1100px;
  margin: 0 auto;
  padding: 100px 24px 60px;
  display: flex;
  align-items: center;
  gap: 60px;
  width: 100%;
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.7s ease, transform 0.7s ease;
}
.hero-content.visible {
  opacity: 1;
  transform: translateY(0);
}

/* Hero Text */
.hero-text-block {
  flex: 1;
  max-width: 540px;
}
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--primary-light);
  border: 1px solid var(--primary-50);
  color: var(--primary);
  font-size: 13px;
  padding: 6px 14px;
  border-radius: 24px;
  margin-bottom: 20px;
  font-weight: 600;
}
.hero-badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #6EF09A;
  animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.6; transform: scale(1.3); }
}
.hero-title {
  font-family: 'Noto Serif TC', serif;
  font-size: clamp(32px, 5vw, 52px);
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.2;
  letter-spacing: -1px;
  margin: 0 0 16px;
}
.hero-title em {
  color: var(--primary);
  font-style: normal;
}
.hero-subtitle {
  font-size: 16px;
  color: var(--text-secondary);
  line-height: 1.65;
  margin: 0 0 32px;
}
.hero-cta-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 40px;
}
.btn-cta-main {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 14px 28px;
  border-radius: 30px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.25s;
  box-shadow: 0 6px 20px rgba(240,41,78,0.3);
}
.btn-cta-main:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(240,41,78,0.38);
}
.btn-cta-ghost {
  display: inline-flex;
  align-items: center;
  background: transparent;
  color: var(--text-secondary);
  border: 1.5px solid var(--border);
  padding: 14px 24px;
  border-radius: 30px;
  font-size: 14px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.25s;
}
.btn-cta-ghost:hover {
  background: var(--surface);
  border-color: var(--text-secondary);
}
.btn-arrow {
  font-size: 16px;
  transition: transform 0.2s;
}
.btn-cta-main:hover .btn-arrow {
  transform: translateX(3px);
}

/* Trust Row */
.hero-trust-row {
  display: flex;
  align-items: center;
  gap: 20px;
}
.trust-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.trust-num {
  font-size: 20px;
  font-weight: 700;
  color: var(--text-primary);
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.5px;
}
.trust-label {
  font-size: 12px;
  color: var(--text-muted);
}
.trust-divider {
  width: 1px;
  height: 36px;
  background: var(--border);
}

/* ─── Phone Mockup ─────────────────────────────────── */
.hero-illustration {
  flex: 0 0 auto;
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
}
.phone-mockup {
  width: 220px;
  height: 420px;
  background: var(--primary-light);
  border: 1px solid var(--primary-50);
  border-radius: 36px;
  padding: 14px;
  box-shadow: 0 24px 60px rgba(240,41,78,0.1), 0 8px 24px rgba(0,0,0,0.06);
  animation: float 4s ease-in-out infinite;
}
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-12px); }
}
.phone-screen {
  background: #fff;
  border-radius: 24px;
  height: 100%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
}
.mock-topbar {
  background: var(--primary);
  padding: 12px 14px 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mock-logo {
  font-family: 'Noto Serif TC', serif;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
}
.mock-avatar-row {
  display: flex;
  gap: -4px;
}
.mock-avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 1.5px solid #fff;
}
.av1 { background: linear-gradient(135deg, #FFB347, #FF6B6B); margin-right: -6px; }
.av2 { background: linear-gradient(135deg, #74B9FF, #6C5CE7); }
.mock-profile-card {
  flex: 1;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.mock-profile-photo {
  width: 100%;
  height: 140px;
  background: linear-gradient(135deg, #FFE4EA 0%, #F0294E22 100%);
  border-radius: 12px;
  position: relative;
  overflow: hidden;
}
.mock-profile-photo::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 40px;
  background: linear-gradient(to top, rgba(240,41,78,0.15), transparent);
}
.mock-credit-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  color: #92400E;
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
  width: fit-content;
}
.credit-dot {
  width: 6px; height: 6px;
  background: var(--gold);
  border-radius: 50%;
}
.mock-profile-info {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.mock-name-bar {
  height: 10px;
  background: var(--border);
  border-radius: 5px;
  width: 70%;
}
.mock-detail-bar {
  height: 8px;
  background: #F3F4F6;
  border-radius: 4px;
}
.mock-detail-bar.short { width: 50%; }
.mock-action-row {
  display: flex;
  gap: 8px;
  margin-top: 4px;
}
.mock-btn {
  flex: 1;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}
.mock-btn.like {
  background: var(--primary-50);
  color: var(--primary);
}
.mock-btn.msg {
  background: #EFF6FF;
  color: #3B82F6;
}
.mock-qr-strip {
  background: #F9F9FB;
  border-top: 1px solid var(--border);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.mock-qr-icon {
  color: var(--primary);
  display: flex;
}
.mock-qr-text {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-secondary);
  flex: 1;
}
.mock-qr-dot {
  width: 7px; height: 7px;
  background: #10B981;
  border-radius: 50%;
}

/* Floating badges */
.float-badge {
  position: absolute;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  white-space: nowrap;
}
.fb1 {
  top: 40px;
  left: -60px;
  animation: float2 3.5s ease-in-out infinite;
}
.fb2 {
  bottom: 60px;
  right: -55px;
  animation: float2 3.5s ease-in-out infinite 1s;
}
@keyframes float2 {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}
.fb-icon {
  width: 18px; height: 18px;
  background: #ECFDF5;
  color: #065F46;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
}
.fb-score {
  background: var(--primary);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 12px;
  font-family: 'Inter', sans-serif;
}

/* ─── Features Section ─────────────────────────────── */
.features-section {
  padding: 96px 24px;
  background: var(--surface);
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}
.features-section.visible {
  opacity: 1;
  transform: translateY(0);
}
.features-inner {
  max-width: 1100px;
  margin: 0 auto;
  text-align: center;
}
.section-label {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--primary);
  margin-bottom: 12px;
}
.section-label.light {
  color: var(--primary);
}
.section-title {
  font-family: 'Noto Serif TC', serif;
  font-size: clamp(24px, 3.5vw, 36px);
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 48px;
  letter-spacing: -0.5px;
}
.section-title.light {
  color: var(--text-primary);
}
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}
.feature-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 32px 28px;
  text-align: left;
  transition: all 0.3s ease;
  animation: fade-up 0.6s ease both;
}
@keyframes fade-up {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.feature-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 40px rgba(240, 41, 78, 0.1);
  border-color: var(--primary-50);
}
.feature-icon-wrap {
  width: 48px;
  height: 48px;
  background: var(--primary-light);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary);
  margin-bottom: 18px;
  transition: background 0.3s;
}
.feature-card:hover .feature-icon-wrap {
  background: var(--primary-50);
}
.feature-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 10px;
  font-family: 'Noto Serif TC', serif;
}
.feature-desc {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.7;
  margin: 0;
}

/* ─── How Section ─────────────────────────────────── */
.how-section {
  padding: 96px 24px;
  background: var(--primary-light);
  border-top: 1px solid var(--primary-50);
  border-bottom: 1px solid var(--primary-50);
}
.how-inner {
  max-width: 1100px;
  margin: 0 auto;
  text-align: center;
}
.steps-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  flex-wrap: wrap;
  margin-top: 0;
}
.step-item {
  flex: 1;
  min-width: 200px;
  max-width: 280px;
  text-align: center;
  padding: 0 24px;
}
.step-num {
  font-family: 'Inter', sans-serif;
  font-size: 40px;
  font-weight: 700;
  color: var(--primary-50);
  line-height: 1;
  margin-bottom: 16px;
  letter-spacing: -2px;
}
.step-content h3 {
  font-size: 17px;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 8px;
  font-family: inherit;
}
.step-content p {
  font-size: 13px;
  color: var(--text-secondary);
  line-height: 1.65;
  margin: 0;
}
.step-connector {
  width: 40px;
  height: 1px;
  background: var(--primary-50);
  flex-shrink: 0;
}

/* ─── CTA Section ─────────────────────────────────── */
.cta-section {
  padding: 96px 24px;
  background: #fff;
  text-align: center;
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.6s ease 0.2s, transform 0.6s ease 0.2s;
}
.cta-section.visible {
  opacity: 1;
  transform: translateY(0);
}
.cta-inner {
  max-width: 560px;
  margin: 0 auto;
  position: relative;
}
.cta-bg-shape {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 400px;
  height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, #FFF5F7 0%, transparent 70%);
  pointer-events: none;
}
.cta-title {
  font-family: 'Noto Serif TC', serif;
  font-size: clamp(26px, 4vw, 40px);
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 14px;
  letter-spacing: -0.5px;
  position: relative;
}
.cta-sub {
  font-size: 15px;
  color: var(--text-secondary);
  margin: 0 0 36px;
  line-height: 1.6;
  position: relative;
}
.btn-cta-large {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 16px 36px;
  border-radius: 30px;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  box-shadow: 0 8px 28px rgba(240,41,78,0.35);
  transition: all 0.25s;
  position: relative;
}
.btn-cta-large:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 12px 36px rgba(240,41,78,0.45);
}
.btn-arrow-lg {
  font-size: 18px;
  transition: transform 0.2s;
}
.btn-cta-large:hover .btn-arrow-lg {
  transform: translateX(4px);
}
.cta-login-hint {
  margin-top: 20px;
  font-size: 14px;
  color: var(--text-muted);
  position: relative;
}
.inline-link {
  background: none;
  border: none;
  color: var(--primary);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  padding: 0;
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* ─── Footer ─────────────────────────────────────── */
.landing-footer {
  background: var(--text-primary);
  padding: 28px 24px;
}
.footer-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.footer-brand {
  font-family: 'Noto Serif TC', serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--primary);
}
.footer-copy {
  font-size: 13px;
  color: #4B5563;
}
.footer-links {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 13px;
  color: #4B5563;
}
.footer-links a {
  color: #6B7280;
  text-decoration: none;
  transition: color 0.2s;
}
.footer-links a:hover {
  color: var(--primary);
}

/* ─── Responsive ─────────────────────────────────── */
@media (max-width: 767px) {
  .hero-content {
    flex-direction: column;
    padding: 90px 20px 48px;
    gap: 40px;
    text-align: center;
  }
  .hero-text-block {
    max-width: 100%;
  }
  .hero-cta-row {
    justify-content: center;
  }
  .hero-trust-row {
    justify-content: center;
  }
  .hero-illustration {
    width: 100%;
  }
  .fb1 { left: 0; top: 20px; }
  .fb2 { right: 0; }
  .features-grid {
    grid-template-columns: 1fr;
  }
  .steps-row {
    flex-direction: column;
    gap: 32px;
  }
  .step-connector {
    display: none;
  }
  .footer-inner {
    flex-direction: column;
    text-align: center;
  }
}

@media (min-width: 768px) and (max-width: 1023px) {
  .hero-content {
    gap: 32px;
    padding: 100px 32px 60px;
  }
  .phone-mockup {
    width: 190px;
    height: 360px;
  }
}
</style>
