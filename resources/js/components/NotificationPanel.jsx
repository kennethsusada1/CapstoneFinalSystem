import axios from 'axios';
import { useMemo, useState } from 'react';

export default function NotificationPanel({ notifications = [], onNotificationsChange }) {
    const [open, setOpen] = useState(false);
    const unreadCount = useMemo(() => notifications.filter((item) => !item.is_read).length, [notifications]);

    const markRead = async (notification) => {
        onNotificationsChange?.(notifications.map((item) => (item.id === notification.id ? { ...item, is_read: true } : item)));
        await axios.post(`/api/notifications/${notification.id}/read`);
        if (notification.url) window.location.href = notification.url;
    };

    const readAll = async () => {
        onNotificationsChange?.(notifications.map((item) => ({ ...item, is_read: true })));
        await axios.post('/api/notifications/read-all');
    };

    return (
        <div className="notif-wrap" style={{ position: 'relative' }}>
            <button type="button" onClick={() => setOpen((value) => !value)} style={{ width: 36, height: 36, borderRadius: 18, border: '1px solid var(--admin-border)', background: 'var(--admin-card)', color: 'var(--admin-text-secondary)', cursor: 'pointer', position: 'relative' }}>
                <i className="bi bi-bell" />
                {unreadCount > 0 && <span style={{ position: 'absolute', top: -4, right: -2, minWidth: 18, height: 18, borderRadius: 999, background: '#ef4444', color: '#fff', fontSize: '0.68rem', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 4px' }}>{unreadCount}</span>}
            </button>

            {open && (
                <div className="notification-panel" style={{ position: 'absolute', top: 'calc(100% + 10px)', right: 0, width: 360, maxWidth: 'calc(100vw - 24px)', background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 18, boxShadow: 'var(--admin-shadow)', overflow: 'hidden', zIndex: 1200 }}>
                    <div className="notification-panel__header" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0.9rem 1rem', borderBottom: '1px solid var(--admin-border)' }}>
                        <div>
                            <div className="notification-panel__title" style={{ fontWeight: 700, color: 'var(--admin-text-primary)' }}>Notifications</div>
                            <div className="notification-panel__meta" style={{ fontSize: '0.76rem', color: 'var(--admin-text-muted)' }}>{unreadCount} unread</div>
                        </div>
                        <button type="button" onClick={readAll} style={{ border: 'none', background: 'transparent', color: 'var(--admin-accent)', cursor: 'pointer', fontSize: '0.8rem', fontWeight: 600 }}>Read all</button>
                    </div>
                    <div className="notification-panel__body" style={{ maxHeight: 420, overflowY: 'auto' }}>
                        {notifications.length === 0 && <div style={{ padding: '1rem', color: 'var(--admin-text-muted)', fontSize: '0.85rem' }}>No notifications yet.</div>}
                        {notifications.map((notification) => (
                            <button key={notification.id} type="button" className={`notification-item${notification.is_read ? '' : ' is-unread'}`} onClick={() => markRead(notification)} style={{ width: '100%', textAlign: 'left', padding: '0.95rem 1rem', border: 'none', borderBottom: '1px solid var(--admin-border)', background: notification.is_read ? 'transparent' : 'rgba(37,99,235,0.08)', cursor: 'pointer' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', marginBottom: '0.25rem' }}>
                                    <div style={{ fontWeight: 700, color: 'var(--admin-text-primary)', fontSize: '0.88rem' }}>{notification.title}</div>
                                    <div style={{ fontSize: '0.72rem', color: 'var(--admin-text-muted)', whiteSpace: 'nowrap' }}>{notification.time}</div>
                                </div>
                                <div style={{ fontSize: '0.82rem', color: 'var(--admin-text-secondary)', lineHeight: 1.55 }}>{notification.body}</div>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
