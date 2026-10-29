import { expect, test } from '@playwright/test'
import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import AxeBuilder from '@axe-core/playwright'
const root=resolve('..')
const widths=[320,360,375,390,430,768,1024,1280,1440,1920,844]
const rows:unknown[]=[]
async function loaded(page: import('@playwright/test').Page,route:string){await page.goto('/#/'+route);await expect(page.locator('.workspace-content')).toBeVisible();await page.waitForFunction(()=>!document.querySelector('main')?.innerText.includes('กำลังโหลด'));await page.waitForLoadState('networkidle')}

test('expanded forms/detail/count/preview/drawer fit every representative viewport',async({page})=>{
 test.setTimeout(180000)
 async function measure(state:string){for(const width of widths){const height=width===844?390:800;await page.setViewportSize({width,height});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);const bad=await page.locator('input:visible,select:visible,button:visible').evaluateAll(es=>es.filter(e=>{const r=e.getBoundingClientRect();return r.right>innerWidth+1||r.left<0}).map(e=>e.tagName));expect(bad).toEqual([]);let screenshot:string|null=null;if([320,768,1280].includes(width)){screenshot=resolve(root,'.local/ux-audit/after/'+state+'-'+width+'.png');await page.screenshot({path:screenshot,fullPage:true})}rows.push({state,role:'owner',viewport:width+'x'+height,layout:'PASS',controls:'PASS',screenshot});}await page.setViewportSize({width:390,height:844});const a=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(a.violations).toEqual([])}
 await loaded(page,'products');await page.getByRole('button',{name:'เพิ่มสินค้าใหม่',exact:true}).click();await page.getByRole('button',{name:'+ เพิ่มรุ่นรถ',exact:true}).click();await measure('product-create-fitment')
 page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'ปิดฟอร์มสินค้า',exact:true}).click();await page.getByRole('button',{name:'แก้ไข',exact:true}).first().click();await measure('product-edit');await page.getByRole('button',{name:'ยกเลิก',exact:true}).click();await page.locator('summary').first().click();await measure('product-detail')
 await loaded(page,'categories');await page.getByRole('button',{name:'แก้ไข',exact:true}).first().click();await measure('category-edit')
 await loaded(page,'locations');await page.getByRole('button',{name:'ดูสินค้า',exact:true}).first().click();await expect(page.getByText('DEMO-001',{exact:true})).toBeVisible();await measure('location-detail')
 await loaded(page,'stock');await measure('stock-receipt-form')
 await loaded(page,'count');const picker=page.getByRole('combobox',{name:'เปิดรอบเดิม',exact:true});await picker.selectOption({index:1});await expect(page.locator('[id^=count-row-]').first()).toBeVisible();await measure('count-active')
 await loaded(page,'excel');await page.locator('input[type=file]').setInputFiles(resolve(root,'.local/ux-audit/import.xlsx'));await page.getByRole('button',{name:'ตรวจสอบและแสดงตัวอย่าง',exact:true}).click();await expect(page.getByText('แถว 3 · ไม่มี SKU',{exact:true})).toBeVisible();await measure('excel-validation-preview')
 page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'เปิดเมนู',exact:true}).click();await measure('drawer-open');await page.keyboard.press('Escape')
 writeFileSync(resolve(root,'docs/evidence/UX_EXPANDED_STATES.json'),JSON.stringify({actual_backend:true,rows,axe_violations:0,physical_keyboard:'NOT_VERIFIED: Chromium viewport is not physical OS keyboard'},null,2))
})

test('real role selector/login/logout, staff visibility and denied deep links; login responsive/a11y',async({browser})=>{
 test.setTimeout(90000)
 const accounts=JSON.parse(readFileSync(resolve(root,'.local/demo/accounts.json'),'utf8')) as {role:string;password:string}[]
 const result=[]
 for(const role of ['owner','manager','staff']){
  const c=await browser.newContext({storageState:{cookies:[],origins:[]},baseURL:'http://127.0.0.1:5177',viewport:{width:1280,height:800}});const p=await c.newPage();await p.goto('/');await expect(p.getByRole('combobox',{name:'บทบาท / Role',exact:true})).toBeVisible()
  if(role==='owner'){for(const width of widths){const height=width===844?390:800;await p.setViewportSize({width,height});expect(await p.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);if([320,768,1280].includes(width))await p.screenshot({path:resolve(root,'.local/ux-audit/after/login-'+width+'.png')});}const a=await new AxeBuilder({page:p}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(a.violations).toEqual([])}
  await p.setViewportSize({width:1280,height:800});await p.getByRole('combobox',{name:'บทบาท / Role',exact:true}).selectOption(role);await expect(p.getByLabel('Username',{exact:true})).toHaveValue(role+'.demo');await p.getByLabel('รหัสผ่าน',{exact:true}).fill(accounts.find(a=>a.role===role)!.password);const login=p.waitForResponse(r=>r.url().endsWith('/api/login')&&r.request().method()==='POST');await p.getByRole('button',{name:'เข้าสู่ระบบ',exact:true}).click();expect((await login).status()).toBe(200);await expect(p.getByRole('navigation',{name:'เมนูหลัก'})).toBeVisible()
  if(role==='staff'){await expect(p.getByRole('link',{name:'หมวดหมู่',exact:true})).toHaveCount(0);await p.goto('/#/excel');await expect(p.getByRole('heading',{name:'ไม่มีสิทธิ์เข้าถึงหน้านี้'})).toBeVisible();await p.goto('/#/stock');await expect(p.getByRole('heading',{name:'สต๊อกและประวัติ'})).toBeVisible();await expect(p.getByRole('button',{name:'บันทึกรายการ',exact:true})).toHaveCount(0);await p.goto('/#/count');await p.getByRole('combobox',{name:'เปิดรอบเดิม',exact:true}).selectOption({index:1});await expect(p.locator('[id^=count-row-]').first()).toBeVisible();await expect(p.getByRole('button',{name:'ปรับยอดตามผลต่าง',exact:true})).toHaveCount(0)}
  const logout=p.waitForResponse(r=>r.url().endsWith('/api/logout'));await p.getByRole('button',{name:'ออกจากระบบ',exact:true}).click();expect((await logout).status()).toBe(204);await expect(p.getByRole('button',{name:'เข้าสู่ระบบ',exact:true})).toBeVisible();result.push({role,login:200,logout:204,result:'PASS'});await c.close()
 }
 writeFileSync(resolve(root,'docs/evidence/UX_ROLE_LOGIN_RESULTS.json'),JSON.stringify({target:'Local Demo',result,credentials_logged:false,login_viewports:widths,axe_violations:0},null,2))
})

test('Manager saves existing Demo category/location unchanged and Staff records count without stock adjustment',async({browser})=>{
 const checks=[]
 for(const role of ['manager','staff']){
  const c=await browser.newContext({storageState:resolve(root,'.local/ux-audit/auth/'+role+'.json'),baseURL:'http://127.0.0.1:5177'});const p=await c.newPage()
  if(role==='manager'){
   await loaded(p,'categories');await p.getByRole('button',{name:'แก้ไข',exact:true}).first().click();const saved=p.waitForResponse(r=>new URL(r.url()).pathname.startsWith('/api/categories/')&&r.request().method()==='PUT');await p.getByRole('button',{name:'บันทึก',exact:true}).click();expect((await saved).status()).toBe(200);await expect(p.getByRole('status')).toContainText('บันทึกหมวดหมู่แล้ว')
   await loaded(p,'locations');await p.getByRole('button',{name:'แก้ไข',exact:true}).first().click();const location=p.waitForResponse(r=>new URL(r.url()).pathname.startsWith('/api/locations/')&&r.request().method()==='PUT');await p.getByRole('button',{name:'บันทึก',exact:true}).click();expect((await location).status()).toBe(200);await expect(p.getByRole('status')).toContainText('บันทึกตำแหน่งแล้ว');checks.push({role,category_update:200,location_update:200,values:'unchanged existing DEMO data'})
  }else{
   await loaded(p,'count');await p.getByRole('combobox',{name:'เปิดรอบเดิม',exact:true}).selectOption({index:1});const row=p.locator('[id^=count-row-]').first();await expect(row).toBeVisible();await expect(p.getByRole('button',{name:'ปรับยอดตามผลต่าง',exact:true})).toHaveCount(0);await row.locator('input[type=number]').fill('0');const saved=p.waitForResponse(r=>/\/(counts|recounts)$/.test(new URL(r.url()).pathname)&&r.request().method()==='POST');await row.getByRole('button',{name:/บันทึกจำนวน|บันทึกตรวจซ้ำ/}).click();expect((await saved).status()).toBe(201);await expect(p.getByRole('status')).toContainText('ยอดสต๊อกไม่เปลี่ยน');const balance=await c.request.get('/api/stock/balances?search=DEMO-001',{headers:{Accept:'application/json',Origin:'http://127.0.0.1:5177'}});expect(balance.status()).toBe(200);expect(Number((await balance.json()).data[0].quantity)).toBe(0);checks.push({role,count_save:201,stock_unchanged:true,correction_hidden:true})
  }await c.close()
 }
 writeFileSync(resolve(root,'docs/evidence/UX_ROLE_OPERATIONS.json'),JSON.stringify({actual_backend:true,checks},null,2))
})
