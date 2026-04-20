<template>
  <RouterView />
  <Toast />
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import Toast from '@/components/common/Toast.vue'
import { useIdleTimeout } from '@/composables/useIdleTimeout'
import { initTracking } from '@/utils/tracking'

useIdleTimeout()

onMounted(() => {
  initTracking()
})
</script>
