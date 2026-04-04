<template>
  <div class="public-layout">
    <slot />
  </div>
</template>

<script setup lang="ts"></script>

<style scoped>
.public-layout {
  min-height: 100dvh;
  background: white;
}
</style>
