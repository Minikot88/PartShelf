import { useEffect } from 'react'

export function confirmNavigation(): boolean {
  return window.dispatchEvent(new Event('partshelf:before-navigate', { cancelable: true }))
}

export function useUnsavedChanges(dirty: boolean) {
  useEffect(() => {
    if (!dirty) return
    const beforeUnload = (event: BeforeUnloadEvent) => { event.preventDefault(); event.returnValue = '' }
    const beforeNavigate = (event: Event) => {
      if (!window.confirm('มีข้อมูลที่ยังไม่ได้บันทึก ต้องการออกจากหน้านี้หรือไม่?')) event.preventDefault()
    }
    window.addEventListener('beforeunload', beforeUnload)
    window.addEventListener('partshelf:before-navigate', beforeNavigate)
    return () => {
      window.removeEventListener('beforeunload', beforeUnload)
      window.removeEventListener('partshelf:before-navigate', beforeNavigate)
    }
  }, [dirty])
}
