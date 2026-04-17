<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'light' | 'dark'
  size?: 'sm' | 'md' | 'lg'
}>(), {
  variant: 'light',
  size: 'md',
})
</script>

<template>
  <span class="mimeet-logo" :class="[`mimeet-logo--${size}`, `mimeet-logo--${variant}`]">
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
</style>
