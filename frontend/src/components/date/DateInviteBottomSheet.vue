<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  targetNickname: string
  form: { date: string; time: string; locationName: string }
  isLoading: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  submit: []
  cancel: []
  'update:form': [val: { date: string; time: string; locationName: string }]
}>()

const today = computed(() => new Date().toISOString().split('T')[0])
const canSubmit = computed(() => props.form.date && props.form.time && !props.isLoading)

function updateField(key: 'date' | 'time' | 'locationName', value: string) {
  emit('update:form', { ...props.form, [key]: value })
}
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet">
      <div class="sheet-overlay" @click.self="emit('cancel')">
        <div class="sheet-panel">
          <div class="sheet-handle" />

          <h2 class="sheet-title">邀請 {{ targetNickname }} 約會</h2>

          <div class="sheet-field">
            <label class="sheet-label">約會日期</label>
            <input
              type="date"
              class="sheet-input"
              :value="form.date"
              :min="today"
              @input="updateField('date', ($event.target as HTMLInputElement).value)"
            />
          </div>

          <div class="sheet-field">
            <label class="sheet-label">約會時間</label>
            <input
              type="time"
              class="sheet-input"
              step="1800"
              :value="form.time"
              @input="updateField('time', ($event.target as HTMLInputElement).value)"
            />
          </div>

          <div class="sheet-field">
            <label class="sheet-label">見面地點</label>
            <input
              type="text"
              class="sheet-input"
              placeholder="輸入見面地點，如：台北101一樓大廳"
              maxlength="100"
              :value="form.locationName"
              @input="updateField('locationName', ($event.target as HTMLInputElement).value)"
            />
          </div>

          <div class="sheet-actions">
            <button class="sheet-btn sheet-btn--ghost" @click="emit('cancel')">取消</button>
            <button
              class="sheet-btn sheet-btn--primary"
              :disabled="!canSubmit"
              @click="emit('submit')"
            >
              <span v-if="isLoading" class="sheet-spinner" />
              <span v-else>確認送出</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.sheet-panel {
  background: #fff;
  width: 100%;
  max-width: 480px;
  border-radius: 20px 20px 0 0;
  padding: 12px 24px 32px;
  animation: slideUp 300ms ease-out;
}

@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}

.sheet-handle {
  width: 40px;
  height: 4px;
  border-radius: 2px;
  background: #D1D5DB;
  margin: 0 auto 16px;
}

.sheet-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 20px;
}

.sheet-field {
  margin-bottom: 16px;
}

.sheet-label {
  display: block;
  font-size: 14px;
  color: #6B7280;
  margin-bottom: 6px;
}

.sheet-input {
  width: 100%;
  height: 48px;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  padding: 0 16px;
  font-size: 16px;
  color: #111827;
  background: #fff;
  outline: none;
  transition: border-color 0.2s;
}

.sheet-input:focus {
  border-color: #F0294E;
  box-shadow: 0 0 0 3px rgba(240,41,78,0.12);
}

.sheet-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}

.sheet-btn {
  flex: 1;
  height: 48px;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.15s;
}

.sheet-btn--ghost {
  background: transparent;
  border: 1.5px solid #E5E7EB;
  color: #374151;
}
.sheet-btn--ghost:hover { background: #F9FAFB; }

.sheet-btn--primary {
  background: #F0294E;
  color: #fff;
}
.sheet-btn--primary:hover { background: #D01A3C; }
.sheet-btn--primary:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.sheet-spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Transition */
.sheet-enter-active { animation: slideUp 300ms ease-out; }
.sheet-leave-active { animation: slideUp 250ms ease-in reverse; }
</style>
