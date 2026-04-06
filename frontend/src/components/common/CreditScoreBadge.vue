<template>
  <span :class="['credit-badge', levelClass]">
    {{ levelLabel }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  score: number
}>()

const levelClass = computed(() => {
  if (props.score >= 91) return 'credit-top'
  if (props.score >= 61) return 'credit-good'
  if (props.score >= 31) return 'credit-normal'
  return 'credit-low'
})

const levelLabel = computed(() => {
  if (props.score >= 91) return '頂級'
  if (props.score >= 61) return '優質'
  if (props.score >= 31) return '普通'
  return '受限'
})
</script>

<style scoped>
.credit-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 600;
  font-family: 'Inter', sans-serif;
  border: 1px solid transparent;
}

.credit-top {
  background: #fffbeb;
  color: #92400e;
  border-color: #fde68a;
}

.credit-good {
  background: #ecfdf5;
  color: #065f46;
  border-color: #a7f3d0;
}

.credit-normal {
  background: #eff6ff;
  color: #1e40af;
  border-color: #bfdbfe;
}

.credit-low {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fecaca;
}
</style>
