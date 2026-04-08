import { test, expect } from '@playwright/test'
import { mockAuthUser, mockApiResponse } from './helpers/auth'

test.describe('Explore Page', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuthUser(page, 3)
    // Mock API responses to avoid backend dependency
    await mockApiResponse(page, '**/api/v1/users/search**', {
      success: true,
      data: { users: [] },
      pagination: { current_page: 1, per_page: 20, total: 0 },
    })
    await mockApiResponse(page, '**/api/v1/auth/me**', {
      success: true,
      data: { user: { id: 9003, nickname: 'E2E測試Lv3', membership_level: 3, credit_score: 95, status: 'active' } },
    })
  })

  test('explore page loads without error', async ({ page }) => {
    await page.goto('/#/app/explore')
    await page.waitForTimeout(2000)

    const url = page.url()
    expect(url).toContain('/app/explore')

    // Should not show error page
    const bodyText = await page.textContent('body')
    expect(bodyText).not.toContain('404')
  })

  test('page has main content area', async ({ page }) => {
    await page.goto('/#/app/explore')
    await page.waitForTimeout(2000)

    // Look for common explore page elements
    const hasContent = await page.locator('#app').count()
    expect(hasContent).toBeGreaterThan(0)
  })
})
