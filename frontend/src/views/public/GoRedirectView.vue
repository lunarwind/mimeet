<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import client from '@/api/client'

const route = useRoute()

onMounted(async () => {
  const slug = route.params.slug as string
  try {
    // The backend will redirect, but in case of SPA we handle it
    const res = await client.get(`/go/${slug}`)
    if (res.data?.target_url) {
      window.location.href = res.data.target_url
    }
  } catch {
    // If 404 or error, redirect to landing
    window.location.href = '/'
  }
})
</script>

<template>
  <div style="display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Noto Sans TC', sans-serif;">
    <p style="color: #6B7280;">正在跳轉...</p>
  </div>
</template>
