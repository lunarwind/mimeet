<script setup lang="ts">
/**
 * F41 用戶廣播 3 步驟 Modal
 *   1. 撰寫內容
 *   2. 篩選對象
 *   3. 確認發送
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import client from '@/api/client'

const emit = defineEmits<{
  close: []
  sent: [broadcastId: number]
}>()

const router = useRouter()
const step = ref<1 | 2 | 3>(1)
const content = ref('')
const filters = ref({
  gender: '' as '' | 'male' | 'female',
  ageMin: 20,
  ageMax: 40,
  location: '',
  datingBudget: '',
  style: '',
})
const previewData = ref<{
  recipientCount: number
  costPerUser: number
  totalCost: number
  currentBalance: number
  canAfford: boolean
  balanceAfter: number
  maxRecipients: number
  dailyLimit: number
  dailyUsed: number
  dailyRemaining: number
} | null>(null)
const isLoading = ref(false)
const isSending = ref(false)
const sentResult = ref<{ recipientCount: number; pointsSpent: number; pointsBalance: number } | null>(null)

const contentLen = computed(() => content.value.length)

function buildFiltersPayload() {
  const f: Record<string, any> = {}
  if (filters.value.gender) f.gender = filters.value.gender
  if (filters.value.ageMin && filters.value.ageMin !== 18) f.age_min = filters.value.ageMin
  if (filters.value.ageMax && filters.value.ageMax !== 99) f.age_max = filters.value.ageMax
  if (filters.value.location.trim()) f.location = filters.value.location.trim()
  if (filters.value.datingBudget) f.dating_budget = filters.value.datingBudget
  if (filters.value.style) f.style = filters.value.style
  return f
}

async function goPreview() {
  if (!content.value.trim()) return
  isLoading.value = true
  try {
    const res = await client.post('/broadcasts/preview', {
      content: content.value,
      filters: buildFiltersPayload(),
    })
    const d = res.data?.data ?? {}
    previewData.value = {
      recipientCount: d.recipient_count,
      costPerUser: d.cost_per_user,
      totalCost: d.total_cost,
      currentBalance: d.current_balance,
      canAfford: d.can_afford,
      balanceAfter: d.balance_after,
      maxRecipients: d.max_recipients,
      dailyLimit: d.daily_limit,
      dailyUsed: d.daily_used,
      dailyRemaining: d.daily_remaining,
    }
    step.value = 3
  } catch (err: any) {
    alert(err.response?.data?.message ?? '預覽失敗')
  } finally {
    isLoading.value = false
  }
}

async function confirmSend() {
  if (isSending.value || !previewData.value) return
  isSending.value = true
  try {
    const res = await client.post('/broadcasts/send', {
      content: content.value,
      filters: buildFiltersPayload(),
    })
    const d = res.data?.data ?? {}
    sentResult.value = {
      recipientCount: d.recipient_count,
      pointsSpent: d.points_spent,
      pointsBalance: d.points_balance,
    }
    emit('sent', d.broadcast_id)
  } catch (err: any) {
    const resp = err.response?.data
    if (resp?.code === 'DAILY_LIMIT_EXCEEDED') {
      alert(resp.message ?? '今日已達廣播上限')
    } else if (resp?.code === 'INSUFFICIENT_POINTS') {
      alert(resp.message ?? '點數不足')
      router.push('/app/shop?tab=points')
      emit('close')
    } else {
      alert(resp?.message ?? '廣播失敗')
    }
  } finally {
    isSending.value = false
  }
}

function goTopUp() {
  emit('close')
  router.push('/app/shop?tab=points')
}
</script>

<template>
  <div class="bc-overlay" @click="emit('close')">
    <div class="bc-modal" @click.stop>
      <!-- 已發送完成 -->
      <template v-if="sentResult">
        <h2 class="bc-title">✅ 廣播已送出</h2>
        <p class="bc-desc">已發送給 <strong>{{ sentResult.recipientCount }}</strong> 位用戶</p>
        <p class="bc-desc">消費 {{ sentResult.pointsSpent }} 點，剩餘 {{ sentResult.pointsBalance }} 點</p>
        <p class="bc-note">對方會在訊息頁收到你的私訊</p>
        <div class="bc-actions">
          <button class="bc-btn bc-btn--primary" @click="emit('close')">完成</button>
        </div>
      </template>

      <!-- Step 1: 撰寫內容 -->
      <template v-else-if="step === 1">
        <h2 class="bc-title">📢 廣播訊息 <span class="bc-step">1 / 3</span></h2>
        <p class="bc-desc">以你本人名義發送私訊給符合條件的用戶</p>
        <textarea v-model="content" class="bc-textarea" rows="4" maxlength="200"
          placeholder="週末想找人一起去米其林餐廳，有興趣的歡迎回覆 😊" />
        <div class="bc-counter">{{ contentLen }} / 200</div>
        <div class="bc-actions">
          <button class="bc-btn bc-btn--secondary" @click="emit('close')">取消</button>
          <button class="bc-btn bc-btn--primary" :disabled="!content.trim()" @click="step = 2">下一步 →</button>
        </div>
      </template>

      <!-- Step 2: 篩選 -->
      <template v-else-if="step === 2">
        <h2 class="bc-title">📢 篩選對象 <span class="bc-step">2 / 3</span></h2>
        <div class="bc-field">
          <label>性別</label>
          <select v-model="filters.gender"><option value="">不限</option><option value="male">男性</option><option value="female">女性</option></select>
        </div>
        <div class="bc-field">
          <label>年齡</label>
          <div class="bc-range">
            <input type="number" v-model.number="filters.ageMin" min="18" max="99" /> ~
            <input type="number" v-model.number="filters.ageMax" min="18" max="99" />
          </div>
        </div>
        <div class="bc-field">
          <label>地區</label>
          <input type="text" v-model="filters.location" placeholder="例：台北" />
        </div>
        <div class="bc-field">
          <label>約會預算</label>
          <select v-model="filters.datingBudget">
            <option value="">不限</option>
            <option value="casual">輕鬆小聚</option>
            <option value="moderate">質感約會</option>
            <option value="generous">高品質體驗</option>
            <option value="luxury">頂級享受</option>
          </select>
        </div>
        <div class="bc-field">
          <label>風格</label>
          <select v-model="filters.style">
            <option value="">不限</option>
            <option value="fresh">清新</option>
            <option value="sweet">甜美</option>
            <option value="sexy">性感</option>
            <option value="intellectual">知性</option>
            <option value="sporty">運動</option>
          </select>
        </div>
        <div class="bc-actions">
          <button class="bc-btn bc-btn--secondary" @click="step = 1">← 上一步</button>
          <button class="bc-btn bc-btn--primary" :disabled="isLoading" @click="goPreview">
            {{ isLoading ? '預覽中...' : '預覽 →' }}
          </button>
        </div>
      </template>

      <!-- Step 3: 確認 -->
      <template v-else-if="step === 3 && previewData">
        <h2 class="bc-title">📢 確認廣播 <span class="bc-step">3 / 3</span></h2>
        <div class="bc-summary">
          <div>符合條件：<strong>{{ previewData.recipientCount }} 人</strong></div>
          <div>消費點數：<strong>{{ previewData.totalCost }} 點</strong>（{{ previewData.recipientCount }} 人 × {{ previewData.costPerUser }} 點/人）</div>
          <div>目前餘額：{{ previewData.currentBalance }} 點</div>
          <div v-if="previewData.canAfford">發送後餘額：{{ previewData.balanceAfter }} 點</div>
          <div v-else style="color:#EF4444;">⚠️ 餘額不足，需要 {{ previewData.totalCost - previewData.currentBalance }} 點</div>
          <div>今日剩餘：<strong>{{ previewData.dailyRemaining }} / {{ previewData.dailyLimit }}</strong> 次</div>
        </div>
        <div class="bc-preview">
          <div class="bc-preview__label">預覽內容</div>
          <div class="bc-preview__content">{{ content }}</div>
        </div>
        <div class="bc-actions">
          <button class="bc-btn bc-btn--secondary" @click="step = 2">← 上一步</button>
          <button v-if="previewData.canAfford && previewData.recipientCount > 0 && previewData.dailyRemaining > 0"
            class="bc-btn bc-btn--primary" :disabled="isSending" @click="confirmSend">
            {{ isSending ? '發送中...' : `確認發送（${previewData.totalCost} 點）` }}
          </button>
          <button v-else-if="!previewData.canAfford" class="bc-btn bc-btn--primary" @click="goTopUp">前往儲值</button>
          <button v-else class="bc-btn bc-btn--secondary" disabled>無法發送</button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.bc-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:500; display:flex; align-items:center; justify-content:center; padding:20px; }
.bc-modal { width:100%; max-width:440px; max-height:90dvh; overflow-y:auto; background:#fff; border-radius:16px; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.18); }
.bc-title { font-size:18px; font-weight:700; color:#111827; margin:0 0 4px; display:flex; justify-content:space-between; align-items:center; }
.bc-step { font-size:12px; color:#9CA3AF; font-weight:400; }
.bc-desc { font-size:13px; color:#6B7280; margin:0 0 16px; }
.bc-note { font-size:12px; color:#9CA3AF; margin:4px 0 16px; }

.bc-textarea { width:100%; border:1.5px solid #E5E7EB; border-radius:10px; padding:12px; font-size:15px; font-family:inherit; line-height:1.5; resize:vertical; outline:none; box-sizing:border-box; }
.bc-textarea:focus { border-color:#F0294E; }
.bc-counter { text-align:right; font-size:12px; color:#9CA3AF; margin-top:4px; margin-bottom:16px; }

.bc-field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
.bc-field label { font-size:13px; font-weight:500; color:#6B7280; }
.bc-field input, .bc-field select { height:40px; border:1.5px solid #E5E7EB; border-radius:8px; padding:0 12px; font-size:14px; outline:none; }
.bc-field input:focus, .bc-field select:focus { border-color:#F0294E; }
.bc-range { display:flex; align-items:center; gap:8px; }
.bc-range input { width:80px; }

.bc-summary { font-size:14px; color:#374151; line-height:2; margin-bottom:14px; padding:14px; background:#F9FAFB; border-radius:10px; }
.bc-summary strong { color:#F0294E; }

.bc-preview { background:#FFF5F7; border-left:3px solid #F0294E; padding:12px; border-radius:8px; margin-bottom:16px; }
.bc-preview__label { font-size:11px; color:#9CA3AF; margin-bottom:4px; }
.bc-preview__content { font-size:14px; color:#111827; white-space:pre-wrap; }

.bc-actions { display:flex; gap:10px; margin-top:16px; }
.bc-btn { flex:1; height:44px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; }
.bc-btn--secondary { background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB; }
.bc-btn--primary { background:#F0294E; color:#fff; }
.bc-btn--primary:disabled { opacity:0.5; cursor:not-allowed; }
</style>
