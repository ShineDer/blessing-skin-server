import React from 'react'
import ReactDOM from 'react-dom'
import NotificationsList from '@/views/widgets/NotificationsList'
import { showModal } from './notify'
import * as fetch from './net'

const container = document.querySelector('[data-notifications]')
if (container) {
  ReactDOM.render(<NotificationsList />, container)
}

// The user dashboard exposes eligible campaign popups in blessing-extra.
// Marking as read makes the popup idempotent across subsequent visits.
const extra = document.querySelector<HTMLScriptElement>('#blessing-extra')
if (extra?.textContent && window.location.pathname.replace(/^\\//, '') === 'user') {
  try {
    const popupNotifications = JSON.parse(extra.textContent).popupNotifications || []
    const showPopup = async (notification: { id: string; title: string; content: string }) => {
      await showModal({ mode: 'alert', title: notification.title, dangerousHTML: notification.content })
      await fetch.post(`/user/notifications/${notification.id}/read`)
    }
    void popupNotifications.reduce(
      (chain: Promise<void>, notification: { id: string; title: string; content: string }) => chain.then(() => showPopup(notification)),
      Promise.resolve(),
    )
  } catch {
    // Ignore malformed optional dashboard data.
  }
}
