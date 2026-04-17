<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

withDefaults(defineProps<{
  variant?: 'light' | 'dark'
  size?: 'sm' | 'md' | 'lg'
  clickable?: boolean
}>(), {
  variant: 'light',
  size: 'md',
  clickable: false,
})

const router = useRouter()
const auth = useAuthStore()

function handleClick() {
  if (auth.isLoggedIn && auth.user?.email_verified) {
    router.push('/app/explore')
  } else {
    router.push('/login')
  }
}
</script>

<template>
  <span
    class="mimeet-logo"
    :class="[`mimeet-logo--${size}`, `mimeet-logo--${variant}`, { 'mimeet-logo--clickable': clickable }]"
    @click="clickable && handleClick()"
  >
    <span class="mimeet-logo__mi">Mi</span><span class="mimeet-logo__meet">Meet</span>
  </span>
</template>

<style scoped>
.mimeet-logo {
  font-family: 'Noto Serif TC', serif;
  font-weight: 600;
  letter-spacing: -0.5px;
  display: inline-flex;
  align-items: baseline;
  line-height: 1;
}
.mimeet-logo--sm  { font-size: 18px; }
.mimeet-logo--md  { font-size: 22px; }
.mimeet-logo--lg  { font-size: 32px; }
.mimeet-logo__mi  { color: #F0294E; }
.mimeet-logo--light .mimeet-logo__meet { color: #111827; }
.mimeet-logo--dark  .mimeet-logo__meet { color: #FFFFFF; }
.mimeet-logo--clickable { cursor: pointer; }
</style>
