import { useCallback, useEffect, useState } from 'react'
import { confirmNavigation, useUnsavedChanges } from './useUnsavedChanges'

type Role = 'owner' | 'manager' | 'staff'
type Product = { id: number; sku: string; name: string; base_unit: string }
type Balance = { product: Product; quantity: string; stock_status: 'out' | 'low' | 'ok' }
type Movement = { id: number; movement_type: string; quantity_delta: string; reason: string | null; occurred_at: string }

function cookieToken(): string | undefined {
  const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : undefined
}

async function csrf(): Promise<string> {
  const response = await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  const value = cookieToken()
  if (!response.ok || !value) throw new Error('เริ่มการยืนยันตัวตนไม่สำเร็จ')
  return value
}

export default function StockPanel({ role }: { role: Role }) {
  const canWrite = role === 'owner' || role === 'manager'
  const [balances, setBalances] = useState<Balance[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [selected, setSelected] = useState('')
  const [quantity, setQuantity] = useState('')
  const [reason, setReason] = useState('')
  const [operation, setOperation] = useState<'opening-balances' | 'receipts'>('opening-balances')
  const [movements, setMovements] = useState<Movement[]>([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  useUnsavedChanges(!!quantity || !!reason)

  const load = useCallback(async (targetPage = 1) => {
    const [stockResponse, productsResponse] = await Promise.all([
      fetch(`/api/stock/balances?page=${targetPage}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
      fetch(`/api/products?page=${targetPage}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
    ])
    if (!stockResponse.ok || !productsResponse.ok) throw new Error('โหลดข้อมูลสต๊อกไม่สำเร็จ')
    const stock = (await stockResponse.json()) as { data: Balance[]; current_page?: number; last_page?: number }
    setBalances(stock.data); setPage(stock.current_page ?? 1); setLastPage(stock.last_page ?? 1)
    setProducts(((await productsResponse.json()) as { data: Product[] }).data)
  }, [])

  const loadMovements = useCallback(async (id: string) => {
    if (!id) return
    const response = await fetch(`/api/products/${id}/movements`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('โหลดประวัติไม่สำเร็จ')
    setMovements(((await response.json()) as { data: Movement[] }).data)
  }, [])

  // These effects synchronize server state when the panel mounts or the selected product changes.
  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    const update = () => { void load().catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'โหลดสต๊อกไม่สำเร็จ')).finally(() => setLoading(false)) }
    window.addEventListener('partshelf:stock-updated', update)
    update()
    return () => window.removeEventListener('partshelf:stock-updated', update)
  }, [load])
  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => { void loadMovements(selected).catch(() => setError('โหลดประวัติไม่สำเร็จ')) }, [loadMovements, selected])

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const xsrf = await csrf()
      const response = await fetch(`/api/stock/${operation}`, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({ product_id: Number(selected), quantity, reason }),
      })
      if (!response.ok) {
        const body = (await response.json()) as { message?: string; errors?: Record<string, string[]> }
        throw new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'บันทึกสต๊อกไม่สำเร็จ')
      }
      setQuantity(''); setReason(''); setNotice('บันทึกความเคลื่อนไหวสต๊อกแล้ว')
      await Promise.all([load(page), loadMovements(selected)])
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'บันทึกสต๊อกไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  const statusText = { out: 'หมด', low: 'ใกล้หมด', ok: 'ปกติ' }
  return <section className="mx-auto max-w-6xl px-5 pb-12 sm:px-8" aria-labelledby="stock-title">
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <h2 className="text-2xl font-bold" id="stock-title">สต๊อกและประวัติ</h2>
      {lastPage > 1 && <div className="mt-3 flex items-center gap-3" aria-label="หน้ารายการสต๊อก"><button type="button" className="rounded-lg border px-3 py-2" disabled={page <= 1 || busy} onClick={() => { if (confirmNavigation()) { setSelected(''); setMovements([]); void load(page - 1).catch(() => setError('โหลดหน้าสต๊อกไม่สำเร็จ')) } }}>ก่อนหน้า</button><span className="text-sm">หน้า {page} / {lastPage}</span><button type="button" className="rounded-lg border px-3 py-2" disabled={page >= lastPage || busy} onClick={() => { if (confirmNavigation()) { setSelected(''); setMovements([]); void load(page + 1).catch(() => setError('โหลดหน้าสต๊อกไม่สำเร็จ')) } }}>ถัดไป</button></div>}
      {canWrite && products.length > 0 && <form className="mt-6 grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2" onSubmit={submit}>
        <h3 className="font-semibold sm:col-span-2">บันทึกยอดตั้งต้นหรือรับสินค้า</h3>
        <label className="text-sm font-medium">สินค้า<select className="mt-1 block w-full rounded-lg border bg-white p-2.5" onChange={(event) => { setSelected(event.target.value); setMovements([]) }} required value={selected}><option value="">เลือกสินค้า</option>{products.map((product) => <option key={product.id} value={product.id}>{product.sku} · {product.name}</option>)}</select></label>
        <label className="text-sm font-medium">รายการ<select className="mt-1 block w-full rounded-lg border bg-white p-2.5" onChange={(event) => setOperation(event.target.value as typeof operation)} value={operation}><option value="opening-balances">ยอดยกมาตั้งต้น (ทำได้ครั้งเดียว)</option><option value="receipts">รับสินค้าเข้า</option></select></label>
        <label className="text-sm font-medium">จำนวน<input className="mt-1 block w-full rounded-lg border bg-white p-2.5" min={operation === 'opening-balances' ? '0' : '0.001'} step="0.001" onChange={(event) => setQuantity(event.target.value)} required inputMode="decimal" type="number" value={quantity} /></label>
        <label className="text-sm font-medium">เหตุผล (ไม่บังคับ)<input className="mt-1 block w-full rounded-lg border bg-white p-2.5" maxLength={2000} onChange={(event) => setReason(event.target.value)} value={reason} /></label>
        <button className="rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white disabled:opacity-60 sm:col-span-2" disabled={busy || !selected} type="submit">{busy ? 'กำลังบันทึก…' : 'บันทึกรายการ'}</button>
      </form>}
      <p className="mt-5 text-sm font-semibold">ยอดคงเหลือสินค้า</p>
      <p className="table-scroll-hint text-xs text-slate-600">เลื่อนตารางซ้าย–ขวาเพื่อดูยอดและสถานะ</p>
      {loading ? <p className="panel-loading mt-4" role="status">กำลังโหลดสต๊อก…</p> : balances.length === 0 ? <p className="mt-4 text-sm text-slate-600">ยังไม่มีสินค้า</p> : <div className="mt-4 overflow-x-auto" role="region" aria-label="ยอดสต๊อก เลื่อนตารางในแนวนอนได้" tabIndex={0}><table className="w-full min-w-[500px] text-left text-sm"><thead><tr className="border-b text-slate-600"><th className="p-3">SKU / สินค้า</th><th className="p-3">คงเหลือ</th><th className="p-3">สถานะ</th></tr></thead><tbody>{balances.map(({ product, quantity: amount, stock_status }) => <tr className="border-b border-slate-100" key={product.id}><td className="p-3"><strong>{product.sku}</strong> · {product.name}</td><td className="p-3">{amount} {product.base_unit}</td><td className="p-3">{statusText[stock_status]}</td></tr>)}</tbody></table></div>}
      <div className="mt-6"><label className="text-sm font-medium">ประวัติการเคลื่อนไหว<select className="mt-1 block w-full rounded-lg border bg-white p-2" onChange={(event) => { setSelected(event.target.value); setMovements([]) }} value={selected}><option value="">เลือกสินค้า</option>{products.map((product) => <option key={product.id} value={product.id}>{product.sku} · {product.name}</option>)}</select></label>
        {movements.length > 0 && <ul className="mt-3 divide-y">{movements.map((movement) => <li className="py-2 text-sm" key={movement.id}>{new Date(movement.occurred_at).toLocaleString('th-TH')} · {movement.movement_type === 'opening' ? 'ยอดตั้งต้น' : movement.movement_type === 'receipt' ? 'รับเข้า' : 'ปรับยอดตรวจนับ'} {Number(movement.quantity_delta) >= 0 ? '+' : ''}{movement.quantity_delta} · {movement.reason ?? 'ไม่ระบุเหตุผล'}</li>)}</ul>}
      </div>
      {error && <p className="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{error}</p>}{notice && <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{notice}</p>}
    </div>
  </section>
}
