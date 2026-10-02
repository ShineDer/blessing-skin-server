import React, { useEffect, useState } from 'react'
import * as fetch from '@/scripts/net'
import { showModal } from '@/scripts/notify'
import Pagination from '@/components/Pagination'
import { t } from '@/scripts/i18n'

type Notification = {
  id: string
  title: string
  content?: string
  content_html?: string
  time?: string
  read_at?: string | null
  read?: boolean
}

type Page = { data: Notification[]; current_page: number; last_page: number }

const Notifications: React.FC = () => {
  const [page, setPage] = useState<Page>({ data: [], current_page: 1, last_page: 1 })
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)

  const load = async (current = 1) => {
    setLoading(true)
    const result = await fetch.get<Page>('/user/notifications', { page: current })
    setPage(result?.data ? result : { data: [], current_page: current, last_page: current })
    setLoading(false)
  }

  useEffect(() => { void load() }, [])

  const read = async (notification: Notification) => {
    if (!notification.read_at && !notification.read) {
      await fetch.post(`/user/notifications/${notification.id}/read`)
      setPage((old) => ({ ...old, data: old.data.map((item) => item.id === notification.id ? { ...item, read: true, read_at: new Date().toISOString() } : item) }))
    }
    showModal({ mode: 'alert', title: notification.title, children: <><div dangerouslySetInnerHTML={{ __html: notification.content_html || notification.content || '' }} /><br /><small>{notification.time}</small></> })
  }

  const markAllRead = async () => {
    setBusy(true)
    await fetch.post('/user/notifications/read-all')
    setPage((old) => ({ ...old, data: old.data.map((item) => ({ ...item, read: true, read_at: item.read_at || new Date().toISOString() })) }))
    setBusy(false)
  }

  const remove = async (id: string) => {
    await fetch.del(`/user/notifications/${id}`)
    setPage((old) => ({ ...old, data: old.data.filter((item) => item.id !== id) }))
  }

  return <div className="card">
    <div className="card-header d-flex justify-content-between align-items-center">
      <h3 className="card-title">{t('user.notifications.title')}</h3>
      <button className="btn btn-sm btn-outline-primary" disabled={busy} onClick={() => void markAllRead()}>{t('user.notifications.mark-all-read')}</button>
    </div>
    <div className="card-body p-0">
      {loading ? <p className="text-center p-4">...</p> : page.data.length === 0 ? <p className="text-center text-muted p-4">{t('user.no-unread')}</p> : <div className="list-group list-group-flush">{page.data.map((notification) => <div className={`list-group-item d-flex justify-content-between ${notification.read_at || notification.read ? '' : 'font-weight-bold'}`} key={notification.id}><button className="btn btn-link text-left p-0" onClick={() => void read(notification)}>{notification.title}</button><button className="btn btn-sm btn-link text-danger" aria-label="Delete" onClick={() => void remove(notification.id)}>&times;</button></div>)}</div>}
    </div>
    <div className="card-footer"><Pagination page={page.current_page} totalPages={page.last_page} onChange={(next) => void load(next)} /></div>
  </div>
}

export default Notifications
