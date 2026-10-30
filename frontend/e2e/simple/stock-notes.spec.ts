import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test'
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import AxeBuilder from '@axe-core/playwright'

const root = resolve('..')
const privateRoot = resolve(root, '.local/smart-stock')
const evidenceRoot = resolve(root, 'docs/evidence/simple-stock')
mkdirSync(evidenceRoot, { recursive: true })
const accounts = JSON.parse(readFileSync(resolve(privateRoot, 'accounts.json'), 'utf8')) as { role: string; username: string; password: string }[]
const run = Date.now().toString(36)
const network: { method: string; path: string; status: number }[] = []
const cookiesByRole = new Map<string, Awaited<ReturnType<BrowserContext['cookies']>>>()
async function login(page: Page, role = 'owner') {
  const savedCookies = cookiesByRole.get(role)
  if (savedCookies) {
    await page.context().addCookies(savedCookies); await page.goto('/')
    await expect(page.locator('#notes-title')).toBeVisible()
    await expect(page.locator('.notes-product').first()).toBeVisible()
    return
  }
  const account = accounts.find(a => a.role === role)!
  await page.goto('/')
  await page.getByLabel('บทบาท / Role').selectOption(role)
  await page.getByLabel('Username', { exact: true }).fill(account.username)
  await page.getByLabel('รหัสผ่าน', { exact: true }).fill(account.password)
  await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'สินค้าในร้าน', exact: true, level: 2 })).toBeVisible()
  await expect(page.locator('.notes-product').first()).toBeVisible()
  cookiesByRole.set(role, await page.context().cookies())
}
async function api(context: BrowserContext, path: string, method = 'GET', data?: unknown, key = crypto.randomUUID()) {
  const headers: Record<string, string> = { Accept: 'application/json', Origin: 'http://127.0.0.1:5178' }
  if (method !== 'GET') {
    await context.request.get('/sanctum/csrf-cookie')
    headers['X-XSRF-TOKEN'] = decodeURIComponent((await context.cookies()).find(c => c.name === 'XSRF-TOKEN')!.value)
    headers['Idempotency-Key'] = key
  }
  const response = await context.request.fetch(path, { method, data, headers })
  network.push({ method, path: path.split('?')[0], status: response.status() })
  return response
}
async function create(context: BrowserContext, data: Record<string, unknown>) {
  const r = await api(context, '/api/stock-notes/products', 'POST', { name: 'API Simple ' + run, tracking_mode: 'QUANTITY', ...data })
  expect(r.status()).toBe(201)
  return (await r.json()).product
}
async function client(browser: Browser, role = 'owner') {
  const context = await browser.newContext({ baseURL: 'http://127.0.0.1:5178', serviceWorkers: 'block' })
  const page = await context.newPage(); await login(page, role)
  return { context, page }
}
async function saved(page: Page) { await expect(page.locator('.notes-notice')).toHaveText('บันทึกแล้ว'); await expect(page.locator('.notes-editor')).toHaveCount(0) }
async function find(page: Page, name: string) { await page.getByLabel('ค้นหาสินค้า', { exact: true }).fill(name); await expect(page.locator('.notes-product')).toHaveCount(1); return page.locator('.notes-product') }

test.afterAll(() => { writeFileSync(resolve(evidenceRoot, 'network.json'), JSON.stringify(network, null, 2)) })

test('Owner: real quick create, image, fitment, sales, receipt, actual, mode, archive, history and refresh', async ({ page, context }) => {
  await login(page)
  const name = 'Browser อะไหล่ ' + run
  await page.getByRole('button', { name: '+ เพิ่มสินค้าด่วน', exact: true }).click()
  await page.getByLabel('ชื่อสินค้า *', { exact: true }).fill(name)
  await page.getByLabel('จำนวนเริ่มต้น', { exact: false }).fill('10')
  await page.locator('.notes-details summary').click()
  writeFileSync(resolve(privateRoot, 'quick-form.html'), await page.locator('.notes-editor').innerHTML())
  await page.screenshot({ path: resolve(evidenceRoot, 'quick-form-diagnostic.png'), fullPage: true })
  await page.getByLabel('ตำแหน่งจัดเก็บ', { exact: true }).selectOption({ index: 1 })
  await page.getByLabel('หมายเหตุ', { exact: true }).fill('บันทึกผ่าน Browser จริง')
  await page.getByLabel('ยี่ห้อรถ', { exact: true }).fill('Toyota')
  await page.getByLabel('รุ่นรถ', { exact: true }).fill('Vios')
  await page.getByLabel('ปีรถ', { exact: true }).fill('2020')
  await page.locator('input[type=file]').setInputFiles({ name: 'simple.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=', 'base64') })
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click(); await saved(page)
  const row = await find(page, name)
  await expect(row.locator('.notes-quantity')).toHaveText('10 ชิ้น')
  await expect(row.locator('.notes-code')).toContainText('PS-')
  await expect(row.locator('img')).toBeVisible()
  expect(await row.locator('img').evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true)
  let product = (await (await api(context, '/api/stock-notes/products?search=' + encodeURIComponent(name))).json()).data[0]
  expect(product.fitments[0].model).toBe('Vios')
  expect(product.sku_is_generated).toBe(true)
  for (const [qty, remaining] of [['1','9'], ['9','0']]) {
    await row.getByRole('button', { name: '− ขายออก', exact: true }).click()
    await expect(page.getByLabel('จำนวน *', { exact: true })).toHaveValue('1')
    await page.getByLabel('จำนวน *', { exact: true }).fill(qty)
    await page.getByRole('button', { name: 'บันทึกขายออก', exact: true }).click(); await saved(page)
    await expect(row.locator('.notes-quantity')).toHaveText(remaining + ' ชิ้น')
  }
  await expect(row.locator('.notes-badge')).toHaveText('สินค้าหมด')
  await expect(row.getByRole('button', { name: '− ขายออก', exact: true })).toBeDisabled()
  expect((await api(context, `/api/stock-notes/products/${product.id}/actions`, 'POST', { action: 'sale', quantity: 1 })).status()).toBe(409)
  await row.getByRole('button', { name: '+ รับเข้า', exact: true }).click(); await page.getByLabel('จำนวน *', { exact: true }).fill('5')
  await page.getByRole('button', { name: 'บันทึกรับเข้า', exact: true }).click(); await saved(page)
  await expect(row.locator('.notes-quantity')).toHaveText('5 ชิ้น'); await expect(row.locator('.notes-badge')).toHaveText('มีสินค้า')
  await row.getByRole('button', { name: 'แก้ยอดจริง', exact: true }).click(); await page.getByLabel('จำนวนจริง *').fill('7'); await page.getByLabel('เหตุผล *', { exact: true }).fill('ตรวจนับจริงบนชั้น')
  await page.getByRole('button', { name: 'บันทึกแก้ยอดจริง', exact: true }).click(); await saved(page)
  await row.getByRole('button', { name: 'แก้ไข', exact: true }).click(); await page.getByLabel('ชื่อสินค้า *', { exact: true }).fill(name + ' แก้ไข')
  await page.getByLabel('ตำแหน่งจัดเก็บ', { exact: true }).selectOption({ index: 2 })
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click(); await saved(page)
  await expect(row.getByRole('heading')).toHaveText(name + ' แก้ไข'); await expect(row.locator('.notes-location')).toContainText('B-01')
  await row.getByRole('button', { name: 'แก้ไข', exact: true }).click(); await page.getByLabel('รูปแบบการติดตาม *').selectOption('AVAILABILITY_ONLY')
  await page.getByLabel('ยืนยันการเปลี่ยนรูปแบบ').check(); await page.getByLabel('เหตุผล *', { exact: true }).fill('ติดตามสถานะแทน')
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click(); await saved(page)
  await expect(row.locator('.notes-badge')).toHaveText('ยังไม่ทราบ'); await expect(row.locator('.notes-quantity')).toHaveCount(0)
  for (const title of ['มีสินค้า','สินค้าหมด','มีสินค้า']) { await row.getByRole('button', { name: title, exact: true }).click(); await saved(page); await expect(row.locator('.notes-badge')).toHaveText(title) }
  await row.getByRole('button', { name: 'แก้ไข', exact: true }).click(); await page.getByLabel('รูปแบบการติดตาม *').selectOption('QUANTITY')
  await page.getByLabel('จำนวนเริ่มต้น *', { exact: true }).fill('6'); await page.getByLabel('ยืนยันการเปลี่ยนรูปแบบ').check(); await page.getByLabel('เหตุผล *', { exact: true }).fill('กลับมานับจริงได้หก')
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click(); await saved(page)
  page.once('dialog', d => d.accept()); await row.getByRole('button', { name: 'Archive', exact: true }).click(); await saved(page)
  await expect(page.locator('.notes-product')).toHaveCount(0); await page.getByLabel('รายการ Archive', { exact: true }).check()
  await expect(row).toBeVisible(); await row.getByRole('button', { name: 'คืนรายการ', exact: true }).click(); await saved(page)
  await page.getByLabel('รายการ Archive', { exact: true }).uncheck(); await expect(row).toBeVisible()
  await page.reload(); await find(page, name); await expect(row.locator('.notes-quantity')).toHaveText('6 ชิ้น')
  await row.getByRole('button', { name: 'ดูประวัติ', exact: true }).click(); await expect(page.locator('.notes-history')).toContainText('ตรวจนับจริงบนชั้น')
  await page.screenshot({ path: resolve(evidenceRoot, 'owner-history-desktop.png'), fullPage: true })
  product = (await (await api(context, '/api/stock-notes/products?search=' + encodeURIComponent(name))).json()).data[0]
  writeFileSync(resolve(privateRoot, 'browser-product.json'), JSON.stringify({ id: product.id, name: product.name, quantity: product.quantity, version: product.stock_version }))
  expect(product.quantity).toBe('6.000'); expect(product.fitments).toHaveLength(1)
})

test('Quick unset and availability; Staff/Manager backend rights and logout', async ({ browser }) => {
  const owner = await client(browser)
  const quantity = await create(owner.context, { name: 'Staff Quantity ' + run, initial_quantity: 10 })
  const availability = await create(owner.context, { name: 'Staff Availability ' + run, tracking_mode: 'AVAILABILITY_ONLY' })
  const unset = await create(owner.context, { name: 'Unset ' + run })
  expect(unset.quantity).toBeNull(); expect(unset.stock_status).toBe('UNSET'); expect(availability.quantity).toBeNull()
  await owner.context.close()
  const staff = await client(browser, 'staff')
  await expect(staff.page.getByRole('button', { name: '+ เพิ่มสินค้าด่วน', exact: true })).toHaveCount(0)
  const row = await find(staff.page, quantity.name)
  await expect(row.getByRole('button', { name: '+ รับเข้า', exact: true })).toHaveCount(0)
  await row.getByRole('button', { name: '− ขายออก', exact: true }).click(); await staff.page.getByRole('button', { name: 'บันทึกขายออก', exact: true }).click(); await saved(staff.page)
  await expect(row.locator('.notes-quantity')).toHaveText('9 ชิ้น')
  await row.getByRole('button', { name: 'ต้องสั่ง', exact: true }).click(); await staff.page.getByLabel('ทำเครื่องหมายว่าต้องสั่ง').check(); await staff.page.getByLabel('หมายเหตุของที่ต้องสั่ง').fill('สั่งเพิ่มจากมือถือ')
  await staff.page.getByRole('button', { name: 'บันทึกรายการต้องสั่ง', exact: true }).click(); await saved(staff.page)
  await expect(row.locator('.notes-reorder')).toContainText('สั่งเพิ่มจากมือถือ')
  const arow = await find(staff.page, availability.name)
  for (const title of ['มีสินค้า','สินค้าหมด','มีสินค้า','ยังไม่ทราบ']) { await arow.getByRole('button', { name: title, exact: true }).click(); await saved(staff.page); await expect(arow.locator('.notes-badge')).toHaveText(title) }
  for (const action of ['receipt','set_quantity','archive','restore']) expect((await api(staff.context, `/api/stock-notes/products/${quantity.id}/actions`, 'POST', { action, quantity: 1, expected_version: quantity.stock_version, reason: 'denied' })).status()).toBe(403)
  expect((await api(staff.context, `/api/stock-notes/products/${quantity.id}`, 'PATCH', { name: 'denied', role: 'owner', expected_version: 0 })).status()).toBe(403)
  expect((await api(staff.context, '/api/user')).ok()).toBe(true)
  expect((await (await api(staff.context, '/api/user')).json()).user.role).toBe('staff')
  await staff.page.getByRole('button', { name: 'ออกจากระบบ', exact: true }).click(); await expect(staff.page.getByLabel('รหัสผ่าน', { exact: true })).toBeVisible()
  expect((await api(staff.context, '/api/stock-notes/products')).status()).toBe(401); await staff.context.close()
  const manager = await client(browser, 'manager')
  const current = (await (await api(manager.context, `/api/stock-notes/products?search=${encodeURIComponent(quantity.name)}`)).json()).data[0]
  expect((await api(manager.context, `/api/stock-notes/products/${quantity.id}/actions`, 'POST', { action: 'set_quantity', quantity: 8, expected_version: current.stock_version, reason: 'Manager actual count' })).status()).toBe(201)
  await manager.context.close()
})

test('Two real sessions: concurrent deltas, oversell, duplicate retry and stale actual conflict', async ({ browser }) => {
  const first = await client(browser); const second = await client(browser, 'manager'); const retry = await client(browser)
  const product = await create(first.context, { name: 'Concurrent ' + run, initial_quantity: 10 })
  const url = `/api/stock-notes/products/${product.id}/actions`
  const results: unknown[] = []
  let responses = await Promise.all([api(first.context, url, 'POST', { action: 'sale', quantity: 7 }), api(second.context, url, 'POST', { action: 'sale', quantity: 7 })])
  expect(responses.map(r => r.status()).sort()).toEqual([201,409]); results.push({ case: 'oversell', statuses: responses.map(r => r.status()), final: '3.000' })
  let p = (await (await api(first.context, '/api/stock-notes/products?search=' + encodeURIComponent(product.name))).json()).data[0]; expect(p.quantity).toBe('3.000')
  responses = await Promise.all([api(first.context, url, 'POST', { action: 'sale', quantity: 1 }), api(second.context, url, 'POST', { action: 'receipt', quantity: 5 })])
  expect(responses.map(r => r.status())).toEqual([201,201]); results.push({ case: 'sale-and-receipt', statuses: [201,201], final: '7.000' })
  p = (await (await api(first.context, '/api/stock-notes/products?search=' + encodeURIComponent(product.name))).json()).data[0]; expect(p.quantity).toBe('7.000')
  const key = crypto.randomUUID()
  responses = await Promise.all([api(first.context, url, 'POST', { action: 'sale', quantity: 1 }, key), api(retry.context, url, 'POST', { action: 'sale', quantity: 1 }, key)])
  expect(responses.map(r => r.status()).sort()).toEqual([200,201]); results.push({ case: 'same-key-retry', statuses: responses.map(r => r.status()), final: '6.000' })
  p = (await (await api(first.context, '/api/stock-notes/products?search=' + encodeURIComponent(product.name))).json()).data[0]; expect(p.quantity).toBe('6.000')
  responses = await Promise.all([api(first.context, url, 'POST', { action: 'set_quantity', quantity: 4, expected_version: p.stock_version, reason: 'client1' }), api(second.context, url, 'POST', { action: 'set_quantity', quantity: 2, expected_version: p.stock_version, reason: 'client2' })])
  expect(responses.map(r => r.status()).sort()).toEqual([201,409]); results.push({ case: 'two-absolute-updates', statuses: responses.map(r => r.status()) })
  p = (await (await api(first.context, '/api/stock-notes/products?search=' + encodeURIComponent(product.name))).json()).data[0]
  await api(second.context, url, 'POST', { action: 'sale', quantity: 1 })
  expect((await api(first.context, url, 'POST', { action: 'set_quantity', quantity: 9, expected_version: p.stock_version, reason: 'stale snapshot' })).status()).toBe(409)
  const sharedSku = 'CONCURRENT-SKU-' + run
  responses = await Promise.all([api(first.context, '/api/stock-notes/products', 'POST', { name: 'SKU race1', sku: sharedSku, tracking_mode: 'QUANTITY' }), api(second.context, '/api/stock-notes/products', 'POST', { name: 'SKU race2', sku: sharedSku, tracking_mode: 'QUANTITY' })])
  expect(responses.map(r => r.status()).sort()).toEqual([201,422]); results.push({ case: 'same-custom-sku', statuses: responses.map(r => r.status()) })
  writeFileSync(resolve(evidenceRoot, 'concurrency.json'), JSON.stringify({ users: ['owner','manager'], independent_clients: 3, database: 'partshelf_simple', results }, null, 2))
  await first.context.close(); await second.context.close(); await retry.context.close()
})

test('All existing views at10 widths; main form mobile, drawer and accessibility', async ({ page }) => {
  test.setTimeout(300000)
  const errors: string[] = []; page.on('pageerror', error => errors.push(error.message))
  await login(page)
  const consoleErrors: string[] = []; page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()) })
  const titles: Record<string,string> = { notes: 'สินค้าในร้าน', products: 'สินค้า', categories: 'หมวดหมู่', locations: 'ตำแหน่งจัดเก็บ', stock: 'สต๊อกและรับเข้า', count: 'ตรวจนับ', dashboard: 'ภาพรวม', excel: 'นำเข้า / ส่งออก', labels: 'รหัสและฉลาก' }
  const rows: unknown[] = []
  for (const width of [320,360,375,390,430,768,1024,1280,1440,1920]) {
    await page.setViewportSize({ width, height: 900 })
    for (const route of ['notes','products','categories','locations','stock','count','dashboard','excel','labels']) {
      await page.goto('/#/' + route)
      await expect(page.locator('.workspace-content')).toBeVisible()
      await expect(page.locator('.workspace-breadcrumb strong')).toHaveText(titles[route])
      await expect(page.locator('.panel-loading')).toHaveCount(0)
      await page.waitForFunction(() => !document.querySelector('.workspace-content')?.textContent?.includes('กำลังโหลด'))
      await page.screenshot({ path: resolve(evidenceRoot, `${route}-${width}.png`), fullPage: true })
      if (route === 'notes' && width === 1280) await page.screenshot({ path: resolve(evidenceRoot, 'notes-first-screen-desktop.png') })
      const overflow = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth, elements: Array.from(document.querySelectorAll('*')).filter(e => { const r=e.getBoundingClientRect(); return r.width && r.right > innerWidth + 1 && !e.closest('table') }).slice(-20).map(e => ({ tag:e.tagName, cls:e.className, width:e.getBoundingClientRect().width })) }))
      if (overflow.scroll > overflow.width) writeFileSync(resolve(privateRoot, 'overflow.json'), JSON.stringify({ route, ...overflow }, null, 2))
      expect(overflow.scroll).toBeLessThanOrEqual(overflow.width)
      rows.push({ route, width, overflow: false, status: 'PASS' })
    }
  }
  await page.setViewportSize({ width: 390, height: 844 }); await page.goto('/#/notes')
  await expect(page.locator('.notes-product').first()).toBeVisible(); await page.screenshot({ path: resolve(evidenceRoot, 'notes-first-screen-mobile.png') })
  await page.getByRole('button', { name: 'เปิดเมนู', exact: true }).click(); await expect(page.getByRole('dialog')).toBeVisible(); await page.keyboard.press('Escape'); await expect(page.getByRole('button', { name: 'เปิดเมนู', exact: true })).toBeFocused()
  await page.getByRole('button', { name: '+ เพิ่มสินค้าด่วน', exact: true }).click(); await page.locator('.notes-details summary').click()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  await page.screenshot({ path: resolve(evidenceRoot, 'quick-add-mobile.png'), fullPage: true })
  const a11y = await new AxeBuilder({ page }).include('.stock-notes').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()
  writeFileSync(resolve(evidenceRoot, 'responsive.json'), JSON.stringify({ rows, page_errors: errors, console_errors: consoleErrors, accessibility_violations: a11y.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.map(n => n.target) })), physical_mobile: 'NOT_VERIFIED' }, null, 2))
  expect(errors).toEqual([]); expect(consoleErrors).toEqual([]); expect(a11y.violations).toEqual([])
})

test('Browser Excel preview/confirm/export supports quantity, unknown availability and unset', async ({ page, context }) => {
  await login(page); await page.goto('/#/excel')
  const prefix = (JSON.parse(readFileSync(resolve(privateRoot, 'excel-prefix.json'), 'utf8')) as { prefix: string }).prefix
  await page.locator('input[type=file]').setInputFiles(resolve(privateRoot, 'import.xlsx'))
  await page.getByRole('button', { name: 'ตรวจสอบและแสดงตัวอย่าง', exact: true }).click()
  await expect(page.getByText('ใช้ได้ 3 แถว · ต้องแก้ไข 0 แถว', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'ยืนยันนำเข้าเฉพาะแถวที่ผ่าน', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('นำเข้าสินค้า 3 รายการแล้ว')
  const data = (await (await api(context, '/api/stock-notes/products?search=' + prefix)).json()).data
  expect(data).toHaveLength(3)
  expect(data.find((p: { sku: string }) => p.sku.endsWith('-Q')).quantity).toBe('10.000')
  expect(data.find((p: { sku: string }) => p.sku.endsWith('-A')).stock_status).toBe('UNKNOWN')
  expect(data.find((p: { sku: string }) => p.sku.endsWith('-A')).quantity).toBeNull()
  expect(data.find((p: { sku: string }) => p.sku.endsWith('-U')).stock_status).toBe('UNSET')
  const downloaded = page.waitForEvent('download')
  await page.getByRole('button', { name: 'ส่งออกสินค้า', exact: true }).click()
  const file = await downloaded; await file.saveAs(resolve(privateRoot, 'browser-export.xlsx'))
  expect(readFileSync(resolve(privateRoot, 'browser-export.xlsx')).subarray(0,2).toString()).toBe('PK')
  await page.screenshot({ path: resolve(evidenceRoot, 'excel-browser.png'), fullPage: true })
  writeFileSync(resolve(evidenceRoot, 'excel-browser.json'), JSON.stringify({ imported: 3, quantity: '10.000', availability_quantity: null, availability_status: 'UNKNOWN', unset_quantity: null, export: 'XLSX ZIP downloaded', status: 'PASS' }, null, 2))
})

test('Lost response retries the same key and a double click cannot sell twice', async ({ page, context }) => {
  await login(page)
  const product = await create(context, { name: 'Network Retry ' + run, initial_quantity: 10 })
  const row = await find(page, product.name)
  await row.getByRole('button', { name: '− ขายออก', exact: true }).click()
  const keys: string[] = []
  let lost = true
  await page.route(`**/api/stock-notes/products/${product.id}/actions`, async route => {
    keys.push(route.request().headers()['idempotency-key'])
    if (lost) { lost = false; const result = await route.fetch(); expect(result.status()).toBe(201); await route.abort('failed') }
    else await route.continue()
  })
  await page.getByRole('button', { name: 'บันทึกขายออก', exact: true }).click()
  await expect(page.locator('.notes-error')).toBeVisible()
  let current = (await (await api(context, '/api/stock-notes/products?search=' + encodeURIComponent(product.name))).json()).data[0]
  expect(current.quantity).toBe('9.000')
  await page.getByRole('button', { name: 'บันทึกขายออก', exact: true }).click(); await saved(page)
  await expect(row.locator('.notes-quantity')).toHaveText('9 ชิ้น'); expect(keys).toHaveLength(2); expect(keys[0]).toBe(keys[1])
  await page.unroute(`**/api/stock-notes/products/${product.id}/actions`)
  await row.getByRole('button', { name: '− ขายออก', exact: true }).click()
  await page.getByRole('button', { name: 'บันทึกขายออก', exact: true }).dblclick(); await saved(page)
  current = (await (await api(context, '/api/stock-notes/products?search=' + encodeURIComponent(product.name))).json()).data[0]
  expect(current.quantity).toBe('8.000')
  const history = await (await api(context, `/api/stock-notes/products/${product.id}/history`)).json()
  expect(history.data.filter((event: { action: string }) => event.action === 'sale')).toHaveLength(2)
  writeFileSync(resolve(evidenceRoot, 'network-retry.json'), JSON.stringify({ lost_response_after_real_commit: true, same_key_replayed: true, quantity_after_retry: '9.000', quantity_after_double_click: '8.000', total_sale_events: 2, status: 'PASS' }, null, 2))
})

test('Mobile emulation: quick availability and decimal sale without count rounds', async ({ browser }) => {
  const context = await browser.newContext({ baseURL: 'http://127.0.0.1:5178', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, serviceWorkers: 'block' })
  const page = await context.newPage(); await login(page)
  const name = 'Mobile สถานะ ' + run
  await page.getByRole('button', { name: '+ เพิ่มสินค้าด่วน', exact: true }).click()
  await page.getByLabel('ชื่อสินค้า *', { exact: true }).fill(name)
  await page.getByLabel('รูปแบบการติดตาม *', { exact: true }).selectOption('AVAILABILITY_ONLY')
  await expect(page.getByLabel('จำนวนเริ่มต้น', { exact: false })).toHaveCount(0)
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click(); await saved(page)
  let row = await find(page, name)
  await expect(row.locator('.notes-quantity')).toHaveCount(0)
  await row.getByRole('button', { name: 'มีสินค้า', exact: true }).tap(); await saved(page)
  await expect(row.locator('.notes-badge')).toHaveText('มีสินค้า')
  const product = await create(context, { name: 'Mobile จำนวน ' + run, initial_quantity: 10 })
  row = await find(page, product.name)
  await row.getByRole('button', { name: '− ขายออก', exact: true }).tap()
  await expect(page.getByLabel('จำนวน *', { exact: true })).toHaveAttribute('inputmode', 'decimal')
  await page.getByLabel('จำนวน *', { exact: true }).fill('1.5')
  await page.screenshot({ path: resolve(evidenceRoot, 'mobile-sale-form.png'), fullPage: true })
  await page.getByRole('button', { name: 'บันทึกขายออก', exact: true }).tap(); await saved(page)
  await expect(row.locator('.notes-quantity')).toHaveText('8.5 ชิ้น')
  await page.reload(); await find(page, product.name); await expect(row.locator('.notes-quantity')).toHaveText('8.5 ชิ้น')
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  await context.close()
})

test('Lost mode-change response replays after the product has already become QUANTITY', async ({ page, context }) => {
  await login(page)
  const product = await create(context, { name: 'Mode Retry ' + run, tracking_mode: 'AVAILABILITY_ONLY' })
  const row = await find(page, product.name)
  await row.getByRole('button', { name: 'แก้ไข', exact: true }).click()
  await page.getByLabel('รูปแบบการติดตาม *', { exact: true }).selectOption('QUANTITY')
  await page.getByLabel('จำนวนเริ่มต้น *', { exact: true }).fill('7')
  await page.getByLabel('ยืนยันการเปลี่ยนรูปแบบ').check()
  await page.getByLabel('เหตุผล *', { exact: true }).fill('Count seven with simulated lost response')
  let lost = true
  await page.route(`**/api/stock-notes/products/${product.id}`, async route => {
    if (lost) { lost = false; const result = await route.fetch(); expect(result.status()).toBe(200); await route.abort('failed') }
    else await route.continue()
  })
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click()
  await expect(page.locator('.notes-error')).toBeVisible()
  await page.getByRole('button', { name: 'บันทึกสินค้า', exact: true }).click(); await saved(page)
  await expect(row.locator('.notes-quantity')).toHaveText('7 ชิ้น')
  const history = await (await api(context, `/api/stock-notes/products/${product.id}/history`)).json()
  expect(history.data).toHaveLength(2)
  writeFileSync(resolve(evidenceRoot, 'mode-retry.json'), JSON.stringify({ status: 'PASS', lost_response_after_commit: true, replay_after_mode_change: true, final_quantity: '7.000', events: 2 }, null, 2))
})
