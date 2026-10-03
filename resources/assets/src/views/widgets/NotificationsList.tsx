import React, { useState, useEffect } from 'react'
import * as fetch from '@/scripts/net'
import { showModal } from '@/scripts/notify'

function updateUnreadBadges(count: number) {
  const formatted = count > 99 ? '99+' : String(count)
  document.querySelectorAll<HTMLElement>('[data-notification-unread-count]').forEach((badge) => {
    badge.textContent = formatted
    badge.classList.toggle('d-none', count <= 0)
  })
  document.dispatchEvent(new CustomEvent('notificationUnreadCountChanged', { detail: count }))
}

export type Notification = {
  id: string
  title: string
  read?: boolean
  read_at?: string | null
  popup?: boolean
  popup_seen?: boolean
}

const NotificationsList: React.FC = () => {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [noUnreadText, setNoUnreadText] = useState('')
  const [externalUnreadCount, setExternalUnreadCount] = useState<number | null>(null)

  useEffect(() => {
    const onUnreadCountChanged = (event: Event) => {
      setExternalUnreadCount((event as CustomEvent<number>).detail)
    }
    document.addEventListener('notificationUnreadCountChanged', onUnreadCountChanged)
    return () => document.removeEventListener('notificationUnreadCountChanged', onUnreadCountChanged)
  }, [])

  useEffect(() => {
    const dataset = document.querySelector<HTMLLIElement>(
      '[data-notifications]',
    )?.dataset
    if (dataset) {
      const notifications: Notification[] = JSON.parse(dataset.notifications!)
      setNotifications(notifications)
      setNoUnreadText(dataset.t!)
    }
  }, [])

  const read = async (id: string) => {
    const { title, content, time } = await fetch.post<{
      title: string
      content: string
      time: string
    }>(`/user/notifications/${id}`)

    showModal({
      mode: 'alert',
      title,
      children: (
        <>
          <div dangerouslySetInnerHTML={{ __html: content }}></div>
          <br />
          <small>{time}</small>
        </>
      ),
    })
    // Keep read notifications visible in the dropdown; only the badge changes.
    setNotifications((notifications) =>
      notifications.map((notification) =>
        notification.id === id
          ? { ...notification, read: true, read_at: new Date().toISOString() }
          : notification,
      ),
    )
    const nextCount = Math.max(0, unreadCount - 1)
    setExternalUnreadCount(nextCount)
    updateUnreadBadges(nextCount)
  }

  const unreadCount = externalUnreadCount ?? notifications.filter((notification) => !notification.read && !notification.read_at).length
  const hasUnread = unreadCount > 0

  return (
    <>
      <a className="nav-link" data-toggle="dropdown" href="#">
        <i className="far fa-bell"></i>
        {hasUnread && (
          <span className="badge badge-warning navbar-badge">
            {unreadCount}
          </span>
        )}
      </a>
      <div className="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        {hasUnread ? (
          notifications.map((notification) => (
            <>
              <a
                href="#"
                className="dropdown-item"
                key={notification.id}
                onClick={() => read(notification.id)}
              >
                <i className="far fa-circle text-info mr-2"></i>
                {notification.title}
              </a>
              <div className="dropdown-divider"></div>
            </>
          ))
        ) : (
          <p className="text-center text-muted pt-2 pb-2">{noUnreadText}</p>
        )}
      </div>
    </>
  )
}

export default NotificationsList
