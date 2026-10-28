# PartShelf — Requirements Register (83 ข้อ)

> **ประเภท:** เอกสารอ้างอิงเพื่อปลดข้อค้าง P00 (จากคำตอบที่ผู้ใช้ยืนยันในบทสนทนา)
> **สถานะ:** `RECONCILED_TO_OWNER_MASTER_PROMPT` — ตรวจครบ 83 แถวเทียบกับ Scope/Plan หลัง Owner อนุมัติ Master Execution Prompt ใน 2026-10-28; P00 revision กำลังตรวจหลักฐาน
> **ขอบเขต MVP ล่าสุด:** สินค้า, สต๊อก, ตำแหน่งจัดเก็บ, ตรวจนับอย่างง่าย, Excel import/export เฉพาะงานหลัก, barcode/QR/ฉลากพื้นฐาน, PWA online-only และ Dashboard สรุปตาม prompt; ดู `MVP_SCOPE.md`
> **หมายเหตุ:** การอนุมัติเริ่ม P01 และสถานะ DONE อ้างอิงจาก Owner decision และหลักฐานใน `DECISIONS.md`/`TEST_RESULTS.md`; ทะเบียนนี้เป็น traceability ไม่ใช่การอนุมัติเพียงลำพัง

## วิธีอ่าน

- **MVP** = ฟังก์ชันธุรกิจในหนึ่งใน 4 โดเมนหลัก: สินค้า, สต๊อก, ตำแหน่ง, ตรวจนับอย่างง่าย
- **MVP-PARTIAL** = แถวที่รวมส่วน in-scope กับส่วน Future; ใช้เฉพาะส่วนที่ระบุใน crosswalk
- **FOUNDATION** = พื้นฐานทางเทคนิค/ความปลอดภัยขั้นต่ำ; **FUTURE** = อยู่นอก MVP; **SCOPE-LIMIT** = ข้อจำกัด/การไม่สร้างฟังก์ชัน
- **คำตอบ** = ตัวเลือกที่ผู้ใช้ยืนยันในบทสนทนา; `—` ใช้สำหรับข้อแรกที่ยืนยันด้วยข้อความ ไม่ได้เลือกตัวอักษร

**ขอบเขตที่มีผล:** Owner อนุมัติ Master Execution Prompt ล่าสุดให้ขยาย MVP ตามรายการที่ระบุไว้ใน prompt; รายการนอกขอบเขตยังเป็น Future Backlog. แท็กเดิมในคอลัมน์ระยะเป็นร่างและต้องอ่านร่วมกับ crosswalk ฉบับปรับปรุงด้านล่าง.

## ตารางข้อกำหนด

| # | คำตอบ | ข้อกำหนดที่ยืนยัน | ระยะ |
|---:|:---:|---|---|
| 1 | — | ร้านเดียวในปัจจุบัน ออกแบบเผื่อหลายสาขาในอนาคต | `MVP-PARTIAL` |
| 2 | B | สต๊อกพร้อมบันทึกการขายแบบง่าย (ไม่ใช่ POS เต็มรูปแบบ) | `FUTURE` |
| 3 | A | สินค้า 1 SKU มีตำแหน่งจัดเก็บเดียวต่อสาขา | `MVP` |
| 4 | B | หมวดหมู่หลักและหมวดหมู่ย่อย 2 ระดับ ผู้ดูแลแก้ไขได้ | `MVP` |
| 5 | B | เชื่อมสินค้า 1 รายการกับยี่ห้อ รุ่น ปี และเครื่องยนต์รถได้หลายรุ่น | `MVP` |
| 6 | C | สแกน Barcode/QR และสร้าง/พิมพ์ฉลากร้านเอง | `MVP` |
| 7 | A | รับสินค้าแบบง่าย เลือกสินค้าและจำนวน ไม่บังคับผู้จำหน่าย/ต้นทุน | `MVP` |
| 8 | A | เดิมตัดสต๊อกออกเมื่อขายเท่านั้น; มีข้อยกเว้นภายหลังตามข้อ 33, 39 และ 46 | `FUTURE` |
| 9 | B | ตรวจนับเทียบยอดและเก็บประวัติ ไม่ปรับยอดอัตโนมัติ | `MVP` |
| 10 | B | ขายหลาย SKU ในบิลเดียว คำนวณรวม ตัดสต๊อกและเก็บประวัติ | `FUTURE` |
| 11 | C | ยกเลิกการขายทั้งบิลและคืนสินค้าบางส่วน พร้อมประวัติ/คืนสต๊อกตามเงื่อนไขข้อ 34 | `FUTURE` |
| 12 | B | ราคาขายมาตรฐาน และผู้มีสิทธิ์เปลี่ยนราคาเฉพาะรายการขายได้ | `FUTURE` |
| 13 | B | สต๊อกขั้นต่ำรายสินค้าและแจ้งเตือนใกล้หมด/หมดใน Dashboard | `MVP` |
| 14 | B | บทบาท Owner, Manager, Staff แยกสิทธิ์ตามหน้าที่ | `FOUNDATION` |
| 15 | C | รายงานสต๊อกและยอดขายครบถ้วน กรองช่วงวันที่ ส่งออก Excel/PDF และพิมพ์ได้ | `FUTURE` |
| 16 | B | สินค้าเดียวรองรับหลายหน่วยและอัตราแปลงหน่วยสำหรับรับเข้า/ขาย | `FUTURE` |
| 17 | B | ตำแหน่ง Zone → Shelf → Level → Bin จัดการได้ในระบบ | `MVP` |
| 18 | C | เพิ่มสินค้าในเว็บ Import Excel และ Bulk Edit ข้อมูลสินค้า | `MVP-PARTIAL` |
| 19 | B | ยอดยกมาราย SKU หรือ Excel พร้อมเก็บประวัติ ไม่ถือเป็นการรับเข้าใหม่ | `MVP` |
| 20 | A | ห้ามขายเกินสต๊อกและห้ามสต๊อกติดลบ | `MVP-PARTIAL` |
| 21 | C | ค้นหาแบบละเอียดพร้อมอะไหล่ทดแทนที่ตรวจสอบความเข้ากันได้แล้ว | `FUTURE` |
| 22 | A | ไม่ติดตามล็อต วันผลิต วันหมดอายุ หรือ Serial Number | `SCOPE-LIMIT` |
| 23 | C | ภาพสินค้าได้หลายรูป เลือกภาพปกและจัดลำดับภาพได้ | `FUTURE` |
| 24 | C | รับประกันและเคลมเต็มรูปแบบ พร้อมติดตามสถานะ/ประวัติ | `FUTURE` |
| 25 | A | ไม่มีทะเบียนผู้จำหน่าย; กรอกชื่อผู้จำหน่ายเป็นข้อความในเคลมได้ | `FUTURE` |
| 26 | A | ไม่มีทะเบียนลูกค้า; ตรวจจากเลขขาย กรอกชื่อ/โทรเฉพาะเคลมตามจำเป็น | `FUTURE` |
| 27 | C | Responsive Web + PWA ติดตั้งไอคอน ใช้งานออนไลน์ | `MVP` |
| 28 | D | ยังไม่เลือก Hosting; ออกแบบให้รองรับ Local Server และ Cloud/VPS | `MVP-PARTIAL` |
| 29 | B | Audit Log รายการสำคัญ ผู้ดำเนินการ เวลา ค่าก่อน/หลัง ค้นหาได้ | `FOUNDATION` |
| 30 | B | สำรองฐานข้อมูล/รูปภาพอัตโนมัติทุกวัน แยกพื้นที่ และกู้คืนได้ | `FUTURE` |
| 31 | A | เก็บ Backup ย้อนหลัง 7 วัน (ไม่ใช่การลบข้อมูลหลัก) | `FUTURE` |
| 32 | C | สถานะวงจรชีวิตสินค้า ระบุรุ่นทดแทน เหตุผลและประวัติการเปลี่ยน | `FUTURE` |
| 33 | B | Owner/Manager ปรับยอดหลังตรวจสอบโดยระบุเหตุผลและ Audit Log; ไม่ปรับจากผลนับอัตโนมัติ | `MVP` |
| 34 | B | ตรวจสภาพสินค้าคืน เพิ่มสต๊อกเฉพาะที่ขายต่อได้ บันทึกสินค้าชำรุด | `FUTURE` |
| 35 | B | คำนวณ/บันทึกเงินคืน ยอดสุทธิ วิธี/เวลา/ผู้ดำเนินการ โดยคืนเงินจริงภายนอก | `FUTURE` |
| 36 | B | บันทึกวิธีชำระ ยอดรับ และเวลา โดยจ่ายเงินจริงภายนอก ไม่เชื่อม Gateway | `FUTURE` |
| 37 | A | ไม่ต้องออกใบเสร็จหรือเอกสารการขาย เก็บประวัติในระบบ | `FUTURE` |
| 38 | A | ผู้ใช้กำหนด SKU เอง และต้อง Unique ทั้งระบบ | `MVP` |
| 39 | B | Owner/Manager แก้หรือยกเลิกรับเข้าผิดพลาด ไม่ทำให้ติดลบ พร้อมเหตุผล/Audit | `MVP` |
| 40 | A | แก้ตำแหน่งโดยตรงตามสิทธิ์ พร้อม Audit โดยไม่เปลี่ยนสต๊อก | `MVP` |
| 41 | A | ระยะแรก UI สาขาเดียว แต่ Schema แยก Branch ในอนาคต | `MVP-PARTIAL` |
| 42 | A | Login Username/Password แยกคน; ความปลอดภัยพื้นฐานและ Password Hash | `FOUNDATION` |
| 43 | B | ต้นทุนมาตรฐานรายสินค้า กำไรขั้นต้นและมูลค่าสต๊อกโดยประมาณ | `FUTURE` |
| 44 | B | เฉพาะ Owner/Manager เห็นต้นทุน กำไร มูลค่าสต๊อก; บังคับสิทธิ์ใน API/Export | `FUTURE` |
| 45 | B | Owner/Manager แก้ต้นทุนมาตรฐานได้พร้อมเหตุผลและ Audit; ไม่แก้กำไรเก่าย้อนหลัง | `FUTURE` |
| 46 | B | ยืนยันเปลี่ยนสินค้าเคลมแล้วตัดสต๊อก ไม่เพิ่มยอดขาย ป้องกันตัดซ้ำ | `FUTURE` |
| 47 | C | ประกันสินค้าทดแทนเลือกเดิมที่เหลือ/เริ่มใหม่/วันกำหนดเอง พร้อมประวัติ | `FUTURE` |
| 48 | B | รับสินค้าทดแทนจากผู้จำหน่ายเข้าสต๊อกโดยอ้างเคลม ป้องกันรับซ้ำ | `FUTURE` |
| 49 | A | บันทึกประวัติสินค้าชำรุดเท่านั้น ไม่ติดตามยอดชำรุดคงค้าง | `FUTURE` |
| 50 | C | รอบตรวจนับมีตาราง มอบหมาย ตรวจซ้ำ และติดตามความคืบหน้า ไม่ปรับยอดอัตโนมัติ | `FUTURE` |
| 51 | A | รับเข้า/ขายระหว่างตรวจนับได้ ต้องเทียบยอดและ Movement ณ เวลาเดียวกัน | `MVP` |
| 52 | B | Draft Sale กลับมาแก้ไขได้ ไม่จอง/ตัดสต๊อก ตรวจใหม่เมื่อยืนยัน | `FUTURE` |
| 53 | B | ยืนยัน Draft Sale ด้วยราคามาตรฐานล่าสุด แจ้งราคาเปลี่ยน ยกเว้นราคาพิเศษที่มีสิทธิ์ | `FUTURE` |
| 54 | A | หน่วยรองใช้ราคาหน่วยฐาน × อัตราแปลง | `FUTURE` |
| 55 | A | Barcode/QR หนึ่งชุดต่อ SKU; หลังสแกนผู้ใช้เลือกหน่วยเอง | `MVP` |
| 56 | B | Owner/Manager เปลี่ยนอัตราแปลงให้มีผลต่อรายการใหม่เท่านั้น พร้อม Audit | `FUTURE` |
| 57 | B | Owner/Manager เปลี่ยน SKU ได้ ตรวจซ้ำ เก็บความเชื่อมโยง/ประวัติ/Audit | `MVP` |
| 58 | B | Barcode/QR เดิมยังสแกนได้หลังเปลี่ยน SKU เพราะผูก Internal Product ID | `MVP` |
| 59 | C | ฉลากพิมพ์ A4/Label Printer เลือกขนาด จำนวนและ Batch Printing | `MVP` |
| 60 | C | Label Template สำเร็จรูปและ Custom Label Designer แบบลากวาง | `FUTURE` |
| 61 | A | UI Modern Clean ขาว/เทาอ่อน/น้ำเงิน | `FOUNDATION` |
| 62 | A | Sidebar ซ้ายยุบ/ขยายได้ และ Mobile Drawer | `FUTURE` |
| 63 | B | Dashboard แยกตามบทบาทและสิทธิ์ | `MVP` |
| 64 | A | UI ภาษาไทยเป็นหลัก แต่กรอกชื่อ/ข้อมูลอะไหล่ภาษาอังกฤษได้ | `FOUNDATION` |
| 65 | B | แจ้งเตือนงานเคลม/ตรวจนับค้างใน Dashboard ตามบทบาท ไม่ส่ง LINE/Email | `FUTURE` |
| 66 | A | ไม่ต้องมี Product Bundle/Kit; เพิ่มสินค้าเป็นราย SKU | `SCOPE-LIMIT` |
| 67 | A | คำตอบล่าสุด: ไม่มีระบบส่วนลด; ใช้การแก้ราคาตามสิทธิ์ข้อ 12 เท่านั้น (แทนคำตอบ B ก่อนหน้า) | `FUTURE` |
| 68 | A | ล็อกบิลที่ยืนยัน; หากผิดให้ยกเลิก/คืนและสร้างใหม่ รักษาประวัติ | `FUTURE` |
| 69 | B | ขายค้างชำระได้ บันทึกยอดรับ/ค้างและรับเพิ่มตามเลขที่ขาย | `FUTURE` |
| 70 | C | บิลค้างชำระบังคับชื่อลูกค้าและเบอร์โทร; ไม่ทำทะเบียนกลาง | `FUTURE` |
| 71 | A | ไม่ต้องกำหนดวันครบชำระ ติดตามยอดค้างด้วยเลขที่ขาย | `FUTURE` |
| 72 | B | คืนสินค้าในบิลค้างชำระต้องคำนวณยอดค้าง/เงินคืนและให้ Owner/Manager ยืนยัน | `FUTURE` |
| 73 | A | แสดงจำนวนในระบบให้พนักงานเห็นก่อนตรวจนับ | `MVP` |
| 74 | C | ตรวจนับได้ทั้งสแกนแล้วกรอกจำนวน และสแกนนับทีละชิ้น | `MVP` |
| 75 | C | เลือกชั้นเพื่อแสดง SKU หรือสแกนตรวจนับ พร้อมสถานะนับแล้ว/ยังไม่นับ/หาไม่พบ | `MVP` |
| 76 | B | พบสินค้าผิดตำแหน่งให้บันทึก แล้ว Owner/Manager แก้จากหน้าสินค้าพร้อม Audit | `MVP` |
| 77 | B | พบสินค้าที่ไม่มีในระบบให้บันทึกข้อมูลชั่วคราวพร้อมรูป/จำนวน/ตำแหน่ง รอตรวจสอบก่อนเพิ่มสต๊อก | `FUTURE` |
| 78 | B | ตรวจแล้วหาไม่พบให้บันทึกยอด 0 แสดงผลต่าง รอตรวจนับซ้ำ ไม่ปรับยอดอัตโนมัติ | `MVP` |
| 79 | B | แยกผลการนับครั้งแรก/นับซ้ำ เก็บทุกครั้ง Owner/Manager เลือกผลสุดท้าย ไม่บวกซ้ำ | `MVP` |
| 80 | B | Owner/Manager ปิดรอบไม่ครบได้พร้อมเหตุผล; ยังไม่นับ ≠ นับได้ 0 | `FUTURE` |
| 81 | B | นำรายการที่ยังไม่ได้นับไปสู่รอบใหม่ได้พร้อมประวัติ; ไม่ปรับสต๊อกอัตโนมัติ | `FUTURE` |
| 82 | C | เริ่มตรวจนับได้ทันที หรือเลือกนับตามชั้น โดยไม่บังคับ workflow ซับซ้อน | `MVP` |
| 83 | C | ยังไม่มี Excel มาตรฐาน ให้ออกแบบ template ง่ายแบบหนึ่งชีตสำหรับนำเข้าสินค้า | `MVP` |

## ผล Reconciliation กับ `MVP_SCOPE.md` และ `IMPLEMENTATION_PLAN.md`

ผลนี้ประเมินเนื้อหา 83 แถวที่มีอยู่ในไฟล์ ไม่ได้พิสูจน์ว่าคำตอบแต่ละข้อถอดจากบทสนทนาต้นฉบับครบถ้วน

### สรุปการจัดกลุ่ม

| ผลจัดกลุ่ม | จำนวน | หมายเลขข้อ |
|---|---:|---|
| `MVP` | 28 | 3, 4, 5, 6, 7, 9, 13, 17, 19, 27, 33, 38, 39, 40, 51, 55, 57, 58, 59, 63, 73, 74, 75, 76, 78, 79, 82, 83 |
| `MVP-PARTIAL` | 5 | 1, 18, 20, 28, 41 |
| `FOUNDATION` | 5 | 14, 29, 42, 61, 64 |
| `SCOPE-LIMIT` | 2 | 22, 66 |
| `FUTURE` | 43 | 2, 8, 10–12, 15–16, 21, 23–26, 30–32, 34–37, 43–50, 52–54, 56, 60, 62, 65, 67–72, 77, 80–81 |
| **รวม** | **83** | ครบหนึ่งครั้งต่อข้อ |

### Traceability จากข้อกำหนดสู่ Scope/Phase

| Requirement | ขอบเขตที่เก็บใน MVP | เอกสาร/Phase |
|---|---|---|
| 3, 17, 40 | หนึ่งตำแหน่งต่อสินค้า; Zone/Shelf/Level/Bin; ย้ายตำแหน่งไม่เปลี่ยนยอด | `MVP_SCOPE.md` Locations; P05 |
| 4, 38, 57 | หมวดสองระดับ, SKU ไม่ซ้ำ, แก้ SKU โดยคง `product_id` | Products; P04 |
| 5 | บันทึก fitment ยี่ห้อ/รุ่น/ปี/เครื่องยนต์เพื่อค้นหารถที่รองรับ; ไม่รวม recommendation/อะไหล่ทดแทน | Products; P04 |
| 7, 13, 19, 39 | รับเข้า/ยอดตั้งต้น, minimum quantity, แก้รายการรับเข้าผิดด้วย movement | Stock; P06–P07 |
| 20 | เฉพาะกฎยอดไม่ติดลบ; ไม่มีฟังก์ชันขายหรือ oversell ใน MVP | Stock integrity; P02/P06 |
| 33 | ปรับยอดแยกจากผลนับ เฉพาะสิทธิ์ที่กำหนด พร้อมเหตุผล/audit | Stock; P03/P07 |
| 9, 73, 76, 78–79, 82 | แสดงยอดอ้างอิง, บันทึกผล/ผิดตำแหน่ง/หาไม่พบ, แยก recount, เริ่มทันทีหรือเลือกชั้น | Simple count; P08–P09 |
| 51 | เทียบยอดกับ movement ณ เวลาเดียวกัน | Stock/count integrity; P09 |
| 1, 41 | ใช้ร้านเดียวก่อน; การรองรับหลายสาขา/branch UI เลื่อนออก | Foundation/P01–P02; multi-branch Future |
| 18, 83 | สร้าง/แก้สินค้าในเว็บ P04; template/import/preview/validation/export ที่กำหนดใน prompt อยู่ใน P07; bulk edit ยังคง Future | Products/Excel; P04/P07 |
| 27 | Responsive web และ PWA ติดตั้งได้แบบ online-only; offline/push notifications ยัง Future | P10 |
| 28 | Docker/local เป็นเป้าหมายแรก; Cloud/VPS ยังไม่อยู่ใน P01 | Foundation/P01; Cloud Future |
| 6, 55, 58–59, 74–75 | Barcode/QR เชื่อม Internal Product ID ให้สแกนได้หลังแก้ SKU; กล้อง/USB ที่รองรับ และฉลากพื้นฐาน A4/Label Printer ไม่ทำ designer | Barcode/labels/count; P08/P10 |
| 63 | Dashboard แสดงจำนวนสินค้า/stock/low/out/variance/latest counts ตามสิทธิ์ ไม่ใช่ full reports | Dashboard; P11 |
| 14, 29, 42, 61, 64 | ขั้นต่ำสำหรับ login/permission/audit และ UI ไทยพื้นฐาน ไม่เพิ่มโมดูลธุรกิจ | Foundation; P03/P07/P10 |
| 22, 66 | ไม่ทำ lot/expiry/serial หรือ bundle/kit | Scope limits; no feature phase |
| ทุกข้อที่เหลือ | ไม่อยู่ใน capability ที่ Owner ระบุ หรือเป็นการขยาย/operations ขั้นสูง | Future Backlog; ไม่มี MVP phase |

### ความขัดแย้งที่แก้

1. Master Execution Prompt ล่าสุดนำ Excel import/export ที่กำหนด, barcode/QR/ฉลากพื้นฐาน, PWA online-only, vehicle fitment และ Dashboard สรุปกลับเข้า MVP; bulk edit, custom label designer, offline, full reports และ backup retention ยัง Future.
2. ข้อ 50, 80–81 มีตาราง มอบหมาย ปิดรอบและ carry-forward ที่ขัดกับ workflow ง่าย; เลื่อนเป็น Future. ข้อ 82 เป็น workflow MVP: เริ่มทันทีหรือเลือกชั้น.
3. ข้อ 2, 8, 10–12, 35–37, 52–54, 67–72 แตะ sales/finance; เลื่อนทั้งหมดเป็น Future. การรับเข้า/ปรับ stock แยกจากการขาย.
4. ข้อ 20 ระบุห้ามขายเกินสต๊อก แต่ไม่มี sales ใน MVP; เก็บเฉพาะ invariant ว่ายอดคงเหลือต้องไม่ติดลบ.
5. ข้อ 1/41 รวมถึงหลายสาขา: ใช้ร้านเดียวก่อน; ไม่บังคับ schema/UI หลายสาขาใน initial release. เพิ่มภายหลังด้วยการอนุมัติ scope change.
6. ข้อ 18 คง `MVP-PARTIAL`: template/import ที่กำหนดอยู่ใน P07 แต่ bulk edit ไม่รวม; ข้อ 1/20/28/41 คง partial ตามข้อจำกัดหลายสาขา/ขาย.

### Future Backlog

Full list is the set of all rows tagged `FUTURE` in the table above. It includes sales/finance, bulk product editing, custom label designer, offline/PWA notifications, advanced count workflow, multi-branch expansion, full sales/reporting, daily backup retention, cloud deploy, customer/supplier/claims, replacement/compatibility recommendation beyond captured fitment, costing, unit conversion, bundles and advanced product media.

### Verification limits and status

- Register row IDs were checked for exact coverage `1..83`, no duplicates, and one classification per row; current counts are 28 MVP, 5 MVP-PARTIAL, 5 FOUNDATION, 2 SCOPE-LIMIT and 43 FUTURE; detailed evidence is in `TEST_RESULTS.md`.
- Register-to-scope crosswalk: `PASS` for the written file contents and Owner's latest Master Prompt boundary.
- Fidelity of the register against the original conversation: `NOT_TESTED` by this file comparison.
- Owner's Master Execution Prompt explicitly authorized local/test development through P12 and revised the MVP boundary. This revision includes 28 MVP rows, 5 MVP-PARTIAL, 5 FOUNDATION, 2 SCOPE-LIMIT and 43 FUTURE (total 83). Source fidelity to the original conversation remains `NOT_TESTED`.
