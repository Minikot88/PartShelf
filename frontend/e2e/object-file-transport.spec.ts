import { createHash } from 'node:crypto'
import { expect, test } from '@playwright/test'

test('private file mode sends bytes directly to storage and only tokens to the API', async ({ page }) => {
  const uploaded = new Map<string, Buffer>()
  const intents = new Map<string, { sha256: string; size: number }>()
  let imageSaved = false
  let workbookPreviewed = false
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204, headers: { 'Set-Cookie': 'XSRF-TOKEN=test-csrf; Path=/; SameSite=Lax' } }))
  await page.route('https://storage.example.test/**', async (route) => {
    const request = route.request()
    expect(request.headers()['cookie']).toBeUndefined()
    expect(request.headers()['x-xsrf-token']).toBeUndefined()
    if (request.method() === 'PUT') {
      const kind = new URL(request.url()).pathname.slice(1)
      const bytes = request.postDataBuffer()!
      expect(createHash('sha256').update(bytes).digest('hex')).toBe(intents.get(kind)?.sha256)
      expect(bytes.length).toBe(intents.get(kind)?.size)
      uploaded.set(kind, bytes)
      return route.fulfill({ status: 200, headers: { 'Access-Control-Allow-Origin': 'http://127.0.0.1:5176' } })
    }
    return route.fulfill({ body: Buffer.from('workbook-download-fixture'), contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', headers: { 'Access-Control-Allow-Origin': 'http://127.0.0.1:5176' } })
  })
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (path === '/api/user') return route.fulfill({ json: { user: { id: 1, name: 'Owner fixture', email: 'owner@example.test', role: 'owner' } } })
    if (path === '/api/file-transport') return route.fulfill({ json: { mode: 'object' } })
    if (path === '/api/file-transfers') {
      const body = request.postDataJSON() as { kind: string; sha256: string; size: number; mime: string }
      intents.set(body.kind, body)
      return route.fulfill({ status: 201, json: { token: `${body.kind}-token`, upload_url: `https://storage.example.test/${body.kind}`, headers: { 'Content-Type': body.mime } } })
    }
    if (path === '/api/products' && request.method() === 'POST') {
      const body = request.postDataJSON() as Record<string, unknown>
      expect(body.image_token).toBe('image-token')
      expect(body.image).toBeUndefined()
      expect(uploaded.has('image')).toBe(true)
      imageSaved = true
      return route.fulfill({ status: 201, json: { product: { id: 1 } } })
    }
    if (path === '/api/products/import/preview') {
      expect(request.postDataJSON()).toEqual({ upload_token: 'workbook-token' })
      expect(uploaded.has('workbook')).toBe(true)
      workbookPreviewed = true
      return route.fulfill({ json: { token: 'preview-token', valid_count: 1, error_count: 0, rows: [{ row: 2, sku: 'TEST', name: 'Test', errors: [] }] } })
    }
    if (path === '/api/products/export') return route.fulfill({ json: { download_url: 'https://storage.example.test/download' } })
    if (path === '/api/categories') return route.fulfill({ json: { categories: [] } })
    if (path === '/api/dashboard') return route.fulfill({ json: { summary: { product_count: 0, total_on_hand: '0.000', low_stock_count: 0, out_of_stock_count: 0 }, recent_counts: [], recent_variances: [], can_view_variances: true } })
    return route.fulfill({ json: { data: [] } })
  })
  await page.goto('/')
  await page.getByLabel('SKU *', { exact: true }).fill('OBJECT-1')
  await page.getByLabel('ชื่อสินค้า *', { exact: true }).fill('Object product')
  await page.locator('input[type=file][accept^="image/jpeg"]').setInputFiles({ name: 'image.png', mimeType: 'image/png', buffer: Buffer.from('browser-upload-fixture') })
  await page.getByRole('button', { name: 'เพิ่มสินค้า', exact: true }).click()
  await expect(page.getByText('เพิ่มสินค้าแล้ว', { exact: true })).toBeVisible()
  expect(imageSaved).toBe(true)
  await page.locator('input[type=file][accept^=".xlsx"]').setInputFiles({ name: 'products.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: Buffer.from('browser-workbook-fixture') })
  await page.getByRole('button', { name: 'ตรวจสอบและแสดงตัวอย่าง' }).click()
  await expect(page.getByText('ใช้ได้ 1 แถว · ต้องแก้ไข 0 แถว')).toBeVisible()
  expect(workbookPreviewed).toBe(true)
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'ส่งออกสินค้า', exact: true }).click()
  expect((await download).suggestedFilename()).toBe('products.xlsx')
})
