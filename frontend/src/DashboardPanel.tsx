import { useCallback, useEffect, useState } from 'react'

type DashboardData = {
  summary: { product_count: number; total_on_hand: string; low_stock_count: number; out_of_stock_count: number }
  recent_counts: { id: number; status: string; started_at: string; completed_at: string | null; started_by: string | null; item_count: number }[]
  recent_variances: { count_id: number; sku: string; name: string; attempt_number: number; counted_at: string; variance: string }[]
  can_view_variances: boolean
}

export default function DashboardPanel() {
  const [data, setData] = useState<DashboardData | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    const response = await fetch('/api/dashboard', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('โหลดสรุปภาพรวมไม่สำเร็จ')
    setData((await response.json()) as DashboardData)
  }, [])

  useEffect(() => {
    let active = true
    // oxlint-disable-next-line react/set-state-in-effect -- async API result updates this view after synchronization.
    void load().catch((cause: unknown) => { if (active) setError(cause instanceof Error ? cause.message : 'โหลดสรุปภาพรวมไม่สำเร็จ') })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [load])

  return <section className="mx-auto max-w-6xl px-5 pb-8 sm:px-8" aria-labelledby="dashboard-title">
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-sm font-semibold text-blue-700">ภาพรวมร้าน</p><h2 id="dashboard-title" className="mt-1 text-2xl font-bold">สรุปสินค้าและสต๊อก</h2></div><button className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium" disabled={loading} onClick={() => { setLoading(true); setError(''); void load().catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'โหลดสรุปภาพรวมไม่สำเร็จ')).finally(() => setLoading(false)) }} type="button">รีเฟรช</button></div>
      {loading && <p className="mt-4 text-sm text-slate-600" role="status">กำลังโหลดข้อมูล…</p>}
      {error && <p className="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{error}</p>}
      {data && <>
        <dl className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[[ 'สินค้า', data.summary.product_count ], [ 'ยอดคงเหลือรวม', data.summary.total_on_hand ], [ 'ใกล้หมด', data.summary.low_stock_count ], [ 'หมดสต๊อก', data.summary.out_of_stock_count ]].map(([label, value]) => <div className="rounded-xl bg-slate-50 p-4" key={label}><dt className="text-sm text-slate-600">{label}</dt><dd className="mt-1 text-2xl font-bold tabular-nums">{value}</dd></div>)}
        </dl>
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
          <div><h3 className="font-semibold">ตรวจนับล่าสุด</h3>{data.recent_counts.length === 0 ? <p className="mt-2 text-sm text-slate-500">ยังไม่มีรายการตรวจนับ</p> : <ul className="mt-2 divide-y">{data.recent_counts.map((count) => <li className="flex flex-wrap justify-between gap-2 py-2 text-sm" key={count.id}><span>รอบ #{count.id} · {count.status === 'open' ? 'กำลังตรวจนับ' : 'เสร็จแล้ว'} · {count.item_count} รายการ</span><span className="text-slate-500">{count.started_by ?? 'ไม่ระบุ'} · {new Date(count.started_at).toLocaleDateString('th-TH')}</span></li>)}</ul>}</div>
          {data.can_view_variances && <div><h3 className="font-semibold">ผลต่างล่าสุด</h3>{data.recent_variances.length === 0 ? <p className="mt-2 text-sm text-slate-500">ยังไม่มีผลการนับ</p> : <ul className="mt-2 divide-y">{data.recent_variances.map((item) => <li className="flex flex-wrap justify-between gap-2 py-2 text-sm" key={`${item.count_id}-${item.sku}-${item.attempt_number}`}><span>{item.sku} · {item.name} · ครั้งที่ {item.attempt_number}</span><strong className={Number(item.variance) === 0 ? 'text-slate-700' : 'text-amber-700'}>ผลต่าง {item.variance}</strong></li>)}</ul>}</div>}
        </div>
      </>}
    </div>
  </section>
}
