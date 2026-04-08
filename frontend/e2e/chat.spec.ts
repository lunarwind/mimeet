import { test, expect } from '@playwright/test'
import { mockAuthUser, mockApiResponse } from './helpers/auth'

test.describe('Chat / Messages', () => {
  test('Lv3 user can access messages page', async ({ page }) => {
    await mockAuthUser(page, 3)
    await mockApiResponse(page, '**/api/v1/chats**', {
      success: true,
      data: { chats: [] },
    })
    await mockApiResponse(page, '**/api/v1/auth/me**', {
      success: true,
      data: { user: { id: 9003, nickname: 'E2E測試Lv3', membership_level: 3, credit_score: 95, status: 'active' } },
    })

    await page.goto('/#/app/messages')
    await page.waitForTimeout(2000)

    const url = page.url()
    expect(url).toContain('/app/messages')
  })

  test('Lv1 user is redirected from messages page', async ({ page }) => {
    await mockAuthUser(page, 1)
    await mockApiResponse(page, '**/api/v1/auth/me**', {
      success: true,
      data: { user: { id: 9001, nickname: 'E2E測試Lv1', membership_level: 1, credit_score: 65, status: 'active' } },
    })

    await page.goto('/#/app/messages')
    await page.waitForTimeout(1500)

    const url = page.url()
    // Should be redirected to shop (membership required)
    expect(url).not.toContain('/app/messages')
  })
})
