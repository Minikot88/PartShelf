import { lazy, Suspense, useEffect, useRef, useState } from 'react'
import { confirmNavigation } from './useUnsavedChanges'

const Catalog = lazy(() => import('./Catalog'))
const Dashboard = lazy(() => import('./DashboardPanel'))
const Stock = lazy(() => import('./StockPanel'))
const Count = lazy(() => import('./StockCountPanel'))
const Excel = lazy(() => import('./ProductExcelPanel'))
const Labels = lazy(() => import('./ProductLabelPanel'))
const StockNotes = lazy(() => import('./StockNotesPanel'))

type Role = 'owner' | 'manager' | 'staff'
type View = 'notes' | 'dashboard' | 'products' | 'categories' | 'locations' | 'stock' | 'count' | 'excel' | 'labels'
const views: { id: View; title: string; path: string; management?: boolean }[] = [
  { id: 'notes', title: 'สินค้าในร้าน', path: 'M3 6l9-4 9 4v12l-9 4-9-4z M3 6l9 4 9-4 M12 10v12' },
  { id: 'dashboard', title: 'ภาพรวม', path: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z' },
  { id: 'count', title: 'ตรวจนับ', path: 'M8 4H5v17h14V4h-3 M9 2h6v4H9z M8 12l2 2 5-5 M8 18h8' },
  { id: 'products', title: 'สินค้า', path: 'M3 6l9-4 9 4v12l-9 4-9-4z M3 6l9 4 9-4 M12 10v12' },
  { id: 'stock', title: 'สต๊อกและรับเข้า', path: 'M3 20h18 M5 20V10h4v10 M10 20V5h4v15 M15 20v-7h4v7' },
  { id: 'locations', title: 'ตำแหน่งจัดเก็บ', path: 'M4 3v18 M20 3v18 M4 9h16 M4 16h16 M8 4h4v5 M14 11h3v5' },
  { id: 'categories', title: 'หมวดหมู่', path: 'M3 4h7l2 3h9v13H3z', management: true },
  { id: 'excel', title: 'นำเข้า / ส่งออก', path: 'M5 3h10l4 4v14H5z M9 12l6 6 M15 12l-6 6', management: true },
  { id: 'labels', title: 'รหัสและฉลาก', path: 'M3 3h6v6H3z M15 3h6v6h-6z M3 15h6v6H3z M15 15h3v3h3v3h-6z' },
]
const roleTitles = { owner: 'เจ้าของร้าน', manager: 'ผู้จัดการ', staff: 'พนักงาน' }

function currentView(): string {
  if (window.location.pathname !== '/') return 'not-found'
  return (window.location.hash.startsWith('#/') ? window.location.hash.slice(2) : '') || 'notes'
}

export default function Workspace({ user, isDemo, submitting, error, onLogout, onInstall }: {
  user: { name: string; role: Role }
  isDemo: boolean
  submitting: boolean
  error?: string
  onLogout: () => void
  onInstall?: () => void
}) {
  const [view, setView] = useState(() => currentView())
  const [collapsed, setCollapsed] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const drawer = useRef<HTMLDialogElement>(null)
  const menuButton = useRef<HTMLButtonElement>(null)
  const approvedNavigation = useRef(false)
  const active = views.find((item) => item.id === view)
  const denied = active?.management && user.role === 'staff'
  const links = views.filter((item) => !item.management || user.role !== 'staff')

  useEffect(() => {
    const changed = () => {
      if (!window.location.hash.startsWith('#/')) return
      const next = currentView()
      if (next !== view && !approvedNavigation.current && !confirmNavigation()) {
        window.history.replaceState(null, '', '/#/' + view)
        return
      }
      approvedNavigation.current = false
      setView(next); window.scrollTo({ top: 0 })
    }
    window.addEventListener('hashchange', changed)
    return () => window.removeEventListener('hashchange', changed)
  }, [user.role, view])

  function closeMenu() { drawer.current?.close(); setMenuOpen(false); menuButton.current?.focus() }
  function navigate(event: React.MouseEvent<HTMLAnchorElement>, destination: View) {
    event.preventDefault()
    if (destination !== view && !confirmNavigation()) return
    approvedNavigation.current = destination !== view
    closeMenu()
    if (window.location.pathname !== '/') window.location.assign('/#/' + destination)
    // oxlint-disable-next-line react/immutability -- browser navigation is an intentional event-handler side effect.
    else window.location.hash = '/' + destination
  }
  function navigation(mobile: boolean) {
    return <nav aria-label={mobile ? 'เมนูมือถือ' : 'เมนูหลัก'} className="workspace-nav">
      {links.filter(item => !['dashboard','count','stock','products'].includes(item.id)).map((item) => <a href={'/#/' + item.id} key={item.id} onClick={(event) => navigate(event, item.id)} aria-current={view === item.id ? 'page' : undefined} title={item.title}>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={item.path} /></svg>
        <span className={!mobile && collapsed ? 'sr-only' : ''}>{item.title}</span>
      </a>)}
      <details className="notes-advanced"><summary>Advanced / ประวัติเดิม</summary>{links.filter(item => ['dashboard','count','stock','products'].includes(item.id)).map(item => <a href={'/#/' + item.id} key={item.id} onClick={event => navigate(event, item.id)} aria-current={view === item.id ? 'page' : undefined}>{item.id === 'products' ? 'ทะเบียนสินค้า / รูป / รถ' : item.title}</a>)}</details>
    </nav>
  }

  return <div className={`app-shell ${collapsed ? 'sidebar-collapsed' : ''}`}>
    <a className="skip-link" href="#workspace-content" onClick={(event) => { event.preventDefault(); document.getElementById('workspace-content')?.focus() }}>ข้ามไปเนื้อหา</a>
    <aside className="desktop-sidebar" aria-label="แถบเมนู">
      <a className="workspace-brand" href="/#/notes" onClick={(event) => navigate(event, 'notes')} aria-label="PartShelf"><span aria-hidden="true">P</span>{!collapsed && <strong>PartShelf</strong>}</a>
      {!collapsed && <p className="sidebar-caption">บันทึกอะไหล่แบบง่าย</p>}
      {navigation(false)}
      <button className="collapse-button" aria-label={collapsed ? 'ขยายเมนู' : 'ย่อเมนู'} onClick={() => setCollapsed(!collapsed)} type="button">{collapsed ? '»' : '« ย่อเมนู'}</button>
      {!collapsed && <div className="sidebar-account"><strong>{user.name}</strong><span>{roleTitles[user.role]}</span></div>}
    </aside>
    <div className="workspace-main">
      <header className="workspace-header">
        <button className="mobile-menu-button" ref={menuButton} aria-label="เปิดเมนู" aria-controls="mobile-menu" aria-expanded={menuOpen} onClick={() => { drawer.current?.showModal(); setMenuOpen(true) }} type="button">☰</button>
        <div className="workspace-breadcrumb"><span>PartShelf</span><span aria-hidden="true">/</span><strong>{active?.title ?? 'ไม่พบหน้า'}</strong></div>
        <div className="workspace-header-actions">
          {isDemo && <span className="demo-badge">Demo</span>}
          {onInstall && <button onClick={onInstall} type="button">ติดตั้งแอป</button>}
          <button onClick={() => { if (confirmNavigation()) onLogout() }} disabled={submitting} type="button">ออกจากระบบ</button>
        </div>
      </header>
      <main className="workspace-content" id="workspace-content" tabIndex={-1}>
        <h1 className="sr-only">{active?.title ?? 'ไม่พบหน้า'}</h1>
        {error && <p role="alert" className="mb-4 rounded-lg bg-rose-50 p-3 text-rose-800">{error}</p>}
        <Suspense fallback={<p className="panel-loading" role="status">กำลังโหลดหน้า…</p>}>
          {denied ? <section className="state-panel"><h2>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2><p>บัญชีพนักงานใช้ค้นหา ดูตำแหน่ง และบันทึกตรวจนับได้</p><a href="/#/count" onClick={(event) => navigate(event, 'count')}>กลับไปตรวจนับ</a></section>
            : view === 'notes' ? <StockNotes role={user.role} />
            : view === 'dashboard' ? <Dashboard />
            : view === 'count' ? <Count key="count" role={user.role} />
            : view === 'stock' ? <Stock role={user.role} />
            : view === 'labels' ? <Labels />
            : view === 'excel' ? <Excel />
            : view === 'products' || view === 'categories' || view === 'locations' ? <Catalog key={view} role={user.role} view={view} />
            : <section className="state-panel"><h2>ไม่พบหน้าที่ต้องการ</h2><p>เลือกเมนูเพื่อกลับไปทำงานต่อ</p><a href="/#/count" onClick={(event) => navigate(event, 'count')}>กลับไปตรวจนับ</a></section>}
        </Suspense>
      </main>
    </div>
    <dialog id="mobile-menu" aria-label="เมนูการทำงาน" ref={drawer} className="mobile-drawer" onKeyDown={(event) => {
      if (event.key !== 'Tab') return
      const controls = event.currentTarget.querySelectorAll<HTMLElement>('a[href],button:not(:disabled)')
      const first = controls[0]; const last = controls[controls.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
    }} onClose={() => { setMenuOpen(false); menuButton.current?.focus() }}>
      <div className="drawer-heading"><strong>PartShelf</strong><button aria-label="ปิดเมนู" onClick={closeMenu} type="button">✕</button></div>
      <p className="sidebar-caption">{user.name} · {roleTitles[user.role]}</p>
      {navigation(true)}
    </dialog>
  </div>
}
