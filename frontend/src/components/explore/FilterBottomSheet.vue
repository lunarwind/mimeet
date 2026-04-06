<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import type { ExploreFilter } from '@/types/explore'

interface Props {
  currentFilters: ExploreFilter
}

const props = defineProps<Props>()

const emit = defineEmits<{
  apply: [filters: ExploreFilter]
  reset: []
  close: []
}>()

// ── 本地篩選狀態（與 props 同步，未套用前不影響外部） ─────
const localFilters = ref<ExploreFilter>({ ...props.currentFilters })

watch(() => props.currentFilters, (val) => {
  localFilters.value = { ...val }
}, { immediate: true })

// ── 年齡滑桿 ──────────────────────────────────────────────
const ageMin = ref(localFilters.value.ageMin ?? 18)
const ageMax = ref(localFilters.value.ageMax ?? 50)

// ── 性別 ──────────────────────────────────────────────────
const gender = ref<'all' | 'male' | 'female'>(localFilters.value.gender ?? 'all')

// ── 誠信分數區間 ──────────────────────────────────────────
type CreditRange = '0-30' | '31-60' | '61-90' | '91-120'
const CREDIT_RANGES: { value: CreditRange; label: string }[] = [
  { value: '0-30',   label: '30以下' },
  { value: '31-60',  label: '31–60' },
  { value: '61-90',  label: '61–90' },
  { value: '91-120', label: '91以上' },
]
const selectedCreditRange = ref<CreditRange | null>(
  (localFilters.value.creditScoreRange as CreditRange) ?? null
)

// ── 地區（多選 Checkbox） ────────────────────────────────
const CITIES = [
  '台北市', '新北市', '桃園市', '台中市',
  '台南市', '高雄市', '新竹市', '嘉義市',
  '基隆市', '宜蘭縣', '花蓮縣', '台東縣',
  '苗栗縣', '彰化縣', '南投縣', '雲林縣',
  '屏東縣', '澎湖縣',
]
const selectedCities = ref<string[]>(localFilters.value.cities ?? [])

function toggleCity(city: string) {
  const idx = selectedCities.value.indexOf(city)
  if (idx >= 0) selectedCities.value.splice(idx, 1)
  else selectedCities.value.push(city)
}

// ── 最後上線 ──────────────────────────────────────────────
type LastOnline = 'today' | '3days' | '7days' | 'all'
const LAST_ONLINE_OPTIONS: { value: LastOnline; label: string }[] = [
  { value: 'today',  label: '今天' },
  { value: '3days',  label: '3 天內' },
  { value: '7days',  label: '7 天內' },
  { value: 'all',    label: '全部' },
]
const lastOnline = ref<LastOnline>((localFilters.value.lastOnline as LastOnline) ?? 'all')

// ── 套用條件數量（用於按鈕 label） ────────────────────────
const activeCount = computed(() => {
  let count = 0
  if (ageMin.value !== 18 || ageMax.value !== 50) count++
  if (gender.value !== 'all') count++
  if (selectedCreditRange.value) count++
  if (selectedCities.value.length) count++
  if (lastOnline.value !== 'all') count++
  return count
})

// ── 年齡 Range Slider（雙滑桿） ──────────────────────────
const trackRef = ref<HTMLElement | null>(null)

const ageRangeStyle = computed(() => {
  const minPct = ((ageMin.value - 18) / 32) * 100
  const maxPct = ((ageMax.value - 18) / 32) * 100
  return {
    '--range-left': `${minPct}%`,
    '--range-width': `${maxPct - minPct}%`,
    '--thumb-min': `${minPct}%`,
    '--thumb-max': `${maxPct}%`,
  }
})

function onMinChange(e: Event) {
  const val = parseInt((e.target as HTMLInputElement).value)
  if (val < ageMax.value) ageMin.value = val
}

function onMaxChange(e: Event) {
  const val = parseInt((e.target as HTMLInputElement).value)
  if (val > ageMin.value) ageMax.value = val
}

// ── 動作 ──────────────────────────────────────────────────
function applyFilters() {
  const filters: ExploreFilter = {}
  if (ageMin.value !== 18)   filters.ageMin = ageMin.value
  if (ageMax.value !== 50)   filters.ageMax = ageMax.value
  if (gender.value !== 'all')        filters.gender = gender.value
  if (selectedCreditRange.value)     filters.creditScoreRange = selectedCreditRange.value
  if (selectedCities.value.length)   filters.cities = [...selectedCities.value]
  if (lastOnline.value !== 'all')    filters.lastOnline = lastOnline.value
  emit('apply', filters)
}

function resetFilters() {
  ageMin.value = 18
  ageMax.value = 50
  gender.value = 'all'
  selectedCreditRange.value = null
  selectedCities.value = []
  lastOnline.value = 'all'
  emit('reset')
}
</script>

<template>
  <!-- Backdrop -->
  <Teleport to="body">
    <div class="filter-backdrop" @click="emit('close')" aria-hidden="true" />
    <div
      class="filter-sheet"
      role="dialog"
      aria-modal="true"
      aria-label="進階篩選"
    >
      <!-- 頂部把手 -->
      <div class="filter-sheet__handle" />

      <!-- 標題行 -->
      <div class="filter-sheet__header">
        <h2 class="filter-sheet__title">進階篩選</h2>
        <button class="filter-sheet__close" @click="emit('close')" aria-label="關閉">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <!-- 內容 -->
      <div class="filter-sheet__body">

        <!-- 1. 年齡區間 -->
        <section class="filter-section">
          <div class="filter-section__label-row">
            <label class="filter-section__label">年齡區間</label>
            <span class="filter-section__value">{{ ageMin }} – {{ ageMax }} 歲</span>
          </div>
          <div class="filter-range" :style="ageRangeStyle">
            <div class="filter-range__track">
              <div class="filter-range__fill" />
            </div>
            <!-- Visible thumb indicators -->
            <div class="filter-range__thumb filter-range__thumb--min" :data-value="ageMin" />
            <div class="filter-range__thumb filter-range__thumb--max" :data-value="ageMax" />
            <!-- Invisible native inputs for interaction -->
            <input
              class="filter-range__input filter-range__input--min"
              type="range" min="18" max="50"
              :value="ageMin"
              @input="onMinChange"
              aria-label="最小年齡"
            />
            <input
              class="filter-range__input filter-range__input--max"
              type="range" min="18" max="50"
              :value="ageMax"
              @input="onMaxChange"
              aria-label="最大年齡"
            />
          </div>
          <div class="filter-range__labels">
            <span>18</span>
            <span>50</span>
          </div>
        </section>

        <div class="filter-divider" />

        <!-- 2. 性別 -->
        <section class="filter-section">
          <label class="filter-section__label">性別</label>
          <div class="filter-radio-group">
            <label
              v-for="opt in [{ value: 'all', label: '全部' }, { value: 'male', label: '男' }, { value: 'female', label: '女' }]"
              :key="opt.value"
              class="filter-radio"
              :class="{ 'filter-radio--active': gender === opt.value }"
            >
              <input
                v-model="gender"
                type="radio"
                :value="opt.value"
                class="sr-only"
              />
              {{ opt.label }}
            </label>
          </div>
        </section>

        <div class="filter-divider" />

        <!-- 3. 誠信分數 -->
        <section class="filter-section">
          <label class="filter-section__label">誠信等級</label>
          <div class="filter-chips">
            <button
              v-for="opt in CREDIT_RANGES"
              :key="opt.value"
              class="filter-chip"
              :class="{ 'filter-chip--active': selectedCreditRange === opt.value }"
              @click="selectedCreditRange = selectedCreditRange === opt.value ? null : opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
        </section>

        <div class="filter-divider" />

        <!-- 4. 地區 -->
        <section class="filter-section">
          <label class="filter-section__label">地區</label>
          <div class="filter-cities">
            <label
              v-for="city in CITIES"
              :key="city"
              class="filter-city"
              :class="{ 'filter-city--active': selectedCities.includes(city) }"
            >
              <input
                type="checkbox"
                :checked="selectedCities.includes(city)"
                class="sr-only"
                @change="toggleCity(city)"
              />
              {{ city }}
            </label>
          </div>
        </section>

        <div class="filter-divider" />

        <!-- 5. 最後上線 -->
        <section class="filter-section">
          <label class="filter-section__label">最後上線</label>
          <div class="filter-radio-group">
            <label
              v-for="opt in LAST_ONLINE_OPTIONS"
              :key="opt.value"
              class="filter-radio"
              :class="{ 'filter-radio--active': lastOnline === opt.value }"
            >
              <input
                v-model="lastOnline"
                type="radio"
                :value="opt.value"
                class="sr-only"
              />
              {{ opt.label }}
            </label>
          </div>
        </section>

      </div>

      <!-- 底部操作按鈕 -->
      <div class="filter-sheet__footer">
        <button class="filter-sheet__reset-btn" @click="resetFilters">
          重設篩選
        </button>
        <button class="filter-sheet__apply-btn" @click="applyFilters">
          套用篩選
          <span v-if="activeCount > 0" class="filter-sheet__apply-count">
            {{ activeCount }}
          </span>
        </button>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* ── Backdrop ─────────────────────────────────────────────── */
.filter-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  z-index: 40;
  animation: fade-in 0.2s ease;
}

@keyframes fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── Sheet ────────────────────────────────────────────────── */
.filter-sheet {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: #fff;
  border-radius: 20px 20px 0 0;
  z-index: 50;
  display: flex;
  flex-direction: column;
  max-height: 90dvh;
  animation: slide-up 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}

@keyframes slide-up {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}

.filter-sheet__handle {
  width: 36px;
  height: 4px;
  background: #E2E8F0;
  border-radius: 2px;
  margin: 12px auto 0;
  flex-shrink: 0;
}

/* ── 標題行 ───────────────────────────────────────────────── */
.filter-sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px 12px;
  flex-shrink: 0;
}

.filter-sheet__title {
  font-size: 17px;
  font-weight: 700;
  color: #0F172A;
}

.filter-sheet__close {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: #F1F5F9;
  color: #64748B;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
}

/* ── 內容區（可捲動） ─────────────────────────────────────── */
.filter-sheet__body {
  flex: 1;
  overflow-y: auto;
  padding: 0 20px;
  -webkit-overflow-scrolling: touch;
}

/* ── Section ─────────────────────────────────────────────── */
.filter-section {
  padding: 16px 0;
}

.filter-section__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.filter-section__label {
  font-size: 14px;
  font-weight: 600;
  color: #1E293B;
  display: block;
  margin-bottom: 12px;
}

.filter-section__label-row .filter-section__label {
  margin-bottom: 0;
}

.filter-section__value {
  font-size: 13px;
  font-weight: 600;
  color: #F0294E;
}

.filter-divider {
  height: 0.5px;
  background: #F1F5F9;
}

/* ── Range Slider ─────────────────────────────────────────── */
.filter-range {
  position: relative;
  height: 44px;
  display: flex;
  align-items: center;
}

.filter-range__track {
  position: absolute;
  left: 0;
  right: 0;
  height: 4px;
  background: #E2E8F0;
  border-radius: 2px;
}

.filter-range__fill {
  position: absolute;
  left: var(--range-left, 0%);
  width: var(--range-width, 100%);
  height: 100%;
  background: #F0294E;
  border-radius: 2px;
}

/* ── Visible thumb dots ─────────────────────────────────── */
.filter-range__thumb {
  position: absolute;
  top: 50%;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #F0294E;
  border: 3px solid #fff;
  box-shadow: 0 1px 6px rgba(0,0,0,0.18);
  transform: translate(-50%, -50%);
  pointer-events: none;
  z-index: 2;
  transition: box-shadow 0.15s;
}

.filter-range__thumb--min {
  left: var(--thumb-min, 0%);
}

.filter-range__thumb--max {
  left: var(--thumb-max, 100%);
}

/* Age value tooltip above thumb */
.filter-range__thumb::after {
  content: attr(data-value);
  position: absolute;
  bottom: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  background: #1E293B;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 5px;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.15s;
  pointer-events: none;
}

/* Show tooltip when adjacent input is being dragged */
.filter-range:active .filter-range__thumb::after {
  opacity: 1;
}

/* ── Invisible native range inputs (for drag interaction) ── */
.filter-range__input {
  position: absolute;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
  margin: 0;
  -webkit-appearance: none;
  pointer-events: none;
  z-index: 3;
}

.filter-range__input::-webkit-slider-thumb {
  pointer-events: all;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  -webkit-appearance: none;
  cursor: grab;
}

.filter-range__input::-moz-range-thumb {
  pointer-events: all;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  background: transparent;
  cursor: grab;
}

.filter-range__input:active::-webkit-slider-thumb {
  cursor: grabbing;
}

.filter-range__labels {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #94A3B8;
  margin-top: 8px;
}

/* ── Radio Group ─────────────────────────────────────────── */
.filter-radio-group {
  display: flex;
  gap: 8px;
}

.filter-radio {
  flex: 1;
  height: 40px;
  border: 1.5px solid #E2E8F0;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}

.filter-radio--active {
  background: #FFF0F3;
  border-color: #F0294E;
  color: #F0294E;
  font-weight: 600;
}

/* ── Chips（誠信分數） ────────────────────────────────────── */
.filter-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-chip {
  height: 36px;
  padding: 0 14px;
  border-radius: 9999px;
  border: 1.5px solid #E2E8F0;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.filter-chip--active {
  background: #F0294E;
  border-color: #F0294E;
  color: #fff;
}

/* ── 城市 Checkbox ────────────────────────────────────────── */
.filter-cities {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-city {
  height: 34px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1.5px solid #E2E8F0;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  align-items: center;
}

.filter-city--active {
  background: #FFF0F3;
  border-color: #FECDD3;
  color: #F0294E;
}

/* ── 底部按鈕 ─────────────────────────────────────────────── */
.filter-sheet__footer {
  display: flex;
  gap: 12px;
  padding: 16px 20px;
  padding-bottom: calc(16px + env(safe-area-inset-bottom));
  border-top: 0.5px solid #F1F5F9;
  flex-shrink: 0;
}

.filter-sheet__reset-btn {
  flex: 0 0 auto;
  height: 48px;
  padding: 0 20px;
  border-radius: 10px;
  border: 1.5px solid #E2E8F0;
  background: #fff;
  font-size: 14px;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}

.filter-sheet__reset-btn:active {
  background: #F8FAFC;
}

.filter-sheet__apply-btn {
  flex: 1;
  height: 48px;
  border-radius: 10px;
  border: none;
  background: #F0294E;
  font-size: 15px;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: background 0.15s, transform 0.1s;
}

.filter-sheet__apply-btn:active {
  background: #D91E3F;
  transform: scale(0.98);
}

.filter-sheet__apply-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(255,255,255,0.3);
  font-size: 12px;
  font-weight: 700;
}

/* ── Accessibility ────────────────────────────────────────── */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border: 0;
}
</style>
