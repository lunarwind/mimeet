/**
 * mocks/users.ts
 * Sprint 3 測試用戶資料（50 筆）
 *
 * 分布規則：
 * - 地區：台北市x10、新北市x8、台中市x8、高雄市x8、桃園市x7、其他x9
 * - 性別：男25、女25
 * - 誠信等級：頂級x10、優質x15、普通x15、受限x10
 * - 驗證：全驗x10、Email+手機x15、僅Email x15、無驗證x10
 * - 線上：15 筆 online
 * - 收藏：10 筆 favorited
 */
import type { UserProfileData } from '@/api/users'
import type { ExploreUser } from '@/types/explore'

// ── 基礎資料池 ───────────────────────────────────────────
const MALE_NAMES = [
  '志明', '俊傑', '建宏', '家豪', '宗翰',
  '冠廷', '柏翰', '承恩', '宇軒', '品睿',
  '子軒', '浩然', '博文', '明哲', '銘澤',
  '嘉瑋', '彥廷', '國豪', '宏達', '育誠',
  '書豪', '哲瑋', '皓宇', '泓毅', '晨曦',
]

const FEMALE_NAMES = [
  '淑芬', '雅婷', '心怡', '佳穎', '詩涵',
  '宜蓁', '欣妤', '芷晴', '思彤', '語彤',
  '紫涵', '筱涵', '子晴', '品妍', '沛瑜',
  '羽彤', '芯瑜', '昀蓁', '沐恩', '芮安',
  '可薰', '依璇', '采潔', '映彤', '晴翎',
]

const CITIES_POOL = [
  ...Array(10).fill('台北市'),
  ...Array(8).fill('新北市'),
  ...Array(8).fill('台中市'),
  ...Array(8).fill('高雄市'),
  ...Array(7).fill('桃園市'),
  '新竹市', '嘉義市', '台南市', '宜蘭縣', '花蓮縣',
  '彰化縣', '苗栗縣', '屏東縣', '基隆市',
]

// 誠信分數分布（依序分配）
const CREDIT_SCORES = [
  // 頂級 x10
  98, 95, 93, 92, 96, 91, 97, 94, 99, 100,
  // 優質 x15
  88, 82, 75, 68, 71, 85, 63, 77, 89, 66, 73, 80, 61, 87, 69,
  // 普通 x15
  55, 42, 38, 50, 33, 48, 57, 35, 44, 59, 31, 52, 46, 40, 36,
  // 受限 x10
  28, 15, 22, 8, 25, 12, 18, 5, 20, 10,
]

// 驗證狀態分配
type VerifyLevel = 'all' | 'email_phone' | 'email_only' | 'none'
const VERIFY_LEVELS: VerifyLevel[] = [
  // 全驗 x10
  ...Array(10).fill('all' as VerifyLevel),
  // Email+手機 x15
  ...Array(15).fill('email_phone' as VerifyLevel),
  // 僅 Email x15
  ...Array(15).fill('email_only' as VerifyLevel),
  // 無驗證 x10
  ...Array(10).fill('none' as VerifyLevel),
]

// 線上狀態（前 15 筆 online）
const ONLINE_IDS = new Set([1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34, 37, 40, 43])

// 收藏狀態（10 筆）
const FAVORITED_IDS = new Set([2, 5, 11, 15, 20, 26, 33, 38, 42, 47])

const JOBS = [
  '軟體工程師', '設計師', '行銷企劃', '會計師', '醫師',
  '律師', '教師', '業務經理', '產品經理', '攝影師',
  '自營商', '護理師', '公務員', '金融分析師', '建築師',
  '藥師', '記者', '餐飲業者', '健身教練', '心理諮商師',
]

const EDUCATIONS = ['高中', '專科', '大學', '碩士', '博士']

const BIOS = [
  '喜歡旅遊和攝影，假日常常帶著相機到處走走。最喜歡的城市是京都，那裡的寧靜和古典美讓我百去不厭。希望找到一個也喜歡探索世界的伴侶，一起收集各地的風景。',
  '工作之餘喜歡下廚，從義式料理到台式小吃都能上手。朋友們都說我做的紅酒燉牛肉是一絕。希望遇到一個懂得享受生活、願意一起在廚房裡創造美好回憶的人。',
  '愛看電影和追劇，從漫威到文藝片都來者不拒。也喜歡閱讀，最近在看村上春樹的新作。週末偶爾去咖啡廳窩一個下午，安靜地享受獨處時光。',
  '運動是我的日常，每週固定跑步和重訓。去年完成了人生第一場半馬，今年目標是全馬。相信自律的人在感情上也會認真負責，希望找到志同道合的夥伴。',
  '音樂是我的靈魂，會彈吉他和鋼琴，偶爾在小酒吧表演。喜歡從爵士到獨立音樂的各種風格。期待找到一個也熱愛音樂的人，一起去音樂祭和 live house。',
  '在科技業工作，但骨子裡是個文青。收藏了很多黑膠唱片，最喜歡在下雨天聽爵士樂配一杯威士忌。想找一個能一起享受生活中小確幸的人。',
  '動物愛好者，家裡有兩隻貓主子。週末喜歡帶牠們曬太陽、去寵物友善餐廳。如果你也是貓奴或狗派，我們一定有很多話聊！',
  '熱愛大自然，爬山和潛水是我最愛的活動。已經征服了台灣百岳中的三十座，蘭嶼和綠島的海底世界也讓我流連忘返。找一個不怕曬的夥伴一起冒險吧！',
  '甜點控一枚，走到哪都要找當地最好吃的蛋糕店。也在學做法式甜點，馬卡龍已經可以端上桌了。找一個一起變胖的人，應該不過分吧？',
  '書蟲一枚，最近迷上歷史類書籍和 podcast。喜歡深入了解一件事的來龍去脈。覺得好的對話比外表更重要，希望找到能深度交流的對象。',
  '自由工作者，在家遠端工作。好處是時間彈性，壞處是社交圈比較小。來這裡希望認識新朋友，最好是那種能一起去探索巷弄小店的人。',
  '剛搬來這座城市不久，一切都還在摸索中。喜歡散步，常常沒有目的地就出門走走。如果你願意當我的城市導遊，我請你喝咖啡作為回報。',
  '白天上班族，晚上化身業餘畫家。最近在學水彩，用畫筆記錄每天的小確幸。相信有創意的靈魂能碰撞出美麗的火花，期待遇見你。',
  '瑜伽和冥想讓我找到內心的平靜，也讓我更懂得珍惜每一段關係。尋找一個心態成熟、懂得溝通的伴侶，一起成長。',
  '美食和旅行是我的兩大信仰。走過十幾個國家，最喜歡的是泰國和日本。每到一個地方一定要先吃當地的街邊小吃，那才是最道地的味道。',
  '熱愛戶外運動，衝浪、滑板、攀岩都有涉獵。覺得生活就是要不斷挑戰自己，不論是工作還是感情。找一個勇敢追夢的人一起前進。',
  '咖啡上癮者，手沖、義式、冷萃都喝，家裡的咖啡器材比廚具還多。夢想是有一天開一間自己的小咖啡店，找一個也喜歡咖啡的人聊聊？',
  '喜歡看 YouTube 學做菜和手工藝，最近在挑戰做陶藝。覺得用雙手創造東西是一件很療癒的事。想找一個也喜歡手作的朋友。',
  '夜貓子一枚，最有靈感的時候都是深夜。喜歡在安靜的夜晚寫寫東西或聽音樂。如果你也是夜貓子，我們或許能一起分享那份寧靜。',
  '科技宅但熱愛陽光，假日不是在程式碼裡就是在海邊。相信最好的感情是互相支持對方的興趣，即使我們喜歡的東西不一樣。',
  '舞蹈是我的表達方式，從街舞到國標都跳。覺得會跳舞的人特別有魅力，但更重要的是有一顆願意溝通和理解的心。',
  '園藝新手，陽台上種了二十幾盆植物，每天早上的第一件事就是跟它們打招呼。相信用心照顧一盆植物的人，也會用心對待一段感情。',
  '電競愛好者，但也懂得在螢幕前適時休息。週末喜歡和朋友打球或騎腳踏車。想找到一個能一起打遊戲也能一起出門的夥伴。',
  '喜歡嘗試各種新事物，最近在學日文和烏克麗麗。覺得人生就是要不斷學習，找一個有好奇心的伴侶，一起探索這個世界。',
  '文青外表下其實是個吃貨，IG 上九成都是食物照片。最近的目標是吃遍台北所有米其林餐廳，需要一個飯友嗎？',
  '個性隨和好相處，朋友都說我是很好的傾聽者。在忙碌的生活中，希望找到一個能互相傾訴、互相陪伴的人。',
  '復古風愛好者，收藏了很多二手傢俱和老件。假日最愛逛跳蚤市場和古董店，總覺得每件老東西都有它的故事。',
  '戲劇系畢業，現在在業餘劇團演出。表演讓我學會了同理心和表達，也讓我更珍惜真實的情感連結。',
  '環保主義者，盡量減少生活中的塑膠使用。自帶環保杯和購物袋已經是日常。希望另一半也能認同永續生活的理念。',
  '早起的人，最喜歡清晨六點的公園，空氣清新、人少安靜。覺得願意為你早起的人才是真的喜歡你，而我就是那個人。',
  '愛笑是我的特質，朋友都說跟我在一起不會無聊。相信正向的能量可以感染身邊的人，希望也能讓你每天都開心。',
  '極簡主義者，喜歡簡單乾淨的生活方式。物質上不追求太多，但精神上希望能遇到一個靈魂契合的人。',
  '手機攝影愛好者，相信好照片不一定需要好器材。喜歡捕捉日常生活中那些轉瞬即逝的美好瞬間。',
  '從小在南部長大，來北部工作幾年了還是改不了台灣國語腔。想念家鄉的小吃，也想念那種人與人之間的溫暖。',
  '喜歡看展覽和逛美術館，最近在追幾個台灣當代藝術家的作品。覺得一起看展是最好的約會方式之一。',
  '烘焙初學者，目前可以穩定輸出司康和磅蛋糕。給我一個下午和一堆食材，我就能變出一桌下午茶。',
  '登山和露營是我的紓壓方式，在山裡什麼都不想，只專注在呼吸和腳下的路。想找一個不怕蚊子、不怕髒的露營夥伴。',
  '前端工程師，白天寫程式碼，晚上寫歌詞。兩者看似不搭，但都是在用不同的語言表達想法。認真生活的人最帥最美。',
  '喜歡研究星座和塔羅，但不會因為對方是什麼星座就不交往。重要的是兩個人願不願意一起努力。你是什麼星座呢？',
  '週末市集常客，喜歡逛手作市集和農夫市集。用銅板價帶回一束花就能開心一整天，這樣簡單的快樂你懂嗎？',
  '最近在學做皮件，已經完成了第一個手工錢包。覺得慢工出細活這句話不只適用於手工藝，在感情中也是。',
  '熱愛街頭文化，從塗鴉到街舞到滑板。覺得街頭是最真實的地方，藝術不應該只待在美術館裡。',
  '喜歡小動物，夢想是退休後開一間動物庇護所。目前先從每個月捐款和假日當志工開始。愛護動物的人最善良。',
  '中醫藥學系畢業，現在在中藥房工作。對養生和食療很有研究，可以幫你調配專屬的四季茶飲。',
  '假日總在廚房實驗新料理，最近在挑戰各國的咖哩。從日式到印度到泰式，每一種都有不同的靈魂，就像每個人一樣。',
  '電影和貓是人生兩大支柱，最愛在沙發上窩著看電影，旁邊有貓就更完美了。尋找另一根支柱——願意一起窩著的你。',
  '公路旅行愛好者，趁著假日沿著海岸線一路開下去是最浪漫的事。車上放著好聽的歌，副駕駛座留給你。',
  '熱愛桌遊和密室逃脫，覺得一起動腦的活動最能看出兩個人合不合拍。你敢接受挑戰嗎？',
  '練了三年的拳擊，不是因為好鬥，是因為它教會我專注和紀律。生活中我其實是很溫柔的人，只是偶爾需要出拳紓壓。',
  '喜歡夜景和城市的光，每個城市最讓我著迷的都是它的夜晚。想找一個願意陪我看遍世界夜景的人。',
]

// ── 用確定性洗牌打散城市（用 seed 保持穩定順序） ──────────
function seededShuffle<T>(arr: T[], seed: number): T[] {
  const result = [...arr]
  let s = seed
  for (let i = result.length - 1; i > 0; i--) {
    s = (s * 16807 + 0) % 2147483647
    const j = s % (i + 1)
    ;[result[i], result[j]] = [result[j], result[i]]
  }
  return result
}

const shuffledCities = seededShuffle(CITIES_POOL, 42)
const shuffledCredits = seededShuffle(CREDIT_SCORES, 77)
const shuffledVerify = seededShuffle(VERIFY_LEVELS, 13)

// ── 產生 50 筆用戶 ───────────────────────────────────────
function buildMockUsers(): ExploreUser[] {
  const users: ExploreUser[] = []

  for (let i = 0; i < 50; i++) {
    const id = i + 1
    const isMale = i < 25
    const nickname = isMale ? MALE_NAMES[i] : FEMALE_NAMES[i - 25]
    const verifyLevel = shuffledVerify[i]

    users.push({
      id,
      nickname,
      age: 20 + (i % 28),                            // 20~47 歲
      location: shuffledCities[i],
      avatar: `https://i.pravatar.cc/150?img=${id}`,
      creditScore: shuffledCredits[i],
      isOnline: ONLINE_IDS.has(id),
      lastActiveAt: ONLINE_IDS.has(id)
        ? new Date().toISOString()
        : new Date(Date.now() - (1 + (i % 14)) * 3600000).toISOString(),
      emailVerified:    verifyLevel !== 'none',
      phoneVerified:    verifyLevel === 'all' || verifyLevel === 'email_phone',
      advancedVerified: verifyLevel === 'all',
      membershipLevel:  verifyLevel === 'all' ? 2 : 1,
      isFavorited: FAVORITED_IDS.has(id),
    })
  }

  return users
}

export const MOCK_USERS: ExploreUser[] = buildMockUsers()

// ── Mock 搜尋（含完整篩選與分頁） ────────────────────────
export function mockSearchUsers(
  params: Record<string, unknown>,
): { users: ExploreUser[]; pagination: { current_page: number; per_page: number; total: number; total_pages: number } } {
  let filtered = [...MOCK_USERS]

  // nickname 搜尋
  const nickname = params.nickname as string | undefined
  if (nickname) {
    filtered = filtered.filter(u => u.nickname.includes(nickname))
  }

  // gender
  const gender = params.gender as string | undefined
  if (gender && gender !== 'all') {
    const isMale = gender === 'male'
    filtered = filtered.filter(u => (u.id <= 25) === isMale)
  }

  // location
  const location = params.location as string | undefined
  if (location) {
    if (location === '其他縣市') {
      const mainCities = ['台北市', '新北市', '台中市', '高雄市', '桃園市']
      filtered = filtered.filter(u => !mainCities.includes(u.location))
    } else {
      // 支援「台北」匹配「台北市」
      filtered = filtered.filter(u => u.location.startsWith(location))
    }
  }

  // age_min / age_max
  const ageMin = params.age_min as number | undefined
  const ageMax = params.age_max as number | undefined
  if (ageMin != null) filtered = filtered.filter(u => u.age >= ageMin)
  if (ageMax != null) filtered = filtered.filter(u => u.age <= ageMax)

  // credit_score_min / credit_score_max
  const csMin = params.credit_score_min as number | undefined
  const csMax = params.credit_score_max as number | undefined
  if (csMin != null) filtered = filtered.filter(u => u.creditScore >= csMin)
  if (csMax != null) filtered = filtered.filter(u => u.creditScore <= csMax)

  // last_online
  const lastOnline = params.last_online as string | undefined
  if (lastOnline && lastOnline !== 'all') {
    const now = Date.now()
    const dayMap: Record<string, number> = { today: 1, '3days': 3, '7days': 7 }
    const days = dayMap[lastOnline] ?? 999
    const cutoff = now - days * 86400000
    filtered = filtered.filter(u =>
      u.isOnline || (u.lastActiveAt && new Date(u.lastActiveAt).getTime() >= cutoff)
    )
  }

  // sort（預設 credit_score desc）
  const sortDir = (params.sort_direction as string) === 'asc' ? 1 : -1
  filtered.sort((a, b) => (a.creditScore - b.creditScore) * sortDir)

  // pagination
  const page = (params.page as number) ?? 1
  const perPage = (params.per_page as number) ?? 20
  const total = filtered.length
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const start = (page - 1) * perPage
  const paged = filtered.slice(start, start + perPage)

  return {
    users: paged,
    pagination: { current_page: page, per_page: perPage, total, total_pages: totalPages },
  }
}

// ── Mock 個人資料 ─────────────────────────────────────────
export function mockFetchUserProfile(userId: number): UserProfileData | null {
  const user = MOCK_USERS.find(u => u.id === userId)
  if (!user) return null

  const idx = userId - 1
  const isMale = userId <= 25

  return {
    id: user.id,
    nickname: user.nickname,
    age: user.age,
    gender: isMale ? 'male' : 'female',
    location: user.location,
    avatar: user.avatar,
    credit_score: user.creditScore,
    membership_level: user.membershipLevel,
    introduction: BIOS[idx % BIOS.length],
    height: 155 + (idx % 35),
    weight: 45 + (idx % 40),
    job: JOBS[idx % JOBS.length],
    education: EDUCATIONS[idx % EDUCATIONS.length],
    photos: [
      { id: 1, url: `https://picsum.photos/seed/user${userId}a/400/400`, is_avatar: true, order: 0 },
      { id: 2, url: `https://picsum.photos/seed/user${userId}b/400/400`, is_avatar: false, order: 1 },
      { id: 3, url: `https://picsum.photos/seed/user${userId}c/400/400`, is_avatar: false, order: 2 },
      { id: 4, url: `https://picsum.photos/seed/user${userId}d/400/400`, is_avatar: false, order: 3 },
    ],
    verification_status: {
      email_verified: user.emailVerified,
      phone_verified: user.phoneVerified,
      verified: user.advancedVerified,
      credit_card_verified: isMale && user.advancedVerified,
    },
    online_status: user.isOnline ? 'online' : 'offline',
    last_active_at: user.lastActiveAt,
    is_favorited: user.isFavorited,
    is_blocked: false,
    stats: {
      profile_views: 50 + (idx * 7) % 300,
      messages_received: 5 + (idx * 3) % 80,
      likes_received: 10 + (idx * 5) % 150,
    },
    created_at: new Date(Date.now() - (30 + idx) * 86400000).toISOString(),
  }
}
