import { expect, test } from '@playwright/test'

const product = {
  id: 1,
  sku: 'BRAKE-PAD-01',
  name: 'ผ้าเบรกหน้า',
  part_number: 'BP-01',
  base_unit: 'ชิ้น',
  min_stock_quantity: '2.000',
  category_id: null,
  category: null,
  storage_location_id: null,
  storage_location: null,
  image_path: null,
  image_url: null,
  internal_code: 'PS-00000001',
  fitments: [],
}

test('login, dashboard, count, stable-code resolution, labels and PWA install prompt', async ({ page }) => {
  let activeCount = false
  let counted = false
  const user = { id: 1, name: 'เจ้าของทดสอบ', email: 'owner@example.test', role: 'owner' as const }
  const countSummary = { id: 7, status: 'open' as const, started_at: '2026-10-28T03:00:00.000Z', completed_at: null, started_by: user.name, item_count: 1 }
  const countItem = () => ({
    id: 21,
    product: { id: product.id, sku: product.sku, name: product.name, base_unit: product.base_unit },
    location: null,
    reference_quantity: '7.000',
    reference_at: '2026-10-28T03:00:00.000Z',
    counted_quantity: counted ? '5.000' : null,
    count_status: counted ? 'counted' : 'not_counted',
    variance: counted ? '-2.000' : null,
    attempts: counted ? [{ attempt_number: 1, counted_quantity: '5.000', variance: '-2.000', correction: null }] : [],
    correction: null,
  })

  await page.addInitScript(() => {
    Object.defineProperty(window, '__printCalls', { value: 0, writable: true })
    window.print = () => { (window as Window & { __printCalls: number }).__printCalls += 1 }
  })
  await page.route('**/sanctum/csrf-cookie', async (route) => route.fulfill({ status: 204, headers: { 'Set-Cookie': 'XSRF-TOKEN=e2e-token; Path=/; SameSite=Lax' } }))
  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname
    if (path === '/api/user') return route.fulfill({ status: 401, json: { message: 'Unauthenticated.' } })
    if (path === '/api/login' && request.method() === 'POST') return route.fulfill({ status: 200, json: { user } })
    if (path === '/api/dashboard') return route.fulfill({ json: {
      summary: { product_count: 1, total_on_hand: '7.000', low_stock_count: 0, out_of_stock_count: 0 },
      recent_counts: activeCount ? [countSummary] : [],
      recent_variances: counted ? [{ count_id: 7, sku: product.sku, name: product.name, attempt_number: 1, counted_at: '2026-10-28T03:01:00.000Z', variance: '-2.000' }] : [],
      can_view_variances: true,
    } })
    if (path === '/api/products/resolve') return route.fulfill({ json: { product } })
    if (path === '/api/products') return route.fulfill({ json: { data: [product], current_page: 1, last_page: 1, total: 1 } })
    if (path === '/api/categories') return route.fulfill({ json: { categories: [] } })
    if (path === '/api/locations') return route.fulfill({ json: { data: [] } })
    if (path === '/api/stock/balances') return route.fulfill({ json: { data: [{ product, quantity: '7.000', stock_status: 'ok' }] } })
    if (path === '/api/stock-counts' && request.method() === 'POST') { activeCount = true; return route.fulfill({ status: 201, json: { count: countSummary } }) }
    if (path === '/api/stock-counts' && request.method() === 'GET') return route.fulfill({ json: { data: activeCount ? [countSummary] : [] } })
    if (path === '/api/stock-counts/7' && request.method() === 'GET') return route.fulfill({ json: { count: countSummary, items: [countItem()] } })
    if (path === '/api/stock-counts/7/items/21/counts' && request.method() === 'POST') { counted = true; return route.fulfill({ status: 201, json: { item: countItem() } }) }
    if (path === '/api/stock-counts/7/lookup' && request.method() === 'POST') return route.fulfill({ json: { status: countItem().count_status, item: countItem() } })
    return route.fulfill({ status: 200, json: { data: [] } })
  })

  await page.goto('/')
  await page.getByLabel('อีเมล').fill(user.email)
  await page.getByLabel('รหัสผ่าน').fill('temporary-e2e-password')
  await page.getByRole('button', { name: 'เข้าสู่ระบบ' }).click()
  await expect(page.getByRole('heading', { name: 'สรุปสินค้าและสต๊อก' })).toBeVisible()
  await expect(page.getByText('ยอดคงเหลือรวม').locator('..')).toContainText('7.000')

  await page.getByRole('navigation', { name: 'เมนูหลัก' }).getByRole('link', { name: 'ตรวจนับ', exact: true }).click()
  await page.getByRole('button', { name: 'เริ่มรอบตรวจนับ' }).click()
  await expect(page.getByText('เริ่มรอบตรวจนับ #7 แล้ว')).toBeVisible()
  await page.getByLabel('จำนวนที่ตรวจนับ BRAKE-PAD-01').fill('5')
  await page.getByRole('button', { name: 'บันทึกจำนวน' }).click()
  await expect(page.getByText('บันทึก BRAKE-PAD-01 แล้ว · ยอดสต๊อกไม่เปลี่ยน')).toBeVisible()
  await expect(page.locator('.count-variance')).toContainText('-2.000')
  await expect(page.getByRole('button', { name: 'ปรับยอดตามผลต่าง' })).toBeVisible()

  await page.getByRole('navigation', { name: 'เมนูหลัก' }).getByRole('link', { name: 'รหัสและฉลาก', exact: true }).click()
  await page.evaluate(() => Object.defineProperty(window, 'BarcodeDetector', { configurable: true, value: undefined }))
  await page.getByRole('button', { name: 'เปิดกล้อง' }).click()
  await expect(page.getByRole('alert')).toContainText('ไม่รองรับกล้องสแกน')

  await page.getByPlaceholder('สแกน QR / Barcode หรือพิมพ์ PS-00000001').fill('PS-00000001')
  await page.getByRole('button', { name: 'ค้นหารหัส' }).click()
  await expect(page.getByText('พบ PS-00000001 · BRAKE-PAD-01 · ผ้าเบรกหน้า')).toBeVisible()
  await page.getByRole('checkbox', { name: 'เลือกฉลาก BRAKE-PAD-01' }).check()
  await page.getByRole('button', { name: 'พิมพ์ฉลากที่เลือก (1)' }).click()
  await expect.poll(() => page.evaluate(() => (window as Window & { __printCalls: number }).__printCalls)).toBe(1)
  const label = page.locator('.partshelf-label-sheet .partshelf-label')
  await expect(label.locator('svg[aria-label="บาร์โค้ด PS-00000001"]')).toBeAttached()
  await expect.poll(() => label.locator('canvas').evaluate((canvas: HTMLCanvasElement) => canvas.toDataURL() !== document.createElement('canvas').toDataURL())).toBe(true)
  await page.emulateMedia({ media: 'print' })
  await expect(label).toBeVisible()
  await page.emulateMedia({ media: 'screen' })

  await page.evaluate(() => {
    const event = new Event('beforeinstallprompt', { cancelable: true }) as Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: 'accepted'; platform: string }> }
    event.prompt = async () => { (window as Window & { __installPrompted?: boolean }).__installPrompted = true }
    event.userChoice = Promise.resolve({ outcome: 'accepted', platform: 'test' })
    window.dispatchEvent(event)
  })
  await page.getByRole('button', { name: 'ติดตั้งแอป' }).click()
  await expect.poll(() => page.evaluate(() => (window as Window & { __installPrompted?: boolean }).__installPrompted)).toBe(true)
})

test('count focus follows visible SKU order instead of numeric item ID', async ({page})=>{
 let counted=false
 const items=()=>[30,10,20].map((id,index)=>({id,product:{id,sku:['A-FIRST','B-SAVED','C-NEXT'][index],name:'Focus fixture',base_unit:'piece'},location:null,reference_quantity:'1.000',reference_at:'2026-10-29T00:00:00Z',counted_quantity:id===10&&counted?'1.000':null,count_status:id===10&&counted?'counted':'not_counted',variance:null,attempts:[],correction:null}))
 await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204,headers:{'Set-Cookie':'XSRF-TOKEN=focus-test-token; Path=/; SameSite=Lax'}}))
 await page.route('**/api/**',async r=>{
  const path=new URL(r.request().url()).pathname
  if(path==='/api/user')return r.fulfill({json:{user:{id:1,name:'Focus fixture',role:'owner',email:'focus@example.test'}}})
  if(path==='/api/stock-counts')return r.fulfill({json:{data:[{id:7,status:'open',started_at:'2026-10-29T00:00:00Z',started_by:'Fixture',item_count:3}]}})
  if(path==='/api/stock-counts/7/items/10/counts'){counted=true;return r.fulfill({status:201,json:{item:items()[1]}})}
  if(path==='/api/stock-counts/7')return r.fulfill({json:{items:items()}})
  return r.fulfill({json:{data:[]}})
 })
 await page.goto('/#/count');await page.getByRole('combobox',{name:'เปิดรอบเดิม',exact:true}).selectOption('7');await page.locator('#count-quantity-10').fill('1');await page.locator('#count-row-10').getByRole('button',{name:'บันทึกจำนวน',exact:true}).click();await expect(page.getByRole('status')).toContainText('ยอดสต๊อกไม่เปลี่ยน');await expect(page.locator('#count-quantity-20')).toBeFocused()
})
