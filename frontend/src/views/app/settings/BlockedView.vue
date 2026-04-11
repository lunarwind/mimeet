<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import { unblockUser } from '@/api/users'
import { useUiStore } from '@/stores/ui'
import client from '@/api/client'

const uiStore = useUiStore()

interface BlockedUser {
  id: number
  nickname: string
  avatar: string
  blockedAt: string
}

const blockedUsers = ref<BlockedUser[]>([])
const isLoading = ref(false)
const showConfirmModal = ref(false)
const pendingUnblock = ref<BlockedUser | null>(null)

onMounted(async () => {
  isLoading.value = true
  try {
    const res = await client.get('/me/blocked-users')
    blockedUsers.value = (res.data?.data?.users ?? []).map((u: Record<string, unknown>) => ({
      id: u.id, nickname: u.nickname, avatar: u.avatar_url, blockedAt: u.blocked_at,
    }))
  } catch {
    blockedUsers.value = []
  }
  isLoading.value = false
})

function confirmUnblock(user: BlockedUser) {
  pendingUnblock.value = user
  showConfirmModal.value = true
}

async function doUnblock() {
  if (!pendingUnblock.value) return
  const userId = pendingUnblock.value.id
  try {
    await unblockUser(userId)
    blockedUsers.value = blockedUsers.value.filter(u => u.id !== userId)
    uiStore.showToast('已解除封鎖', 'success')
  } catch {
    uiStore.showToast('操作失敗', 'error')
  }
  showConfirmModal.value = false
  pendingUnblock.value = null
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('zh-TW')
}
</script>

<template>
  <AppLayout title="封鎖名單" :show-back="true">
    <div class="blocked-page">
      <!-- Loading -->
      <div v-if="isLoading" class="blocked-loading">
        <div class="spinner" />
      </div>

      <!-- Empty -->
      <div v-else-if="blockedUsers.length === 0" class="blocked-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <div class="blocked-empty__title">封鎖名單是空的</div>
        <p class="blocked-empty__desc">你目前沒有封鎖任何用戶</p>
      </div>

      <!-- List -->
      <div v-else class="blocked-list">
        <div
          v-for="user in blockedUsers"
          :key="user.id"
          class="blocked-item"
        >
          <img :src="user.avatar" :alt="user.nickname" class="blocked-item__avatar" />
          <div class="blocked-item__info">
            <span class="blocked-item__name">{{ user.nickname }}</span>
            <span class="blocked-item__date">封鎖於 {{ formatDate(user.blockedAt) }}</span>
          </div>
          <button class="unblock-btn" @click="confirmUnblock(user)">解除封鎖</button>
        </div>
      </div>

      <!-- Confirm Modal -->
      <div v-if="showConfirmModal" class="modal-overlay" @click="showConfirmModal = false">
        <div class="modal-card" @click.stop>
          <h3 class="modal-card__title">解除封鎖</h3>
          <p class="modal-card__desc">確定要解除封鎖 {{ pendingUnblock?.nickname }} 嗎？解除後對方將能看到你的資料並傳送訊息。</p>
          <div class="modal-card__actions">
            <button class="btn-secondary" @click="showConfirmModal = false">取消</button>
            <button class="btn-primary" @click="doUnblock">確認解除</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
.blocked-page { padding: 16px; }

.blocked-loading { display: flex; justify-content: center; padding: 48px 0; }
.spinner { width: 24px; height: 24px; border: 3px solid #E5E7EB; border-top-color: #F0294E; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.blocked-empty { text-align: center; padding: 64px 24px; }
.blocked-empty__title { font-size: 16px; font-weight: 600; color: #9CA3AF; margin-top: 16px; }
.blocked-empty__desc { font-size: 14px; color: #D1D5DB; margin-top: 4px; }

.blocked-list { display: flex; flex-direction: column; gap: 8px; }
.blocked-item { display: flex; align-items: center; gap: 12px; background: white; border-radius: 12px; padding: 12px 16px; border: 1px solid #F1F5F9; }
.blocked-item__avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.blocked-item__info { flex: 1; display: flex; flex-direction: column; }
.blocked-item__name { font-size: 14px; font-weight: 600; color: #111827; }
.blocked-item__date { font-size: 12px; color: #9CA3AF; }
.unblock-btn { padding: 6px 14px; border-radius: 8px; border: 1.5px solid #E5E7EB; background: white; color: #6B7280; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; }
.unblock-btn:hover { border-color: #F0294E; color: #F0294E; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-card { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 360px; }
.modal-card__title { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px; }
.modal-card__desc { font-size: 14px; color: #6B7280; margin-bottom: 20px; line-height: 1.5; }
.modal-card__actions { display: flex; gap: 10px; }
.btn-primary { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #F0294E; color: white; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-secondary { flex: 1; padding: 12px; border-radius: 10px; border: 1.5px solid #E5E7EB; background: white; color: #374151; font-size: 15px; font-weight: 500; cursor: pointer; }
</style>
