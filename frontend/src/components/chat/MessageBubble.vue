<script setup lang="ts">
import { ref } from 'vue'
import type { Message } from '@/types/chat'

const props = defineProps<{
  message: Message
  isSelf: boolean
}>()

const showMenu = ref(false)
let longPressTimer: ReturnType<typeof setTimeout> | undefined

function onPointerDown() {
  longPressTimer = setTimeout(() => { showMenu.value = true }, 500)
}
function onPointerUp() { clearTimeout(longPressTimer) }
function closeMenu() { showMenu.value = false }

function copyText() {
  navigator.clipboard?.writeText(props.message.content)
  showMenu.value = false
}
function recallMsg() {
  showMenu.value = false
  // TODO: emit recall
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div
    class="bubble-row"
    :class="isSelf ? 'bubble-row--self' : 'bubble-row--other'"
    @pointerdown="onPointerDown"
    @pointerup="onPointerUp"
    @pointerleave="onPointerUp"
  >
    <div class="bubble" :class="isSelf ? 'bubble--self' : 'bubble--other'">
      <p class="bubble__text">{{ message.content }}</p>
      <span class="bubble__meta">
        {{ formatTime(message.createdAt) }}
        <template v-if="isSelf">
          <span class="bubble__status">{{ message.status === 'read' ? '已讀' : message.status === 'sending' ? '傳送中' : '' }}</span>
        </template>
      </span>
    </div>

    <!-- 長按選單 -->
    <Transition name="menu">
      <div v-if="showMenu" class="bubble-menu" @click.stop>
        <button class="bubble-menu__item" @click="copyText">複製</button>
        <button v-if="isSelf" class="bubble-menu__item bubble-menu__item--danger" @click="recallMsg">收回</button>
        <button class="bubble-menu__item" @click="closeMenu">取消</button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.bubble-row { display:flex; max-width:80%; margin-bottom:4px; position:relative; user-select:none; }
.bubble-row--self { align-self:flex-end; flex-direction:row-reverse; }
.bubble-row--other { align-self:flex-start; }

.bubble { padding:10px 14px; border-radius:16px; word-break:break-word; }
.bubble--self { background:#F0294E; color:#fff; border-bottom-right-radius:4px; }
.bubble--other { background:#F1F5F9; color:#111827; border-bottom-left-radius:4px; }

.bubble__text { font-size:14px; line-height:1.5; margin:0; white-space:pre-wrap; }

.bubble__meta { display:flex; align-items:center; gap:4px; font-size:10px; margin-top:4px; justify-content:flex-end; }
.bubble--self .bubble__meta { color:rgba(255,255,255,0.65); }
.bubble--other .bubble__meta { color:#9CA3AF; }

.bubble__status { font-size:10px; }

/* ── Long-press menu ─────────────────────────────────────── */
.bubble-menu { position:absolute; top:100%; right:0; z-index:50; background:#fff; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,0.12); overflow:hidden; min-width:100px; margin-top:4px; }
.bubble-row--other .bubble-menu { left:0; right:auto; }
.bubble-menu__item { display:block; width:100%; padding:10px 16px; border:none; background:none; font-size:13px; color:#374151; cursor:pointer; text-align:left; }
.bubble-menu__item:active { background:#F3F4F6; }
.bubble-menu__item--danger { color:#EF4444; }

.menu-enter-active,.menu-leave-active { transition:all 0.15s ease; }
.menu-enter-from,.menu-leave-to { opacity:0; transform:scale(0.9); }
</style>
