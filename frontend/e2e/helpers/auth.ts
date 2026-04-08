import { Page } from '@playwright/test'

const AUTH_TOKEN_KEY = 'auth_token'
const MEMBER_LEVEL_KEY = 'member_level'
const IS_SUSPENDED_KEY = 'is_suspended'
const DEV_IDENTITY_KEY = 'dev_identity_key'

function makeMockUser(level: 0 | 1 | 2 | 3, status = 'active') {
  return {
    id: 9000 + level,
    nickname: `E2E測試Lv${level}`,
    gender: 'male',
    membership_level: level,
    credit_score: [0, 65, 78, 95][level],
    status,
    email: `e2e-lv${level}@mimeet.tw`,
    avatar: null,
    verified: String(level),
  }
}

export async function mockAuthUser(page: Page, level: 1 | 2 | 3 = 3) {
  const user = makeMockUser(level)
  await page.addInitScript(({ keys, token, user, level }) => {
    localStorage.setItem(keys.token, token)
    localStorage.setItem(keys.level, String(level))
    localStorage.setItem(keys.suspended, 'false')
    localStorage.setItem(keys.identity, `lv${level}`)
  }, {
    keys: { token: AUTH_TOKEN_KEY, level: MEMBER_LEVEL_KEY, suspended: IS_SUSPENDED_KEY, identity: DEV_IDENTITY_KEY },
    token: `mock-e2e-lv${level}`,
    user,
    level,
  })
}

export async function mockSuspendedUser(page: Page) {
  await page.addInitScript(({ keys }) => {
    localStorage.setItem(keys.token, 'mock-suspended')
    localStorage.setItem(keys.level, '0')
    localStorage.setItem(keys.suspended, 'true')
    localStorage.setItem(keys.identity, 'suspended')
  }, {
    keys: { token: AUTH_TOKEN_KEY, level: MEMBER_LEVEL_KEY, suspended: IS_SUSPENDED_KEY, identity: DEV_IDENTITY_KEY },
  })
}

export async function clearAuth(page: Page) {
  await page.addInitScript(() => localStorage.clear())
}

export async function mockApiResponse(
  page: Page,
  pattern: string | RegExp,
  body: unknown,
  status = 200,
) {
  await page.route(pattern, (route) =>
    route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) }),
  )
}
