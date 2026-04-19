<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted } from 'vue'

const emit = defineEmits<{
  send: [content: string]
  sendImage: [file: File]
}>()

const text = ref('')
const textareaRef = ref<HTMLTextAreaElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const rootRef = ref<HTMLElement | null>(null)

const EMOJI_LIST = [
  { emoji: '😊', label: '開心' },
  { emoji: '😂', label: '大笑' },
  { emoji: '🥰', label: '喜歡' },
  { emoji: '😘', label: '飛吻' },
  { emoji: '😍', label: '愛心眼' },
  { emoji: '🤗', label: '擁抱' },
  { emoji: '😢', label: '難過' },
  { emoji: '😠', label: '生氣' },
  { emoji: '😱', label: '驚訝' },
  { emoji: '🤔', label: '思考' },
  { emoji: '👍', label: '讚' },
  { emoji: '❤️', label: '愛心' },
]

const showEmoji = ref(false)

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
  showEmoji.value = false
  nextTick(() => {
    adjustHeight()
    textareaRef.value?.focus()
  })
}

function toggleEmoji() {
  showEmoji.value = !showEmoji.value
}

function insertEmoji(emoji: string) {
  const el = textareaRef.value
  if (!el) {
    text.value += emoji
    return
  }
  const start = el.selectionStart ?? text.value.length
  const end = el.selectionEnd ?? text.value.length
  text.value = text.value.slice(0, start) + emoji + text.value.slice(end)
  nextTick(() => {
    const pos = start + emoji.length
    el.focus()
    el.setSelectionRange(pos, pos)
    adjustHeight()
  })
}

function triggerImagePicker() {
  showEmoji.value = false
  fileInputRef.value?.click()
}

function onImageSelected(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    alert('圖片不可超過 5MB')
    input.value = ''
    return
  }
  emit('sendImage', file)
  input.value = ''
}

// 點擊 ChatInput 外部時關閉 emoji 面板
function handleDocClick(e: MouseEvent) {
  if (!showEmoji.value) return
  if (!rootRef.value?.contains(e.target as Node)) {
    showEmoji.value = false
  }
}

onMounted(() => document.addEventListener('click', handleDocClick))
onUnmounted(() => document.removeEventListener('click', handleDocClick))
</script>

<template>
  <div ref="rootRef" class="chat-input-root">
    <!-- Emoji 面板（向上展開） -->
    <Transition name="emoji-panel">
      <div v-if="showEmoji" class="emoji-panel" @click.stop>
        <div class="emoji-panel__grid">
          <button
            v-for="item in EMOJI_LIST"
            :key="item.emoji"
            type="button"
            class="emoji-panel__item"
            :aria-label="item.label"
            :title="item.label"
            @click="insertEmoji(item.emoji)"
          >{{ item.emoji }}</button>
        </div>
      </div>
    </Transition>

    <div class="chat-input">
      <button
        type="button"
        class="chat-input__btn"
        :class="{ 'chat-input__btn--active': showEmoji }"
        aria-label="表情符號"
        @click="toggleEmoji"
      >😊</button>

      <button type="button" class="chat-input__btn" aria-label="傳送圖片" @click="triggerImagePicker">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
      </button>
      <input
        ref="fileInputRef"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="chat-input__file"
        @change="onImageSelected"
      />

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
  </div>
</template>

<style scoped>
/* ── Root wrapper ────────────────────────────────────────── */
.chat-input-root { position:relative; flex-shrink:0; }

/* ── Emoji Panel ─────────────────────────────────────────── */
.emoji-panel { position:absolute; bottom:100%; left:8px; right:8px; max-width:360px; margin:0 auto 6px; background:#fff; border:1px solid #E5E7EB; border-radius:14px; box-shadow:0 6px 24px -6px rgba(17,24,39,0.18), 0 2px 6px rgba(17,24,39,0.08); padding:10px; z-index:20; }
.emoji-panel__grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:4px; }
.emoji-panel__item { height:44px; font-size:24px; line-height:1; border:none; background:transparent; border-radius:10px; cursor:pointer; transition:background 0.1s; padding:0; }
.emoji-panel__item:hover { background:#F3F4F6; }
.emoji-panel__item:active { background:#E5E7EB; transform:scale(0.95); }

.emoji-panel-enter-active,.emoji-panel-leave-active { transition:transform 0.18s ease, opacity 0.18s ease; }
.emoji-panel-enter-from,.emoji-panel-leave-to { transform:translateY(6px); opacity:0; }

/* ── Input Bar ───────────────────────────────────────────── */
.chat-input { display:flex; align-items:flex-end; gap:8px; padding:8px 16px; background:#fff; border-top:1px solid #F1F5F9; padding-bottom:max(8px, env(safe-area-inset-bottom)); }

.chat-input__btn { width:44px; height:44px; border:1.5px solid #E5E7EB; border-radius:50%; background:#F9FAFB; color:#6B7280; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; font-size:22px; line-height:1; padding:0; }
.chat-input__btn:hover { background:#F1F5F9; color:#F0294E; border-color:#F0294E; }
.chat-input__btn:active { transform:scale(0.93); }
.chat-input__btn--active { background:#FFE4EA; color:#F0294E; border-color:#F0294E; }
.chat-input__file { display:none; }

.chat-input__field { flex:1; min-height:44px; max-height:120px; border:1.5px solid #E5E7EB; border-radius:20px; padding:10px 16px; font-size:16px; color:#111827; background:#F9FAFB; outline:none; resize:none; font-family:inherit; line-height:1.5; box-sizing:border-box; min-width:0; }
.chat-input__field:focus { border-color:#F0294E; background:#fff; }
.chat-input__field::placeholder { color:#9CA3AF; }

.chat-input__send { width:44px; height:44px; border-radius:50%; border:none; background:#E5E7EB; color:#9CA3AF; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.15s; }
.chat-input__send--active { background:#F0294E; color:#fff; }
.chat-input__send:active:not(:disabled) { transform:scale(0.93); }
.chat-input__send:disabled { cursor:not-allowed; }

/* ── Tablet (768px+) — 6 欄 ──────────────────────────────── */
@media (min-width: 768px) {
  .chat-input { padding:12px 24px; gap:10px; }
  .chat-input__btn { width:48px; height:48px; font-size:24px; }
  .chat-input__field { min-height:48px; max-height:140px; font-size:15px; }
  .chat-input__send { width:48px; height:48px; }
  .emoji-panel { max-width:420px; padding:12px; }
  .emoji-panel__grid { grid-template-columns:repeat(6, 1fr); gap:6px; }
  .emoji-panel__item { height:48px; font-size:26px; }
}

/* ── Desktop (1024px+) ───────────────────────────────────── */
@media (min-width: 1024px) {
  .chat-input { padding:14px 32px; gap:12px; }
  .chat-input__field { min-height:52px; max-height:160px; font-size:15px; padding:12px 20px; }
  .chat-input__send { width:52px; height:52px; }
  .chat-input__btn { width:52px; height:52px; }
}
</style>
