import { Link } from '@inertiajs/react';

export const panelStyle = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 18,
    boxShadow: 'var(--admin-shadow)',
};

export function SupervisorStyles() {
    return (
        <style>{`
            .sup-page { display: grid; gap: 1rem; }
            .sup-hero { position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 1.5rem; min-height: 155px; padding: 1.45rem 1.6rem; border: 1px solid rgba(14,165,233,.22); border-radius: 21px; background: radial-gradient(circle at 84% 10%, rgba(34,197,94,.17), transparent 32%), linear-gradient(125deg, rgba(8,47,73,.9), rgba(15,23,42,.96) 58%, rgba(20,83,45,.76)); box-shadow: var(--admin-shadow); }
            .sup-hero::after { content: ''; position: absolute; right: 12%; bottom: -90px; width: 210px; height: 210px; border: 1px solid rgba(255,255,255,.07); border-radius: 50%; box-shadow: 0 0 0 35px rgba(255,255,255,.02), 0 0 0 70px rgba(255,255,255,.012); }
            .sup-kicker { color: #7dd3fc; font-size: .66rem; font-weight: 800; letter-spacing: .16em; }
            .sup-hero h1 { margin: .4rem 0; color: #f8fafc; font-family: Georgia, 'Times New Roman', serif; font-size: clamp(1.7rem, 4vw, 2.45rem); font-weight: 500; letter-spacing: -.035em; }
            .sup-hero p { max-width: 650px; margin: 0; color: #cbd5e1; font-size: .82rem; line-height: 1.65; }
            .sup-hero-action { z-index: 1; display: flex; align-items: center; gap: .55rem; padding: .72rem .9rem; color: #e0f2fe; border: 1px solid rgba(125,211,252,.25); border-radius: 12px; background: rgba(2,6,23,.4); text-decoration: none; font-size: .75rem; font-weight: 750; white-space: nowrap; }
            .sup-stats { display: grid; grid-template-columns: repeat(4, minmax(145px, 1fr)); gap: .8rem; }
            .sup-stat { display: flex; align-items: center; gap: .8rem; padding: .95rem 1rem; }
            .sup-stat-icon { display: grid; width: 40px; height: 40px; flex: 0 0 auto; place-items: center; color: var(--stat-color); border: 1px solid color-mix(in srgb, var(--stat-color) 30%, transparent); border-radius: 12px; background: color-mix(in srgb, var(--stat-color) 10%, transparent); }
            .sup-stat div:last-child { display: grid; gap: .12rem; }.sup-stat span { color: var(--admin-text-muted); font-size: .68rem; }.sup-stat strong { color: var(--admin-text-primary); font-size: 1.35rem; }
            .sup-panel { padding: 1rem 1.1rem; }.sup-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .85rem; }
            .sup-panel-head h2 { margin: 0; color: var(--admin-text-primary); font-size: .92rem; }.sup-panel-head p { margin: .2rem 0 0; color: var(--admin-text-muted); font-size: .7rem; }
            .sup-grid-2 { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr); gap: 1rem; }
            .sup-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }
            .sup-list { display: grid; gap: .65rem; }.sup-list-item { padding: .8rem .9rem; border: 1px solid var(--admin-border); border-radius: 13px; background: rgba(56,189,248,.025); }
            .sup-row { display: flex; align-items: center; justify-content: space-between; gap: .8rem; }.sup-title { color: var(--admin-text-primary); font-size: .78rem; font-weight: 750; }.sup-muted { color: var(--admin-text-muted); font-size: .69rem; line-height: 1.5; }.sup-copy { color: var(--admin-text-secondary); font-size: .76rem; line-height: 1.6; }
            .sup-pill { display: inline-flex; align-items: center; width: fit-content; padding: .28rem .52rem; color: #bae6fd; border: 1px solid rgba(56,189,248,.2); border-radius: 999px; background: rgba(56,189,248,.08); color-scheme: dark; font-size: .62rem; font-weight: 750; text-transform: capitalize; white-space: nowrap; }.sup-pill option { color: #e2e8f0; background: #0f172a; text-transform: capitalize; }
            .sup-pill[data-tone='success'] { color: #86efac; border-color: rgba(34,197,94,.22); background: rgba(34,197,94,.09); }.sup-pill[data-tone='warning'] { color: #fde047; border-color: rgba(250,204,21,.22); background: rgba(250,204,21,.08); }.sup-pill[data-tone='danger'] { color: #fca5a5; border-color: rgba(239,68,68,.22); background: rgba(239,68,68,.08); }
            .sup-progress { height: 8px; overflow: hidden; border-radius: 999px; background: rgba(148,163,184,.12); }.sup-progress > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0284c7, #22c55e); }
            .sup-analytics-grid { align-items: stretch; }.sup-analytics-grid > .sup-panel, .sup-analytics-watchlist + * { min-width: 0; }.sup-analytics-overview { display: grid; grid-template-columns: 150px 1fr; align-items: center; gap: 1rem; min-height: 164px; }.sup-signal-ring { position: relative; width: 150px; height: 150px; filter: drop-shadow(0 0 16px rgba(34,211,238,.12)); }.sup-signal-ring svg { width: 100%; height: 100%; }.sup-signal-ring circle:last-child { filter: drop-shadow(0 0 5px rgba(103,232,249,.5)); }.sup-signal-ring-label { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; align-content: center; }.sup-signal-ring-label strong { color: #cffafe; font-size: 1.35rem; letter-spacing: -.04em; }.sup-signal-ring-label span { margin-top: .1rem; color: var(--admin-text-muted); font-size: .6rem; text-transform: uppercase; letter-spacing: .08em; }.sup-analytics-metrics { display: grid; gap: .55rem; }.sup-analytics-metrics > div { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .65rem .7rem; border: 1px solid rgba(56,189,248,.14); border-radius: 11px; background: linear-gradient(90deg, rgba(56,189,248,.06), rgba(15,23,42,.18)); }.sup-analytics-metrics span, .sup-analytics-watch-grid span { color: var(--admin-text-muted); font-size: .63rem; text-transform: uppercase; letter-spacing: .06em; }.sup-analytics-metrics strong { color: #bae6fd; font-size: .9rem; }.sup-analytics-metrics strong.is-warning { color: #facc15; }.sup-recommendation-chart { display: grid; gap: .85rem; padding-top: .2rem; }.sup-recommendation-row { display: grid; grid-template-columns: minmax(0, 1fr) 90px; align-items: center; gap: .65rem; }.sup-recommendation-copy { display: grid; gap: .25rem; min-width: 0; }.sup-recommendation-copy strong { overflow: hidden; color: var(--admin-text-primary); font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }.sup-recommendation-copy span { overflow: hidden; color: var(--admin-text-muted); font-size: .63rem; text-overflow: ellipsis; white-space: nowrap; }.sup-recommendation-track { grid-column: 1 / -1; height: 7px; overflow: hidden; border-radius: 999px; background: rgba(148,163,184,.12); }.sup-recommendation-track i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #38bdf8, #8b5cf6, #ec4899); box-shadow: 0 0 12px rgba(56,189,248,.25); }.sup-recommendation-score { color: #cffafe; font-size: .72rem; text-align: right; }.sup-analytics-watchlist { display: grid; gap: .7rem; }.sup-analytics-watch-item { padding: .85rem .9rem; border: 1px solid rgba(56,189,248,.14); border-radius: 14px; background: linear-gradient(120deg, rgba(8,47,73,.17), rgba(15,23,42,.12)); }.sup-analytics-watch-grid { display: grid; grid-template-columns: 1.1fr 1.35fr .45fr; gap: .7rem; margin-top: .75rem; }.sup-analytics-watch-grid > div { display: grid; gap: .24rem; min-width: 0; }.sup-analytics-watch-grid strong { overflow: hidden; color: var(--admin-text-secondary); font-size: .7rem; line-height: 1.4; text-overflow: ellipsis; }.sup-analytics-watch-score { color: #67e8f9 !important; font-size: .9rem !important; }
            .sup-table-wrap { overflow-x: auto; }.sup-table { width: 100%; border-collapse: collapse; min-width: 720px; }.sup-table th { padding: .6rem .65rem; color: var(--admin-text-muted); font-size: .65rem; font-weight: 750; text-align: left; text-transform: uppercase; letter-spacing: .05em; }.sup-table td { padding: .75rem .65rem; color: var(--admin-text-secondary); border-top: 1px solid var(--admin-border); font-size: .73rem; }.sup-table td strong { color: var(--admin-text-primary); }
            .sup-link { color: #7dd3fc; text-decoration: none; font-size: .7rem; font-weight: 750; }.sup-link:hover { color: #bae6fd; }
            .sup-search { display: flex; align-items: center; gap: .5rem; min-width: 250px; padding: .58rem .7rem; border: 1px solid var(--admin-border-strong); border-radius: 11px; background: var(--admin-bg-secondary); }.sup-search input { width: 100%; border: 0; outline: 0; background: transparent; color: var(--admin-text-primary); font-size: .72rem; }.sup-search i { color: var(--admin-text-muted); }
            .sup-empty { display: grid; min-height: 180px; place-items: center; align-content: center; gap: .35rem; color: var(--admin-text-muted); text-align: center; }.sup-empty i { color: #38bdf8; font-size: 1.7rem; opacity: .7; }.sup-empty strong { color: var(--admin-text-primary); font-size: .8rem; }.sup-empty span { font-size: .68rem; }
            .sup-avatar { display: grid; width: 38px; height: 38px; flex: 0 0 auto; place-items: center; color: #bae6fd; border: 1px solid rgba(56,189,248,.23); border-radius: 12px; background: linear-gradient(145deg, rgba(14,116,144,.44), rgba(15,23,42,.78)); font-size: .68rem; font-weight: 800; }
            .sup-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }.sup-field { display: grid; gap: .35rem; }.sup-field.full { grid-column: 1 / -1; }.sup-field label { color: var(--admin-text-muted); font-size: .68rem; font-weight: 700; }.sup-field input, .sup-field textarea, .sup-field select { width: 100%; padding: .68rem .75rem; color: var(--admin-text-primary); border: 1px solid var(--admin-border-strong); border-radius: 11px; outline: none; background: var(--admin-bg-secondary); font: inherit; font-size: .75rem; }.sup-field input:disabled { opacity: .7; cursor: not-allowed; }.sup-button { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; padding: .68rem .9rem; color: #fff; border: 0; border-radius: 11px; background: #0284c7; font-size: .73rem; font-weight: 800; cursor: pointer; text-decoration: none; }.sup-button.secondary { color: var(--admin-text-primary); border: 1px solid var(--admin-border-strong); background: var(--admin-bg-secondary); }.sup-button:disabled { opacity: .6; cursor: wait; }
            .sup-success { display: flex; align-items: center; gap: .5rem; padding: .72rem .85rem; color: #86efac; border: 1px solid rgba(34,197,94,.24); border-radius: 12px; background: rgba(34,197,94,.09); font-size: .74rem; }
            @media (max-width: 1050px) { .sup-stats { grid-template-columns: repeat(2, 1fr); }.sup-grid-3 { grid-template-columns: 1fr 1fr; }.sup-grid-2 { grid-template-columns: 1fr; }.sup-analytics-watch-grid { grid-template-columns: 1fr 1fr; }.sup-analytics-watch-grid > div:last-child { grid-column: 1 / -1; } }
            @media (max-width: 700px) { .sup-hero { align-items: flex-start; flex-direction: column; padding: 1.15rem 1.1rem; }.sup-hero-action { width: 100%; justify-content: center; }.sup-panel-head { align-items: flex-start; flex-direction: column; }.sup-search { width: 100%; min-width: 0; }.sup-grid-3, .sup-form-grid { grid-template-columns: 1fr; }.sup-field.full { grid-column: auto; }.sup-panel { padding: .85rem; }.sup-button { width: 100%; } }
            @media (max-width: 470px) { .sup-stats { grid-template-columns: 1fr; } }
        `}</style>
    );
}

export function PageHero({ kicker, title, description, href, action, icon = 'bi-arrow-right' }) {
    return (
        <section className="sup-hero">
            <div>
                <div className="sup-kicker">{kicker}</div>
                <h1>{title}</h1>
                <p>{description}</p>
            </div>
            {href && <Link className="sup-hero-action" href={href}><i className={`bi ${icon}`} />{action}</Link>}
        </section>
    );
}

export function StatCard({ label, value, icon, color = '#38bdf8' }) {
    return (
        <div className="sup-stat" style={{ ...panelStyle, '--stat-color': color }}>
            <div className="sup-stat-icon"><i className={`bi ${icon}`} /></div>
            <div><span>{label}</span><strong>{value}</strong></div>
        </div>
    );
}

export function Panel({ title, subtitle, action, children, className = '' }) {
    return (
        <section className={`sup-panel ${className}`} style={panelStyle}>
            {(title || action) && (
                <div className="sup-panel-head">
                    <div><h2>{title}</h2>{subtitle && <p>{subtitle}</p>}</div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}

export function StatusPill({ children, tone = 'info' }) {
    return <span className="sup-pill" data-tone={tone}>{children}</span>;
}

export function EmptyState({ icon = 'bi-inbox', title, text }) {
    return <div className="sup-empty"><i className={`bi ${icon}`} /><strong>{title}</strong><span>{text}</span></div>;
}

export function Initials({ name }) {
    const initials = name.split(' ').map((part) => part[0]).slice(0, 2).join('');
    return <div className="sup-avatar">{initials}</div>;
}
