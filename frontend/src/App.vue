<template>
  <RouterView />
  <Toast />
</template>

<script setup lang="ts">
import Toast from '@/components/common/Toast.vue'
import { useIdleTimeout } from '@/composables/useIdleTimeout'

useIdleTimeout()
</script>
