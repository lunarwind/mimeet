<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { fetchDates, respondToDate } from '@/api/dates'
import AppLayout from '@/components/layout/AppLayout.vue'
import DateCard from '@/components/dates/DateCard.vue'
import type { DateInvitation } from '@/types/chat'

const router = useRouter()
const authStore = useAuthStore()

const allDates = ref<DateInvitation[]>([])
const isLoading = ref(true)
const activeTab = ref<'pending' | 'accepted' | 'verified'>('pending')

const TABS = [
  { key: 'pending' as const, label: '待接受' },
  { key: 'accepted' as const, label: '進行中' },
  { key: 'verified' as const, label: '已完成' },
]

const filteredDates = computed(() =>
  allDates.value.filter(d => {
    if (activeTab.value === 'pending') return d.status === 'pending'
    if (activeTab.value === 'accepted') return d.status === 'accepted'
    return d.status === 'verified'
  })
)

const pendingCount = computed(() => allDates.value.filter(d => d.status === 'pending').length)

onMounted(async () => {
  allDates.value = await fetchDates()
  isLoading.value = false
})

async function handleAccept(id: number) {
  await respondToDate(id, 'accepted')
  const d = allDates.value.find(x => x.id === id)
  if (d) d.status = 'accepted'
}

async function handleReject(id: number) {
  await respondToDate(id, 'rejected')
  allDates.value = allDates.value.filter(x => x.id !== id)
}

function handleScan(_id: number) {
  router.push('/app/dates/scan')
}
</script>

<template>
  <AppLayout title="約會">
    <template #topbar-right>
      <span v-if="pendingCount > 0" class="pending-badge">{{ pendingCount }}</span>
    </template>

    <!-- Tabs -->
    <div class="date-tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="date-tab"
        :class="{ 'date-tab--active': activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="date-loading"><span class="spinner" /></div>

    <!-- Empty -->
    <div v-else-if="filteredDates.length === 0" class="date-empty">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <p class="date-empty__text">
        {{ activeTab === 'pending' ? '沒有待接受的約會' : activeTab === 'accepted' ? '沒有進行中的約會' : '還沒有完成的約會' }}
      </p>
    </div>

    <!-- List -->
    <div v-else class="date-list">
      <DateCard
        v-for="d in filteredDates"
        :key="d.id"
        :date="d"
        :my-id="authStore.user?.id ?? 0"
        @accept="handleAccept"
        @reject="handleReject"
        @scan="handleScan"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.pending-badge { background:#F0294E; color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:9999px; display:flex; align-items:center; justify-content:center; padding:0 5px; }

.date-tabs { display:flex; gap:0; background:#fff; border-bottom:1px solid #E5E7EB; }
.date-tab { flex:1; height:44px; border:none; background:none; font-size:14px; font-weight:500; color:#6B7280; cursor:pointer; position:relative; transition:color 0.15s; }
.date-tab--active { color:#F0294E; font-weight:700; }
.date-tab--active::after { content:''; position:absolute; bottom:0; left:20%; right:20%; height:2px; background:#F0294E; border-radius:1px; }

.date-loading { display:flex; justify-content:center; padding:48px 0; }
.spinner { width:24px; height:24px; border-radius:50%; border:2.5px solid #E5E7EB; border-top-color:#F0294E; animation:spin 0.7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

.date-empty { display:flex; flex-direction:column; align-items:center; gap:12px; padding:48px 20px; text-align:center; }
.date-empty__text { font-size:14px; color:#9CA3AF; }

.date-list { padding:16px; }
</style>
