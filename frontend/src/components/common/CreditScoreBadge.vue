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
  background: #FFFBEB;
  color: #92400E;
  border-color: #FDE68A;
}

.credit-good {
  background: #ECFDF5;
  color: #065F46;
  border-color: #A7F3D0;
}

.credit-normal {
  background: #EFF6FF;
  color: #1E40AF;
  border-color: #BFDBFE;
}

.credit-low {
  background: #FEF2F2;
  color: #991B1B;
  border-color: #FECACA;
}
</style>
