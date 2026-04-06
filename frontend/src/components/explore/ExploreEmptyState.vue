<script setup lang="ts">
const emit = defineEmits<{
  adjustFilter: []
}>()
</script>

<template>
  <div class="empty-state" role="status" aria-live="polite">
    <!-- SVG 插圖：放大鏡 + 問號 -->
    <div class="empty-state__illustration" aria-hidden="true">
      <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
        <!-- 外圓（放大鏡圓框） -->
        <circle cx="50" cy="50" r="32" stroke="#E2E8F0" stroke-width="3" fill="#F8FAFC"/>
        <!-- 問號 -->
        <text x="50" y="58" text-anchor="middle" font-size="28" font-weight="700" fill="#CBD5E1" font-family="system-ui">?</text>
        <!-- 放大鏡把手 -->
        <line x1="74" y1="74" x2="96" y2="96" stroke="#E2E8F0" stroke-width="6" stroke-linecap="round"/>
        <!-- 裝飾小點 -->
        <circle cx="18" cy="30" r="4" fill="#FECDD3"/>
        <circle cx="96" cy="28" r="6" fill="#FEF9C3"/>
        <circle cx="100" cy="62" r="3" fill="#BFDBFE"/>
        <circle cx="14" cy="70" r="5" fill="#DCFCE7"/>
      </svg>
    </div>

    <h2 class="empty-state__title">找不到符合的用戶</h2>
    <p class="empty-state__desc">試試放寬篩選條件，發現更多可能的緣分</p>

    <button
      class="empty-state__btn"
      @click="emit('adjustFilter')"
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
      </svg>
      調整篩選條件
    </button>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 32px;
  gap: 12px;
  text-align: center;
}

.empty-state__illustration {
  margin-bottom: 8px;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(-8px); }
}

.empty-state__title {
  font-size: 17px;
  font-weight: 700;
  color: #334155;
  margin: 0;
}

.empty-state__desc {
  font-size: 14px;
  color: #94A3B8;
  margin: 0;
  line-height: 1.6;
}

.empty-state__btn {
  margin-top: 8px;
  height: 44px;
  padding: 0 20px;
  border-radius: 9999px;
  border: 1.5px solid #FECDD3;
  background: #FFF0F3;
  color: #F0294E;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s;
}

.empty-state__btn:active {
  background: #FFE4E8;
  transform: scale(0.97);
}
</style>
