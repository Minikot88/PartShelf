import { useCallback, useEffect, useRef, useState } from 'react'
import QRCode from 'qrcode'
import JsBarcode from 'jsbarcode'

type Product = { id: number; sku: string; name: string; part_number: string | null; internal_code: string; storage_location?: { path: string } | null }
type BarcodeResult = { rawValue: string }
type BarcodeDetectorLike = { detect: (source: HTMLVideoElement) => Promise<BarcodeResult[]> }
type BarcodeDetectorConstructor = new (options?: { formats: string[] }) => BarcodeDetectorLike
type CodeWindow = Window & { BarcodeDetector?: BarcodeDetectorConstructor }

function InternalLabel({ product }: { product: Product }) {
  const qr = useRef<HTMLCanvasElement>(null)
  const barcode = useRef<SVGSVGElement>(null)
  useEffect(() => {
    if (qr.current) void QRCode.toCanvas(qr.current, product.internal_code, { width: 112, margin: 1, errorCorrectionLevel: 'M' })
    if (barcode.current) JsBarcode(barcode.current, product.internal_code, { format: 'CODE128', width: 1.4, height: 38, margin: 2, displayValue: false })
  }, [product.internal_code])

  return <article className="partshelf-label">
    <strong className="partshelf-label-name">{product.name}</strong>
    <span>SKU: {product.sku}</span>
    {product.part_number && <span>Part Number: {product.part_number}</span>}
    {product.storage_location?.path && <span>ตำแหน่ง: {product.storage_location.path}</span>}
    <svg aria-label={`บาร์โค้ด ${product.internal_code}`} ref={barcode} />
    <div className="partshelf-label-footer"><code>{product.internal_code}</code><canvas aria-label={`QR code ${product.internal_code}`} ref={qr} /></div>
  </article>
}

export default function ProductLabelPanel() {
  const [products, setProducts] = useState<Product[]>([])
  const [selected, setSelected] = useState<Record<number, boolean>>({})
  const [searchDraft, setSearchDraft] = useState('')
  const [code, setCode] = useState('')
  const [resolved, setResolved] = useState<Product | null>(null)
  const [error, setError] = useState('')
  const [scanning, setScanning] = useState(false)
  const [busy, setBusy] = useState(false)
  const video = useRef<HTMLVideoElement>(null)

  const loadProducts = useCallback(async (term = '') => {
    const response = await fetch(`/api/products${term ? `?search=${encodeURIComponent(term)}` : ''}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('โหลดสินค้าเพื่อพิมพ์ฉลากไม่สำเร็จ')
    setProducts(((await response.json()) as { data: Product[] }).data)
  }, [])

  // oxlint-disable-next-line react/set-state-in-effect -- initial fetch synchronizes this panel with the server.
  useEffect(() => { void loadProducts().catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'โหลดสินค้าไม่สำเร็จ')) }, [loadProducts])

  useEffect(() => {
    if (!scanning || !video.current) return
    const Detector = (window as CodeWindow).BarcodeDetector
    if (!Detector) return
    const videoElement = video.current
    let active = true
    let frame = 0
    let stream: MediaStream | null = null
    const detector = new Detector({ formats: ['qr_code', 'code_128'] })
    const scan = async () => {
      if (!active || !videoElement) return
      try {
        if (videoElement.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
          const matches = await detector.detect(videoElement)
          if (matches[0]?.rawValue) {
            setCode(matches[0].rawValue)
            setScanning(false)
            return
          }
        }
      } catch { /* keep scanning until the user stops the camera */ }
      if (active) frame = requestAnimationFrame(() => { void scan() })
    }
    void navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
      .then((camera) => {
        if (!active || !videoElement) { camera.getTracks().forEach((track) => track.stop()); return }
        stream = camera
        videoElement.srcObject = camera
        void videoElement.play().then(() => { void scan() })
      })
      .catch(() => { if (active) { setScanning(false); setError('เปิดกล้องไม่ได้ กรุณาอนุญาตการใช้กล้องหรือใช้เครื่องสแกน USB') } })
    return () => {
      active = false
      cancelAnimationFrame(frame)
      stream?.getTracks().forEach((track) => track.stop())
      videoElement.srcObject = null
    }
  }, [scanning])

  async function resolveCode(value = code) {
    const candidate = value.trim()
    if (!candidate) return
    setBusy(true); setError(''); setResolved(null)
    try {
      const response = await fetch(`/api/products/resolve?code=${encodeURIComponent(candidate)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      const body = (await response.json()) as { product?: Product; message?: string }
      if (!response.ok || !body.product) throw new Error(body.message ?? 'ไม่พบรหัสสินค้า')
      setResolved(body.product); setCode(candidate)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'ค้นหารหัสสินค้าไม่สำเร็จ') }
    finally { setBusy(false) }
  }

  async function submitSearch(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    try { await loadProducts(searchDraft.trim()) } catch (cause) { setError(cause instanceof Error ? cause.message : 'ค้นหาไม่สำเร็จ') }
  }

  const selectedProducts = products.filter((product) => selected[product.id])
  return <section className="mx-auto max-w-6xl px-5 pb-12 sm:px-8" aria-labelledby="labels-title">
    <div className="partshelf-label-screen rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <div><p className="text-sm font-semibold text-blue-700">บาร์โค้ด · QR · ฉลากพื้นฐาน</p><h2 id="labels-title" className="mt-1 text-2xl font-bold">รหัสสินค้าและฉลากพิมพ์</h2><p className="mt-1 text-sm text-slate-600">รหัสภายในผูกกับ Product ID จึงคงเดิมเมื่อแก้ SKU</p></div>
      <form className="mt-4 flex flex-wrap gap-2" onSubmit={submitSearch}><label className="sr-only" htmlFor="label-search">ค้นหาสินค้าเพื่อพิมพ์ฉลาก</label><input className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2" id="label-search" onChange={(event) => setSearchDraft(event.target.value)} placeholder="ค้นหา SKU หรือชื่อสินค้า" value={searchDraft} /><button className="rounded-lg border px-3 py-2 text-sm font-medium" type="submit">ค้นหา</button><button className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" disabled={selectedProducts.length === 0} onClick={() => window.print()} type="button">พิมพ์ฉลากที่เลือก ({selectedProducts.length})</button></form>
      <ul className="mt-3 max-h-60 divide-y overflow-auto">{products.map((product) => <li className="flex items-center gap-3 py-2" key={product.id}><input aria-label={`เลือกฉลาก ${product.sku}`} checked={!!selected[product.id]} onChange={(event) => setSelected((current) => ({ ...current, [product.id]: event.target.checked }))} type="checkbox" /><span className="text-sm"><strong>{product.sku}</strong> · {product.name} <code className="ml-1 text-xs text-slate-500">{product.internal_code}</code></span></li>)}</ul>
      <form className="mt-5 flex flex-wrap gap-2" onSubmit={(event) => { event.preventDefault(); void resolveCode() }}><label className="sr-only" htmlFor="resolve-internal-code">สแกนหรือพิมพ์รหัสภายใน</label><input autoComplete="off" className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2" id="resolve-internal-code" onChange={(event) => setCode(event.target.value)} placeholder="สแกน QR / Barcode หรือพิมพ์ PS-00000001" value={code} /><button className="rounded-lg border px-3 py-2 text-sm font-medium" disabled={busy} type="submit">ค้นหารหัส</button><button className="rounded-lg border px-3 py-2 text-sm font-medium" disabled={scanning} onClick={() => { setError(''); if (!(window as CodeWindow).BarcodeDetector || !navigator.mediaDevices?.getUserMedia) { setError('เบราว์เซอร์นี้ไม่รองรับกล้องสแกน ใช้เครื่องสแกน USB หรือพิมพ์รหัสสินค้าได้'); return }; setScanning(true) }} type="button">เปิดกล้อง</button>{scanning && <button className="rounded-lg border px-3 py-2 text-sm font-medium" onClick={() => setScanning(false)} type="button">ปิดกล้อง</button>}</form>
      {scanning && <video className="mt-3 max-h-64 w-full rounded-lg bg-slate-950 object-contain" autoPlay muted playsInline ref={video} />}
      {resolved && <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900" role="status">พบ {resolved.internal_code} · {resolved.sku} · {resolved.name}</p>}
      {error && <p className="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{error}</p>}
    </div>
    <div className="partshelf-label-sheet" aria-label="ฉลากสินค้าที่เลือก">{selectedProducts.map((product) => <InternalLabel key={product.id} product={product} />)}</div>
  </section>
}
