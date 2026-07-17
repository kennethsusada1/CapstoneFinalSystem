import axios from 'axios';
import { Head, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import Sidebar from '@/components/Sidebar';
import Topbar from '@/components/Topbar';
import { ToastProvider } from '@/components/Snackbar';
import { ConfirmProvider } from '@/components/ConfirmDialog';

export default function AppLayout({ children, title, description }) {
    const page = usePage();
    const [darkMode, setDarkMode] = useState(() => (localStorage.getItem('theme') ?? 'dark') === 'dark');
    const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sb-collapsed') === '1');
    const [mobileOpen, setMobileOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [showLoader, setShowLoader] = useState(() => !!page?.props?.flash?.just_logged_in);

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light');
        localStorage.setItem('theme', darkMode ? 'dark' : 'light');
    }, [darkMode]);

    useEffect(() => {
        const timer = setTimeout(() => window.dispatchEvent(new Event('resize')), 220);
        return () => clearTimeout(timer);
    }, [collapsed]);

    useEffect(() => {
        const handler = () => {
            if (window.innerWidth >= 768) setMobileOpen(false);
        };
        window.addEventListener('resize', handler);
        return () => window.removeEventListener('resize', handler);
    }, []);

    useEffect(() => {
        if (!showLoader) return undefined;
        const timer = setTimeout(() => setShowLoader(false), 1400);
        return () => clearTimeout(timer);
    }, [showLoader]);

    const fetchNotifications = useCallback(async () => {
        try {
            const { data } = await axios.get('/api/notifications');
            setNotifications(Array.isArray(data) ? data : []);
        } catch {
            setNotifications([]);
        }
    }, []);

    useEffect(() => {
        fetchNotifications();
    }, [fetchNotifications]);

    const sidebarWidth = collapsed ? 68 : 280;

    return (
        <ToastProvider>
            <ConfirmProvider>
                <Head title={title} />
                <div style={{ display: 'flex', minHeight: '100vh' }}>
                    {mobileOpen && <div onClick={() => setMobileOpen(false)} style={{ position: 'fixed', inset: 0, zIndex: 999, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(2px)' }} />}

                    {showLoader && (
                        <div style={{ position: 'fixed', inset: 0, zIndex: 13000, background: 'rgba(10,15,26,0.94)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: '1rem' }}>
                            <img src="/images/ld-logo.png" alt="Smart L&D" style={{ width: 64, height: 64, borderRadius: 20 }} />
                            <div style={{ fontWeight: 700, fontSize: '1.1rem' }}>Loading Smart L&amp;D</div>
                            <div style={{ width: 220, height: 8, borderRadius: 999, background: 'rgba(255,255,255,0.08)', overflow: 'hidden' }}>
                                <div style={{ width: '60%', height: '100%', background: 'linear-gradient(90deg, #2563eb, #60a5fa)', animation: 'ld-progress 1.1s ease-in-out infinite alternate' }} />
                            </div>
                        </div>
                    )}

                    <Sidebar
                        collapsed={collapsed}
                        onToggle={() =>
                            setCollapsed((value) => {
                                const next = !value;
                                localStorage.setItem('sb-collapsed', next ? '1' : '0');
                                return next;
                            })
                        }
                        mobileOpen={mobileOpen}
                        onMobileClose={() => setMobileOpen(false)}
                    />

                    <div className="app-main" style={{ marginLeft: sidebarWidth, flex: 1, display: 'flex', flexDirection: 'column', transition: 'margin-left 0.2s ease', minWidth: 0 }}>
                        <Topbar title={title} description={description} darkMode={darkMode} onToggleDarkMode={() => setDarkMode((value) => !value)} onMobileMenuToggle={() => setMobileOpen((value) => !value)} notifications={notifications} setNotifications={setNotifications} />
                        <main className="admin-content">{children}</main>
                    </div>
                </div>

                <style>{`
                    .admin-content { flex: 1; padding: 1rem 1.5rem; overflow: auto; }
                    @keyframes ld-progress {
                        from { transform: translateX(-18%); }
                        to { transform: translateX(38%); }
                    }
                    @media (max-width: 767px) {
                        .app-main { margin-left: 0 !important; }
                        .admin-content { padding: 0.75rem 1rem; }
                    }
                `}</style>
            </ConfirmProvider>
        </ToastProvider>
    );
}
