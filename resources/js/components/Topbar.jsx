import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import NotificationPanel from '@/components/NotificationPanel';
import defaultAvatar from '@/components/defaultAvatar';

function Breadcrumb({ title, description }) {
    const segments = description ? description.split(/[\/·>]+/).map((item) => item.trim()).filter(Boolean) : [];

    return (
        <nav style={{ display: 'flex', alignItems: 'center', fontSize: '0.78rem', minWidth: 0 }}>
            <Link href="/" className="tb-bc-home" style={{ display: 'flex', alignItems: 'center', gap: 4, color: 'var(--admin-text-muted)', textDecoration: 'none' }}>
                <i className="bi bi-house-door" style={{ fontSize: '0.72rem' }} />
                <span>Home</span>
            </Link>
            {segments.map((seg, i) => (
                <span key={`${seg}-${i}`} style={{ display: 'flex', alignItems: 'center' }} className="tb-bc-past">
                    <i className="bi bi-chevron-right tb-bc-sep" style={{ fontSize: '0.58rem', color: 'var(--admin-text-muted)', margin: '0 3px' }} />
                    <span style={{ color: 'var(--admin-text-muted)', fontWeight: 500 }}>{seg}</span>
                </span>
            ))}
            <span style={{ display: 'flex', alignItems: 'center', minWidth: 0 }}>
                <i className="bi bi-chevron-right tb-bc-sep" style={{ fontSize: '0.58rem', color: 'var(--admin-text-muted)', margin: '0 3px' }} />
                <span className="tb-bc-current" style={{ color: 'var(--admin-text-primary)', fontWeight: 700, fontSize: '0.8rem' }}>{title}</span>
            </span>
        </nav>
    );
}

export default function Topbar({ title, description, darkMode, onToggleDarkMode, onMobileMenuToggle, notifications, setNotifications }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const avatar = user?.avatar || user?.profile_photo_url || defaultAvatar(user?.name);
    const role = user?.roles?.[0] ?? 'employee';
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (menuRef.current && !menuRef.current.contains(event.target)) {
                setMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleLogout = () => {
        setMenuOpen(false);
        router.post('/logout');
    };

    return (
        <header className="tb-root" style={{ position: 'sticky', top: 0, zIndex: 900, backdropFilter: 'blur(16px)', background: 'var(--admin-topbar, rgba(10,15,26,0.88))', borderBottom: '1px solid var(--admin-border)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '1rem', padding: '0.65rem 1.25rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', minWidth: 0 }}>
                <button type="button" className="tb-hamburger" onClick={onMobileMenuToggle} style={{ display: 'none', width: 32, height: 32, borderRadius: 10, border: '1px solid var(--admin-border)', background: 'var(--admin-card)', color: 'var(--admin-text-secondary)', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}>
                    <i className="bi bi-list" />
                </button>
                <Breadcrumb title={title} description={description} />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <button type="button" onClick={onToggleDarkMode} style={{ width: 34, height: 34, borderRadius: 17, border: '1px solid var(--admin-border)', background: 'var(--admin-card)', color: 'var(--admin-text-secondary)', cursor: 'pointer' }}>
                    <i className={`bi ${darkMode ? 'bi-sun' : 'bi-moon-stars'}`} />
                </button>
                <NotificationPanel notifications={notifications} onNotificationsChange={setNotifications} />
                <div ref={menuRef} style={{ position: 'relative' }}>
                    <button type="button" onClick={() => setMenuOpen((value) => !value)} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', background: 'var(--admin-card)', border: '1px solid var(--admin-border)', borderRadius: 50, padding: '0.2rem 0.65rem 0.2rem 0.2rem', cursor: 'pointer' }}>
                        <div style={{ width: 28, height: 28, borderRadius: '50%', overflow: 'hidden', border: '2px solid rgba(59,130,246,0.45)', flexShrink: 0 }}>
                            <img src={avatar} alt={user?.name || 'User'} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                        </div>
                        <div className="tb-info" style={{ display: 'flex', flexDirection: 'column', textAlign: 'left' }}>
                            <span style={{ fontSize: '0.78rem', fontWeight: 600, color: 'var(--admin-text-primary)' }}>{user?.name || 'Guest User'}</span>
                            <span style={{ fontSize: '0.65rem', color: 'var(--admin-text-muted)', textTransform: 'capitalize' }}>{role}</span>
                        </div>
                        <i className="bi bi-chevron-down" style={{ fontSize: '0.65rem', color: 'var(--admin-text-muted)' }} />
                    </button>

                    {menuOpen && (
                        <div style={{ position: 'absolute', right: 0, top: 'calc(100% + 10px)', minWidth: 190, background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 16, boxShadow: 'var(--admin-shadow)', padding: '0.45rem', zIndex: 1300 }}>
                            <Link href={`/${role === 'system-admin' ? 'admin' : role}/profile`} onClick={() => setMenuOpen(false)} style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', padding: '0.7rem 0.8rem', borderRadius: 12, textDecoration: 'none', color: 'var(--admin-text-secondary)', fontSize: '0.84rem' }}>
                                <i className="bi bi-person-circle" />
                                <span>View profile</span>
                            </Link>
                            <button type="button" onClick={handleLogout} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '0.65rem', padding: '0.7rem 0.8rem', borderRadius: 12, border: 'none', background: 'transparent', color: '#fca5a5', fontSize: '0.84rem', cursor: 'pointer' }}>
                                <i className="bi bi-box-arrow-right" />
                                <span>Log out</span>
                            </button>
                        </div>
                    )}
                </div>
            </div>

            <style>{`
                @media (max-width: 767px) {
                    .tb-hamburger { display: flex !important; }
                    .tb-root { padding: 0.4rem 0.85rem !important; }
                    .tb-info { display: none !important; }
                    .tb-bc-home, .tb-bc-past, .tb-bc-sep { display: none !important; }
                    .tb-bc-current { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; display: block; }
                }
                @media (max-width: 420px) {
                    .tb-root { gap: .5rem !important; }
                    .tb-root > div:last-child { gap: .35rem !important; }
                    .tb-root > div:last-child > button:first-child { display: none; }
                    .tb-bc-current { max-width: 46vw; }
                }
            `}</style>
        </header>
    );
}
