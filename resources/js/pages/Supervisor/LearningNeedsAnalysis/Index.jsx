import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

const card = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 18,
    boxShadow: 'var(--admin-shadow)',
};

const statusColors = {
    submitted: { color: '#facc15', background: 'rgba(250,204,21,0.12)', border: 'rgba(250,204,21,0.28)' },
    reviewed: { color: '#6ee7b7', background: 'rgba(16,185,129,0.12)', border: 'rgba(16,185,129,0.28)' },
    returned: { color: '#fca5a5', background: 'rgba(239,68,68,0.12)', border: 'rgba(239,68,68,0.28)' },
};

function AnalyticsPanel({ icon, eyebrow, title, accent, children }) {
    return (
        <section className="lna-analytics-card" style={{ '--analytics-accent': accent }}>
            <div className="lna-analytics-icon"><i className={`bi ${icon}`} /></div>
            <div>
                <div className="lna-eyebrow">{eyebrow}</div>
                <div className="lna-analytics-title">{title}</div>
                {children}
            </div>
        </section>
    );
}

function ReviewForm({ entry }) {
    const form = useForm({
        status: 'reviewed',
        review_remarks: entry.review_remarks ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.patch(`/supervisor/lna-reviews/${entry.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="lna-review-form">
            <div>
                <label htmlFor={`decision-${entry.id}`}>Review decision</label>
                <select id={`decision-${entry.id}`} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                    <option value="reviewed">Mark as reviewed</option>
                    <option value="returned">Return to employee</option>
                </select>
            </div>
            <div>
                <label htmlFor={`remarks-${entry.id}`}>Supervisor remarks {form.data.status === 'returned' ? '(required)' : '(optional)'}</label>
                <textarea
                    id={`remarks-${entry.id}`}
                    value={form.data.review_remarks}
                    onChange={(event) => form.setData('review_remarks', event.target.value)}
                    placeholder="Add clear feedback or next steps for the employee."
                />
                {form.errors.review_remarks && <div className="lna-error">{form.errors.review_remarks}</div>}
            </div>
            <button type="submit" disabled={form.processing}>
                <i className={`bi ${form.data.status === 'reviewed' ? 'bi-check2-circle' : 'bi-arrow-counterclockwise'}`} />
                {form.processing ? 'Saving...' : 'Save Review'}
            </button>
        </form>
    );
}

export default function Index({ teamOffice, summary, lnaEntries }) {
    const { props } = usePage();
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('all');
    const [expandedId, setExpandedId] = useState(lnaEntries?.[0]?.id ?? null);
    const success = props?.flash?.success;

    const filteredEntries = (lnaEntries ?? []).filter((entry) => {
        const haystack = `${entry.employee_name} ${entry.employee_id} ${entry.focus_area}`.toLowerCase();
        return haystack.includes(query.toLowerCase()) && (status === 'all' || entry.status === status);
    });

    return (
        <AppLayout title="LNA Reviews" description={`Supervisor / ${teamOffice ?? 'Team'} / Static Analytics`}>
            <div className="lna-review-page">
                <section className="lna-hero">
                    <div>
                        <div className="lna-kicker">TEAM CAPABILITY DESK</div>
                        <h1>Review needs. Shape growth.</h1>
                        <p>Review employee LNA responses and use static, rule-based analytics as an initial guide for development decisions.</p>
                    </div>
                    <div className="lna-static-note">
                        <i className="bi bi-stars" />
                        <div><strong>Static analytics mode</strong><span>Fixed mappings for prototype validation</span></div>
                    </div>
                </section>

                {success && <div className="lna-success"><i className="bi bi-check-circle-fill" />{success}</div>}

                <section className="lna-summary-grid">
                    {[
                        ['Assessments', summary?.total_assessments ?? 0, 'bi-files', '#60a5fa'],
                        ['Employees', summary?.employees_assessed ?? 0, 'bi-people-fill', '#38bdf8'],
                        ['High Priority', summary?.high_priority ?? 0, 'bi-exclamation-diamond-fill', '#fb923c'],
                        ['Pending Review', summary?.pending_review ?? 0, 'bi-hourglass-split', '#facc15'],
                    ].map(([label, value, icon, color]) => (
                        <div key={label} style={{ ...card, '--metric-color': color }} className="lna-metric">
                            <i className={`bi ${icon}`} />
                            <div><span>{label}</span><strong>{value}</strong></div>
                        </div>
                    ))}
                    <div style={card} className="lna-completion">
                        <div className="lna-ring" style={{ '--progress': `${summary?.completion_rate ?? 0}%` }}>
                            <span>{summary?.completion_rate ?? 0}%</span>
                        </div>
                        <div><strong>Review completion</strong><span>{summary?.reviewed ?? 0} reviewed, {summary?.returned ?? 0} returned</span></div>
                    </div>
                </section>

                <section style={card} className="lna-workspace">
                    <div className="lna-toolbar">
                        <div>
                            <h2>Employee Assessments</h2>
                            <p>{teamOffice ? `${teamOffice} team submissions` : 'No supervisor office assignment found'}</p>
                        </div>
                        <div className="lna-filters">
                            <div className="lna-search"><i className="bi bi-search" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search employee or focus area" /></div>
                            <select value={status} onChange={(event) => setStatus(event.target.value)}>
                                <option value="all">All statuses</option>
                                <option value="submitted">Pending review</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="returned">Returned</option>
                            </select>
                        </div>
                    </div>

                    <div className="lna-entry-list">
                        {filteredEntries.length === 0 && (
                            <div className="lna-empty"><i className="bi bi-clipboard2-pulse" /><strong>No matching LNA assessments</strong><span>Employee submissions from your office will appear here.</span></div>
                        )}
                        {filteredEntries.map((entry) => {
                            const isExpanded = expandedId === entry.id;
                            const statusStyle = statusColors[entry.status] ?? statusColors.submitted;

                            return (
                                <article key={entry.id} className={`lna-entry${isExpanded ? ' is-expanded' : ''}`}>
                                    <button type="button" className="lna-entry-header" onClick={() => setExpandedId(isExpanded ? null : entry.id)}>
                                        <div className="lna-avatar">{entry.employee_name.split(' ').map((part) => part[0]).slice(0, 2).join('')}</div>
                                        <div className="lna-person">
                                            <strong>{entry.employee_name}</strong>
                                            <span>{entry.position || 'Employee'} · {entry.employee_id || 'No employee ID'}</span>
                                        </div>
                                        <div className="lna-focus"><span>Focus area</span><strong>{entry.focus_area}</strong></div>
                                        <span className="lna-priority" data-priority={entry.priority_level}>{entry.priority_level} priority</span>
                                        <span className="lna-status" style={{ color: statusStyle.color, background: statusStyle.background, borderColor: statusStyle.border }}>{entry.status}</span>
                                        <i className={`bi bi-chevron-${isExpanded ? 'up' : 'down'} lna-chevron`} />
                                    </button>

                                    {isExpanded && (
                                        <div className="lna-entry-body">
                                            <div className="lna-assessment-grid">
                                                <div><span>Employee-identified competency gap</span><p>{entry.competency_gap}</p></div>
                                                <div><span>Proposed intervention</span><p>{entry.proposed_intervention}</p></div>
                                                <div><span>Submission date</span><p>{entry.submitted_on || 'Not recorded'}</p></div>
                                            </div>

                                            <div className="lna-analytics-grid">
                                                <AnalyticsPanel icon="bi-bar-chart-line-fill" eyebrow="DESCRIPTIVE · STATIC" title="What the assessment shows" accent="#38bdf8">
                                                    <dl><div><dt>Category</dt><dd>{entry.descriptive_analytics.competency_category}</dd></div><div><dt>Priority score</dt><dd>{entry.descriptive_analytics.priority_score}/100</dd></div><div><dt>Finding</dt><dd>{entry.descriptive_analytics.assessment_finding}</dd></div></dl>
                                                </AnalyticsPanel>
                                                <AnalyticsPanel icon="bi-compass-fill" eyebrow="PRESCRIPTIVE · STATIC" title="What action to take" accent="#fb923c">
                                                    <dl><div><dt>Skills gap</dt><dd>{entry.prescriptive_analytics.skills_gap}</dd></div><div><dt>Recommended action</dt><dd>{entry.prescriptive_analytics.recommended_action}</dd></div><div><dt>Target</dt><dd>{entry.prescriptive_analytics.target_timeframe}</dd></div></dl>
                                                </AnalyticsPanel>
                                                <AnalyticsPanel icon="bi-graph-up-arrow" eyebrow="PREDICTIVE · STATIC" title="Likely training match" accent="#34d399">
                                                    <dl><div><dt>Recommendation</dt><dd>{entry.predictive_analytics.training_recommendation}</dd></div><div><dt>Rule match</dt><dd>{entry.predictive_analytics.match_score}%</dd></div><div><dt>Expected outcome</dt><dd>{entry.predictive_analytics.expected_outcome}</dd></div></dl>
                                                </AnalyticsPanel>
                                            </div>

                                            {entry.reviewed_at && <div className="lna-review-history"><i className="bi bi-clock-history" />Last reviewed by {entry.reviewed_by || 'Supervisor'} on {entry.reviewed_at}{entry.review_remarks ? `: ${entry.review_remarks}` : ''}</div>}
                                            <ReviewForm entry={entry} />
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                </section>
            </div>

            <style>{`
                .lna-review-page { display: grid; gap: 1rem; --lna-ink: var(--admin-text-primary); }
                .lna-hero { position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 2rem; min-height: 180px; padding: 1.6rem 1.8rem; border: 1px solid rgba(56,189,248,.22); border-radius: 22px; background: radial-gradient(circle at 82% 12%, rgba(56,189,248,.18), transparent 32%), linear-gradient(125deg, rgba(8,47,73,.82), rgba(15,23,42,.96) 55%, rgba(15,56,48,.82)); box-shadow: var(--admin-shadow); }
                .lna-hero::after { content: ''; position: absolute; right: 18%; bottom: -70px; width: 180px; height: 180px; border: 1px solid rgba(255,255,255,.08); border-radius: 50%; box-shadow: 0 0 0 32px rgba(255,255,255,.025), 0 0 0 64px rgba(255,255,255,.015); }
                .lna-kicker, .lna-eyebrow { color: #7dd3fc; font-size: .67rem; font-weight: 800; letter-spacing: .16em; }
                .lna-hero h1 { margin: .45rem 0 .4rem; color: #f8fafc; font-family: Georgia, 'Times New Roman', serif; font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 500; letter-spacing: -.035em; }
                .lna-hero p { max-width: 650px; margin: 0; color: #cbd5e1; font-size: .86rem; line-height: 1.65; }
                .lna-static-note { z-index: 1; display: flex; align-items: center; gap: .75rem; min-width: 245px; padding: .85rem 1rem; border: 1px solid rgba(125,211,252,.22); border-radius: 15px; background: rgba(2,6,23,.42); backdrop-filter: blur(12px); }
                .lna-static-note > i { color: #fbbf24; font-size: 1.3rem; }
                .lna-static-note div { display: grid; gap: .15rem; }.lna-static-note strong { color: #f8fafc; font-size: .8rem; }.lna-static-note span { color: #94a3b8; font-size: .7rem; }
                .lna-success { display: flex; align-items: center; gap: .55rem; padding: .75rem 1rem; color: #86efac; border: 1px solid rgba(16,185,129,.28); border-radius: 13px; background: rgba(16,185,129,.12); font-size: .82rem; }
                .lna-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(130px, 1fr)) minmax(220px, 1.35fr); gap: .8rem; }
                .lna-metric { display: flex; align-items: center; gap: .8rem; padding: 1rem; }.lna-metric > i { display: grid; width: 38px; height: 38px; place-items: center; color: var(--metric-color); border: 1px solid color-mix(in srgb, var(--metric-color) 35%, transparent); border-radius: 12px; background: color-mix(in srgb, var(--metric-color) 10%, transparent); }
                .lna-metric div { display: grid; gap: .15rem; }.lna-metric span { color: var(--admin-text-muted); font-size: .7rem; }.lna-metric strong { color: var(--lna-ink); font-size: 1.35rem; }
                .lna-completion { display: flex; align-items: center; gap: .9rem; padding: .8rem 1rem; }.lna-completion > div:last-child { display: grid; gap: .2rem; }.lna-completion strong { color: var(--lna-ink); font-size: .8rem; }.lna-completion span { color: var(--admin-text-muted); font-size: .7rem; }
                .lna-ring { display: grid; width: 52px; height: 52px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: conic-gradient(#38bdf8 var(--progress), rgba(148,163,184,.15) 0); }.lna-ring::before { content: ''; position: absolute; width: 40px; height: 40px; border-radius: 50%; background: var(--admin-card); }.lna-ring span { z-index: 1; color: var(--lna-ink); font-size: .68rem; font-weight: 800; }
                .lna-workspace { overflow: hidden; }.lna-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.2rem; border-bottom: 1px solid var(--admin-border); }.lna-toolbar h2 { margin: 0; color: var(--lna-ink); font-size: 1rem; }.lna-toolbar p { margin: .2rem 0 0; color: var(--admin-text-muted); font-size: .72rem; }
                .lna-filters { display: flex; gap: .6rem; }.lna-filters select, .lna-search { border: 1px solid var(--admin-border-strong); border-radius: 11px; background: var(--admin-bg-secondary); color: var(--lna-ink); }.lna-filters select { padding: .6rem .75rem; font-size: .75rem; }.lna-search { display: flex; align-items: center; gap: .5rem; padding: 0 .7rem; }.lna-search i { color: var(--admin-text-muted); }.lna-search input { width: 220px; padding: .6rem 0; border: 0; outline: 0; background: transparent; color: var(--lna-ink); font-size: .75rem; }
                .lna-entry-list { display: grid; }.lna-entry { border-bottom: 1px solid var(--admin-border); }.lna-entry:last-child { border-bottom: 0; }.lna-entry.is-expanded { background: linear-gradient(180deg, rgba(56,189,248,.035), transparent 45%); }
                .lna-entry-header { display: grid; width: 100%; grid-template-columns: 42px minmax(180px, 1.2fr) minmax(180px, 1.2fr) auto auto 18px; align-items: center; gap: .8rem; padding: .9rem 1.2rem; border: 0; background: transparent; color: inherit; text-align: left; cursor: pointer; }.lna-entry-header:hover { background: rgba(56,189,248,.035); }
                .lna-avatar { display: grid; width: 40px; height: 40px; place-items: center; color: #bae6fd; border: 1px solid rgba(56,189,248,.25); border-radius: 13px; background: linear-gradient(145deg, rgba(14,116,144,.5), rgba(15,23,42,.8)); font-size: .72rem; font-weight: 800; }
                .lna-person, .lna-focus { display: grid; gap: .18rem; }.lna-person strong, .lna-focus strong { overflow: hidden; color: var(--lna-ink); font-size: .79rem; text-overflow: ellipsis; white-space: nowrap; }.lna-person span, .lna-focus span { color: var(--admin-text-muted); font-size: .68rem; }
                .lna-priority, .lna-status { padding: .32rem .55rem; border-radius: 999px; font-size: .65rem; font-weight: 750; text-transform: capitalize; white-space: nowrap; }.lna-priority { color: #facc15; background: rgba(250,204,21,.09); }.lna-priority[data-priority='high'] { color: #fb923c; background: rgba(251,146,60,.1); }.lna-priority[data-priority='low'] { color: #94a3b8; background: rgba(148,163,184,.1); }.lna-status { border: 1px solid; }.lna-chevron { color: var(--admin-text-muted); font-size: .75rem; }
                .lna-entry-body { padding: .35rem 1.2rem 1.2rem 4.95rem; }.lna-assessment-grid { display: grid; grid-template-columns: 1.3fr 1.3fr .6fr; gap: .75rem; padding: .85rem; border: 1px solid var(--admin-border); border-radius: 14px; background: rgba(15,23,42,.18); }.lna-assessment-grid span { color: var(--admin-text-muted); font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }.lna-assessment-grid p { margin: .35rem 0 0; color: var(--admin-text-secondary); font-size: .76rem; line-height: 1.55; }
                .lna-analytics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-top: .75rem; }.lna-analytics-card { display: grid; grid-template-columns: 34px 1fr; gap: .7rem; padding: .9rem; border: 1px solid color-mix(in srgb, var(--analytics-accent) 24%, var(--admin-border)); border-radius: 15px; background: linear-gradient(145deg, color-mix(in srgb, var(--analytics-accent) 7%, transparent), transparent 60%); }.lna-analytics-icon { display: grid; width: 32px; height: 32px; place-items: center; color: var(--analytics-accent); border-radius: 10px; background: color-mix(in srgb, var(--analytics-accent) 12%, transparent); }
                .lna-analytics-title { margin-top: .18rem; color: var(--lna-ink); font-size: .78rem; font-weight: 750; }.lna-analytics-card dl { display: grid; gap: .5rem; margin: .7rem 0 0; }.lna-analytics-card dl div { display: grid; gap: .12rem; }.lna-analytics-card dt { color: var(--admin-text-muted); font-size: .62rem; text-transform: uppercase; letter-spacing: .05em; }.lna-analytics-card dd { margin: 0; color: var(--admin-text-secondary); font-size: .72rem; line-height: 1.4; }
                .lna-review-history { margin-top: .75rem; padding: .65rem .8rem; color: var(--admin-text-muted); border-left: 2px solid #38bdf8; background: rgba(56,189,248,.05); font-size: .7rem; line-height: 1.5; }.lna-review-history i { margin-right: .45rem; color: #38bdf8; }
                .lna-review-form { display: grid; grid-template-columns: .7fr 1.7fr auto; align-items: end; gap: .7rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px dashed var(--admin-border-strong); }.lna-review-form > div { display: grid; gap: .3rem; }.lna-review-form label { color: var(--admin-text-muted); font-size: .67rem; font-weight: 700; }.lna-review-form select, .lna-review-form textarea { width: 100%; padding: .62rem .7rem; border: 1px solid var(--admin-border-strong); border-radius: 10px; outline: none; background: var(--admin-bg-secondary); color: var(--lna-ink); font: inherit; font-size: .73rem; }.lna-review-form textarea { min-height: 62px; resize: vertical; }.lna-review-form button { display: flex; align-items: center; justify-content: center; gap: .4rem; min-height: 40px; padding: .65rem .9rem; border: 0; border-radius: 11px; background: #0284c7; color: #fff; font-size: .73rem; font-weight: 800; cursor: pointer; }.lna-review-form button:disabled { cursor: wait; opacity: .65; }.lna-error { color: #fca5a5; font-size: .66rem; }
                .lna-empty { display: grid; min-height: 220px; place-items: center; align-content: center; gap: .4rem; color: var(--admin-text-muted); text-align: center; }.lna-empty i { margin-bottom: .25rem; color: #38bdf8; font-size: 1.8rem; opacity: .7; }.lna-empty strong { color: var(--lna-ink); font-size: .85rem; }.lna-empty span { font-size: .72rem; }
                @media (max-width: 1180px) { .lna-summary-grid { grid-template-columns: repeat(4, 1fr); }.lna-completion { grid-column: span 4; }.lna-entry-header { grid-template-columns: 42px 1fr 1fr auto 18px; }.lna-status { display: none; }.lna-analytics-grid { grid-template-columns: 1fr; } }
                @media (max-width: 780px) { .lna-hero { align-items: flex-start; flex-direction: column; }.lna-static-note { width: 100%; }.lna-summary-grid { grid-template-columns: repeat(2, 1fr); }.lna-completion { grid-column: span 2; }.lna-toolbar, .lna-filters { align-items: stretch; flex-direction: column; }.lna-search input { width: 100%; }.lna-entry-header { grid-template-columns: 42px 1fr auto 18px; }.lna-focus, .lna-status { display: none; }.lna-entry-body { padding-left: 1.2rem; }.lna-assessment-grid, .lna-review-form { grid-template-columns: 1fr; } }
                @media (max-width: 500px) { .lna-summary-grid { grid-template-columns: 1fr; }.lna-completion { grid-column: auto; }.lna-entry-header { grid-template-columns: 38px 1fr 16px; padding-inline: .85rem; }.lna-priority { display: none; }.lna-entry-body { padding-inline: .85rem; }.lna-hero { padding: 1.25rem; } }
            `}</style>
        </AppLayout>
    );
}
