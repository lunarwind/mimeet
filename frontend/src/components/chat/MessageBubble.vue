<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { Message } from '@/types/chat'

const props = defineProps<{
  message: Message
  isSelf: boolean
}>()

const emit = defineEmits<{
  recall: [messageId: number]
  imageClick: [imageUrl: string]
}>()

const authStore = useAuthStore()
const showMenu = ref(false)
const imagePreview = ref<string | null>(null)
let longPressTimer: ReturnType<typeof setTimeout> | undefined

function onPointerDown() {
  if (props.message.isRecalled) return
  longPressTimer = setTimeout(() => { showMenu.value = true }, 500)
}
function onPointerUp() { clearTimeout(longPressTimer) }
function closeMenu() { showMenu.value = false }

function copyText() {
  if (props.message.type === 'text') {
    navigator.clipboard?.writeText(props.message.content)
  }
  showMenu.value = false
}

function doRecall() {
  showMenu.value = false
  emit('recall', props.message.id)
}

function onImageClick() {
  const url = props.message.imageUrl || props.message.content
  if (url) emit('imageClick', url)
}

function formatTime(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })
}

// 回收條件：本人 + 付費會員 + 尚未讀 + 5 分鐘內 + 未被回收
const canRecall = computed(() => {
  if (!props.isSelf) return false
  if (props.message.isRecalled) return false
  if (props.message.isRead) return false
  if ((authStore.user?.membership_level ?? 0) < 3) return false
  const sent = new Date(props.message.createdAt).getTime()
  if (isNaN(sent)) return false
  return Date.now() - sent <= 5 * 60 * 1000
})

// 已讀顯示：自己發出 + 付費會員 + 訊息已讀
const showReadReceipt = computed(() =>
  props.isSelf
  && !props.message.isRecalled
  && props.message.isRead
  && (authStore.user?.membership_level ?? 0) >= 3,
)
</script>

<template>
  <div
    class="bubble-row"
    :class="isSelf ? 'bubble-row--self' : 'bubble-row--other'"
    @pointerdown="onPointerDown"
    @pointerup="onPointerUp"
    @pointerleave="onPointerUp"
  >
    <!-- 已回收 -->
    <div v-if="message.isRecalled" class="bubble bubble--recalled">
      <p class="bubble__text bubble__text--recalled">訊息已收回</p>
    </div>

    <!-- 圖片 -->
    <div v-else-if="message.type === 'image'" class="bubble bubble--image" :class="isSelf ? 'bubble--self' : 'bubble--other'">
      <img
        :src="message.imageUrl || message.content"
        class="bubble__image"
        alt="圖片訊息"
        @click="onImageClick"
      />
      <span class="bubble__meta bubble__meta--image">
        {{ formatTime(message.createdAt) }}
        <template v-if="isSelf">
          <span v-if="showReadReceipt" class="bubble__status">已讀</span>
          <span v-else-if="message.status === 'sending'" class="bubble__status">傳送中</span>
        </template>
      </span>
    </div>

    <!-- 文字 -->
    <div v-else class="bubble" :class="isSelf ? 'bubble--self' : 'bubble--other'">
      <p class="bubble__text">{{ message.content }}</p>
      <span class="bubble__meta">
        {{ formatTime(message.createdAt) }}
        <template v-if="isSelf">
          <span v-if="showReadReceipt" class="bubble__status">已讀</span>
          <span v-else-if="message.status === 'sending'" class="bubble__status">傳送中</span>
        </template>
      </span>
    </div>

    <!-- 長按選單 -->
    <Transition name="menu">
      <div v-if="showMenu" class="bubble-menu" @click.stop>
        <button v-if="message.type === 'text'" class="bubble-menu__item" @click="copyText">複製</button>
        <button v-if="canRecall" class="bubble-menu__item bubble-menu__item--danger" @click="doRecall">收回</button>
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

.bubble--recalled { background:#F3F4F6; color:#9CA3AF; border:1px dashed #E5E7EB; }
.bubble__text--recalled { font-style:italic; font-size:13px; }

.bubble--image { padding:4px; background:transparent; }
.bubble--image.bubble--self { background:transparent; }
.bubble--image.bubble--other { background:transparent; }
.bubble__image { display:block; max-width:220px; max-height:300px; width:auto; height:auto; border-radius:12px; cursor:zoom-in; background:#F1F5F9; }

.bubble__text { font-size:14px; line-height:1.5; margin:0; white-space:pre-wrap; }

.bubble__meta { display:flex; align-items:center; gap:4px; font-size:10px; margin-top:4px; justify-content:flex-end; }
.bubble__meta--image { padding:2px 4px 0; color:#9CA3AF !important; }
.bubble--self .bubble__meta { color:rgba(255,255,255,0.65); }
.bubble--other .bubble__meta { color:#9CA3AF; }

.bubble__status { font-size:10px; }

.bubble-menu { position:absolute; top:100%; right:0; z-index:50; background:#fff; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,0.12); overflow:hidden; min-width:100px; margin-top:4px; }
.bubble-row--other .bubble-menu { left:0; right:auto; }
.bubble-menu__item { display:block; width:100%; padding:10px 16px; border:none; background:none; font-size:13px; color:#374151; cursor:pointer; text-align:left; }
.bubble-menu__item:active { background:#F3F4F6; }
.bubble-menu__item--danger { color:#EF4444; }

.menu-enter-active,.menu-leave-active { transition:all 0.15s ease; }
.menu-enter-from,.menu-leave-to { opacity:0; transform:scale(0.9); }
</style>
