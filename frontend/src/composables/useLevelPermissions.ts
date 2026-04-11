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

export function useLevelPermissions() {
  const authStore = useAuthStore()

  const level = computed(() => {
    const ml = authStore.user?.membership_level ?? 0
    return ml
  })

  const permissions = computed<LevelPermissions>(() => {
    const l = level.value
    // Find exact match or nearest lower level
    if (PERMISSION_MAP[l]) return PERMISSION_MAP[l]!
    const levels = Object.keys(PERMISSION_MAP).map(Number).sort((a, b) => b - a)
    const match = levels.find(k => k <= l) ?? 0
    return PERMISSION_MAP[match]!
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
  }
}
