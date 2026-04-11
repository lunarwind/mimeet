import type { MemberListItem, MemberDetail, ScoreRecord, SubscriptionRecord, Ticket, PaymentRecord } from '../types/admin'

const MALE_NAMES = [
  '志明', '俊傑', '建宏', '家豪', '宗翰', '冠廷', '柏翰', '承恩', '宇軒', '品睿',
  '子軒', '浩然', '博文', '明哲', '銘澤', '嘉瑋', '彥廷', '國豪', '宏達', '育誠',
  '書豪', '哲瑋', '皓宇', '泓毅', '晨曦',
]
const FEMALE_NAMES = [
  '淑芬', '雅婷', '心怡', '佳穎', '詩涵', '宜蓁', '欣妤', '芷晴', '思彤', '語彤',
  '紫涵', '筱涵', '子晴', '品妍', '沛瑜', '羽彤', '芯瑜', '昀蓁', '沐恩', '芮安',
  '可薰', '依璇', '采潔', '映彤', '晴翎',
]
const CITIES = ['台北市', '新北市', '台中市', '高雄市', '桃園市', '新竹市', '嘉義市', '台南市', '宜蘭縣', '花蓮縣']
const JOBS = ['軟體工程師', '設計師', '行銷企劃', '會計師', '醫師', '律師', '教師', '業務經理', '產品經理', '攝影師']
const EDUCATIONS = ['高中', '專科', '大學', '碩士', '博士']

const CREDIT_SCORES = [
  98, 95, 93, 92, 96, 91, 97, 94, 99, 100,
  88, 82, 75, 68, 71, 85, 63, 77, 89, 66, 73, 80, 61, 87, 69,
  55, 42, 38, 50, 33, 48, 57, 35, 44, 59, 31, 52, 46, 40, 36,
  28, 15, 22, 8, 25, 12, 18, 5, 20, 10,
]

function buildMembers(): MemberListItem[] {
  const members: MemberListItem[] = []
  for (let i = 0; i < 50; i++) {
    const id = i + 1
    const isMale = i < 25
    const nickname = isMale ? MALE_NAMES[i] : FEMALE_NAMES[i - 25]
    const score = CREDIT_SCORES[i]
    const level = score >= 91 ? 3 : score >= 61 ? 2 : 1
    const levelLabels: Record<number, string> = { 1: '基本會員', 2: '進階驗證會員', 3: '付費會員' }
    members.push({
      uid: id,
      nickname,
      gender: isMale ? 'male' : 'female',
      age: 20 + (i % 28),
      avatar: `https://i.pravatar.cc/150?img=${id}`,
      credit_score: score,
      level,
      level_label: levelLabels[level] || '基本會員',
      location: CITIES[i % CITIES.length],
      email: `user${id}@example.com`,
      phone_last4: String(1000 + id).slice(-4),
      status: i === 49 ? 'suspended' : 'active',
      email_verified: i < 40,
      phone_verified: i < 30,
      advanced_verified: i < 10,
      last_login_at: new Date(Date.now() - (i * 3600000)).toISOString(),
      registered_at: new Date(Date.now() - (30 + i) * 86400000).toISOString(),
      profile_views: 50 + (i * 7) % 300,
    })
  }
  return members
}

export const MOCK_MEMBERS = buildMembers()

export function getMemberDetail(uid: number): MemberDetail | null {
  const m = MOCK_MEMBERS.find((x) => x.uid === uid)
  if (!m) return null
  const idx = uid - 1
  return {
    ...m,
    introduction: '這是一段個人簡介，喜歡旅遊和攝影。',
    height: 155 + (idx % 35),
    weight: 45 + (idx % 40),
    job: JOBS[idx % JOBS.length],
    education: EDUCATIONS[idx % EDUCATIONS.length],
    birth_date: `${2004 - m.age}-${String((idx % 12) + 1).padStart(2, '0')}-15`,
    photos: [
      { id: 1, url: `https://picsum.photos/seed/user${uid}a/400/400`, is_avatar: true, order: 0 },
      { id: 2, url: `https://picsum.photos/seed/user${uid}b/400/400`, is_avatar: false, order: 1 },
    ],
    membership_level: m.level,
    verification_status: {
      email_verified: m.email_verified,
      phone_verified: m.phone_verified,
      verified: m.advanced_verified,
      credit_card_verified: m.gender === 'male' && m.advanced_verified,
    },
  }
}

export function getMemberScoreRecords(_uid: number): ScoreRecord[] {
  const records: ScoreRecord[] = []
  const events = [
    { delta: 10, reason: 'Email 驗證完成', operator: '系統' },
    { delta: 10, reason: '手機驗證完成', operator: '系統' },
    { delta: 5, reason: 'QR 約會驗證成功', operator: '系統' },
    { delta: -10, reason: '被檢舉（一般）', operator: '系統' },
    { delta: 15, reason: '管理員手動調整', operator: 'admin@mimeet.tw' },
    { delta: -5, reason: '爽約扣分', operator: '系統' },
  ]
  for (let i = 0; i < 8; i++) {
    const evt = events[i % events.length]
    records.push({
      id: i + 1,
      delta: evt.delta,
      reason: evt.reason,
      operator: evt.operator,
      created_at: new Date(Date.now() - (i * 2 * 86400000)).toISOString(),
    })
  }
  return records
}

export function getMemberSubscriptions(uid: number): SubscriptionRecord[] {
  const m = MOCK_MEMBERS.find((x) => x.uid === uid)
  if (!m || m.level < 3) return []
  return [
    {
      id: 1,
      plan: '月費方案',
      amount: 499,
      status: 'active',
      started_at: new Date(Date.now() - 15 * 86400000).toISOString(),
      expires_at: new Date(Date.now() + 15 * 86400000).toISOString(),
    },
  ]
}

// Mock tickets
export function buildMockTickets(): Ticket[] {
  const tickets: Ticket[] = []
  const types: Array<{ type: 1 | 2 | 3; label: string }> = [
    { type: 1, label: '一般檢舉' },
    { type: 2, label: '系統問題' },
    { type: 3, label: '匿名聊天室檢舉' },
  ]
  const titles = [
    '對方傳送騷擾訊息', '頭像照片不當', '網站登入異常', '疑似詐騙帳號', '聊天室言語騷擾',
    '無法上傳照片', '約會對象未出現', '個人資料顯示錯誤', '付款後未升級', '收到不當內容',
  ]
  for (let i = 0; i < 15; i++) {
    const t = types[i % types.length]
    const status = (i < 5 ? 1 : i < 10 ? 2 : 3) as 1 | 2 | 3
    const statusLabels: Record<number, string> = { 1: '待處理', 2: '處理中', 3: '已結案' }
    tickets.push({
      id: i + 1,
      ticket_number: `REPORT-20260408-${String(i + 1).padStart(5, '0')}`,
      type: t.type,
      type_label: t.label,
      title: titles[i % titles.length],
      content: `這是案件 #${i + 1} 的詳細描述內容，用戶回報了相關問題。`,
      reporter: { uid: (i % 25) + 1, nickname: MALE_NAMES[i % 25], avatar: `https://i.pravatar.cc/150?img=${(i % 25) + 1}` },
      reported_user: t.type !== 2 ? { uid: (i % 25) + 26, nickname: FEMALE_NAMES[i % 25], avatar: `https://i.pravatar.cc/150?img=${(i % 25) + 26}` } : null,
      status,
      status_label: statusLabels[status],
      admin_reply: status === 3 ? '經查證屬實，已對違規用戶進行處理' : null,
      images: i % 3 === 0 ? [`https://picsum.photos/seed/report${i}/400/300`] : [],
      created_at: new Date(Date.now() - i * 86400000).toISOString(),
      updated_at: new Date(Date.now() - i * 43200000).toISOString(),
    })
  }
  return tickets
}

export const MOCK_TICKETS = buildMockTickets()

// Mock payments
export function buildMockPayments(): PaymentRecord[] {
  const payments: PaymentRecord[] = []
  const plans = ['月費方案', '季費方案', '年費方案', '體驗方案']
  const amounts = [499, 1299, 4499, 199]
  const statuses: Array<'paid' | 'failed' | 'refunded'> = ['paid', 'paid', 'paid', 'paid', 'failed', 'refunded']
  for (let i = 0; i < 30; i++) {
    const planIdx = i % plans.length
    const s = statuses[i % statuses.length]
    payments.push({
      id: i + 1,
      order_number: `ORD2026040${String(i + 1).padStart(4, '0')}`,
      user: { uid: (i % 50) + 1, nickname: i < 25 ? MALE_NAMES[i % 25] : FEMALE_NAMES[i % 25] },
      payment_type: 'subscription',
      plan: plans[planIdx],
      amount: amounts[planIdx],
      amount_paid: s === 'refunded' ? 0 : amounts[planIdx],
      payment_method: 'Credit',
      status: s,
      paid_at: new Date(Date.now() - i * 86400000).toISOString(),
    })
  }
  return payments
}

export const MOCK_PAYMENTS = buildMockPayments()
