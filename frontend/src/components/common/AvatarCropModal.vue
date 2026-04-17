<script setup lang="ts">
import { ref } from 'vue'
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

defineProps<{ src: string }>()
const emit = defineEmits<{
  confirm: [blob: Blob]
  cancel: []
}>()

const cropperRef = ref()

function handleConfirm() {
  const { canvas } = cropperRef.value.getResult()
  canvas.toBlob((blob: Blob | null) => {
    if (blob) emit('confirm', blob)
  }, 'image/jpeg', 0.9)
}
</script>

<template>
  <div class="crop-overlay" @click.self="emit('cancel')">
    <div class="crop-modal">
      <div class="crop-header">
        <span class="crop-title">裁切頭像</span>
        <button class="crop-close" @click="emit('cancel')">✕</button>
      </div>
      <Cropper
        ref="cropperRef"
        :src="src"
        :stencil-props="{ aspectRatio: 1 }"
        class="crop-area"
      />
      <div class="crop-actions">
        <button class="crop-btn crop-btn--cancel" @click="emit('cancel')">取消</button>
        <button class="crop-btn crop-btn--confirm" @click="handleConfirm">確認裁切</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.crop-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8);
  z-index: 9999; display: flex; align-items: center; justify-content: center;
}
.crop-modal {
  background: white; border-radius: 16px; width: min(400px, 95vw); overflow: hidden;
}
.crop-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px; font-weight: 600; font-size: 16px;
}
.crop-close {
  background: none; border: none; font-size: 20px; color: #6B7280; cursor: pointer;
}
.crop-area { height: 300px; background: #f3f4f6; }
.crop-actions { display: flex; gap: 12px; padding: 16px; }
.crop-btn {
  flex: 1; height: 44px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer;
}
.crop-btn--cancel {
  background: white; color: #374151; border: 1.5px solid #E5E7EB;
}
.crop-btn--confirm {
  background: #F0294E; color: white; border: none;
}
</style>
