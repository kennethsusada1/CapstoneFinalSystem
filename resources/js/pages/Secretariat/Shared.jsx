import { Link } from '@inertiajs/react';

export const panelStyle = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 18,
    boxShadow: 'var(--admin-shadow)',
};

export function SecretariatStyles() {
    return (
        <style>{`
            .sec-page { display: grid; gap: 1rem; }
            .sec-hero { position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 1.5rem; min-height: 160px; padding: 1.5rem 1.65rem; border: 1px solid rgba(245,158,11,.24); border-radius: 22px; background: radial-gradient(circle at 84% 12%, rgba(245,158,11,.2), transparent 32%), linear-gradient(125deg, rgba(19,78,74,.94), rgba(15,23,42,.97) 58%, rgba(120,53,15,.72)); box-shadow: var(--admin-shadow); }
            .sec-hero::after { content: ''; position: absolute; right: 13%; bottom: -95px; width: 215px; height: 215px; border: 1px solid rgba(255,255,255,.07); border-radius: 50%; box-shadow: 0 0 0 36px rgba(255,255,255,.022), 0 0 0 72px rgba(255,255,255,.012); }
            .sec-kicker { color: #fcd34d; font-size: .66rem; font-weight: 800; letter-spacing: .16em; }
            .sec-hero h1 { margin: .42rem 0; color: #fff7ed; font-family: Georgia, 'Times New Roman', serif; font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 500; letter-spacing: -.035em; }
            .sec-hero p { max-width: 680px; margin: 0; color: #d6d3d1; font-size: .82rem; line-height: 1.65; }
            .sec-hero-action { z-index: 1; display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .72rem .92rem; color: #fef3c7; border: 1px solid rgba(252,211,77,.25); border-radius: 12px; background: rgba(2,6,23,.42); text-decoration: none; font-size: .74rem; font-weight: 750; white-space: nowrap; }
            .sec-stats { display: grid; grid-template-columns: repeat(4, minmax(145px, 1fr)); gap: .8rem; }.sec-stat { display: flex; align-items: center; gap: .8rem; padding: .95rem 1rem; }.sec-stat-icon { display: grid; width: 40px; height: 40px; flex: 0 0 auto; place-items: center; color: var(--stat-color); border: 1px solid color-mix(in srgb, var(--stat-color) 30%, transparent); border-radius: 12px; background: color-mix(in srgb, var(--stat-color) 10%, transparent); }.sec-stat div:last-child { display: grid; gap: .12rem; }.sec-stat span { color: var(--admin-text-muted); font-size: .68rem; }.sec-stat strong { color: var(--admin-text-primary); font-size: 1.35rem; }
            .sec-panel { padding: 1rem 1.1rem; }.sec-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .85rem; }.sec-panel-head h2 { margin: 0; color: var(--admin-text-primary); font-size: .92rem; }.sec-panel-head p { margin: .2rem 0 0; color: var(--admin-text-muted); font-size: .7rem; }
            .sec-grid-2 { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr); gap: 1rem; }.sec-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }
            .sec-list { display: grid; gap: .65rem; }.sec-item { padding: .82rem .9rem; border: 1px solid var(--admin-border); border-radius: 13px; background: rgba(245,158,11,.025); }.sec-row { display: flex; align-items: center; justify-content: space-between; gap: .8rem; }.sec-title { color: var(--admin-text-primary); font-size: .78rem; font-weight: 750; }.sec-muted { color: var(--admin-text-muted); font-size: .69rem; line-height: 1.5; }.sec-copy { color: var(--admin-text-secondary); font-size: .75rem; line-height: 1.6; }
            .sec-pill { display: inline-flex; align-items: center; width: fit-content; padding: .28rem .53rem; color: #fde68a; border: 1px solid rgba(245,158,11,.22); border-radius: 999px; background: rgba(245,158,11,.08); color-scheme: dark; font-size: .62rem; font-weight: 750; text-transform: capitalize; white-space: nowrap; }.sec-pill option { color: #e2e8f0; background: #0f172a; text-transform: capitalize; }.sec-pill[data-tone='success'] { color: #86efac; border-color: rgba(34,197,94,.22); background: rgba(34,197,94,.09); }.sec-pill[data-tone='info'] { color: #7dd3fc; border-color: rgba(56,189,248,.22); background: rgba(56,189,248,.08); }.sec-pill[data-tone='danger'] { color: #fca5a5; border-color: rgba(239,68,68,.22); background: rgba(239,68,68,.08); }
            .sec-progress { height: 8px; overflow: hidden; border-radius: 999px; background: rgba(148,163,184,.12); }.sec-progress > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #d97706, #14b8a6); }
            .sec-table-wrap { overflow-x: auto; }.sec-table { width: 100%; min-width: 760px; border-collapse: collapse; }.sec-table th { padding: .6rem .65rem; color: var(--admin-text-muted); font-size: .65rem; text-align: left; text-transform: uppercase; letter-spacing: .05em; }.sec-table td { padding: .75rem .65rem; color: var(--admin-text-secondary); border-top: 1px solid var(--admin-border); font-size: .73rem; }.sec-table td strong { color: var(--admin-text-primary); }
            .sec-form { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }.sec-field { display: grid; gap: .34rem; }.sec-field.full { grid-column: 1 / -1; }.sec-field label { color: var(--admin-text-muted); font-size: .67rem; font-weight: 700; }.sec-field input, .sec-field textarea, .sec-field select { width: 100%; padding: .67rem .74rem; color: var(--admin-text-primary); border: 1px solid var(--admin-border-strong); border-radius: 11px; outline: none; background: var(--admin-bg-secondary); font: inherit; font-size: .74rem; }.sec-field input:disabled { opacity: .65; }
            .sec-button { display: inline-flex; align-items: center; justify-content: center; gap: .43rem; padding: .67rem .9rem; color: #fff; border: 0; border-radius: 11px; background: linear-gradient(135deg, #d97706, #0f766e); font-size: .72rem; font-weight: 800; cursor: pointer; text-decoration: none; }.sec-button.secondary { color: var(--admin-text-primary); border: 1px solid var(--admin-border-strong); background: var(--admin-bg-secondary); }.sec-button:disabled { opacity: .6; cursor: wait; }
            .sec-search { display: flex; align-items: center; gap: .5rem; min-width: 245px; padding: .58rem .7rem; border: 1px solid var(--admin-border-strong); border-radius: 11px; background: var(--admin-bg-secondary); }.sec-search input { width: 100%; border: 0; outline: 0; background: transparent; color: var(--admin-text-primary); font-size: .72rem; }
            .sec-success { display: flex; align-items: center; gap: .5rem; padding: .72rem .85rem; color: #86efac; border: 1px solid rgba(34,197,94,.24); border-radius: 12px; background: rgba(34,197,94,.09); font-size: .74rem; }.sec-error { color: #fca5a5; font-size: .64rem; }.sec-empty { display: grid; min-height: 180px; place-items: center; align-content: center; gap: .35rem; color: var(--admin-text-muted); text-align: center; }.sec-empty i { color: #fbbf24; font-size: 1.7rem; opacity: .75; }.sec-empty strong { color: var(--admin-text-primary); font-size: .8rem; }.sec-empty span { font-size: .68rem; }
            .sec-avatar { display: grid; width: 38px; height: 38px; flex: 0 0 auto; place-items: center; color: #fef3c7; border: 1px solid rgba(245,158,11,.24); border-radius: 12px; background: linear-gradient(145deg, rgba(180,83,9,.44), rgba(15,23,42,.8)); font-size: .68rem; font-weight: 800; }
            @media (max-width: 1050px) { .sec-stats { grid-template-columns: repeat(2, 1fr); }.sec-grid-2 { grid-template-columns: 1fr; }.sec-grid-3 { grid-template-columns: 1fr 1fr; } }
            @media (max-width: 700px) { .sec-hero { align-items: flex-start; flex-direction: column; }.sec-hero-action { width: 100%; }.sec-panel-head { align-items: flex-start; flex-direction: column; }.sec-search { width: 100%; min-width: 0; }.sec-grid-3, .sec-form { grid-template-columns: 1fr; }.sec-field.full { grid-column: auto; } }
            @media (max-width: 470px) { .sec-stats { grid-template-columns: 1fr; } }
        `}</style>
    );
}

export function PageHero({ kicker, title, description, href, action, icon = 'bi-arrow-right' }) {
    return <section className="sec-hero"><div><div className="sec-kicker">{kicker}</div><h1>{title}</h1><p>{description}</p></div>{href && <Link className="sec-hero-action" href={href}><i className={`bi ${icon}`} />{action}</Link>}</section>;
}

export function StatCard({ label, value, icon, color = '#f59e0b' }) {
    return <div className="sec-stat" style={{ ...panelStyle, '--stat-color': color }}><div className="sec-stat-icon"><i className={`bi ${icon}`} /></div><div><span>{label}</span><strong>{value}</strong></div></div>;
}

export function Panel({ title, subtitle, action, children }) {
    return <section className="sec-panel" style={panelStyle}>{(title || action) && <div className="sec-panel-head"><div><h2>{title}</h2>{subtitle && <p>{subtitle}</p>}</div>{action}</div>}{children}</section>;
}

export function StatusPill({ children, tone = 'warning' }) {
    return <span className="sec-pill" data-tone={tone}>{children}</span>;
}

export function EmptyState({ icon = 'bi-inbox', title, text }) {
    return <div className="sec-empty"><i className={`bi ${icon}`} /><strong>{title}</strong><span>{text}</span></div>;
}

export function Initials({ name }) {
    return <div className="sec-avatar">{name.split(' ').map((part) => part[0]).slice(0, 2).join('')}</div>;
}
