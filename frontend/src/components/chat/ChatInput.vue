<script setup lang="ts">
import { ref, nextTick } from 'vue'

const emit = defineEmits<{ send: [content: string] }>()

const text = ref('')
const textareaRef = ref<HTMLTextAreaElement | null>(null)

function adjustHeight() {
  const el = textareaRef.value
  if (!el) return
  el.style.height = '44px'
  el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSend()
  }
}

function handleSend() {
  const content = text.value.trim()
  if (!content) return
  emit('send', content)
  text.value = ''
  nextTick(() => {
    adjustHeight()
    textareaRef.value?.focus()
  })
}
</script>

<template>
  <div class="chat-input">
    <textarea
      ref="textareaRef"
      v-model="text"
      class="chat-input__field"
      placeholder="輸入訊息…"
      rows="1"
      @input="adjustHeight"
      @keydown="handleKeydown"
    />
    <button
      class="chat-input__send"
      :class="{ 'chat-input__send--active': text.trim() }"
      :disabled="!text.trim()"
      @click="handleSend"
    >
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>
</template>

<style scoped>
.chat-input { display:flex; align-items:flex-end; gap:8px; padding:8px 16px; background:#fff; border-top:1px solid #F1F5F9; padding-bottom:max(8px, env(safe-area-inset-bottom)); }

.chat-input__field { flex:1; min-height:44px; max-height:120px; border:1.5px solid #E5E7EB; border-radius:20px; padding:10px 16px; font-size:14px; color:#111827; background:#F9FAFB; outline:none; resize:none; font-family:inherit; line-height:1.5; box-sizing:border-box; }
.chat-input__field:focus { border-color:#F0294E; background:#fff; }
.chat-input__field::placeholder { color:#9CA3AF; }

.chat-input__send { width:44px; height:44px; border-radius:50%; border:none; background:#E5E7EB; color:#9CA3AF; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; }
.chat-input__send--active { background:#F0294E; color:#fff; }
.chat-input__send:active:not(:disabled) { transform:scale(0.93); }
.chat-input__send:disabled { cursor:not-allowed; }
</style>
