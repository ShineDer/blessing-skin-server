import React, { useState, useEffect } from 'react'
import * as fetch from '@/scripts/net'
import { showModal } from '@/scripts/notify'

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
  }

  const unreadCount = notifications.filter((notification) => !notification.read && !notification.read_at).length
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
