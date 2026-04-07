/**
 * mocks/dates.ts
 * Sprint 4 約會 Mock 資料
 */
import type { DateInvitation } from '@/types/chat'

const now = Date.now()

export const MOCK_DATES: DateInvitation[] = [
  {
    id: 1, inviterId: 97, inviteeId: 1,
    inviterNickname: '測試會員C', inviteeNickname: '志明',
    inviterAvatar: null, inviteeAvatar: 'https://i.pravatar.cc/150?img=1',
    status: 'pending',
    scheduledAt: new Date(now + 48 * 3600000).toISOString(),
    location: '台北101 美食街',
    qrToken: null, expiresAt: null, creditScoreChange: null,
    createdAt: new Date(now - 2 * 3600000).toISOString(),
  },
  {
    id: 2, inviterId: 26, inviteeId: 97,
    inviterNickname: '淑芬', inviteeNickname: '測試會員C',
    inviterAvatar: 'https://i.pravatar.cc/150?img=26', inviteeAvatar: null,
    status: 'pending',
    scheduledAt: new Date(now + 72 * 3600000).toISOString(),
    location: '信義誠品 B1 咖啡廳',
    qrToken: null, expiresAt: null, creditScoreChange: null,
    createdAt: new Date(now - 5 * 3600000).toISOString(),
  },
  {
    id: 3, inviterId: 97, inviteeId: 3,
    inviterNickname: '測試會員C', inviteeNickname: '建宏',
    inviterAvatar: null, inviteeAvatar: 'https://i.pravatar.cc/150?img=3',
    status: 'accepted',
    scheduledAt: new Date(now + 1.5 * 3600000).toISOString(),
    location: '大安森林公園 入口',
    qrToken: 'mock-qr-token-003',
    expiresAt: new Date(now + 2 * 3600000).toISOString(),
    creditScoreChange: null,
    createdAt: new Date(now - 24 * 3600000).toISOString(),
  },
  {
    id: 4, inviterId: 27, inviteeId: 97,
    inviterNickname: '雅婷', inviteeNickname: '測試會員C',
    inviterAvatar: 'https://i.pravatar.cc/150?img=27', inviteeAvatar: null,
    status: 'accepted',
    scheduledAt: new Date(now + 26 * 3600000).toISOString(),
    location: '中山站 地下街',
    qrToken: 'mock-qr-token-004',
    expiresAt: new Date(now + 27 * 3600000).toISOString(),
    creditScoreChange: null,
    createdAt: new Date(now - 48 * 3600000).toISOString(),
  },
  {
    id: 5, inviterId: 97, inviteeId: 4,
    inviterNickname: '測試會員C', inviteeNickname: '家豪',
    inviterAvatar: null, inviteeAvatar: 'https://i.pravatar.cc/150?img=4',
    status: 'verified',
    scheduledAt: new Date(now - 48 * 3600000).toISOString(),
    location: '西門町 萬年大樓',
    qrToken: null, expiresAt: null,
    creditScoreChange: 5,
    createdAt: new Date(now - 96 * 3600000).toISOString(),
  },
  {
    id: 6, inviterId: 28, inviteeId: 97,
    inviterNickname: '心怡', inviteeNickname: '測試會員C',
    inviterAvatar: 'https://i.pravatar.cc/150?img=28', inviteeAvatar: null,
    status: 'verified',
    scheduledAt: new Date(now - 120 * 3600000).toISOString(),
    location: '華山文創園區',
    qrToken: null, expiresAt: null,
    creditScoreChange: 2,
    createdAt: new Date(now - 168 * 3600000).toISOString(),
  },
]

export function mockFetchDates(): DateInvitation[] {
  return [...MOCK_DATES]
}
