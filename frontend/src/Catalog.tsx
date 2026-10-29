import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { objectUpload } from './fileTransport'
import { confirmNavigation, useUnsavedChanges } from './useUnsavedChanges'

type Role = 'owner' | 'manager' | 'staff'

type Fitment = {
  id?: number
  make: string
  model: string
  year: number | ''
  engine: string
}

type Category = {
  id: number
  name: string
  parent_id: number | null
  children?: Category[]
}

type StorageLocation = {
  id: number
  zone: string
  shelf: string
  level: string
  bin: string
  path: string
  products_count: number
}

type Product = {
  id: number
  sku: string
  name: string
  part_number: string | null
  base_unit: string
  min_stock_quantity: string
  category_id: number | null
  category?: Category | null
  storage_location_id: number | null
  storage_location?: StorageLocation | null
  image_path: string | null
  image_url: string | null
  fitments: Fitment[]
}

type ProductResponse = { data: Product[]; current_page?: number; last_page?: number; total?: number }
type CategoryResponse = { categories: Category[] }
type LocationResponse = { data: StorageLocation[] }

const emptyProduct = {
  sku: '',
  name: '',
  part_number: '',
  base_unit: 'ชิ้น',
  min_stock_quantity: '0',
  category_id: '',
}

function xsrfToken(): string | undefined {
  const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : undefined
}

async function refreshCsrf(): Promise<string> {
  const response = await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new Error('เริ่มการยืนยันตัวตนไม่สำเร็จ')
  const token = xsrfToken()
  if (!token) throw new Error('ไม่พบโทเคนความปลอดภัย')
  return token
}

async function responseError(response: Response): Promise<Error> {
  if (response.status === 403) return new Error('บัญชีนี้ไม่มีสิทธิ์แก้ไขข้อมูลสินค้า')
  if (response.status === 401) return new Error('เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่')
  if (response.status === 422) {
    const body = (await response.json()) as { errors?: Record<string, string[]> }
    const message = Object.values(body.errors ?? {}).flat()[0]
    return new Error(message ?? 'ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง')
  }
  return new Error('บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง')
}

function Catalog({ role, view = 'products' }: { role: Role; view?: 'products' | 'categories' | 'locations' }) {
  const canEdit = role === 'owner' || role === 'manager'
  const [products, setProducts] = useState<Product[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [locations, setLocations] = useState<StorageLocation[]>([])
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')
  const [editing, setEditing] = useState<Product | null>(null)
  const [fields, setFields] = useState(emptyProduct)
  const [fitments, setFitments] = useState<Fitment[]>([])
  const [image, setImage] = useState<File | null>(null)
  const [categoryName, setCategoryName] = useState('')
  const [categoryParentId, setCategoryParentId] = useState('')
  const [editingCategory, setEditingCategory] = useState<Category | null>(null)
  const [locationSearch, setLocationSearch] = useState('')
  const [locationDraft, setLocationDraft] = useState({ zone: '', shelf: '', level: '', bin: '' })
  const [editingLocation, setEditingLocation] = useState<StorageLocation | null>(null)
  const [expandedLocation, setExpandedLocation] = useState<number | null>(null)
  const [locationProducts, setLocationProducts] = useState<Product[]>([])
  const [locationSelections, setLocationSelections] = useState<Record<number, string>>({})
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const originalFields = editing ? { sku: editing.sku, name: editing.name, part_number: editing.part_number ?? '', base_unit: editing.base_unit, min_stock_quantity: editing.min_stock_quantity, category_id: editing.category_id?.toString() ?? '' } : emptyProduct
  useUnsavedChanges((view === 'products' && showForm && (JSON.stringify(fields) !== JSON.stringify(originalFields) || !!image || JSON.stringify(fitments) !== JSON.stringify(editing?.fitments ?? []))) || (view === 'products' && products.some((p) => locationSelections[p.id] !== undefined && locationSelections[p.id] !== String(p.storage_location_id ?? ''))) || (view === 'categories' && (categoryName !== (editingCategory?.name ?? '') || (!editingCategory && categoryParentId !== ''))) || (view === 'locations' && JSON.stringify(locationDraft) !== JSON.stringify(editingLocation ? { zone: editingLocation.zone, shelf: editingLocation.shelf, level: editingLocation.level, bin: editingLocation.bin } : { zone: '', shelf: '', level: '', bin: '' })))

  const categoryOptions = useMemo(() => categories.flatMap((category) => [
    category,
    ...(category.children ?? []),
  ]), [categories])

  async function loadCatalog(term = appliedSearch, locationTerm = locationSearch, targetPage = 1) {
    const query = `?page=${targetPage}${term ? `&search=${encodeURIComponent(term)}` : ''}`
    const locationQuery = locationTerm ? `?search=${encodeURIComponent(locationTerm)}` : ''
    const [productResponse, categoryResponse, locationResponse] = await Promise.all([
      fetch(`/api/products${query}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
      fetch('/api/categories', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
      fetch(`/api/locations${locationQuery}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
    ])
    if (!productResponse.ok) throw await responseError(productResponse)
    if (!categoryResponse.ok) throw await responseError(categoryResponse)
    if (!locationResponse.ok) throw await responseError(locationResponse)
    const productData = (await productResponse.json()) as ProductResponse
    const categoryData = (await categoryResponse.json()) as CategoryResponse
    const locationData = (await locationResponse.json()) as LocationResponse
    setProducts(productData.data)
    setPage(productData.current_page ?? 1); setLastPage(productData.last_page ?? 1)
    setCategories(categoryData.categories)
    setLocations(locationData.data)
  }

  useEffect(() => {
    let active = true
      Promise.all([
      fetch('/api/products', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
      fetch('/api/categories', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
        fetch('/api/locations', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
    ])
      .then(async ([productResponse, categoryResponse, locationResponse]) => {
        if (!productResponse.ok) throw await responseError(productResponse)
        if (!categoryResponse.ok) throw await responseError(categoryResponse)
        if (!locationResponse.ok) throw await responseError(locationResponse)
        return Promise.all([productResponse.json(), categoryResponse.json(), locationResponse.json()]) as Promise<[ProductResponse, CategoryResponse, LocationResponse]>
      })
      .then(([productData, categoryData, locationData]) => {
        if (!active) return
        setProducts(productData.data)
    setPage(productData.current_page ?? 1); setLastPage(productData.last_page ?? 1)
        setCategories(categoryData.categories)
        setLocations(locationData.data)
      })
      .catch((cause: unknown) => {
        if (active) setError(cause instanceof Error ? cause.message : 'โหลดสินค้าไม่สำเร็จ')
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  function editProduct(product: Product) {
    if (!confirmNavigation()) return
    setShowForm(true)
    setEditing(product)
    setFields({
      sku: product.sku,
      name: product.name,
      part_number: product.part_number ?? '',
      base_unit: product.base_unit,
      min_stock_quantity: product.min_stock_quantity,
      category_id: product.category_id?.toString() ?? '',
    })
    setFitments(product.fitments.map((fitment) => ({ ...fitment, year: fitment.year })))
    setImage(null)
    setMessage('')
    setError('')
  }

  function resetProductForm() {
    setShowForm(false)
    setEditing(null)
    setFields(emptyProduct)
    setFitments([])
    setImage(null)
    setMessage('')
    setError('')
  }

  async function saveProduct(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')

    try {
      const token = await refreshCsrf()
      const imageToken = image ? await objectUpload(image, 'image', token) : null
      const body: FormData | string = image && !imageToken ? new FormData() : JSON.stringify({
        ...fields,
        ...(imageToken ? { image_token: imageToken } : {}),
        category_id: fields.category_id || null,
        min_stock_quantity: Number(fields.min_stock_quantity),
        fitments: fitments.filter((fitment) => fitment.make.trim() || fitment.model.trim() || fitment.year || fitment.engine.trim()),
      })

      if (body instanceof FormData) {
        body.set('sku', fields.sku)
        body.set('name', fields.name)
        body.set('part_number', fields.part_number)
        body.set('base_unit', fields.base_unit)
        body.set('min_stock_quantity', fields.min_stock_quantity)
        body.set('category_id', fields.category_id)
        fitments.forEach((fitment, index) => {
          if (!fitment.make.trim() && !fitment.model.trim() && !fitment.year && !fitment.engine.trim()) return
          body.set(`fitments[${index}][make]`, fitment.make)
          body.set(`fitments[${index}][model]`, fitment.model)
          body.set(`fitments[${index}][year]`, String(fitment.year))
          body.set(`fitments[${index}][engine]`, fitment.engine)
        })
        if (image) body.set('image', image)
      }

      const response = await fetch(editing ? `/api/products/${editing.id}` : '/api/products', {
        method: editing ? 'PUT' : 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-XSRF-TOKEN': token,
          ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        },
        body,
      })
      if (!response.ok) throw await responseError(response)
      await loadCatalog(appliedSearch)
      resetProductForm()
      setMessage(editing ? 'บันทึกการแก้ไขสินค้าแล้ว' : 'เพิ่มสินค้าแล้ว')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'บันทึกสินค้าไม่สำเร็จ')
    } finally {
      setBusy(false)
    }
  }

  async function saveCategory(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')

    try {
      const token = await refreshCsrf()
      const response = await fetch(editingCategory ? `/api/categories/${editingCategory.id}` : '/api/categories', {
        method: editingCategory ? 'PUT' : 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify({
          name: categoryName,
          ...(!editingCategory && { parent_id: categoryParentId || null }),
        }),
      })
      if (!response.ok) throw await responseError(response)
      await loadCatalog(appliedSearch)
      setCategoryName('')
      setCategoryParentId('')
      setEditingCategory(null)
      setMessage(editingCategory ? 'บันทึกหมวดหมู่แล้ว' : 'เพิ่มหมวดหมู่แล้ว')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'บันทึกหมวดหมู่ไม่สำเร็จ')
    } finally {
      setBusy(false)
    }
  }

  async function saveLocation(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')

    try {
      const token = await refreshCsrf()
      const response = await fetch(editingLocation ? `/api/locations/${editingLocation.id}` : '/api/locations', {
        method: editingLocation ? 'PUT' : 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify(locationDraft),
      })
      if (!response.ok) throw await responseError(response)
      await loadCatalog(appliedSearch, locationSearch)
      setLocationDraft({ zone: '', shelf: '', level: '', bin: '' })
      setEditingLocation(null)
      setMessage(editingLocation ? 'บันทึกตำแหน่งแล้ว' : 'เพิ่มตำแหน่งแล้ว')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'บันทึกตำแหน่งไม่สำเร็จ')
    } finally {
      setBusy(false)
    }
  }

  async function showLocationProducts(location: StorageLocation) {
    if (expandedLocation === location.id) {
      setExpandedLocation(null)
      setLocationProducts([])
      return
    }

    setError('')
    try {
      const response = await fetch(`/api/locations/${location.id}/products`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      })
      if (!response.ok) throw await responseError(response)
      const result = (await response.json()) as ProductResponse
      setLocationProducts(result.data)
      setExpandedLocation(location.id)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'โหลดสินค้าในตำแหน่งไม่สำเร็จ')
    }
  }

  async function assignProductLocation(product: Product) {
    const selected = locationSelections[product.id] ?? String(product.storage_location_id ?? '')
    const storageLocationId = selected ? Number(selected) : null
    setBusy(true)
    setError('')
    setMessage('')

    try {
      const token = await refreshCsrf()
      const response = await fetch(`/api/products/${product.id}/location`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify({ storage_location_id: storageLocationId }),
      })
      if (!response.ok) throw await responseError(response)
      setLocationSelections({ ...locationSelections, [product.id]: String(storageLocationId ?? '') })
      await loadCatalog(appliedSearch, locationSearch)
      setMessage('บันทึกตำแหน่งสินค้าแล้ว โดยไม่เปลี่ยนยอดสต๊อก')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'เปลี่ยนตำแหน่งสินค้าไม่สำเร็จ')
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
    <section className="mx-auto max-w-6xl px-5 pb-16 sm:px-8" aria-label={view === 'products' ? 'สินค้าและหมวดหมู่' : view === 'categories' ? 'ข้อมูลหมวดหมู่' : 'ข้อมูลตำแหน่ง'}>
      {view === 'products' && <>
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm font-semibold text-blue-700">ข้อมูลสินค้า</p>
          <h2 className="mt-1 text-2xl font-bold tracking-tight" id="catalog-title">สินค้าและหมวดหมู่</h2>
          <p className="mt-1 text-sm text-slate-600">ค้นหาด้วย SKU ชื่อ Part Number หรือข้อมูลรถที่รองรับ</p>
        </div>
        <form className="flex w-full gap-2 sm:w-auto" onSubmit={(event) => {
          event.preventDefault()
          if (!confirmNavigation()) return
          setAppliedSearch(search.trim())
          loadCatalog(search.trim(), locationSearch).catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'ค้นหาไม่สำเร็จ'))
        }}>
          <label className="sr-only" htmlFor="product-search">ค้นหาสินค้า</label>
          <input
            className="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm sm:w-72"
            id="product-search"
            onChange={(event) => setSearch(event.target.value)}
            placeholder="ค้นหา SKU / ชื่อ / รถที่รองรับ"
            value={search}
          />
          <button className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white" type="submit">ค้นหา</button>
        </form>
      </div>
      {canEdit && <button className="mb-4 rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white" aria-expanded={showForm} onClick={() => { if (!showForm) setShowForm(true); else if (confirmNavigation()) resetProductForm() }} type="button">{showForm ? 'ปิดฟอร์มสินค้า' : 'เพิ่มสินค้าใหม่'}</button>}
      </>}

      {message && <p className="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{message}</p>}
      {error && <p className="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{error}</p>}
      {error && <button type="button" className="mb-4 rounded-lg border px-3 py-2" onClick={() => { setError(''); setLoading(true); void loadCatalog().catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'โหลดไม่สำเร็จ')).finally(() => setLoading(false)) }}>ลองใหม่</button>}
      {loading && <p className="panel-loading" role="status">กำลังโหลดข้อมูล…</p>}

      {canEdit && (view === 'categories' || (view === 'products' && showForm)) && (
        <div className="mb-5 grid gap-4">
          {view === 'products' && <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" onSubmit={saveProduct}>
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="font-semibold">{editing ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า'}</h3>
                <p className="mt-1 text-xs text-slate-500">กำหนด SKU, หมวดหมู่ และข้อมูลรถที่รองรับ</p>
              </div>
              {editing && <button className="text-sm font-medium text-slate-600 underline" onClick={() => { if (confirmNavigation()) resetProductForm() }} type="button">ยกเลิก</button>}
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="SKU *" value={fields.sku} onChange={(value) => setFields({ ...fields, sku: value })} required />
              <Field label="ชื่อสินค้า *" value={fields.name} onChange={(value) => setFields({ ...fields, name: value })} required />
              <Field label="Part Number" value={fields.part_number} onChange={(value) => setFields({ ...fields, part_number: value })} />
              <Field label="หน่วยนับ *" value={fields.base_unit} onChange={(value) => setFields({ ...fields, base_unit: value })} required />
              <label className="block text-sm font-medium text-slate-700">
                หมวดหมู่
                <select className="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5" onChange={(event) => setFields({ ...fields, category_id: event.target.value })} value={fields.category_id}>
                  <option value="">ไม่ระบุ</option>
                  {categoryOptions.map((category) => <option key={category.id} value={category.id}>{category.parent_id ? '　↳ ' : ''}{category.name}</option>)}
                </select>
              </label>
              <Field label="จำนวนขั้นต่ำ" min="0" step="0.001" type="number" value={fields.min_stock_quantity} onChange={(value) => setFields({ ...fields, min_stock_quantity: value })} />
            </div>

            <fieldset className="space-y-3 rounded-xl bg-slate-50 p-4">
              <div className="flex items-center justify-between gap-3">
                <legend className="px-1 text-sm font-semibold text-slate-800">รถที่รองรับ</legend>
                <button className="text-sm font-semibold text-blue-700" onClick={() => setFitments([...fitments, { make: '', model: '', year: '', engine: '' }])} type="button">+ เพิ่มรุ่นรถ</button>
              </div>
              {fitments.length === 0 && <p className="text-xs text-slate-500">ยังไม่ระบุรุ่นรถที่รองรับ</p>}
              {fitments.map((fitment, index) => (
                <div className="grid gap-2 sm:grid-cols-[1fr_1fr_100px_1fr_auto]" key={`${fitment.id ?? 'new'}-${index}`}>
                  <Field ariaLabel="ยี่ห้อรถ" placeholder="ยี่ห้อ" value={fitment.make} onChange={(value) => setFitment(index, { make: value })} />
                  <Field ariaLabel="รุ่นรถ" placeholder="รุ่น" value={fitment.model} onChange={(value) => setFitment(index, { model: value })} />
                  <Field ariaLabel="ปีรถ" min="1886" max="2200" placeholder="ปี" type="number" value={String(fitment.year)} onChange={(value) => setFitment(index, { year: value ? Number(value) : '' })} />
                  <Field ariaLabel="เครื่องยนต์" placeholder="เครื่องยนต์" value={fitment.engine} onChange={(value) => setFitment(index, { engine: value })} />
                  <button aria-label="ลบรุ่นรถ" className="px-2 text-slate-500 hover:text-rose-700" onClick={() => setFitments(fitments.filter((_, itemIndex) => itemIndex !== index))} type="button">ลบ</button>
                </div>
              ))}
            </fieldset>

            <div className="flex flex-wrap items-center justify-between gap-3">
              <label className="text-sm text-slate-600">
                รูปสินค้า (ไม่บังคับ)
                <input accept="image/jpeg,image/png,image/webp" className="mt-1 block max-w-full text-xs" onChange={(event) => setImage(event.target.files?.[0] ?? null)} type="file" />
              </label>
              <button className="rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60" disabled={busy} type="submit">
                {busy ? 'กำลังบันทึก…' : editing ? 'บันทึกการแก้ไข' : 'เพิ่มสินค้า'}
              </button>
            </div>
          </form>}

          {view === 'categories' && <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
              <h2 className="text-xl font-semibold">จัดการหมวดหมู่</h2>
              <p className="mt-1 text-xs text-slate-500">รองรับหมวดหลักและหมวดย่อยหนึ่งระดับ</p>
            </div>
            <form className="space-y-3" onSubmit={saveCategory}>
              <Field label={editingCategory ? 'แก้ชื่อหมวดหมู่' : 'ชื่อหมวดหมู่ใหม่'} value={categoryName} onChange={setCategoryName} required />
              {!editingCategory && (
                <label className="block text-sm font-medium text-slate-700">
                  หมวดหลัก (เว้นว่างเพื่อสร้างหมวดหลัก)
                  <select className="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5" onChange={(event) => setCategoryParentId(event.target.value)} value={categoryParentId}>
                    <option value="">หมวดหลัก</option>
                    {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                  </select>
                </label>
              )}
              <div className="flex gap-2">
                <button className="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white disabled:opacity-60" disabled={busy} type="submit">{editingCategory ? 'บันทึก' : 'เพิ่มหมวดหมู่'}</button>
                {editingCategory && <button className="text-sm text-slate-600 underline" onClick={() => { if (confirmNavigation()) { setEditingCategory(null); setCategoryName('') } }} type="button">ยกเลิก</button>}
              </div>
            </form>
            <ul className="space-y-2 border-t border-slate-100 pt-3">
              {categories.map((category) => (
                <li className="text-sm" key={category.id}>
                  <CategoryRow category={category} onEdit={(item) => { if (confirmNavigation()) { setEditingCategory(item); setCategoryName(item.name) } }} />
                  {(category.children ?? []).map((child) => <CategoryRow category={child} key={child.id} nested onEdit={(item) => { if (confirmNavigation()) { setEditingCategory(item); setCategoryName(item.name) } }} />)}
                </li>
              ))}
            </ul>
          </div>}
        </div>
      )}

      {view === 'locations' && <section className="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="locations-title">
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 className="text-xl font-semibold" id="locations-title">ตำแหน่งจัดเก็บ</h2>
            <p className="mt-1 text-xs text-slate-500">Zone → Shelf → Level → Bin · การย้ายตำแหน่งไม่เปลี่ยนยอดสต๊อก</p>
          </div>
          <form className="flex gap-2" onSubmit={(event) => {
            event.preventDefault()
            loadCatalog(appliedSearch, locationSearch).catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'ค้นหาตำแหน่งไม่สำเร็จ'))
          }}>
            <label className="sr-only" htmlFor="location-search">ค้นหาตำแหน่ง</label>
            <input className="w-44 rounded-lg border border-slate-300 px-3 py-2 text-sm" id="location-search" onChange={(event) => setLocationSearch(event.target.value)} placeholder="เช่น A-01-02-03" value={locationSearch} />
            <button className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700" type="submit">ค้นหาชั้น</button>
          </form>
        </div>

        {canEdit && (
          <form className="mb-4 grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-[1fr_1fr_1fr_1fr_auto]" onSubmit={saveLocation}>
            <Field ariaLabel="Zone" placeholder="Zone" value={locationDraft.zone} onChange={(zone) => setLocationDraft({ ...locationDraft, zone })} required />
            <Field ariaLabel="Shelf" placeholder="Shelf" value={locationDraft.shelf} onChange={(shelf) => setLocationDraft({ ...locationDraft, shelf })} required />
            <Field ariaLabel="Level" placeholder="Level" value={locationDraft.level} onChange={(level) => setLocationDraft({ ...locationDraft, level })} required />
            <Field ariaLabel="Bin" placeholder="Bin" value={locationDraft.bin} onChange={(bin) => setLocationDraft({ ...locationDraft, bin })} required />
            <div className="flex gap-2">
              <button className="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" disabled={busy} type="submit">{editingLocation ? 'บันทึก' : 'เพิ่มตำแหน่ง'}</button>
              {editingLocation && <button className="px-2 text-xs text-slate-600 underline" onClick={() => { if (!confirmNavigation()) return; setEditingLocation(null); setLocationDraft({ zone: '', shelf: '', level: '', bin: '' }) }} type="button">ยกเลิก</button>}
            </div>
          </form>
        )}

        {locations.length === 0 ? (
          <p className="rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">ยังไม่มีตำแหน่งจัดเก็บ</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {locations.map((location) => (
              <li className="py-3" key={location.id}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="font-medium text-slate-900">{location.path}</p>
                    <p className="mt-0.5 text-xs text-slate-500">{location.products_count} สินค้า</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <button className="text-sm font-medium text-blue-700 underline" onClick={() => showLocationProducts(location)} type="button">{expandedLocation === location.id ? 'ซ่อนสินค้า' : 'ดูสินค้า'}</button>
                    {canEdit && <button className="text-sm text-slate-600 underline" onClick={() => { if (!confirmNavigation()) return; setEditingLocation(location); setLocationDraft({ zone: location.zone, shelf: location.shelf, level: location.level, bin: location.bin }) }} type="button">แก้ไข</button>}
                  </div>
                </div>
                {expandedLocation === location.id && (
                  <ul className="mt-3 space-y-2 rounded-lg bg-slate-50 p-3 text-sm">
                    {locationProducts.length === 0 ? <li className="text-slate-500">ไม่มีสินค้าในตำแหน่งนี้</li> : locationProducts.map((product) => <li key={product.id}><span className="font-medium">{product.sku}</span> · {product.name}</li>)}
                  </ul>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>}

      {view === 'products' && !loading && <>
      <div className="mb-3 flex items-center justify-between">
        <h3 className="font-semibold">รายการสินค้า</h3>
        <span className="text-sm text-slate-500">{products.length} รายการในผลลัพธ์</span>
      </div>
      {lastPage > 1 && <div className="mb-3 flex items-center gap-3" aria-label="หน้ารายการสินค้า"><button type="button" className="rounded-lg border px-3 py-2" disabled={page <= 1 || busy} onClick={() => { if (confirmNavigation()) void loadCatalog(appliedSearch, locationSearch, page - 1).catch(() => setError('โหลดหน้าสินค้าไม่สำเร็จ')) }}>ก่อนหน้า</button><span className="text-sm">หน้า {page} / {lastPage}</span><button type="button" className="rounded-lg border px-3 py-2" disabled={page >= lastPage || busy} onClick={() => { if (confirmNavigation()) void loadCatalog(appliedSearch, locationSearch, page + 1).catch(() => setError('โหลดหน้าสินค้าไม่สำเร็จ')) }}>ถัดไป</button></div>}
      {products.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">ยังไม่พบสินค้า ลองค้นหาคำอื่นหรือเพิ่มสินค้าใหม่</div>
      ) : (
        <ul className="grid gap-3 md:grid-cols-2">
          {products.map((product) => (
            <li className="flex gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" key={product.id}>
              {product.image_url ? <img alt="" loading="lazy" decoding="async" width="56" height="56" className="size-14 shrink-0 rounded-lg bg-slate-100 object-cover" src={product.image_url} /> : <div aria-hidden="true" className="grid size-14 shrink-0 place-items-center rounded-lg bg-blue-50 text-xl font-semibold text-blue-700">{product.name.slice(0, 1)}</div>}
              <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-blue-700">{product.sku}</p>
                    <h4 className="mt-1 font-semibold text-slate-900">{product.name}</h4>
                  </div>
                  {canEdit && <button className="shrink-0 text-sm font-semibold text-blue-700 underline" onClick={() => editProduct(product)} type="button">แก้ไข</button>}
                </div>
                <p className="mt-1 text-xs text-slate-600">{product.part_number ? `Part Number ${product.part_number} · ` : ''}{product.category?.name ?? 'ไม่ระบุหมวด'} · ขั้นต่ำ {product.min_stock_quantity} {product.base_unit}</p>
                <p className="mt-1 text-xs text-slate-600">ตำแหน่ง: {product.storage_location?.path ?? 'ยังไม่กำหนด'}</p>
                <details className="mt-2 text-sm text-slate-600">
                  <summary className="cursor-pointer font-semibold text-blue-700">ดูรายละเอียด {product.sku}</summary>
                  <dl className="mt-2 space-y-1 rounded-lg bg-slate-50 p-3">
                    <div><dt className="inline font-medium">รหัสสินค้า: </dt><dd className="inline">{product.id}</dd></div>
                    <div><dt className="inline font-medium">หน่วยนับ: </dt><dd className="inline">{product.base_unit}</dd></div>
                    <div><dt className="inline font-medium">Part Number: </dt><dd className="inline">{product.part_number ?? 'ไม่ระบุ'}</dd></div>
                    <div><dt className="inline font-medium">ตำแหน่งจัดเก็บ: </dt><dd className="inline">{product.storage_location?.path ?? 'ยังไม่กำหนด'}</dd></div>
                  </dl>
                </details>
                {product.fitments.length > 0 && <p className="mt-2 text-xs leading-5 text-slate-600">รองรับ: {product.fitments.map((fitment) => `${fitment.make} ${fitment.model} ${fitment.year}${fitment.engine ? ` (${fitment.engine})` : ''}`).join(' · ')}</p>}
                {canEdit && (
                  <form className="mt-3 flex flex-wrap gap-2" onSubmit={(event) => { event.preventDefault(); assignProductLocation(product) }}>
                    <label className="sr-only" htmlFor={`location-for-${product.id}`}>กำหนดตำแหน่งสินค้า {product.sku}</label>
                    <select className="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs" id={`location-for-${product.id}`} onChange={(event) => setLocationSelections({ ...locationSelections, [product.id]: event.target.value })} value={locationSelections[product.id] ?? String(product.storage_location_id ?? '')}>
                      <option value="">ยังไม่กำหนดตำแหน่ง</option>
                      {locations.map((location) => <option key={location.id} value={location.id}>{location.path}</option>)}
                    </select>
                    <button className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-60" disabled={busy} type="submit">บันทึกตำแหน่ง</button>
                  </form>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
      </>}
    </section>
    </>
  )

  function setFitment(index: number, changes: Partial<Fitment>) {
    setFitments(fitments.map((fitment, itemIndex) => itemIndex === index ? { ...fitment, ...changes } : fitment))
  }
}

function Field({
  label,
  value,
  onChange,
  type = 'text',
  required = false,
  min,
  max,
  step,
  placeholder,
  ariaLabel,
}: {
  label?: string
  value: string
  onChange: (value: string) => void
  type?: string
  required?: boolean
  min?: string
  max?: string
  step?: string
  placeholder?: string
  ariaLabel?: string
}) {
  return (
    <label className="block text-sm font-medium text-slate-700">
      {label ?? ariaLabel}
      <input
        aria-label={ariaLabel}
        className={`${label ? 'mt-1.5' : ''} block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100`}
        max={max}
        min={min}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        required={required}
        step={step}
        type={type}
        value={value}
      />
    </label>
  )
}

function CategoryRow({ category, nested = false, onEdit }: { category: Category; nested?: boolean; onEdit: (category: Category) => void }) {
  return (
    <div className={`flex items-center justify-between gap-2 py-1 ${nested ? 'pl-4 text-slate-600' : 'font-medium'}`}>
      <span>{nested ? '↳ ' : ''}{category.name}</span>
      <button className="text-xs font-medium text-blue-700 underline" onClick={() => onEdit(category)} type="button">แก้ไข</button>
    </div>
  )
}

export default Catalog
