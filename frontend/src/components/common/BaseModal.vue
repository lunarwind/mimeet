<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" @click.self="onOverlayClick">
        <div class="modal-container">
          <!-- Header -->
          <div v-if="title" class="modal-header">
            <span class="modal-title">{{ title }}</span>
            <button v-if="closable" class="modal-close" @click="$emit('update:modelValue', false)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6 6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <slot />
          </div>

          <!-- Footer -->
          <div v-if="$slots.footer" class="modal-footer">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  closable?: boolean
  closeOnOverlay?: boolean
}>(), {
  closable: true,
  closeOnOverlay: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

function onOverlayClick() {
  if (props.closeOnOverlay) {
    emit('update:modelValue', false)
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  z-index: 200;
  padding-bottom: env(safe-area-inset-bottom);
}

.modal-container {
  background: white;
  border-radius: 20px 20px 0 0;
  width: 100%;
  max-width: 480px;
  max-height: 90dvh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 20px 0;
}

.modal-title {
  font-size: 17px;
  font-weight: 600;
  color: #111827;
  font-family: 'Noto Sans TC', sans-serif;
}

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #6B7280;
  padding: 4px;
  display: flex;
  align-items: center;
}

.modal-body {
  padding: 16px 20px;
}

.modal-footer {
  padding: 0 20px 20px;
  display: flex;
  gap: 8px;
}

/* Transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.25s ease;
}
.modal-enter-active .modal-container,
.modal-leave-active .modal-container {
  transition: transform 0.25s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-from .modal-container,
.modal-leave-to .modal-container {
  transform: translateY(100%);
}
</style>
