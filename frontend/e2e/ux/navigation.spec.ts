import { expect, test } from '@playwright/test'

test('existing stock-count work is directly reachable without scrolling through unrelated panels', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('navigation', { name: 'เมนูหลัก' })).toBeVisible()
  await page.getByRole('link', { name: 'ตรวจนับ', exact: true }).click()
  await expect(page).toHaveURL(/#\/count$/)
  await expect(page.getByRole('heading', { name: 'รอบตรวจนับ', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'จัดการหมวดหมู่', exact: true })).toHaveCount(0)
})
