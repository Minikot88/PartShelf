import {test,expect} from '@playwright/test'
import {writeFileSync} from 'node:fs'
test('real internal code, Code128 QR render and print layout',async({page})=>{
 await page.goto('/#/labels');await expect(page.getByRole('heading',{name:'รหัสสินค้าและฉลากพิมพ์',exact:true})).toBeVisible()
 const choice=page.getByRole('checkbox').first();await choice.check()
 await page.emulateMedia({media:'print'}); const sheet=page.locator('.partshelf-label-sheet');await expect(sheet.locator('svg').first()).toBeVisible();await expect(sheet.locator('canvas').first()).toBeVisible()
 expect(await sheet.locator('canvas').first().evaluate((e:HTMLCanvasElement)=>e.width>0&&e.getContext('2d')!.getImageData(0,0,e.width,e.height).data.some(v=>v>0))).toBe(true)
 await page.emulateMedia({media:'screen'}); const code=(await page.locator('li').filter({has:page.getByRole('checkbox')}).first().locator('code').innerText()).trim()
 await page.getByLabel('สแกนหรือพิมพ์รหัสภายใน',{exact:true}).fill(code);const resolved=page.waitForResponse(r=>r.url().includes('/api/products/resolve'));await page.getByRole('button',{name:'ค้นหารหัส',exact:true}).click();expect((await resolved).status()).toBe(200)
 await page.emulateMedia({media:'print'});await expect(sheet).toBeVisible();await page.pdf({path:'../.local/restore-verification/labels.pdf',format:'A4'})
 await page.screenshot({path:'../.local/restore-verification/screenshots/labels-print.png',fullPage:true})
 writeFileSync('../docs/evidence/RESTORE_LABELS.json',JSON.stringify({internal_code:code,resolve:200,barcode_svg:'PASS',qr_canvas:'PASS',print_media_pdf:'PASS',physical_printer:'NOT_VERIFIED',physical_scanner:'NOT_VERIFIED'},null,2))
})



