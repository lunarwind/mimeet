<template>
  <button
    :class="['btn', `btn-${variant}`, `btn-${size}`, { 'btn-loading': loading, 'w-full': fullWidth }]"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="spinner" />
    <slot v-else />
  </button>
</template>

<script setup lang="ts">
defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
  fullWidth?: boolean
}>()

defineEmits<{
  click: [event: MouseEvent]
}>()
</script>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-weight: 600;
  font-family: 'Noto Sans TC', sans-serif;
  cursor: pointer;
  transition: all 0.15s;
  border: none;
  outline: none;
}

.btn:active { transform: scale(0.97); }
.btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Size */
.btn-sm  { height: 40px; padding: 0 16px; font-size: 14px; }
.btn-md  { height: 48px; padding: 0 24px; font-size: 15px; }
.btn-lg  { height: 56px; padding: 0 32px; font-size: 16px; }

/* Variant */
.btn-primary   { background: #F0294E; color: white; }
.btn-primary:hover:not(:disabled)   { background: #D01A3C; }
.btn-secondary { background: transparent; color: #374151; border: 1.5px solid #E5E7EB; }
.btn-secondary:hover:not(:disabled) { background: #F9FAFB; border-color: #D1D5DB; }
.btn-danger    { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
.btn-danger:hover:not(:disabled)    { background: #FEE2E2; }
.btn-ghost     { background: transparent; color: #F0294E; border: none; }
.btn-ghost:hover:not(:disabled)     { background: #FFF1F3; }

.w-full { width: 100%; }

/* Spinner */
.spinner {
  width: 18px; height: 18px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
