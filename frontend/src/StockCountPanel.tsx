import { useCallback, useEffect, useState } from 'react'

type Location = { id: number; path: string }
type Role = 'owner' | 'manager' | 'staff'
type CountSummary = { id: number; status: 'open' | 'completed'; started_at: string; started_by: string | null; item_count: number }
type CountAttempt = { attempt_number: number; counted_quantity: string; variance: string; correction: { delta: string; reason: string } | null }
type CountItem = {
  id: number
  product: { id: number; sku: string; name: string; base_unit: string }
  location: string | null
  reference_quantity: string
  reference_at: string
  counted_quantity: string | null
  count_status: 'not_counted' | 'counted_zero' | 'counted'
  variance: string | null
  attempts: CountAttempt[]
  correction: { delta: string; reason: string } | null
}

function xsrfToken(): string | undefined {
  const value = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))
  return value ? decodeURIComponent(value.slice('XSRF-TOKEN='.length)) : undefined
}

async function csrf(): Promise<string> {
  const response = await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  const value = xsrfToken()
  if (!response.ok || !value) throw new Error('เริ่มการยืนยันตัวตนไม่สำเร็จ')
  return value
}

export default function StockCountPanel({ role }: { role: Role }) {
  const canCorrect = role === 'owner' || role === 'manager'
  const [counts, setCounts] = useState<CountSummary[]>([])
  const [locations, setLocations] = useState<Location[]>([])
  const [selectedLocation, setSelectedLocation] = useState('')
  const [selectedCount, setSelectedCount] = useState('')
  const [items, setItems] = useState<CountItem[]>([])
  const [quantities, setQuantities] = useState<Record<number, string>>({})
  const [reasons, setReasons] = useState<Record<number, string>>({})
  const [searchDraft, setSearchDraft] = useState('')
  const [sku, setSku] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const loadCounts = useCallback(async () => {
    const [countsResponse, locationsResponse] = await Promise.all([
      fetch('/api/stock-counts', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
      fetch('/api/locations', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
    ])
    if (!countsResponse.ok || !locationsResponse.ok) throw new Error('โหลดรอบตรวจนับไม่สำเร็จ')
    setCounts(((await countsResponse.json()) as { data: CountSummary[] }).data)
    setLocations(((await locationsResponse.json()) as { data: Location[] }).data)
  }, [])

  const loadCount = useCallback(async (id: string, search = '') => {
    if (!id) return
    const query = search ? `?search=${encodeURIComponent(search)}` : ''
    const response = await fetch(`/api/stock-counts/${id}${query}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('โหลดรายการตรวจนับไม่สำเร็จ')
    const detail = (await response.json()) as { items: CountItem[] }
    setItems(detail.items)
    setQuantities(Object.fromEntries(detail.items.map((item) => [item.id, item.counted_quantity ?? ''])))
  }, [])

  // Synchronize the count picker with the authenticated server session on mount.
  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => { void loadCounts().catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'โหลดรอบตรวจนับไม่สำเร็จ')) }, [loadCounts])

  async function startCount(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('')
    try {
      const response = await fetch('/api/stock-counts', {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': await csrf() },
        body: JSON.stringify(selectedLocation ? { storage_location_id: Number(selectedLocation) } : {}),
      })
      const body = (await response.json()) as { count?: CountSummary; message?: string; errors?: Record<string, string[]> }
      if (!response.ok || !body.count) throw new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'เริ่มตรวจนับไม่สำเร็จ')
      const id = String(body.count.id)
      setSelectedCount(id); setMessage(`เริ่มรอบตรวจนับ #${id} แล้ว`)
      await Promise.all([loadCount(id), loadCounts()])
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'เริ่มตรวจนับไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  async function saveCount(item: CountItem, recount = false) {
    setBusy(true); setError(''); setMessage('')
    try {
      const action = recount ? 'recounts' : 'counts'
      const response = await fetch(`/api/stock-counts/${selectedCount}/items/${item.id}/${action}`, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': await csrf() },
        body: JSON.stringify({ counted_quantity: quantities[item.id] }),
      })
      const body = (await response.json()) as { item?: CountItem; message?: string; errors?: Record<string, string[]> }
      if (!response.ok || !body.item) throw new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'บันทึกยอดนับไม่สำเร็จ')
      await loadCount(selectedCount, searchDraft)
      setMessage(`${recount ? 'บันทึกการตรวจซ้ำ' : 'บันทึก'} ${item.product.sku} แล้ว · ยอดสต๊อกไม่เปลี่ยน`)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'บันทึกยอดนับไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  async function correctCount(item: CountItem) {
    setBusy(true); setError(''); setMessage('')
    try {
      const response = await fetch(`/api/stock-counts/${selectedCount}/items/${item.id}/correction`, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': await csrf(), 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({ reason: reasons[item.id] }),
      })
      const body = (await response.json()) as { correction?: { balance_after: string }; message?: string; errors?: Record<string, string[]> }
      if (!response.ok || !body.correction) throw new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'ปรับยอดไม่สำเร็จ')
      await Promise.all([loadCount(selectedCount, searchDraft), loadCounts()])
      window.dispatchEvent(new Event('partshelf:stock-updated'))
      setMessage(`ปรับยอดตามผลต่างแล้ว · คงเหลือ ${body.correction.balance_after}`)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'ปรับยอดไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  async function lookupSku(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCount) return
    setError(''); setMessage('')
    try {
      const response = await fetch(`/api/stock-counts/${selectedCount}/lookup`, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': await csrf() },
        body: JSON.stringify({ sku }),
      })
      const body = (await response.json()) as { item?: CountItem; status?: string }
      if (!response.ok || !body.item) throw new Error(body.status === 'not_found' ? 'ไม่พบ SKU นี้ในรอบตรวจนับ' : 'ค้นหา SKU ไม่สำเร็จ')
      let item = items.find((row) => row.id === body.item?.id)
      if (!item && searchDraft) {
        setSearchDraft('')
        await loadCount(selectedCount)
        item = body.item
      }
      if (item) {
        document.getElementById(`count-quantity-${item.id}`)?.focus()
        document.getElementById(`count-row-${item.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
      setMessage(`พบ ${body.item.product.sku} · ${body.status === 'not_counted' ? 'ยังไม่นับ' : body.status === 'counted_zero' ? 'นับแล้วได้ศูนย์' : 'นับแล้ว'}`)
      setSku('')
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'ค้นหา SKU ไม่สำเร็จ') }
  }

  async function searchItems(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    try { await loadCount(selectedCount, searchDraft) } catch (cause) { setError(cause instanceof Error ? cause.message : 'ค้นหาไม่สำเร็จ') }
  }

  const statusLabel = { not_counted: 'ยังไม่นับ', counted_zero: 'นับแล้ว · 0', counted: 'นับแล้ว' }
  return <section className="mx-auto max-w-6xl px-5 pb-12 sm:px-8" aria-labelledby="count-title">
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <div><p className="text-sm font-semibold text-blue-700">ตรวจนับอย่างง่าย</p><h2 id="count-title" className="mt-1 text-2xl font-bold">รอบตรวจนับ</h2></div>
      <form className="mt-4 flex flex-wrap items-end gap-3" onSubmit={startCount}>
        <label className="text-sm font-medium">ขอบเขต<select className="mt-1 block min-w-64 rounded-lg border border-slate-300 bg-white p-2.5" onChange={(event) => setSelectedLocation(event.target.value)} value={selectedLocation}><option value="">ตรวจนับทันที · สินค้าทั้งหมด</option>{locations.map((location) => <option key={location.id} value={location.id}>ตำแหน่ง {location.path}</option>)}</select></label>
        <button className="rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white disabled:opacity-60" disabled={busy} type="submit">{busy ? 'กำลังเริ่ม…' : 'เริ่มรอบตรวจนับ'}</button>
        <label className="text-sm font-medium">เปิดรอบเดิม<select className="mt-1 block min-w-56 rounded-lg border border-slate-300 bg-white p-2.5" onChange={(event) => { const id = event.target.value; setSelectedCount(id); setError(''); if (id) void loadCount(id).catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'โหลดรอบไม่สำเร็จ')) }} value={selectedCount}><option value="">เลือกรอบ</option>{counts.map((count) => <option key={count.id} value={count.id}>#{count.id} · {new Date(count.started_at).toLocaleString('th-TH')} · {count.item_count} รายการ</option>)}</select></label>
      </form>
      {selectedCount && <>
        <div className="mt-5 grid gap-3 sm:grid-cols-2">
          <form className="flex gap-2" onSubmit={searchItems}><label className="sr-only" htmlFor="count-search">ค้นหารายการในรอบ</label><input id="count-search" className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setSearchDraft(event.target.value)} placeholder="ค้นหา SKU หรือชื่อสินค้า" value={searchDraft} /><button className="rounded-lg border px-3 py-2 text-sm font-medium" type="submit">ค้นหา</button></form>
          <form className="flex gap-2" onSubmit={lookupSku}><label className="sr-only" htmlFor="count-sku">สแกนหรือพิมพ์ SKU</label><input id="count-sku" className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2" autoComplete="off" onChange={(event) => setSku(event.target.value)} placeholder="สแกนหรือพิมพ์ SKU แล้วกด Enter" value={sku} /><button className="rounded-lg border px-3 py-2 text-sm font-medium" type="submit">ค้นหา SKU</button></form>
        </div>
        <p className="mt-3 text-xs text-slate-500">ยอดอ้างอิงถูกบันทึกเมื่อเริ่มรอบนับ การบันทึกจำนวนจริงไม่เปลี่ยนยอดสต๊อก</p>
        {items.length === 0 ? <p className="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">ไม่พบสินค้าในรายการนี้</p> : <ul className="mt-4 divide-y divide-slate-200">{items.map((item) => <li className="py-4" id={`count-row-${item.id}`} key={item.id}>
          <div className="flex flex-wrap items-start justify-between gap-2"><div><p className="font-semibold">{item.product.sku} · {item.product.name}</p><p className="mt-1 text-xs text-slate-600">ตำแหน่ง {item.location ?? 'ยังไม่กำหนด'} · ยอดอ้างอิง {item.reference_quantity} {item.product.base_unit} · {new Date(item.reference_at).toLocaleString('th-TH')}</p></div><span className={item.count_status === 'not_counted' ? 'rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800' : 'rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800'}>{statusLabel[item.count_status]}</span></div>
          {item.variance !== null && <p className="mt-2 text-sm text-slate-700">ผลต่างล่าสุด <strong>{item.variance}</strong> {item.product.base_unit}</p>}
          {item.attempts.length > 0 && <ol className="mt-2 space-y-1 text-xs text-slate-600">{item.attempts.map((attempt) => <li key={attempt.attempt_number}>ครั้งที่ {attempt.attempt_number}: {attempt.counted_quantity} · ผลต่าง {attempt.variance}{attempt.correction ? ` · ปรับแล้ว (${attempt.correction.delta})` : ''}</li>)}</ol>}
          <div className="mt-3 flex max-w-xl flex-wrap gap-2">
            <label className="sr-only" htmlFor={`count-quantity-${item.id}`}>{item.count_status === 'not_counted' ? 'จำนวนที่ตรวจนับ' : 'จำนวนตรวจซ้ำ'} {item.product.sku}</label>
            <input id={`count-quantity-${item.id}`} className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2" min="0" onChange={(event) => setQuantities((current) => ({ ...current, [item.id]: event.target.value }))} required step="0.001" type="number" value={quantities[item.id] ?? ''} />
            <button className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" disabled={busy} onClick={() => void saveCount(item, item.count_status !== 'not_counted')} type="button">{item.count_status === 'not_counted' ? 'บันทึกจำนวน' : 'บันทึกตรวจซ้ำ'}</button>
          </div>
          {canCorrect && item.variance !== null && !item.correction && <form className="mt-3 flex max-w-xl flex-wrap gap-2" onSubmit={(event) => { event.preventDefault(); void correctCount(item) }}><label className="sr-only" htmlFor={`correction-reason-${item.id}`}>เหตุผลการปรับยอด {item.product.sku}</label><input id={`correction-reason-${item.id}`} className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2" minLength={3} onChange={(event) => setReasons((current) => ({ ...current, [item.id]: event.target.value }))} placeholder="เหตุผลประกอบการปรับยอด" required value={reasons[item.id] ?? ''} /><button className="rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" disabled={busy} type="submit">ปรับยอดตามผลต่าง</button></form>}
        </li>)}</ul>}
      </>}
      {error && <p className="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{error}</p>}{message && <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{message}</p>}
    </div>
  </section>
}
