import { lazy, Suspense, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
const Workspace = lazy(() => import('./Workspace'))

type User = {
  id: number
  name: string
  email: string
  role: 'owner' | 'manager' | 'staff'
}

type UserResponse = { user: User }
type InstallPrompt = Event & {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>
}

function csrfCookie(): string | undefined {
  const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : undefined
}

async function initializeCsrf(): Promise<void> {
  const response = await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) throw new Error('เริ่มการยืนยันตัวตนไม่สำเร็จ กรุณาลองอีกครั้ง')
}

async function loadCurrentUser(): Promise<User | null> {
  const response = await fetch('/api/user', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })

  if (response.status === 401) return null
  if (!response.ok) throw new Error('ตรวจสอบสถานะการเข้าสู่ระบบไม่สำเร็จ')

  return ((await response.json()) as UserResponse).user
}

function App() {
  const [user, setUser] = useState<User | null>(null)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [demoAccounts, setDemoAccounts] = useState<Partial<Record<User['role'], string>>>({})
  const [selectedRole, setSelectedRole] = useState<User['role']>('owner')
  const isDemo = !!demoAccounts.owner
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [installPrompt, setInstallPrompt] = useState<InstallPrompt | null>(null)

  useEffect(() => {
    let active = true
    fetch('/api/demo', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(async (response) => response.ok ? response.json() : { enabled: false })
      .then((body: { enabled: boolean; accounts?: Partial<Record<User['role'], string>> }) => {
        if (active && body.enabled && body.accounts) {
          setDemoAccounts(body.accounts)
          setEmail(body.accounts.owner ?? '')
        }
      }).catch(() => { /* Ordinary login remains available if demo metadata is unavailable. */ })
    return () => { active = false }
  }, [])

  useEffect(() => {
    const available = (event: Event) => {
      event.preventDefault()
      setInstallPrompt(event as InstallPrompt)
    }
    const installed = () => setInstallPrompt(null)
    window.addEventListener('beforeinstallprompt', available)
    window.addEventListener('appinstalled', installed)
    return () => {
      window.removeEventListener('beforeinstallprompt', available)
      window.removeEventListener('appinstalled', installed)
    }
  }, [])

  useEffect(() => {
    let active = true

    initializeCsrf()
      .then(loadCurrentUser)
      .then((currentUser) => {
        if (active) setUser(currentUser)
      })
      .catch(() => {
        if (active) setError('เชื่อมต่อระบบไม่ได้ กรุณารีเฟรชแล้วลองอีกครั้ง')
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await initializeCsrf()
      const xsrfToken = csrfCookie()
      if (!xsrfToken) throw new Error('ไม่พบโทเคนความปลอดภัย กรุณาลองอีกครั้ง')

      const response = await fetch('/api/login', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': xsrfToken,
        },
        body: JSON.stringify(isDemo ? { username: email, password, role: selectedRole } : { email, password }),
      })

      if (!response.ok) {
        if (response.status === 422) throw new Error(isDemo ? 'Username รหัสผ่าน หรือบทบาทไม่ตรงกับบัญชี' : 'อีเมลหรือรหัสผ่านไม่ถูกต้อง')
        if (response.status === 429) throw new Error('ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอสักครู่')
        throw new Error('เข้าสู่ระบบไม่สำเร็จ กรุณาลองอีกครั้ง')
      }

      const result = (await response.json()) as UserResponse
      setUser(result.user)
      setPassword('')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'เข้าสู่ระบบไม่สำเร็จ กรุณาลองอีกครั้ง')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleLogout() {
    setSubmitting(true)
    setError('')

    try {
      await initializeCsrf()
      const xsrfToken = csrfCookie()
      if (!xsrfToken) throw new Error('ไม่พบโทเคนความปลอดภัย กรุณาลองอีกครั้ง')

      const response = await fetch('/api/logout', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken },
      })

      if (!response.ok) throw new Error('ออกจากระบบไม่สำเร็จ กรุณาลองอีกครั้ง')

      setUser(null)
      setEmail(isDemo ? demoAccounts[selectedRole] ?? '' : '')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'ออกจากระบบไม่สำเร็จ กรุณาลองอีกครั้ง')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleInstall() {
    if (!installPrompt) return
    await installPrompt.prompt()
    await installPrompt.userChoice
    setInstallPrompt(null)
  }

  return (
    user ? <Suspense fallback={<p className="panel-loading" role="status">กำลังโหลดพื้นที่ทำงาน…</p>}><Workspace user={user} isDemo={isDemo} submitting={submitting} error={error} onLogout={() => void handleLogout()} onInstall={installPrompt ? () => void handleInstall() : undefined} /></Suspense> :
    <main className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8">
          <a className="flex items-center gap-3 font-semibold tracking-tight" href="/">
            <span aria-hidden="true" className="grid size-10 place-items-center rounded-xl bg-blue-700 text-lg text-white">
              P
            </span>
            <span className="text-xl">PartShelf</span>
            {isDemo && <span className="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">Demo Environment</span>}
          </a>
          <div className="flex items-center gap-2">
            {installPrompt && <button className="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 ring-1 ring-blue-200" onClick={() => void handleInstall()} type="button">ติดตั้งแอป</button>}
              <span className="rounded-full bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 ring-1 ring-blue-200">
                ระบบจัดการอะไหล่
              </span>
          </div>
        </div>
      </header>

      <section className="login-layout mx-auto grid max-w-6xl gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[1fr_400px] lg:items-center">
        <div>
          <p className="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-blue-700">สินค้า · สต๊อก · ตำแหน่ง · ตรวจนับ</p>
          <h1 className="max-w-3xl text-2xl font-bold leading-tight tracking-tight sm:text-4xl">
            ดูแลสินค้าและตรวจนับสต๊อกได้ง่ายขึ้น
          </h1>
          <p className="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
            ค้นหาสินค้า ดูตำแหน่งจัดเก็บ ตรวจสอบยอดคงเหลือ และบันทึกผลนับในขั้นตอนสั้น ๆ
          </p>

          <div className="login-features mt-6 grid gap-3 sm:grid-cols-2">
            {[
              ['สินค้า', 'ค้นหาและดูข้อมูลสินค้า'],
              ['สต๊อก', 'ตรวจสอบยอดและความเคลื่อนไหว'],
              ['ตำแหน่งจัดเก็บ', 'ค้นหาสินค้าตามชั้นวาง'],
              ['ตรวจนับ', 'บันทึกยอดนับและผลต่าง'],
            ].map(([title, description]) => (
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" key={title}>
                <h2 className="font-semibold">{title}</h2>
                <p className="mt-2 text-sm leading-6 text-slate-600">{description}</p>
              </article>
            ))}
          </div>
        </div>

        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
          {loading ? (
            <div className="py-10 text-center text-sm text-slate-600" role="status">กำลังตรวจสอบการเข้าสู่ระบบ…</div>
          ) : (
            <form className="space-y-5" onSubmit={handleLogin}>
              <div>
                <p className="text-sm font-semibold text-blue-700">PartShelf</p>
                <h2 className="mt-2 text-2xl font-bold">เข้าสู่ระบบ</h2>
                <p className="mt-2 text-sm leading-6 text-slate-600">{isDemo ? 'ทดลองจัดการอะไหล่ด้วยข้อมูลตัวอย่าง เลือกบทบาทและกรอกรหัสผ่านของบัญชี' : 'กรอกบัญชีที่ผู้ดูแลระบบสร้างให้'}</p>
              </div>

              {isDemo && <label className="block text-sm font-medium text-slate-700" htmlFor="demo-role">
                บทบาท / Role
                <select className="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3" id="demo-role" value={selectedRole} onChange={(event) => {
                  const role = event.target.value as User['role']
                  setSelectedRole(role); setEmail(demoAccounts[role] ?? ''); setPassword(''); setError('')
                }}>
                  <option value="owner">Owner — เจ้าของร้าน</option>
                  <option value="manager">Manager — ผู้จัดการ</option>
                  <option value="staff">Staff — พนักงาน</option>
                </select>
              </label>}

              <label className="block text-sm font-medium text-slate-700" htmlFor="email">
                {isDemo ? 'Username' : 'อีเมล'}
                <input
                  autoComplete="username"
                  className="mt-2 block w-full rounded-xl border border-slate-300 px-3.5 py-3 text-base text-slate-900 outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
                  id="email"
                  name="email"
                  onChange={(event) => setEmail(event.target.value)}
                  required
                  type={isDemo ? 'text' : 'email'}
                  value={email}
                />
              </label>

              <label className="block text-sm font-medium text-slate-700" htmlFor="password">
                รหัสผ่าน
                <input
                  autoComplete="current-password"
                  className="mt-2 block w-full rounded-xl border border-slate-300 px-3.5 py-3 text-base text-slate-900 outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
                  id="password"
                  name="password"
                  onChange={(event) => setPassword(event.target.value)}
                  required
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                />
              </label>
              <button aria-controls="password" aria-pressed={showPassword} className="rounded-lg px-2 py-1 text-sm font-semibold text-blue-700 focus:ring-2 focus:ring-blue-200" onClick={() => setShowPassword(!showPassword)} type="button">{showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'}</button>

              {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{error}</p>}

              <button
                className="w-full rounded-xl bg-blue-700 px-4 py-3 font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-wait disabled:opacity-60"
                disabled={submitting}
                type="submit"
              >
                {submitting ? 'กำลังตรวจสอบ…' : 'เข้าสู่ระบบ'}
              </button>
            </form>
          )}

        </div>
      </section>
    </main>
  )
}

export default App
