import React, { useEffect, useState } from 'react'
import * as fetch from '@/scripts/net'
import { showModal, toast } from '@/scripts/notify'
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
  const [retention, setRetention] = useState('0')

  const confirm = async (text: string) => {
    try {
      await showModal({ mode: 'confirm', text })
      return true
    } catch {
      return false
    }
  }

  const load = async (current = 1) => {
    setLoading(true)
    const result = await fetch.get<Page>('/user/notifications/data', { page: current })
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
    if (!(await confirm(t('user.notifications.mark-all-read-warning')))) return
    setBusy(true)
    await fetch.post('/user/notifications/read-all')
    setPage((old) => ({ ...old, data: old.data.map((item) => ({ ...item, read: true, read_at: new Date().toISOString() })) }))
    setBusy(false)
  }

  const markUnread = async (id: string) => {
    await fetch.post(`/user/notifications/${id}/unread`)
    setPage((old) => ({ ...old, data: old.data.map((item) => item.id === id ? { ...item, read: false, read_at: null } : item) }))
  }

  const remove = async (id: string) => {
    if (!(await confirm(t('user.notifications.delete-warning')))) return
    await fetch.del(`/user/notifications/${id}`)
    setPage((old) => ({ ...old, data: old.data.filter((item) => item.id !== id) }))
  }

  const updateRetention = async (value: string) => {
    const days = Number(value)
    if (days > 0 && !(await confirm(t('user.notifications.retention-warning')))) return
    setRetention(value)
    await fetch.post('/user/notifications/retention', { days })
    toast.success(t('user.notifications.retention-saved'))
  }

  return <div className="card">
    <div className="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 className="card-title">{t('user.notifications.title')}</h3>
        <label className="d-block small text-muted mb-0">
          {t('user.notifications.retention')}
          <select className="ml-2" value={retention} onChange={(event) => void updateRetention(event.target.value)}>
            <option value="0">{t('user.notifications.retention-forever')}</option>
            {[30, 90, 180, 365, 730].map((days) => <option key={days} value={days}>{days} {t('user.notifications.days')}</option>)}
          </select>
        </label>
      </div>
      <button className="btn btn-sm btn-outline-primary" disabled={busy} onClick={() => void markAllRead()}>{t('user.notifications.mark-all-read')}</button>
    </div>
    <div className="card-body p-0">
      {loading ? <p className="text-center p-4">...</p> : page.data.length === 0 ? <p className="text-center text-muted p-4">一片空白，连一缕阳光也不曾留下~<br /><small>有新的通知时会显示在这里</small></p> : <div className="list-group list-group-flush">{page.data.map((notification) => <div className={`list-group-item d-flex justify-content-between ${notification.read_at || notification.read ? '' : 'font-weight-bold'}`} key={notification.id}><button className="btn btn-link text-left p-0" onClick={() => void read(notification)}>{notification.title}</button><div><button className="btn btn-sm btn-link" onClick={() => void markUnread(notification.id)}>{t('user.notifications.mark-unread')}</button><button className="btn btn-sm btn-link text-danger" aria-label="Delete" onClick={() => void remove(notification.id)}>&times;</button></div></div>)}</div>}
    </div>
    <div className="card-footer"><Pagination page={page.current_page} totalPages={page.last_page} onChange={(next) => void load(next)} /></div>
  </div>
}

export default Notifications
