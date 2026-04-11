<<<<<<< HEAD
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

interface LevelPermissions {
  canBrowseExplore: boolean
  canBasicSearch: boolean
  canViewProfiles: boolean
  canSendMessages: boolean
  canSendDateInvite: boolean
  canViewVisitors: boolean
  canUseStealthMode: boolean
  canSeeReadReceipts: boolean
  canPostContent: boolean
  dailyMessageLimit: number
}

const PERMISSION_MAP: Record<number, LevelPermissions> = {
  0: { canBrowseExplore: true, canBasicSearch: true, canViewProfiles: true, canSendMessages: false, canSendDateInvite: false, canViewVisitors: false, canUseStealthMode: false, canSeeReadReceipts: false, canPostContent: false, dailyMessageLimit: 5 },
  1: { canBrowseExplore: true, canBasicSearch: true, canViewProfiles: true, canSendMessages: false, canSendDateInvite: false, canViewVisitors: false, canUseStealthMode: false, canSeeReadReceipts: false, canPostContent: false, dailyMessageLimit: 10 },
  1.5: { canBrowseExplore: true, canBasicSearch: true, canViewProfiles: true, canSendMessages: true, canSendDateInvite: false, canViewVisitors: false, canUseStealthMode: false, canSeeReadReceipts: false, canPostContent: false, dailyMessageLimit: 20 },
  2: { canBrowseExplore: true, canBasicSearch: true, canViewProfiles: true, canSendMessages: true, canSendDateInvite: true, canViewVisitors: false, canUseStealthMode: false, canSeeReadReceipts: false, canPostContent: true, dailyMessageLimit: 30 },
  3: { canBrowseExplore: true, canBasicSearch: true, canViewProfiles: true, canSendMessages: true, canSendDateInvite: true, canViewVisitors: true, canUseStealthMode: true, canSeeReadReceipts: true, canPostContent: true, dailyMessageLimit: 999 },
}
=======
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
>>>>>>> develop

export function useLevelPermissions() {
  const authStore = useAuthStore()

<<<<<<< HEAD
  const level = computed(() => {
    const ml = authStore.user?.membership_level ?? 0
    return ml
  })

  const permissions = computed<LevelPermissions>(() => {
    const l = level.value
    // Find exact match or nearest lower level
    if (PERMISSION_MAP[l]) return PERMISSION_MAP[l]
    const levels = Object.keys(PERMISSION_MAP).map(Number).sort((a, b) => b - a)
    const match = levels.find(k => k <= l) ?? 0
    return PERMISSION_MAP[match]
  })

  return {
    level,
    permissions,
    canSendMessages: computed(() => permissions.value.canSendMessages),
    canSendDateInvite: computed(() => permissions.value.canSendDateInvite),
    canViewVisitors: computed(() => permissions.value.canViewVisitors),
    canUseStealthMode: computed(() => permissions.value.canUseStealthMode),
    canSeeReadReceipts: computed(() => permissions.value.canSeeReadReceipts),
    canPostContent: computed(() => permissions.value.canPostContent),
    dailyMessageLimit: computed(() => permissions.value.dailyMessageLimit),
=======
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
>>>>>>> develop
  }
}
