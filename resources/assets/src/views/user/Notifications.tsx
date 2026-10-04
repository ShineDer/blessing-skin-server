import React, { useEffect, useState } from 'react'
import * as fetch from '@/scripts/net'
import { showModal, toast } from '@/scripts/notify'
import Pagination from '@/components/Pagination'
import { t } from '@/scripts/i18n'

function updateUnreadBadges(count: number) {
  const formatted = count > 99 ? '99+' : String(count)
  document.querySelectorAll<HTMLElement>('[data-notification-unread-count]').forEach((badge) => {
    badge.textContent = formatted
    badge.classList.toggle('d-none', count <= 0)
  })
  document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: count }))
}

type Notification = {
  id: string
  title: string
  content?: string
  content_html?: string
  time?: string
  read_at?: string | null
  read?: boolean
}

type Page = { data: Notification[]; current_page: number; last_page: number; unread_count?: number }

const Notifications: React.FC = () => {
  const [page, setPage] = useState<Page>({ data: [], current_page: 1, last_page: 1 })
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [retention, setRetention] = useState('0')
  const [unreadCount, setUnreadCount] = useState(0)

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
    const nextPage = result?.data ? result : { data: [], current_page: current, last_page: current }
    setPage(nextPage)
    const count = typeof result?.unread_count === 'number'
      ? result.unread_count
      : nextPage.data.filter((item) => !item.read_at && !item.read).length
    setUnreadCount(count)
    updateUnreadBadges(count)
    document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: count }))
    setLoading(false)
  }

  useEffect(() => { void load() }, [])

  const read = async (notification: Notification) => {
    const wasUnread = !notification.read_at && !notification.read
    const detail = await fetch.post<{
      title: string
      content: string
      time: string
    }>(`/user/notifications/${notification.id}/read`)
    if (wasUnread) {
      setPage((old) => ({ ...old, data: old.data.map((item) => item.id === notification.id ? { ...item, read: true, read_at: new Date().toISOString() } : item) }))
      const count = Math.max(0, unreadCount - 1)
      setUnreadCount(count)
      updateUnreadBadges(count)
    }
    await showModal({
      mode: 'alert',
      title: detail.title,
      children: <><div dangerouslySetInnerHTML={{ __html: detail.content }} /><br /><small>{detail.time}</small></>,
    })
  }

  const markAllRead = async () => {
    if (!(await confirm(t('user.notifications.mark-all-read-warning')))) return
    setBusy(true)
    await fetch.post('/user/notifications/read-all')
    setPage((old) => ({ ...old, data: old.data.map((item) => ({ ...item, read: true, read_at: new Date().toISOString() })) }))
    setUnreadCount(0)
    updateUnreadBadges(0)
    document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: 0 }))
    setBusy(false)
  }

  const markRead = async (id: string) => {
    const notification = page.data.find((item) => item.id === id)
    if (!notification || notification.read_at || notification.read) return
    await fetch.post(`/user/notifications/${id}/read`)
    setPage((old) => ({ ...old, data: old.data.map((item) => item.id === id ? { ...item, read: true, read_at: new Date().toISOString() } : item) }))
    const count = Math.max(0, unreadCount - 1)
    setUnreadCount(count)
    updateUnreadBadges(count)
    document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: count }))
  }

  const markUnread = async (id: string) => {
    const notification = page.data.find((item) => item.id === id)
    if (!notification || (!notification.read_at && !notification.read)) return
    await fetch.post(`/user/notifications/${id}/unread`)
    setPage((old) => ({ ...old, data: old.data.map((item) => item.id === id ? { ...item, read: false, read_at: null } : item) }))
    const count = unreadCount + 1
    setUnreadCount(count)
    updateUnreadBadges(count)
    document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: count }))
  }

  const remove = async (id: string) => {
    if (!(await confirm(t('user.notifications.delete-warning')))) return
    await fetch.del(`/user/notifications/${id}`)
    const removed = page.data.find((item) => item.id === id)
    setPage((old) => ({ ...old, data: old.data.filter((item) => item.id !== id) }))
    if (removed && !removed.read_at && !removed.read) {
      const count = Math.max(0, unreadCount - 1)
      setUnreadCount(count)
      updateUnreadBadges(count)
      document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: count }))
    }
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
      <button className="btn btn-sm btn-outline-primary rounded-pill ml-auto flex-shrink-0" disabled={busy} onClick={() => void markAllRead()}>{t('user.notifications.mark-all-read')}</button>
    </div>
    <div className="card-body p-0">
      {loading ? (
        <p className="text-center p-4">...</p>
      ) : page.data.length === 0 ? (
        <p className="text-center text-muted p-4">
          一片空白，连一缕阳光也不曾留下~<br />
          <small>有新的通知时会显示在这里</small>
        </p>
      ) : (
        <div className="list-group list-group-flush">
          {page.data.map((notification) => {
            const isRead = Boolean(notification.read_at || notification.read)
            return (
              <div
                className={`list-group-item d-flex align-items-center ${isRead ? '' : 'font-weight-bold'}`}
                key={notification.id}
              >
                <button
                  className="btn btn-link text-left p-0 flex-grow-1 text-truncate notification-title"
                  style={{ color: 'var(--g-ink)' }}
                  onClick={() => void read(notification)}
                >
                  {notification.title}
                </button>
                <div className="ml-auto flex-shrink-0 d-flex align-items-center">
                  <button
                    className={`btn btn-sm rounded-pill mr-1 ${isRead ? 'btn-outline-secondary' : 'btn-primary'}`}
                    onClick={() => void (isRead ? markUnread(notification.id) : markRead(notification.id))}
                  >
                    {t(isRead ? 'user.notifications.mark-unread' : 'user.notifications.mark-read')}
                  </button>
                  <button
                    className="btn btn-sm btn-danger rounded-square notification-delete"
                    title={t('user.notifications.delete')}
                    aria-label={t('user.notifications.delete')}
                    onClick={() => void remove(notification.id)}
                  >
                    <i className="fas fa-trash-alt" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
    <div className="card-footer"><Pagination page={page.current_page} totalPages={page.last_page} onChange={(next) => void load(next)} /></div>
  </div>
}

export default Notifications
