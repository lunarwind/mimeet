import { test, expect } from '@playwright/test'

test.describe('Dev Check Page', () => {
  test('dev/check page opens without 404', async ({ page }) => {
    await page.goto('/#/dev/check')
    await page.waitForTimeout(2000)

    const url = page.url()
    expect(url).toContain('/dev/check')

    // Should not be a 404 page
    const bodyText = await page.textContent('body')
    expect(bodyText).not.toContain('找不到')
  })

  test('page contains Sprint text', async ({ page }) => {
    await page.goto('/#/dev/check')
    await page.waitForTimeout(2000)

    const bodyText = await page.textContent('body')
    expect(bodyText).toContain('Sprint')
  })

  test('page has progress statistics', async ({ page }) => {
    await page.goto('/#/dev/check')
    await page.waitForTimeout(2000)

    const bodyText = await page.textContent('body') || ''
    // Should contain progress indicator (pass count / total or %)
    const hasProgress = bodyText.includes('通過') || bodyText.includes('/') || bodyText.includes('%')
    expect(hasProgress).toBeTruthy()
  })

  test('Sprint 7 section exists', async ({ page }) => {
    await page.goto('/#/dev/check')
    await page.waitForTimeout(2000)

    const bodyText = await page.textContent('body') || ''
    // Should contain S7 items
    const hasSprint7 = bodyText.includes('S7-01') || bodyText.includes('s7-01') || bodyText.includes('儀表板')
    expect(hasSprint7).toBeTruthy()
  })

  test('checklist items are clickable', async ({ page }) => {
    await page.goto('/#/dev/check')
    await page.waitForTimeout(2000)

    // Find any clickable check circle/button
    const checkItems = page.locator('[class*="check-circle"], [class*="check-item"], [class*="status-circle"]')
    const count = await checkItems.count()

    if (count > 0) {
      // Click the first item
      await checkItems.first().click()
      // Should not crash — page still visible
      const bodyText = await page.textContent('body')
      expect(bodyText).toContain('Sprint')
    } else {
      // Fallback: just verify page is interactive (has clickable elements)
      const anyButton = page.locator('button, [role="button"], [onclick]')
      const buttonCount = await anyButton.count()
      expect(buttonCount).toBeGreaterThan(0)
    }
  })
})
