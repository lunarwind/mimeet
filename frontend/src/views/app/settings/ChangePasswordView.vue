<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { changePassword } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import {
  validatePassword,
  validatePasswordConfirm,
  getPasswordStrength,
  validateRequired,
} from '@/utils/validators'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const form = reactive({
  currentPassword: '',
  password: '',
  passwordConfirm: '',
})

const errors = reactive({
  currentPassword: '',
  password: '',
  passwordConfirm: '',
})

const isLoading = ref(false)
const showCurrent = ref(false)
const showPass = ref(false)
const showPassConfirm = ref(false)

const pwStrength = computed(() => getPasswordStrength(form.password))
const strength = computed(() => pwStrength.value.score)
const strengthLabel = computed(() => pwStrength.value.label)
const strengthColor = computed(() => pwStrength.value.color)

async function handleSubmit() {
  errors.currentPassword = validateRequired(form.currentPassword, '目前密碼')
  errors.password        = validatePassword(form.password)
  errors.passwordConfirm = validatePasswordConfirm(form.passwordConfirm, form.password)
  if (errors.currentPassword || errors.password || errors.passwordConfirm) return

  isLoading.value = true
  try {
    await changePassword({
      current_password: form.currentPassword,
      password: form.password,
      password_confirmation: form.passwordConfirm,
    })
    uiStore.showToast('密碼已更新，請以新密碼重新登入', 'success')
    authStore.logout()
    router.push('/login')
  } catch (e: unknown) {
    const ex = e as { response?: { data?: { error?: { code?: string } } } }
    const code = ex?.response?.data?.error?.code
    if (code === 'PASSWORD_INCORRECT') {
      errors.currentPassword = '目前密碼不正確'
    } else if (code === 'PASSWORD_SAME_AS_CURRENT') {
      errors.password = '新密碼不可與目前密碼相同'
    } else {
      uiStore.showToast('修改失敗，請稍後再試', 'error')
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <AppLayout title="修改密碼">
    <div class="cp-page">

      <!-- 注意事項 -->
      <div class="notice-card">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
        </svg>
        <span>修改成功後將自動登出所有裝置，請以新密碼重新登入。</span>
      </div>

      <div class="cp-card">

        <!-- 目前密碼 -->
        <div class="field-group">
          <label class="field-label">目前密碼</label>
          <div class="input-wrap" :class="{ error: errors.currentPassword }">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input
              v-model="form.currentPassword"
              :type="showCurrent ? 'text' : 'password'"
              placeholder="請輸入目前的密碼"
              autocomplete="current-password"
              class="field-input"
              @input="errors.currentPassword = ''"
            />
            <button class="eye-btn" type="button" @click="showCurrent = !showCurrent">
              <svg v-if="showCurrent" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
            </button>
          </div>
          <Transition name="err">
            <p v-if="errors.currentPassword" class="field-error">{{ errors.currentPassword }}</p>
          </Transition>
        </div>

        <!-- 新密碼 -->
        <div class="field-group">
          <label class="field-label">新密碼</label>
          <div class="input-wrap" :class="{ error: errors.password }">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input
              v-model="form.password"
              :type="showPass ? 'text' : 'password'"
              placeholder="請輸入新密碼（至少 8 個字元）"
              autocomplete="new-password"
              class="field-input"
              @input="errors.password = ''"
            />
            <button class="eye-btn" type="button" @click="showPass = !showPass">
              <svg v-if="showPass" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
            </button>
          </div>

          <div v-if="form.password" class="strength-wrap">
            <div class="strength-bar">
              <div
                class="strength-fill"
                :style="{ width: `${(strength / 5) * 100}%`, background: strengthColor }"
              />
            </div>
            <span class="strength-label" :style="{ color: strengthColor }">{{ strengthLabel }}</span>
          </div>

          <Transition name="err">
            <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
          </Transition>
        </div>

        <!-- 確認新密碼 -->
        <div class="field-group">
          <label class="field-label">確認新密碼</label>
          <div class="input-wrap" :class="{ error: errors.passwordConfirm }">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input
              v-model="form.passwordConfirm"
              :type="showPassConfirm ? 'text' : 'password'"
              placeholder="再輸入一次新密碼"
              autocomplete="new-password"
              class="field-input"
              @input="errors.passwordConfirm = ''"
              @keyup.enter="handleSubmit"
            />
            <button class="eye-btn" type="button" @click="showPassConfirm = !showPassConfirm">
              <svg v-if="showPassConfirm" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
            </button>
          </div>
          <Transition name="err">
            <p v-if="errors.passwordConfirm" class="field-error">{{ errors.passwordConfirm }}</p>
          </Transition>
        </div>

        <button
          class="btn-main"
          :class="{ loading: isLoading }"
          :disabled="isLoading"
          @click="handleSubmit"
        >
          <span v-if="!isLoading">確認修改密碼</span>
          <span v-else class="spinner-wrap">
            <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            處理中…
          </span>
        </button>
      </div>

    </div>
  </AppLayout>
</template>

<style scoped>
.cp-page {
  padding: 16px;
  padding-bottom: 80px;
}

.notice-card {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 12px 14px;
  background: #FFF7ED;
  border: 1px solid #FDE68A;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: 13px;
  color: #92400E;
  line-height: 1.5;
}
.notice-card svg { flex-shrink: 0; margin-top: 1px; color: #F59E0B; }

.cp-card {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 16px;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 13px; font-weight: 600; color: #111827; }

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  border: 1.5px solid #E5E7EB;
  border-radius: 12px;
  background: #F9F9FB;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrap:focus-within {
  border-color: #F0294E;
  box-shadow: 0 0 0 3px rgba(240,41,78,0.08);
  background: #fff;
}
.input-wrap.error { border-color: #EF4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.08); }

.input-icon { position: absolute; left: 14px; color: #9CA3AF; pointer-events: none; }
.field-input {
  width: 100%;
  height: 48px;
  padding: 0 44px;
  border: none;
  background: transparent;
  font-size: 15px;
  color: #111827;
  font-family: inherit;
  outline: none;
}
.field-input::placeholder { color: #9CA3AF; }

.eye-btn {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: #9CA3AF;
  cursor: pointer;
  padding: 4px;
  display: flex;
}

.field-error { font-size: 12px; color: #EF4444; margin: 0; }
.err-enter-active, .err-leave-active { transition: all 0.2s; }
.err-enter-from, .err-leave-to { opacity: 0; transform: translateY(-4px); }

.strength-wrap { display: flex; align-items: center; gap: 8px; }
.strength-bar { flex: 1; height: 4px; background: #E5E7EB; border-radius: 2px; overflow: hidden; }
.strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s ease, background 0.3s ease; }
.strength-label { font-size: 12px; font-weight: 600; min-width: 20px; }

.btn-main {
  width: 100%;
  height: 50px;
  background: #F0294E;
  color: #fff;
  border: none;
  border-radius: 14px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(240,41,78,0.28);
  transition: all 0.25s;
  margin-top: 4px;
}
.btn-main:hover:not(:disabled) { background: #D01A3C; transform: translateY(-1px); }
.btn-main:disabled { opacity: 0.75; cursor: not-allowed; }

.spinner-wrap { display: flex; align-items: center; gap: 8px; }
.spinner { animation: spin 0.9s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
