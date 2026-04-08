import { test, expect } from '@playwright/test'
import { mockAuthUser, mockApiResponse } from './helpers/auth'

test.describe('Profile Page', () => {
  test('Lv3 user can view a profile page', async ({ page }) => {
    await mockAuthUser(page, 3)
    await mockApiResponse(page, '**/api/v1/users/1**', {
      success: true,
      data: {
        user: {
          id: 1, nickname: '測試用戶', age: 25, location: '台北市',
          credit_score: 80, avatar: null, gender: 'female',
          membership_level: 2, bio: '大家好',
        },
      },
    })
    await mockApiResponse(page, '**/api/v1/auth/me**', {
      success: true,
      data: { user: { id: 9003, nickname: 'E2E測試Lv3', membership_level: 3, credit_score: 95, status: 'active' } },
    })

    await page.goto('/#/app/profiles/1')
    await page.waitForTimeout(2000)

    const url = page.url()
    expect(url).toContain('/app/profiles/1')
  })

  test('Lv1 user accessing messages is restricted', async ({ page }) => {
    await mockAuthUser(page, 1)
    await mockApiResponse(page, '**/api/v1/auth/me**', {
      success: true,
      data: { user: { id: 9001, nickname: 'E2E測試Lv1', membership_level: 1, credit_score: 65, status: 'active' } },
    })

    await page.goto('/#/app/messages')
    await page.waitForTimeout(1500)

    const url = page.url()
    // Lv1 should be redirected to /app/shop (minLevel: 2 required for messages)
    expect(url).not.toContain('/app/messages')
  })
})
