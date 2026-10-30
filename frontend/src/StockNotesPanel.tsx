import { useCallback, useEffect, useRef, useState } from 'react'
import { useUnsavedChanges } from './useUnsavedChanges'
import { objectUpload } from './fileTransport'

type Role = 'owner' | 'manager' | 'staff'
type Mode = 'QUANTITY' | 'AVAILABILITY_ONLY'
type Location = { id: number; zone: string; shelf: string; level: string; bin: string }
type Product = { id: number; name: string; sku: string; internal_code: string; sku_is_generated: boolean; tracking_mode: Mode; availability_status: string; quantity: string | null; stock_status: string; min_stock_quantity: string; stock_version: number; storage_location_id: number | null; storage_location: Location | null; category_id: number | null; part_number: string | null; base_unit: string; notes: string | null; reorder_needed: boolean; needs_reorder: boolean; reorder_note: string | null; archived_at: string | null; status_updated_at: string | null; image_url: string | null }
type Form = { name: string; sku: string; tracking_mode: Mode; initial_quantity: string; min_stock_quantity: string; storage_location_id: string; category_id: string; part_number: string; base_unit: string; notes: string; reason: string; confirm_mode_change: boolean }
type History = { id: number; action: string; actor_name: string; created_at: string; reason: string | null; before_data: Product | null; after_data: Product }
const blank: Form = { name: '', sku: '', tracking_mode: 'QUANTITY', initial_quantity: '', min_stock_quantity: '0', storage_location_id: '', category_id: '', part_number: '', base_unit: 'ชิ้น', notes: '', reason: '', confirm_mode_change: false }
const statusTitles: Record<string, string> = { IN_STOCK: 'มีสินค้า', OUT_OF_STOCK: 'สินค้าหมด', LOW_STOCK: 'ใกล้หมด', UNKNOWN: 'ยังไม่ทราบ', UNSET: 'ยังไม่กำหนดยอด' }
const actionTitles: Record<string, string> = { create: 'เพิ่มสินค้า', sale: 'ขายออก', receipt: 'รับเข้า', set_quantity: 'แก้ยอดจริง', edit: 'แก้ไขข้อมูล / รูปแบบ', availability: 'เปลี่ยนสถานะ', reorder: 'รายการต้องสั่ง', archive: 'จัดเก็บรายการ', restore: 'คืนรายการ' }
const amount = (value: string | null) => value === null ? '—' : Number(value).toLocaleString('th-TH', { maximumFractionDigits: 3 })
const date = (value: string | null) => value ? new Date(value).toLocaleString('th-TH') : 'ยังไม่มีการอัปเดต'

export default function StockNotesPanel({ role }: { role: Role }) {
  const manager = role !== 'staff'
  const [products, setProducts] = useState<Product[]>([])
  const [locations, setLocations] = useState<Location[]>([])
  const [categories, setCategories] = useState<{ id: number; name: string }[]>([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [mode, setMode] = useState('')
  const [reorder, setReorder] = useState(false)
  const [archived, setArchived] = useState(false)
  const [page, setPage] = useState(1)
  const [pages, setPages] = useState(1)
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [panel, setPanel] = useState('')
  const [selected, setSelected] = useState<Product | null>(null)
  const [form, setForm] = useState<Form>(blank)
  const [quantity, setQuantity] = useState('1')
  const [reason, setReason] = useState('')
  const [orderNote, setOrderNote] = useState('')
  const [orderFlag, setOrderFlag] = useState(false)
  const [image, setImage] = useState<File | null>(null)
  const [fitment, setFitment] = useState({ make: '', model: '', year: '', engine: '' })
  const [events, setEvents] = useState<History[]>([])
  const [movements, setMovements] = useState<{ id: number; movement_type: string; quantity_delta: string; occurred_at: string; reason: string | null }[]>([])
  const [historyPage, setHistoryPage] = useState(1)
  const [historyPages, setHistoryPages] = useState(1)
  const [conflict, setConflict] = useState(false)
  const inFlight = useRef(false)
  const requestKeys = useRef(new Map<string, string>())
  const loadSequence = useRef(0)
  const panelRef = useRef<HTMLElement>(null)
  useUnsavedChanges(panel !== '' && panel !== 'history')

  const load = useCallback(async () => {
    const sequence = ++loadSequence.current
    setLoading(true)
    try {
      const query = new URLSearchParams({ search, page: String(page), ...(status ? { stock_status: status } : {}), ...(mode ? { tracking_mode: mode } : {}), ...(reorder ? { reorder: '1' } : {}), ...(archived ? { archived: 'only' } : {}) })
      const response = await fetch('/api/stock-notes/products?' + query, { headers: { Accept: 'application/json' } })
      if (!response.ok) throw new Error('โหลดสินค้าไม่สำเร็จ กรุณาเข้าสู่ระบบใหม่หรือโหลดอีกครั้ง')
      const data = await response.json() as { data: Product[]; last_page: number; total: number }
      if (sequence === loadSequence.current) { setProducts(data.data); setPages(data.last_page); setTotal(data.total) }
    } catch (cause) { if (sequence === loadSequence.current) setError(cause instanceof Error ? cause.message : 'โหลดสินค้าไม่สำเร็จ') }
    finally { if (sequence === loadSequence.current) setLoading(false) }
  }, [search, page, status, mode, reorder, archived])

  useEffect(() => {
    const timer = setTimeout(() => { void load() }, 180)
    // This is an async generation counter, not a DOM ref; invalidating it prevents stale results.
    // oxlint-disable-next-line react-hooks/exhaustive-deps
    return () => { clearTimeout(timer); loadSequence.current++ }
  }, [load])
  useEffect(() => {
    void Promise.all([fetch('/api/locations'), fetch('/api/categories')]).then(async ([l, c]) => {
      if (!l.ok || !c.ok) return
      const a = await l.json(); const b = await c.json()
      setLocations(a.data ?? a.locations ?? a); setCategories(b.data ?? b.categories ?? b)
    }).catch(() => setError('โหลดหมวดหมู่หรือตำแหน่งไม่สำเร็จ'))
  }, [])
  useEffect(() => { if (panel) panelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }, [panel])

  async function mutate(path: string, method: string, body: Record<string, unknown>) {
    if (inFlight.current) return
    inFlight.current = true; setBusy(true); setError(''); setNotice('')
    const signature = JSON.stringify([path, method, body, image ? [image.name, image.size, image.lastModified] : null])
    const key = requestKeys.current.get(signature) ?? crypto.randomUUID()
    requestKeys.current.set(signature, key)
    try {
      const csrf = await fetch('/sanctum/csrf-cookie', { headers: { Accept: 'application/json' } })
      const token = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.slice(11)
      if (!csrf.ok || !token) throw new Error('ยืนยันตัวตนไม่สำเร็จ กรุณาเข้าสู่ระบบอีกครั้ง')
      const headers: Record<string, string> = { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token), 'Idempotency-Key': key }
      let payload: string | FormData = JSON.stringify(body)
      let sendMethod = method
      if (image && (panel === 'create' || panel === 'edit')) {
        const uploadToken = await objectUpload(image, 'image', decodeURIComponent(token))
        if (uploadToken) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify({ ...body, image_token: uploadToken }) }
        else {
          const fields = new FormData()
          Object.entries(body).forEach(([name, value]) => { if (value !== undefined) fields.set(name, value === null ? '' : typeof value === 'object' ? JSON.stringify(value) : typeof value === 'boolean' ? (value ? '1' : '0') : String(value)) })
          fields.set('image', image)
          if (method === 'PATCH') { fields.set('_method', 'PATCH'); sendMethod = 'POST' }
          payload = fields
        }
      } else { headers['Content-Type'] = 'application/json' }
      const response = await fetch(path, { method: sendMethod, headers, body: payload })
      const result = await response.json() as { message?: string; errors?: Record<string, string[]> }
      if (!response.ok) {
        if (response.status === 409) { setConflict(true); await load() }
        throw new Error(response.status === 409 ? 'ข้อมูลเปลี่ยนแล้ว หรือจำนวนไม่เพียงพอ กรุณาโหลดข้อมูลล่าสุดและตรวจสอบก่อนบันทึกใหม่' : Object.values(result.errors ?? {}).flat()[0] ?? result.message ?? 'บันทึกไม่สำเร็จ')
      }
      requestKeys.current.delete(signature)
      setPanel(''); setSelected(null); setForm(blank); setImage(null); setConflict(false)
      setNotice('บันทึกแล้ว'); await load()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'บันทึกไม่สำเร็จ ใช้ปุ่มเดิมเพื่อลองซ้ำอย่างปลอดภัย') }
    finally { inFlight.current = false; setBusy(false) }
  }

  function open(product: Product | null, next: string) {
    if (panel && panel !== 'history' && !window.confirm('เปลี่ยนรายการและละทิ้งข้อมูลที่ยังไม่บันทึก?')) return
    setSelected(product); setPanel(next); setError(''); setNotice(''); setConflict(false); setReason(''); setQuantity(next === 'set_quantity' ? product?.quantity ?? '' : '1'); setOrderNote(product?.reorder_note ?? '')
    setOrderFlag(product?.reorder_needed ?? false); setImage(null); setFitment({ make: '', model: '', year: '', engine: '' })
    setForm(product ? { ...blank, name: product.name, sku: product.sku_is_generated ? '' : product.sku, tracking_mode: product.tracking_mode, min_stock_quantity: product.min_stock_quantity, storage_location_id: String(product.storage_location_id ?? ''), category_id: String(product.category_id ?? ''), part_number: product.part_number ?? '', base_unit: product.base_unit, notes: product.notes ?? '' } : blank)
    if (product && next === 'history') { setEvents([]); setMovements([]); void history(product.id, 1) }
  }
  async function history(id: number, target: number) {
    try {
      const [e, m] = await Promise.all([fetch(`/api/stock-notes/products/${id}/history?page=${target}`), fetch(`/api/products/${id}/movements?page=${target}`)])
      if (!e.ok || !m.ok) throw new Error('โหลดประวัติไม่สำเร็จ')
      const data = await e.json(); const ledger = await m.json()
      setEvents(data.data); setMovements(ledger.data); setHistoryPage(target); setHistoryPages(Math.max(data.last_page, ledger.last_page))
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'โหลดประวัติไม่สำเร็จ') }
  }
  function action(product: Product, operation: string, data: Record<string, unknown> = {}) {
    void mutate(`/api/stock-notes/products/${product.id}/actions`, 'POST', { action: operation, expected_version: product.stock_version, ...data })
  }
  function saveProduct(event: React.FormEvent) {
    event.preventDefault()
    const data: Record<string, unknown> = { name: form.name, tracking_mode: form.tracking_mode, part_number: form.part_number || null, base_unit: form.base_unit, min_stock_quantity: form.min_stock_quantity, storage_location_id: form.storage_location_id ? Number(form.storage_location_id) : null, category_id: form.category_id ? Number(form.category_id) : null, notes: form.notes || null }
    if (form.sku) data.sku = form.sku
    if (fitment.make || fitment.model || fitment.year) data.fitments = [{ ...fitment, year: Number(fitment.year) }]
    if (form.tracking_mode === 'QUANTITY' && form.initial_quantity !== '') data.initial_quantity = form.initial_quantity
    if (selected) Object.assign(data, { expected_version: selected.stock_version, confirm_mode_change: form.confirm_mode_change, reason: form.reason || undefined })
    void mutate('/api/stock-notes/products' + (selected ? '/' + selected.id : ''), selected ? 'PATCH' : 'POST', data)
  }
  function field<K extends keyof Form>(key: K, value: Form[K]) { setForm(previous => ({ ...previous, [key]: value })) }
  const changedMode = selected && selected.tracking_mode !== form.tracking_mode

  return <section className="stock-notes" aria-labelledby="notes-title">
    <div className="notes-heading"><div><h2 id="notes-title">สินค้าในร้าน</h2><p>ค้นหา ดูตำแหน่ง และอัปเดตสต๊อกได้ทันที</p></div>{manager && <button className="notes-primary" onClick={() => open(null, 'create')}>+ เพิ่มสินค้าด่วน</button>}</div>
    <div className="notes-filters">
      <label className="notes-search">ค้นหาสินค้า<input type="search" placeholder="ชื่อ · SKU · Part No. · รถ · ตำแหน่ง" value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} /></label>
      <label>สถานะ<select value={status} onChange={e => { setStatus(e.target.value); setPage(1) }}><option value="">ทุกสถานะ</option>{Object.entries(statusTitles).map(([id, title]) => <option key={id} value={id}>{title}</option>)}</select></label>
      <label>รูปแบบ<select value={mode} onChange={e => { setMode(e.target.value); setPage(1) }}><option value="">ทุกรูปแบบ</option><option value="QUANTITY">นับจำนวน</option><option value="AVAILABILITY_ONLY">สถานะเท่านั้น</option></select></label>
      <label className="notes-check"><input type="checkbox" checked={reorder} onChange={e => { setReorder(e.target.checked); setPage(1) }} />ของหมด / ต้องสั่ง</label>
      <label className="notes-check"><input type="checkbox" checked={archived} onChange={e => { setArchived(e.target.checked); setPage(1) }} />รายการ Archive</label>
    </div>
    {error && <p className="notes-error" role="alert">{error}</p>}{notice && <p className="notes-notice" role="status">{notice}</p>}
    {panel && <section className="notes-editor" ref={panelRef} aria-label="จัดการสินค้า">
      <div className="notes-heading"><h3>{panel === 'create' ? 'เพิ่มสินค้าด่วน' : panel === 'edit' ? 'แก้ไขสินค้า' : actionTitles[panel] ?? 'ประวัติสินค้า'}{selected ? ' · ' + selected.name : ''}</h3><button disabled={busy} onClick={() => { if (panel === 'history' || window.confirm('ปิดรายการที่ยังไม่บันทึก?')) { setPanel(''); setForm(blank) } }}>ปิด</button></div>
      {conflict && selected && <button onClick={() => { const current = products.find(p => p.id === selected.id); if (current) { setSelected(current); setConflict(false); setError('ตรวจสอบค่าที่กรอกอีกครั้งก่อนบันทึก') } }}>โหลดข้อมูลล่าสุดเพื่อตรวจสอบ</button>}
      {(panel === 'create' || panel === 'edit') && <form onSubmit={saveProduct} className="notes-form">
        <label>ชื่อสินค้า *<input required maxLength={255} value={form.name} onChange={e => field('name', e.target.value)} /></label>
        <label>รูปแบบการติดตาม *<select aria-label="รูปแบบการติดตาม *" value={form.tracking_mode} onChange={e => field('tracking_mode', e.target.value as Mode)}><option value="QUANTITY">นับจำนวน</option><option value="AVAILABILITY_ONLY">สถานะเท่านั้น</option></select></label>
        {form.tracking_mode === 'QUANTITY' && (!selected || changedMode) && <label>จำนวนเริ่มต้น{changedMode ? ' *' : ' (เว้นว่าง = ยังไม่กำหนดยอด)'}<input required={!!changedMode} type="number" min="0" step="0.001" inputMode="decimal" value={form.initial_quantity} onChange={e => field('initial_quantity', e.target.value)} /></label>}
        {changedMode && <div className="notes-warning"><p>เปลี่ยนรูปแบบแล้ว ประวัติเก่าจะยังอยู่ เมื่อกลับมานับจำนวนต้องกำหนดยอดใหม่อย่างชัดเจน</p><label className="notes-check"><input required type="checkbox" checked={form.confirm_mode_change} onChange={e => field('confirm_mode_change', e.target.checked)} />ยืนยันการเปลี่ยนรูปแบบ</label><label>เหตุผล *<input required value={form.reason} maxLength={2000} onChange={e => field('reason', e.target.value)} /></label></div>}
        <details className="notes-details" open={panel === 'edit'}><summary>รายละเอียดเพิ่มเติม (ไม่บังคับ)</summary><div className="notes-form">
          <label>SKU<input maxLength={100} placeholder="เว้นว่างเพื่อสร้างรหัสอัตโนมัติ" value={form.sku} onChange={e => field('sku', e.target.value)} /></label>
          <label>Part Number<input maxLength={120} value={form.part_number} onChange={e => field('part_number', e.target.value)} /></label>
          <label>ตำแหน่งจัดเก็บ<select aria-label="ตำแหน่งจัดเก็บ" value={form.storage_location_id} onChange={e => field('storage_location_id', e.target.value)}><option value="">ยังไม่ระบุ</option>{locations.map(l => <option key={l.id} value={l.id}>{[l.zone,l.shelf,l.level,l.bin].join('-')}</option>)}</select></label>
          <label>หมวดหมู่<select aria-label="หมวดหมู่" value={form.category_id} onChange={e => field('category_id', e.target.value)}><option value="">ยังไม่ระบุ</option>{categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select></label>
          <label>หน่วย<input required maxLength={40} value={form.base_unit} onChange={e => field('base_unit', e.target.value)} /></label>
          <label>จำนวนขั้นต่ำ<input type="number" min="0" step="0.001" inputMode="decimal" value={form.min_stock_quantity} onChange={e => field('min_stock_quantity', e.target.value)} /></label>
          <label className="notes-wide">หมายเหตุ<textarea maxLength={2000} rows={2} value={form.notes} onChange={e => field('notes', e.target.value)} /></label>
          <label className="notes-wide">รูปภาพ (JPEG / PNG / WebP ไม่เกิน 5 MB)<input type="file" accept="image/jpeg,image/png,image/webp" onChange={e => setImage(e.target.files?.[0] ?? null)} /></label>
          {!selected && <fieldset className="notes-wide"><legend>รถที่รองรับ (ไม่บังคับ)</legend><div className="notes-form"><label>ยี่ห้อรถ<input maxLength={100} value={fitment.make} onChange={e => setFitment({ ...fitment, make: e.target.value })} /></label><label>รุ่นรถ<input maxLength={120} value={fitment.model} onChange={e => setFitment({ ...fitment, model: e.target.value })} /></label><label>ปีรถ<input type="number" min="1886" max="2200" value={fitment.year} onChange={e => setFitment({ ...fitment, year: e.target.value })} /></label><label>เครื่องยนต์<input maxLength={100} value={fitment.engine} onChange={e => setFitment({ ...fitment, engine: e.target.value })} /></label></div></fieldset>}
          {selected && <p className="notes-wide">จัดการรถที่รองรับหลายรายการได้ที่ Advanced → ทะเบียนสินค้า / รูป / รถ</p>}
        </div></details>
        <button className="notes-primary" disabled={busy || conflict}>{busy ? 'กำลังบันทึก…' : 'บันทึกสินค้า'}</button>
      </form>}
      {selected && ['sale','receipt','set_quantity'].includes(panel) && <form className="notes-form" onSubmit={e => { e.preventDefault(); action(selected, panel, { quantity, reason: reason || undefined }) }}>
        <p className="notes-wide">คงเหลือล่าสุดในหน้าที่เปิด: <strong>{amount(selected.quantity)} {selected.base_unit}</strong>{selected.quantity === null ? ' · ยังไม่กำหนดยอด' : ''}</p>
        <label>{panel === 'set_quantity' ? 'จำนวนจริง *' : 'จำนวน *'}<input autoFocus required type="number" min={panel === 'set_quantity' ? '0' : '0.001'} step="0.001" inputMode="decimal" value={quantity} onChange={e => setQuantity(e.target.value)} /></label>
        <label>เหตุผล{panel === 'set_quantity' ? ' *' : ' (ไม่บังคับ)'}<input required={panel === 'set_quantity'} maxLength={2000} value={reason} onChange={e => setReason(e.target.value)} /></label>
        <button className="notes-primary" disabled={busy || conflict}>บันทึก{actionTitles[panel]}</button>
      </form>}
      {selected && panel === 'reorder' && <form className="notes-form" onSubmit={e => { e.preventDefault(); action(selected, 'reorder', { reorder_needed: orderFlag, reorder_note: orderNote || null }) }}><label className="notes-check"><input type="checkbox" checked={orderFlag} onChange={e => setOrderFlag(e.target.checked)} />ทำเครื่องหมายว่าต้องสั่ง</label><label className="notes-wide">หมายเหตุของที่ต้องสั่ง<textarea rows={2} maxLength={2000} value={orderNote} onChange={e => setOrderNote(e.target.value)} /></label><p>การเตือนหมด/ใกล้หมดคำนวณจากสต๊อกแยกจากเครื่องหมายนี้</p><button className="notes-primary" disabled={busy || conflict}>บันทึกรายการต้องสั่ง</button></form>}
      {selected && panel === 'history' && <div className="notes-history"><h4>การเปลี่ยนแปลงสินค้า</h4>{events.length === 0 && <p>ไม่มีประวัติแบบใหม่</p>}{events.map(e => <div key={e.id}><strong>{actionTitles[e.action] ?? e.action}</strong><span>{e.actor_name} · {date(e.created_at)}</span><p>{e.before_data ? `${amount(e.before_data.quantity)} / ${statusTitles[e.before_data.stock_status]} → ` : ''}{amount(e.after_data.quantity)} / {statusTitles[e.after_data.stock_status]}</p>{e.reason && <p>{e.reason}</p>}</div>)}<h4>Stock Movements รวมประวัติเดิม</h4>{movements.map(m => <div key={m.id}><strong>{actionTitles[m.movement_type] ?? m.movement_type} {Number(m.quantity_delta) > 0 ? '+' : ''}{amount(m.quantity_delta)}</strong><span>{date(m.occurred_at)}</span><p>{m.reason}</p></div>)}{historyPages > 1 && <div className="notes-pagination"><button disabled={historyPage <= 1} onClick={() => { void history(selected.id, historyPage - 1) }}>ประวัติก่อนหน้า</button><span>{historyPage}/{historyPages}</span><button disabled={historyPage >= historyPages} onClick={() => { void history(selected.id, historyPage + 1) }}>ประวัติถัดไป</button></div>}</div>}
    </section>}
    <div className="notes-results"><span>{total} รายการ {loading ? '· กำลังโหลด…' : ''}</span><button onClick={() => { void load() }}>รีเฟรชรายการ</button></div>
    {!loading && products.length === 0 && <p className="notes-empty">ไม่พบสินค้า ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>}
    <div className="notes-products">{products.map(p => <article className="notes-product" key={p.id} aria-label={p.name}>
      <div className="notes-product-main">{p.image_url && <img className="notes-image" src={p.image_url} alt="" loading="lazy" />}<div><h3>{p.name}</h3><p className="notes-code">{p.sku_is_generated ? p.internal_code : p.sku}{p.part_number ? ' · ' + p.part_number : ''}</p><p className="notes-location">⌖ {p.storage_location ? [p.storage_location.zone,p.storage_location.shelf,p.storage_location.level,p.storage_location.bin].join('-') : 'ยังไม่ระบุตำแหน่ง'}</p>{p.notes && <p>{p.notes}</p>}</div></div>
      <div className="notes-product-status"><span className={'notes-badge status-' + p.stock_status}>{statusTitles[p.stock_status]}</span>{p.tracking_mode === 'QUANTITY' && p.quantity !== null ? <strong className="notes-quantity">{amount(p.quantity)} <small>{p.base_unit}</small></strong> : <span className="notes-mode">{p.tracking_mode === 'AVAILABILITY_ONLY' ? 'ติดตามสถานะเท่านั้น' : 'กำหนดยอดก่อนขาย / รับเข้า'}</span>}{p.needs_reorder && <span className="notes-reorder">ต้องสั่ง{p.reorder_note ? ' · ' + p.reorder_note : ''}</span>}<small>{date(p.status_updated_at)}</small></div>
      <div className="notes-product-actions">{p.archived_at ? manager && <button disabled={busy} onClick={() => action(p, 'restore')}>คืนรายการ</button> : <>
        {p.tracking_mode === 'QUANTITY' ? <><button disabled={busy || p.quantity === null || Number(p.quantity) === 0} onClick={() => open(p, 'sale')}>− ขายออก</button>{manager && <><button disabled={busy || p.quantity === null} onClick={() => open(p, 'receipt')}>+ รับเข้า</button><button disabled={busy} onClick={() => open(p, 'set_quantity')}>{p.quantity === null ? 'กำหนดยอด' : 'แก้ยอดจริง'}</button></>}</> : <>{['IN_STOCK','OUT_OF_STOCK','UNKNOWN'].map(s => <button key={s} disabled={busy || p.availability_status === s} aria-pressed={p.availability_status === s} onClick={() => action(p, 'availability', { availability_status: s })}>{statusTitles[s]}</button>)}</>}
        <button disabled={busy} onClick={() => open(p, 'reorder')}>{p.reorder_needed ? 'แก้รายการต้องสั่ง' : 'ต้องสั่ง'}</button>{manager && <><button disabled={busy} onClick={() => open(p, 'edit')}>แก้ไข</button><button disabled={busy} onClick={() => { if (window.confirm('Archive สินค้านี้? ประวัติและยอดเดิมยังคงอยู่')) action(p, 'archive') }}>Archive</button></>}
      </>}<button disabled={busy} onClick={() => open(p, 'history')}>ดูประวัติ</button></div>
    </article>)}</div>
    <div className="notes-pagination"><button disabled={page <= 1 || loading} onClick={() => setPage(page - 1)}>ก่อนหน้า</button><span>หน้า {page} / {pages}</span><button disabled={page >= pages || loading} onClick={() => setPage(page + 1)}>ถัดไป</button></div>
  </section>
}
