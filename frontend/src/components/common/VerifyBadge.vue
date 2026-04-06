<script setup lang="ts">
interface Props {
  type: 'email' | 'phone' | 'advanced'
  verified: boolean
}

const props = defineProps<Props>()

const config = {
  email:    { label: 'Email',    icon: '✉', verifiedClass: 'badge--email' },
  phone:    { label: '手機',     icon: '☎', verifiedClass: 'badge--phone' },
  advanced: { label: '進階驗證', icon: '✓', verifiedClass: 'badge--advanced' },
} as const
</script>

<template>
  <span
    class="verify-badge"
    :class="[
      props.verified ? config[props.type].verifiedClass : 'badge--unverified',
    ]"
    :title="props.verified ? `${config[props.type].label} 已驗證` : `${config[props.type].label} 未驗證`"
    :aria-label="props.verified ? `${config[props.type].label} 已驗證` : `${config[props.type].label} 未驗證`"
  >
    <span class="verify-badge__icon" aria-hidden="true">{{ config[props.type].icon }}</span>
    <span class="verify-badge__label">{{ config[props.type].label }}</span>
  </span>
</template>

<style scoped>
.verify-badge {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 10px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
  line-height: 1.4;
}

.badge--email {
  background: #EFF6FF;
  color: #3B82F6;
}

.badge--phone {
  background: #F0FDF4;
  color: #22C55E;
}

.badge--advanced {
  background: #FFF7ED;
  color: #F97316;
}

.badge--unverified {
  background: #F1F5F9;
  color: #94A3B8;
}
</style>
