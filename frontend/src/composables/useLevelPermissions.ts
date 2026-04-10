import { ref, computed, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface LevelPermission {
  level: number
  feature_key: string
  enabled: boolean
  value: string | null
}

const permissions = ref<LevelPermission[]>([])
const loaded = ref(false)

export function useLevelPermissions() {
  const authStore = useAuthStore()

  const userLevel = computed(() => authStore.user?.membership_level ?? 0)

  async function loadPermissions() {
    if (loaded.value) return
    try {
      // Public endpoint for frontend to read permissions
      const res = await apiClient.get('/admin/settings/member-level-permissions')
      permissions.value = res.data.data.permissions
      loaded.value = true
    } catch {
      // Fallback: use hardcoded defaults if API unavailable
      loaded.value = true
    }
  }

  function isFeatureEnabled(featureKey: string): boolean {
    const level = userLevel.value
    const perm = permissions.value.find(
      p => Number(p.level) === level && p.feature_key === featureKey
    )
    return perm?.enabled ?? false
  }

  function getFeatureValue(featureKey: string): string | null {
    const level = userLevel.value
    const perm = permissions.value.find(
      p => Number(p.level) === level && p.feature_key === featureKey
    )
    if (!perm?.enabled) return null
    return perm.value
  }

  const dailyMessageLimit = computed(() => {
    const val = getFeatureValue('daily_message_limit')
    if (val === null) return 0
    const num = parseInt(val, 10)
    return num === 0 ? Infinity : num // 0 = unlimited
  })

  const canPostMoment = computed(() => isFeatureEnabled('post_moment'))
  const canUseQrDate = computed(() => isFeatureEnabled('qr_date'))
  const canBroadcast = computed(() => isFeatureEnabled('broadcast'))
  const canAdvancedSearch = computed(() => isFeatureEnabled('advanced_search'))
  const canViewFullProfile = computed(() => isFeatureEnabled('view_full_profile'))
  const hasReadReceipt = computed(() => isFeatureEnabled('read_receipt'))
  const hasVipInvisible = computed(() => isFeatureEnabled('vip_invisible'))

  onMounted(() => {
    loadPermissions()
  })

  return {
    permissions,
    isFeatureEnabled,
    getFeatureValue,
    dailyMessageLimit,
    canPostMoment,
    canUseQrDate,
    canBroadcast,
    canAdvancedSearch,
    canViewFullProfile,
    hasReadReceipt,
    hasVipInvisible,
    loadPermissions,
  }
}
