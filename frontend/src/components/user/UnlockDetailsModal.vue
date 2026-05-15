<template>
  <BaseModal v-model="open" title="解鎖詳細資料" :closable="!isLoading">
    <div class="unlock-modal__body">
      <p class="unlock-modal__lead">
        消費 <strong class="unlock-modal__cost">{{ cost }} 點</strong>
        即可在 <strong>{{ durationHours }} 小時內</strong> 查看任何用戶的詳細資料
      </p>

      <div class="unlock-modal__balance-row">
        <span class="unlock-modal__balance-label">目前點數餘額</span>
        <span class="unlock-modal__balance-value">{{ balance }} 點</span>
      </div>

      <div v-if="!canAfford" class="unlock-modal__warn">
        點數不足，請先儲值。
      </div>

      <div v-if="errorMsg" class="unlock-modal__error">{{ errorMsg }}</div>
    </div>

    <template #footer>
      <button class="unlock-modal__btn unlock-modal__btn--ghost" :disabled="isLoading" @click="onCancel">
        取消
      </button>
      <button
        v-if="canAfford"
        class="unlock-modal__btn unlock-modal__btn--primary"
        :disabled="isLoading"
        @click="onConfirm"
      >
        {{ isLoading ? '處理中…' : `確認消費 ${cost} 點` }}
      </button>
      <button
        v-else
        class="unlock-modal__btn unlock-modal__btn--primary"
        @click="onGoTopUp"
      >
        前往儲值
      </button>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import BaseModal from '@/components/common/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import { unlockProfileDetails, type UnlockDetailsResponse } from '@/api/users'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    cost?: number
    durationHours?: number
  }>(),
  {
    cost: 5,
    durationHours: 24,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  success: [payload: UnlockDetailsResponse]
}>()

const router = useRouter()
const authStore = useAuthStore()

const isLoading = ref(false)
const errorMsg = ref<string | null>(null)

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const balance = computed(() => authStore.user?.points_balance ?? 0)
const canAfford = computed(() => balance.value >= props.cost)

function onCancel() {
  if (isLoading.value) return
  errorMsg.value = null
  emit('update:modelValue', false)
}

function onGoTopUp() {
  emit('update:modelValue', false)
  router.push('/app/shop')
}

async function onConfirm() {
  if (isLoading.value) return
  isLoading.value = true
  errorMsg.value = null
  try {
    const payload = await unlockProfileDetails()
    // Refresh auth user so points_balance / details_pass_until are current
    await authStore.refreshUser()
    emit('success', payload)
    emit('update:modelValue', false)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { code?: string; message?: string } } }
    const code = e?.response?.data?.code
    if (code === 'DETAILS_PASS_ACTIVE') {
      errorMsg.value = '通行證仍有效，請於到期後再購買。'
    } else if (code === 'INSUFFICIENT_POINTS') {
      errorMsg.value = '點數不足，請先儲值。'
    } else {
      errorMsg.value = e?.response?.data?.message ?? '解鎖失敗，請稍後再試。'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.unlock-modal__body {
  padding: 4px 0;
}

.unlock-modal__lead {
  font-size: 14px;
  color: #334155;
  line-height: 1.5;
  margin: 0 0 16px;
}

.unlock-modal__cost {
  color: #F0294E;
  font-weight: 700;
}

.unlock-modal__balance-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  background: #F8FAFC;
  border-radius: 10px;
  margin-bottom: 12px;
}

.unlock-modal__balance-label {
  font-size: 13px;
  color: #64748B;
}

.unlock-modal__balance-value {
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
}

.unlock-modal__warn {
  font-size: 13px;
  color: #B45309;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 8px;
  padding: 8px 12px;
  margin-bottom: 8px;
}

.unlock-modal__error {
  font-size: 13px;
  color: #991B1B;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: 8px;
  padding: 8px 12px;
  margin-top: 8px;
}

.unlock-modal__btn {
  height: 40px;
  padding: 0 18px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: filter 0.15s;
}

.unlock-modal__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.unlock-modal__btn--ghost {
  background: #fff;
  color: #334155;
  border-color: #E2E8F0;
}

.unlock-modal__btn--ghost:hover:not(:disabled) {
  background: #F8FAFC;
}

.unlock-modal__btn--primary {
  background: #F0294E;
  color: #fff;
}

.unlock-modal__btn--primary:hover:not(:disabled) {
  filter: brightness(0.95);
}
</style>
