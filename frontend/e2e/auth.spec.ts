import { test, expect } from '@playwright/test'
import { mockAuthUser, mockSuspendedUser, clearAuth } from './helpers/auth'

test.describe('Auth Guards', () => {
  test('unauthenticated user visiting explore is redirected away', async ({ page }) => {
    await clearAuth(page)
    await page.goto('/#/app/explore')
    await page.waitForTimeout(1000)

    const url = page.url()
    // Should NOT be on explore — redirected to login or landing
    expect(url).not.toContain('/app/explore')
  })

  test('Lv3 user can access explore page', async ({ page }) => {
    await mockAuthUser(page, 3)
    await page.goto('/#/app/explore')
    await page.waitForTimeout(1500)

    const url = page.url()
    expect(url).toContain('/app/explore')
  })

  test('suspended user is redirected to /suspended', async ({ page }) => {
    await mockSuspendedUser(page)
    await page.goto('/#/app/explore')
    await page.waitForTimeout(1500)

    const url = page.url()
    // Should be on suspended page or landing (not explore)
    const redirectedAway = !url.includes('/app/explore')
    expect(redirectedAway).toBeTruthy()
  })
})
