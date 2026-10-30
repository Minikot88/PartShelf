import { useRef, useState } from 'react'
import { objectUpload } from './fileTransport'
import { useUnsavedChanges } from './useUnsavedChanges'

type RowPreview = { row: number; sku: string; name: string; errors: string[] }
type Preview = { token: string; valid_count: number; error_count: number; rows: RowPreview[] }

function csrfToken(): string | undefined {
  const value = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))
  return value ? decodeURIComponent(value.slice('XSRF-TOKEN='.length)) : undefined
}

async function refreshCsrf(): Promise<string> {
  const response = await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  const token = csrfToken()
  if (!response.ok || !token) throw new Error('เริ่มการยืนยันตัวตนไม่สำเร็จ')
  return token
}

async function saveDownload(path: string, name: string): Promise<void> {
  const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' } })
  if (!response.ok) throw new Error('ดาวน์โหลดไฟล์ไม่สำเร็จ')
  const fileResponse = response.headers.get('content-type')?.includes('application/json')
    ? await fetch(((await response.json()) as { download_url: string }).download_url, { credentials: 'omit' })
    : response
  if (!fileResponse.ok) throw new Error('ดาวน์โหลดไฟล์ไม่สำเร็จ')
  const blob = await fileResponse.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url; link.download = name; link.click()
  URL.revokeObjectURL(url)
}

export default function ProductExcelPanel() {
  const fileInput = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<Preview | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  useUnsavedChanges(!!file || !!preview)

  async function makePreview(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice(''); setPreview(null)
    try {
      const csrf = await refreshCsrf()
      const uploadToken = await objectUpload(file as File, 'workbook', csrf)
      const form = new FormData()
      form.append('file', file as File)
      const response = await fetch('/api/products/import/preview', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf, ...(uploadToken ? { 'Content-Type': 'application/json' } : {}) }, body: uploadToken ? JSON.stringify({ upload_token: uploadToken }) : form })
      const body = (await response.json()) as Preview & { message?: string; errors?: Record<string, string[]> }
      if (!response.ok) throw new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'ตรวจไฟล์ไม่สำเร็จ')
      setPreview(body)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'ตรวจไฟล์ไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  async function confirmImport() {
    if (!preview) return
    setBusy(true); setError('')
    try {
      const response = await fetch('/api/products/import/confirm', {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': await refreshCsrf() },
        body: JSON.stringify({ token: preview.token }),
      })
      const body = (await response.json()) as { imported_count?: number; message?: string }
      if (!response.ok) throw new Error(body.message ?? 'นำเข้าสินค้าไม่สำเร็จ')
      setNotice(`นำเข้าสินค้า ${body.imported_count ?? 0} รายการแล้ว`); setPreview(null); setFile(null); if (fileInput.current) fileInput.current.value = ''
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'นำเข้าสินค้าไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  async function download(path: string, filename: string) {
    setBusy(true); setError(''); setNotice('')
    try { await saveDownload(path, filename); setNotice('เตรียมไฟล์ดาวน์โหลดแล้ว') } catch (cause) { setError(cause instanceof Error ? cause.message : 'ดาวน์โหลดไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  return <section className="mx-auto max-w-6xl px-5 pb-12 sm:px-8" aria-labelledby="excel-title">
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <h2 className="text-2xl font-bold" id="excel-title">นำเข้าและส่งออก Excel</h2>
      <p className="mt-1 text-sm text-slate-600">ใช้แม่แบบสินค้าแบบชีตเดียว ตรวจแถวและรายการซ้ำก่อนยืนยันนำเข้า รองรับ QUANTITY / AVAILABILITY_ONLY ช่อง quantity ว่างคือยังไม่กำหนดยอด และสินค้าติดตามสถานะต้องเว้น quantity ว่าง ใช้สถานะ IN_STOCK / OUT_OF_STOCK / UNKNOWN และ reorder_needed เป็น 0 หรือ 1 ยังอ่านไฟล์เดิม 6 คอลัมน์ได้ ไม่เขียนทับ SKU เดิม</p>
      <div className="mt-4 flex flex-wrap gap-2">
        <button className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium disabled:opacity-60" disabled={busy} onClick={() => void download('/api/products/import/template', 'products-template.xlsx')} type="button">ดาวน์โหลดแม่แบบ</button>
        <button className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium disabled:opacity-60" disabled={busy} onClick={() => void download('/api/products/export', 'products.xlsx')} type="button">ส่งออกสินค้า</button>
        <button className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium disabled:opacity-60" disabled={busy} onClick={() => void download('/api/stock-counts/export', 'count-results-all.xlsx')} type="button">ส่งออกผลตรวจนับ</button>
      </div>
      <form className="mt-5 flex flex-wrap items-end gap-3" onSubmit={makePreview}>
        <label className="text-sm font-medium">ไฟล์ Excel (.xlsx)<input ref={fileInput} accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" className="mt-1 block max-w-full rounded-lg border border-slate-300 p-2 text-sm" onChange={(event) => { setFile(event.target.files?.[0] ?? null); setPreview(null) }} required type="file" /></label>
        <button className="rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white disabled:opacity-60" disabled={busy || !file} type="submit">{busy ? 'กำลังตรวจ…' : 'ตรวจสอบและแสดงตัวอย่าง'}</button>
      </form>
      {preview && <div className="mt-5 rounded-xl bg-slate-50 p-4">
        <p className="font-semibold">ใช้ได้ {preview.valid_count} แถว · ต้องแก้ไข {preview.error_count} แถว</p>
        <ul className="mt-3 max-h-64 space-y-2 overflow-auto text-sm">{preview.rows.map((row) => <li className={`rounded-lg p-2 ${row.errors.length ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800'}`} key={row.row}><strong>แถว {row.row} · {row.sku || 'ไม่มี SKU'}</strong> — {row.errors.length ? row.errors.join(' ') : row.name}</li>)}</ul>
        <button className="mt-4 rounded-lg bg-emerald-700 px-4 py-2.5 font-semibold text-white disabled:opacity-60" disabled={busy || preview.valid_count === 0} onClick={() => void confirmImport()} type="button">ยืนยันนำเข้าเฉพาะแถวที่ผ่าน</button>
      </div>}
      {error && <p className="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{error}</p>}{notice && <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{notice}</p>}
    </div>
  </section>
}
