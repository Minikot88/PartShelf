export async function objectUpload(file: File, kind: 'image' | 'workbook', csrf: string): Promise<string | null> {
  const config = await fetch('/api/file-transport', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  if (!config.ok) throw new Error('ตรวจสอบการรับส่งไฟล์ไม่สำเร็จ')
  if (((await config.json()) as { mode?: string }).mode !== 'object') return null
  if (file.size < 1 || file.size > 5 * 1024 * 1024) throw new Error('ไฟล์ต้องมีขนาดไม่เกิน 5 MB')
  const sha256 = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await file.arrayBuffer())), (value) => value.toString(16).padStart(2, '0')).join('')
  const mime = kind === 'workbook' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : file.type
  const response = await fetch('/api/file-transfers', {
    method: 'POST', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf },
    body: JSON.stringify({ kind, mime, size: file.size, sha256 }),
  })
  const result = (await response.json()) as { token: string; upload_url: string; headers: Record<string, string>; message?: string }
  if (!response.ok) throw new Error(result.message ?? 'เริ่มอัปโหลดไม่สำเร็จ')
  const uploaded = await fetch(result.upload_url, { method: 'PUT', credentials: 'omit', headers: result.headers, body: file })
  if (!uploaded.ok) throw new Error('อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองอีกครั้ง')
  return result.token
}
