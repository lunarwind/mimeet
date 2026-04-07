<script setup lang="ts">
import { ref } from 'vue'
import TopBar from '@/components/layout/TopBar.vue'
import { useInfiniteScroll } from '@/composables/useInfiniteScroll'
import { fetchBlockedUsers, unblockUser, type BlockedUser } from '@/api/users'

const unblocking = ref<Set<number>>(new Set())

const {
  items: blockedUsers,
  isLoading,
  isLoadingMore,
  hasMore,
  isEmpty,
  sentinelRef,
  fetchFirst,
} = useInfiniteScroll<BlockedUser>({
  fetchFn: async (page) => {
    const res = await fetchBlockedUsers(page)
    return { data: res.users, hasMore: res.hasMore }
  },
})

fetchFirst()

async function handleUnblock(userId: number) {
  if (unblocking.value.has(userId)) return
  unblocking.value.add(userId)
  try {
    await unblockUser(userId)
    blockedUsers.value = blockedUsers.value.filter(u => u.id !== userId)
  } finally {
    unblocking.value.delete(userId)
  }
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  return `${d.getMonth() + 1}/${d.getDate()}`
}
</script>

<template>
  <div class="blocked-view">
    <TopBar title="封鎖名單" show-back />

    <div class="blocked-body">
      <!-- Loading -->
      <div v-if="isLoading" class="blocked-loading">
        <span class="spinner" />
      </div>

      <!-- Empty -->
      <div v-else-if="isEmpty" class="blocked-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
        <p class="blocked-empty__text">沒有封鎖任何用戶</p>
      </div>

      <!-- List -->
      <template v-else>
        <div
          v-for="user in blockedUsers"
          :key="user.id"
          class="blocked-card"
        >
          <img
            :src="user.avatar || 'https://i.pravatar.cc/150?img=0'"
            :alt="user.nickname"
            class="blocked-card__avatar"
          />
          <div class="blocked-card__info">
            <span class="blocked-card__name">{{ user.nickname }}</span>
            <span class="blocked-card__date">封鎖於 {{ formatDate(user.blockedAt) }}</span>
          </div>
          <button
            class="blocked-card__unblock"
            :disabled="unblocking.has(user.id)"
            @click="handleUnblock(user.id)"
          >
            {{ unblocking.has(user.id) ? '解除中…' : '解除封鎖' }}
          </button>
        </div>

        <div ref="sentinelRef" class="sentinel" />
        <div v-if="isLoadingMore" class="loading-more">
          <span class="spinner" />
        </div>
        <p v-if="!hasMore && blockedUsers.length > 0" class="end-hint">已顯示全部</p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.blocked-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  background: #F9F9FB;
}

.blocked-body {
  flex: 1;
  padding: 12px 16px;
}

.blocked-loading {
  display: flex;
  justify-content: center;
  padding: 48px 0;
}

.spinner {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2.5px solid #E5E7EB;
  border-top-color: #F0294E;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.blocked-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 64px 0;
}

.blocked-empty__text {
  font-size: 14px;
  color: #9CA3AF;
}

.blocked-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 0;
  border-bottom: 0.5px solid #F1F5F9;
}

.blocked-card:last-of-type { border-bottom: none; }

.blocked-card__avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  background: #F1F5F9;
  flex-shrink: 0;
}

.blocked-card__info { flex: 1; min-width: 0; }

.blocked-card__name {
  display: block;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.blocked-card__date {
  display: block;
  font-size: 12px;
  color: #9CA3AF;
  margin-top: 2px;
}

.blocked-card__unblock {
  flex-shrink: 0;
  height: 32px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid #E5E7EB;
  background: #fff;
  font-size: 12px;
  font-weight: 600;
  color: #F0294E;
  cursor: pointer;
  transition: all 0.15s;
}

.blocked-card__unblock:active { background: #FFF5F7; transform: scale(0.97); }
.blocked-card__unblock:disabled { opacity: 0.5; cursor: not-allowed; }

.sentinel { height: 1px; }
.loading-more { display: flex; justify-content: center; padding: 16px 0; }
.end-hint { text-align: center; font-size: 12px; color: #CBD5E1; padding: 16px 0; }
</style>
