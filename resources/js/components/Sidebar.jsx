import { Link, usePage } from '@inertiajs/react';

const roleHeaders = {
    'system-admin': { icon: 'bi-shield-lock-fill', label: 'Admin Portal' },
    secretariat: { icon: 'bi-people-fill', label: 'Secretariat Portal' },
    hrdc: { icon: 'bi-building-fill', label: 'HRDC Portal' },
    supervisor: { icon: 'bi-person-workspace', label: 'Supervisor Portal' },
    employee: { icon: 'bi-person-fill', label: 'Employee Portal' },
};

const linkSets = {
    'system-admin': [
        { href: '/admin', label: 'Dashboard', icon: 'bi-grid-1x2-fill' },
        { href: '/admin/users', label: 'Users', icon: 'bi-people-fill' },
        { href: '/admin/employees', label: 'Employee Records', icon: 'bi-building-fill' },
        { href: '/admin/settings', label: 'L&D Reports', icon: 'bi-bar-chart-fill' },
        { href: '/admin/profile', label: 'Profile', icon: 'bi-person-badge-fill' },
    ],
    secretariat: [
        { href: '/secretariat', label: 'Dashboard', icon: 'bi-grid-1x2-fill' },
        { href: '/secretariat/applications', label: 'Applications', icon: 'bi-inbox-fill' },
        { href: '/secretariat/ld-plans', label: 'L&D Plans', icon: 'bi-journal-richtext' },
        { href: '/secretariat/training-monitor', label: 'Training Monitor', icon: 'bi-activity' },
        { href: '/secretariat/lap-submissions', label: 'LAP Submissions', icon: 'bi-journal-check' },
        { href: '/secretariat/reports', label: 'Terminal Reports', icon: 'bi-file-earmark-bar-graph-fill' },
        { href: '/secretariat/profile', label: 'Profile', icon: 'bi-person-badge-fill' },
    ],
    hrdc: [
        { href: '/hrdc', label: 'Dashboard', icon: 'bi-grid-1x2-fill' },
        { href: '/hrdc/ld-plans', label: 'L&D Plan Inbox', icon: 'bi-inbox-fill' },
        { href: '/hrdc/program-approvals', label: 'Program Approvals', icon: 'bi-patch-check-fill' },
        { href: '/hrdc/reports', label: 'Decision Reports', icon: 'bi-bar-chart-fill' },
        { href: '/hrdc/profile', label: 'Profile', icon: 'bi-person-badge-fill' },
    ],
    supervisor: [
        { href: '/supervisor', label: 'Dashboard', icon: 'bi-grid-1x2-fill' },
        { href: '/supervisor/team', label: 'My Team', icon: 'bi-people-fill' },
        { href: '/supervisor/lna-reviews', label: 'LNA Reviews', icon: 'bi-clipboard2-check-fill' },
        { href: '/supervisor/nominations', label: 'Nominations', icon: 'bi-person-plus-fill' },
        { href: '/supervisor/trainings', label: 'Trainings', icon: 'bi-mortarboard-fill' },
        { href: '/supervisor/idp', label: 'Team IDP', icon: 'bi-journal-check' },
        { href: '/supervisor/profile', label: 'Profile', icon: 'bi-person-badge-fill' },
    ],
    employee: [
        { href: '/employee', label: 'Dashboard', icon: 'bi-grid-1x2-fill' },
        { href: '/employee/learning-needs-analysis', label: 'LNA Assessment', icon: 'bi-ui-checks-grid' },
        { href: '/employee/recommendations', label: 'Recommendations', icon: 'bi-mortarboard-fill' },
        { href: '/employee/training-applications', label: 'Training Applications', icon: 'bi-journal-arrow-up' },
        { href: '/employee/learning-action-plan', label: 'Learning Action Plan', icon: 'bi-journal-check' },
        { href: '/employee/history', label: 'History', icon: 'bi-clock-history' },
        { href: '/employee/profile', label: 'Profile', icon: 'bi-person-badge-fill' },
    ],
};

const supportLinks = [
    { href: '/logout', label: 'Portal Home', icon: 'bi-door-open-fill' },
];

export default function Sidebar({ collapsed, onToggle, mobileOpen, onMobileClose }) {
    const { url, props } = usePage();
    const role = props.auth?.user?.roles?.[0] ?? 'employee';
    const header = roleHeaders[role] ?? roleHeaders.employee;
    const links = linkSets[role] ?? linkSets.employee;
    const showFull = !collapsed || (typeof window !== 'undefined' && window.innerWidth < 768);
    const currentPath = url.split('?')[0].replace(/\/+$/, '') || '/';
    const activeHref = links
        .filter(({ href }) => currentPath === href || currentPath.startsWith(`${href}/`))
        .sort((a, b) => b.href.length - a.href.length)[0]?.href;
    const isActive = (href) => href === activeHref;

    return (
        <aside className={`admin-sidebar${collapsed ? ' sb-collapsed' : ''}${mobileOpen ? ' sb-mobile-open' : ''}`}>
            <div className="sb-brand">
                <img src="/images/ld-logo.png" alt="Smart L&D" style={{ width: 34, height: 34, borderRadius: 10, objectFit: 'cover' }} />
                {showFull && (
                    <div className="sb-brand-text">
                        <div className="sb-app-name">Smart L&amp;D</div>
                        <div className="sb-sub">{header.label}</div>
                    </div>
                )}
                <button type="button" className="sb-toggle sb-desktop-only" onClick={onToggle}>
                    <i className={`bi ${collapsed ? 'bi-chevron-right' : 'bi-chevron-left'}`} />
                </button>
                <button type="button" className="sb-toggle sb-mobile-only" onClick={onMobileClose}>
                    <i className="bi bi-x-lg" />
                </button>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', padding: showFull ? '0 0.4rem 0.25rem' : '0 0 0.25rem', color: 'var(--admin-text-muted)' }}>
                <i className={`bi ${header.icon}`} />
                {showFull && <span style={{ fontSize: '0.7rem', fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase' }}>{header.label}</span>}
            </div>

            <nav className="sb-nav">
                {links.map(({ href, label, icon }) => {
                    return (
                        <Link key={href} href={href} className={`sb-link${isActive(href) ? ' sb-link-active' : ''}`} title={label}>
                            <i className={`bi ${icon} sb-link-icon`} />
                            {showFull && <span>{label}</span>}
                        </Link>
                    );
                })}
            </nav>

            <div className="sb-support">
                {showFull && <div className="sb-support-label">Support</div>}
                {supportLinks.map(({ href, label, icon }) => (
                    <Link key={href} href={href} className="sb-link sb-link-support" title={label}>
                        <i className={`bi ${icon} sb-link-icon`} />
                        {showFull && <span>{label}</span>}
                    </Link>
                ))}
            </div>

            <style>{`
                .admin-sidebar {
                    position: fixed; top: 0; left: 0; bottom: 0; width: 280px;
                    padding: 1.5rem 1rem; background: linear-gradient(180deg, var(--admin-sidebar), var(--admin-bg-primary));
                    border-right: 1px solid var(--admin-border); backdrop-filter: blur(16px);
                    display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; z-index: 1000;
                    transition: width 0.2s ease, padding 0.2s ease, transform 0.22s ease;
                }
                .admin-sidebar.sb-collapsed { width: 68px; padding: 1.5rem 0.5rem; }
                .sb-brand { display: flex; align-items: center; gap: 0.75rem; padding: 0.25rem 0.25rem 1rem; border-bottom: 1px solid var(--admin-border); }
                .sb-app-name { font-weight: 700; font-size: 1rem; color: var(--admin-text-primary); }
                .sb-sub { font-size: 0.72rem; color: var(--admin-text-muted); }
                .sb-toggle { margin-left: auto; background: rgba(59,130,246,0.08); border: 1px solid var(--admin-border); border-radius: 8px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--admin-text-muted); }
                .sb-toggle:hover { background: rgba(59,130,246,0.18); color: var(--admin-accent); }
                .sb-nav { display: flex; flex-direction: column; gap: 0.15rem; flex: 1; }
                .sb-support { display: flex; flex-direction: column; gap: 0.35rem; padding-top: 0.35rem; border-top: 1px solid var(--admin-border); }
                .sb-support-label { padding: 0 0.95rem; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--admin-text-muted); }
                .sb-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 12px; color: var(--admin-text-secondary); text-decoration: none; font-size: 0.875rem; font-weight: 500; border: 1px solid transparent; transition: background 0.15s, color 0.15s; }
                .sb-link:hover { background: rgba(59,130,246,0.08); color: var(--admin-text-primary); }
                .sb-link-active { background: rgba(59,130,246,0.12); border-color: rgba(59,130,246,0.3); color: var(--admin-text-primary); }
                .sb-link-support { background: color-mix(in srgb, var(--admin-card) 55%, transparent); }
                .sb-link-icon { font-size: 1rem; flex-shrink: 0; }
                .sb-mobile-only { display: none; }
                @media (max-width: 767px) {
                    .admin-sidebar { width: 280px !important; transform: translateX(-100%); }
                    .admin-sidebar.sb-mobile-open { transform: translateX(0); }
                    .sb-desktop-only { display: none; }
                    .sb-mobile-only { display: flex; }
                }
            `}</style>
        </aside>
    );
}
