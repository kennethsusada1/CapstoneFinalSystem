import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';

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

const supervisorAssessmentMethods = [
    'Supervisor Assessment',
    'Questionnaire',
    'Feedback',
    'Observation',
    'Reflection',
    'Customer Feedback',
    'Performance Review',
    'Performance Evaluation (MPOR)',
];

function ReviewForm({ entry, supervisorName }) {
    const skills = Object.keys(entry.skill_assessments ?? {});
    const supervisorRatings = Object.fromEntries(
        skills.map((skill) => [skill, entry.supervisor_skill_assessments?.[skill] ?? 'N/A']),
    );

    const form = useForm({
        status: ['reviewed', 'returned'].includes(entry.status) ? entry.status : 'reviewed',
        review_remarks: entry.review_remarks ?? '',
        supervisor_skill_assessments: supervisorRatings,
        supervisor_assessment_methods: entry.supervisor_assessment_methods ?? ['Supervisor Assessment'],
        supervisor_signature: entry.supervisor_signature ?? supervisorName ?? '',
        supervisor_signed_on: entry.supervisor_signed_on ?? new Date().toLocaleDateString('en-CA'),
    });

    const toggleMethod = (method) => {
        const methods = form.data.supervisor_assessment_methods.includes(method)
            ? form.data.supervisor_assessment_methods.filter((item) => item !== method)
            : [...form.data.supervisor_assessment_methods, method];
        form.setData('supervisor_assessment_methods', methods);
    };

    const submit = (event) => {
        event.preventDefault();
        form.patch(`/supervisor/lna-reviews/${entry.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="lna-review-form">
            <div>
                <label htmlFor={`decision-${entry.id}`}>Supervisor decision</label>
                <select id={`decision-${entry.id}`} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                    <option value="reviewed">Mark as reviewed</option>
                    <option value="returned">Return to employee</option>
                </select>
            </div>
            <div className="lna-review-wide">
                <label>Supervisor's Assessment of Employee's Competencies</label>
                <div className="lna-supervisor-ratings">
                    {skills.map((skill) => (
                        <div className="lna-supervisor-rating" key={skill}>
                            <span>{skill}</span>
                            <select
                                aria-label={`${skill} supervisor assessment`}
                                value={form.data.supervisor_skill_assessments[skill] ?? 'N/A'}
                                onChange={(event) => form.setData('supervisor_skill_assessments', {
                                    ...form.data.supervisor_skill_assessments,
                                    [skill]: event.target.value,
                                })}
                            >
                                <option value="N/A">N/A</option>
                                <option value="1">1 - Not Demonstrated</option>
                                <option value="2">2 - Basic</option>
                                <option value="3">3 - Intermediate</option>
                                <option value="4">4 - Advance</option>
                            </select>
                        </div>
                    ))}
                </div>
                {form.errors.supervisor_skill_assessments && <div className="lna-error">{form.errors.supervisor_skill_assessments}</div>}
            </div>
            <div className="lna-review-wide">
                <label>Supervisor Assessment Methods</label>
                <div className="lna-method-list">
                    {supervisorAssessmentMethods.map((method) => (
                        <label key={method} className="lna-method">
                            <input type="checkbox" checked={form.data.supervisor_assessment_methods.includes(method)} onChange={() => toggleMethod(method)} />
                            <span>{method}</span>
                        </label>
                    ))}
                </div>
                {form.errors.supervisor_assessment_methods && <div className="lna-error">{form.errors.supervisor_assessment_methods}</div>}
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
            <div>
                <label htmlFor={`signature-${entry.id}`}>Name of Supervisor</label>
                <input id={`signature-${entry.id}`} value={form.data.supervisor_signature} onChange={(event) => form.setData('supervisor_signature', event.target.value)} placeholder="Type supervisor name" />
                {form.errors.supervisor_signature && <div className="lna-error">{form.errors.supervisor_signature}</div>}
            </div>
            <div>
                <label htmlFor={`signed-on-${entry.id}`}>Date</label>
                <input id={`signed-on-${entry.id}`} type="date" value={form.data.supervisor_signed_on} onChange={(event) => form.setData('supervisor_signed_on', event.target.value)} />
                {form.errors.supervisor_signed_on && <div className="lna-error">{form.errors.supervisor_signed_on}</div>}
            </div>
            <button type="submit" disabled={form.processing}>
                <i className={`bi ${form.data.status === 'reviewed' ? 'bi-check2-circle' : 'bi-arrow-counterclockwise'}`} />
                {form.processing ? 'Saving...' : 'Save Review'}
            </button>
        </form>
    );
}

function SupervisorAnalytics({ entry }) {
    const recommendations = entry.recommendations ?? [];
    const modelVersion = entry.analytics_model_version || 'Static fallback';

    return (
        <section className="lna-analytics-panel" aria-label="AI analytics summary">
            <div className="lna-analytics-header">
                <div className="lna-analytics-title">
                    <i className="bi bi-stars" />
                    <span>AI-ASSISTED ANALYTICS</span>
                </div>
                <div className="lna-analytics-model"><i className="bi bi-cpu" />{modelVersion}</div>
            </div>

            <div className="lna-analytics-table">
                <div className="lna-analytics-table-head"><span>Ranked competency gap</span><span>Recommended training</span><span>Probability</span></div>
                {recommendations.length === 0 && <div className="lna-analytics-empty">No ranked recommendations were generated.</div>}
                {recommendations.map((recommendation) => {
                    const score = Number(recommendation.probability);
                    const scorePercentage = Number.isFinite(score) ? score * 100 : null;
                    const scoreClass = score >= .75 ? 'high' : score >= .5 ? 'medium' : 'low';

                    return (
                        <div className="lna-analytics-table-row" key={`${entry.id}-${recommendation.rank}`}>
                            <div className="lna-analytics-gap"><strong>{recommendation.rank}.</strong><span>{recommendation.competency_name}</span></div>
                            <span className="lna-analytics-training">{recommendation.training_title}</span>
                            <strong className={`lna-analytics-probability ${scoreClass}`}>{scorePercentage === null ? 'N/A' : `${scorePercentage.toFixed(1)}%`}</strong>
                        </div>
                    );
                })}
            </div>
            <div className="lna-analytics-footer"><i className="bi bi-info-circle" />{entry.training_needed ? 'Training need flagged for supervisor and HRDC planning.' : 'No immediate training need was flagged.'} · Model: {modelVersion}</div>
        </section>
    );
}

export default function Index({ teamOffice, supervisorName, summary, lnaEntries }) {
    const { props } = usePage();
    const [query, setQuery] = useState('');
    const [activeQueue, setActiveQueue] = useState('pending');
    const [expandedId, setExpandedId] = useState(null);
    const success = props?.flash?.success;

    const filteredEntries = (lnaEntries ?? []).filter((entry) => {
        const haystack = `${entry.employee_name} ${entry.employee_id} ${entry.focus_area}`.toLowerCase();
        return haystack.includes(query.trim().toLowerCase());
    });
    const pendingEntries = filteredEntries.filter((entry) => entry.status === 'submitted');
    const reviewedEntries = filteredEntries.filter((entry) => ['reviewed', 'returned'].includes(entry.status));
    const visibleEntries = activeQueue === 'pending' ? pendingEntries : reviewedEntries;

    const changeQueue = (queue) => {
        setActiveQueue(queue);
        setExpandedId(null);
    };

    return (
        <AppLayout title="LNA Reviews" description={`Supervisor / ${teamOffice ?? 'Team'}`}>
            <div className="lna-review-page">
                <section className="lna-hero">
                    <div>
                        <div className="lna-kicker">TEAM CAPABILITY DESK</div>
                        <h1>Review needs. Shape growth.</h1>
                        <p>Review employee LNA responses, validate their competency needs, and provide clear development feedback.</p>
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
                            <h2>{activeQueue === 'pending' ? 'For Review' : 'Reviewed LNA'}</h2>
                            <p>
                                {activeQueue === 'pending'
                                    ? 'Submitted employee assessments waiting for supervisor review'
                                    : 'Completed and returned employee LNA review records'}
                            </p>
                        </div>
                        <div className="lna-filters">
                            <div className="lna-search"><i className="bi bi-search" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search employee or focus area" /></div>
                        </div>
                    </div>

                    <div className="lna-queue-tabs" role="tablist" aria-label="LNA review queues">
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeQueue === 'pending'}
                            className={activeQueue === 'pending' ? 'is-active' : ''}
                            onClick={() => changeQueue('pending')}
                        >
                            <i className="bi bi-inbox-fill" />
                            <span>For Review</span>
                            <strong>{pendingEntries.length}</strong>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeQueue === 'reviewed'}
                            className={activeQueue === 'reviewed' ? 'is-active' : ''}
                            onClick={() => changeQueue('reviewed')}
                        >
                            <i className="bi bi-check2-circle" />
                            <span>Reviewed LNA</span>
                            <strong>{reviewedEntries.length}</strong>
                        </button>
                    </div>

                    <div className="lna-entry-list">
                        {visibleEntries.length === 0 && (
                            <div className="lna-empty">
                                <i className={`bi ${activeQueue === 'pending' ? 'bi-clipboard2-check' : 'bi-archive'}`} />
                                <strong>{activeQueue === 'pending' ? 'No LNA assessments waiting for review' : 'No reviewed LNA records yet'}</strong>
                                <span>
                                    {query.trim()
                                        ? 'Try a different employee name, ID, or focus area.'
                                        : activeQueue === 'pending'
                                            ? 'New employee submissions will appear here.'
                                            : 'Completed supervisor reviews will be stored here.'}
                                </span>
                            </div>
                        )}
                        {visibleEntries.map((entry) => {
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
                                                <div><span>Core functions</span><p>{entry.core_functions?.filter(Boolean).join('; ') || 'Not provided'}</p></div>
                                                <div><span>Support functions</span><p>{entry.support_functions?.filter(Boolean).join('; ') || 'Not provided'}</p></div>
                                                <div><span>Submission date</span><p>{entry.submitted_on || 'Not recorded'}</p></div>
                                                <div><span>Preferred learning methods</span><p>{entry.preferred_learning_methods?.join(', ') || 'Not provided'}{entry.preferred_learning_methods_other ? `: ${entry.preferred_learning_methods_other}` : ''}</p></div>
                                                <div><span>Employee assessment methods</span><p>{entry.assessment_methods?.join(', ') || 'Not provided'}</p></div>
                                                <div><span>Employee signature</span><p>{entry.employee_signature || 'Not provided'}</p></div>
                                            </div>

                                            {Object.keys(entry.skill_assessments ?? {}).length > 0 && (
                                                <div className="lna-employee-ratings">
                                                    <div className="lna-section-heading"><span>Employee Self-Assessment</span><strong>Read-only reference from submitted LNA</strong></div>
                                                    <div className="lna-rating-reference">
                                                        {Object.entries(entry.skill_assessments).map(([skill, rating]) => (
                                                            <div key={skill}><span>{skill}</span><strong>{rating}</strong></div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {entry.reviewed_at && <div className="lna-review-history"><i className="bi bi-clock-history" />Last reviewed by {entry.reviewed_by || 'Supervisor'} on {entry.reviewed_at}{entry.review_remarks ? `: ${entry.review_remarks}` : ''}</div>}
                                            {entry.status === 'reviewed' && <SupervisorAnalytics entry={entry} />}
                                            {activeQueue === 'pending' && <ReviewForm entry={entry} supervisorName={supervisorName} />}
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
                .lna-kicker { color: #7dd3fc; font-size: .67rem; font-weight: 800; letter-spacing: .16em; }
                .lna-hero h1 { margin: .45rem 0 .4rem; color: #f8fafc; font-family: Georgia, 'Times New Roman', serif; font-size: clamp(1.8rem, 4vw, 2.7rem); font-weight: 500; letter-spacing: -.035em; }
                .lna-hero p { max-width: 650px; margin: 0; color: #cbd5e1; font-size: .86rem; line-height: 1.65; }
                .lna-success { display: flex; align-items: center; gap: .55rem; padding: .75rem 1rem; color: #86efac; border: 1px solid rgba(16,185,129,.28); border-radius: 13px; background: rgba(16,185,129,.12); font-size: .82rem; }
                .lna-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(130px, 1fr)) minmax(220px, 1.35fr); gap: .8rem; }
                .lna-metric { display: flex; align-items: center; gap: .8rem; padding: 1rem; }.lna-metric > i { display: grid; width: 38px; height: 38px; place-items: center; color: var(--metric-color); border: 1px solid color-mix(in srgb, var(--metric-color) 35%, transparent); border-radius: 12px; background: color-mix(in srgb, var(--metric-color) 10%, transparent); }
                .lna-metric div { display: grid; gap: .15rem; }.lna-metric span { color: var(--admin-text-muted); font-size: .7rem; }.lna-metric strong { color: var(--lna-ink); font-size: 1.35rem; }
                .lna-completion { display: flex; align-items: center; gap: .9rem; padding: .8rem 1rem; }.lna-completion > div:last-child { display: grid; gap: .2rem; }.lna-completion strong { color: var(--lna-ink); font-size: .8rem; }.lna-completion span { color: var(--admin-text-muted); font-size: .7rem; }
                .lna-ring { display: grid; width: 52px; height: 52px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: conic-gradient(#38bdf8 var(--progress), rgba(148,163,184,.15) 0); }.lna-ring::before { content: ''; position: absolute; width: 40px; height: 40px; border-radius: 50%; background: var(--admin-card); }.lna-ring span { z-index: 1; color: var(--lna-ink); font-size: .68rem; font-weight: 800; }
                .lna-workspace { overflow: hidden; }.lna-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.2rem; }.lna-toolbar h2 { margin: 0; color: var(--lna-ink); font-size: 1rem; }.lna-toolbar p { margin: .2rem 0 0; color: var(--admin-text-muted); font-size: .72rem; }
                .lna-filters { display: flex; gap: .6rem; }.lna-search { display: flex; align-items: center; gap: .5rem; padding: 0 .7rem; border: 1px solid var(--admin-border-strong); border-radius: 11px; background: var(--admin-bg-secondary); color: var(--lna-ink); }.lna-search i { color: var(--admin-text-muted); }.lna-search input { width: 220px; padding: .6rem 0; border: 0; outline: 0; background: transparent; color: var(--lna-ink); font-size: .75rem; }
                .lna-queue-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0 1.2rem; border-bottom: 1px solid var(--admin-border); background: color-mix(in srgb, var(--admin-bg-secondary) 58%, transparent); }.lna-queue-tabs button { position: relative; display: flex; align-items: center; justify-content: center; gap: .5rem; padding: .85rem 1rem; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--admin-text-muted); font-size: .74rem; font-weight: 750; cursor: pointer; }.lna-queue-tabs button:hover { color: var(--lna-ink); background: rgba(56,189,248,.035); }.lna-queue-tabs button.is-active { border-bottom-color: #38bdf8; color: #7dd3fc; background: rgba(56,189,248,.06); }.lna-queue-tabs button strong { display: grid; min-width: 24px; height: 22px; padding: 0 .35rem; place-items: center; border-radius: 999px; background: rgba(148,163,184,.13); color: inherit; font-size: .65rem; }.lna-queue-tabs button.is-active strong { background: rgba(56,189,248,.14); }
                .lna-entry-list { display: grid; }.lna-entry { border-bottom: 1px solid var(--admin-border); }.lna-entry:last-child { border-bottom: 0; }.lna-entry.is-expanded { background: linear-gradient(180deg, rgba(56,189,248,.035), transparent 45%); }
                .lna-entry-header { display: grid; width: 100%; grid-template-columns: 42px minmax(180px, 1.2fr) minmax(180px, 1.2fr) auto auto 18px; align-items: center; gap: .8rem; padding: .9rem 1.2rem; border: 0; background: transparent; color: inherit; text-align: left; cursor: pointer; }.lna-entry-header:hover { background: rgba(56,189,248,.035); }
                .lna-avatar { display: grid; width: 40px; height: 40px; place-items: center; color: #bae6fd; border: 1px solid rgba(56,189,248,.25); border-radius: 13px; background: linear-gradient(145deg, rgba(14,116,144,.5), rgba(15,23,42,.8)); font-size: .72rem; font-weight: 800; }
                .lna-person, .lna-focus { display: grid; gap: .18rem; }.lna-person strong, .lna-focus strong { overflow: hidden; color: var(--lna-ink); font-size: .79rem; text-overflow: ellipsis; white-space: nowrap; }.lna-person span, .lna-focus span { color: var(--admin-text-muted); font-size: .68rem; }
                .lna-priority, .lna-status { padding: .32rem .55rem; border-radius: 999px; font-size: .65rem; font-weight: 750; text-transform: capitalize; white-space: nowrap; }.lna-priority { color: #facc15; background: rgba(250,204,21,.09); }.lna-priority[data-priority='high'] { color: #fb923c; background: rgba(251,146,60,.1); }.lna-priority[data-priority='low'] { color: #94a3b8; background: rgba(148,163,184,.1); }.lna-status { border: 1px solid; }.lna-chevron { color: var(--admin-text-muted); font-size: .75rem; }
                .lna-entry-body { padding: .35rem 1.2rem 1.2rem 4.95rem; }.lna-assessment-grid { display: grid; grid-template-columns: 1.3fr 1.3fr .6fr; gap: .75rem; padding: .85rem; border: 1px solid var(--admin-border); border-radius: 14px; background: rgba(15,23,42,.18); }.lna-assessment-grid span { color: var(--admin-text-muted); font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }.lna-assessment-grid p { margin: .35rem 0 0; color: var(--admin-text-secondary); font-size: .76rem; line-height: 1.55; }
                .lna-employee-ratings { margin-top: .75rem; padding: .85rem; border: 1px solid var(--admin-border); border-radius: 14px; background: rgba(15,23,42,.18); }.lna-section-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .65rem; }.lna-section-heading span { color: var(--lna-ink); font-size: .78rem; font-weight: 800; }.lna-section-heading strong { color: var(--admin-text-muted); font-size: .64rem; font-weight: 650; }.lna-rating-reference { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }.lna-rating-reference > div { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .5rem .6rem; border: 1px solid var(--admin-border); border-radius: 9px; }.lna-rating-reference span { color: var(--admin-text-secondary); font-size: .68rem; }.lna-rating-reference strong { display: grid; min-width: 27px; height: 24px; place-items: center; color: #bae6fd; border-radius: 7px; background: rgba(56,189,248,.1); font-size: .67rem; }
                .lna-review-history { margin-top: .75rem; padding: .65rem .8rem; color: var(--admin-text-muted); border-left: 2px solid #38bdf8; background: rgba(56,189,248,.05); font-size: .7rem; line-height: 1.5; }.lna-review-history i { margin-right: .45rem; color: #38bdf8; }
                .lna-analytics-panel { position: relative; overflow: hidden; margin-top: .85rem; padding: 1rem; border: 1px solid rgba(103,232,249,.24); border-radius: 16px; background: radial-gradient(circle at 92% 4%, rgba(129,140,248,.13), transparent 28%), linear-gradient(135deg, rgba(8,47,73,.22), rgba(15,23,42,.16) 58%, rgba(76,29,149,.08)); box-shadow: 0 16px 34px rgba(8,47,73,.12); }.lna-analytics-panel::after { content: ''; position: absolute; right: 11%; top: -120px; width: 220px; height: 220px; border: 1px solid rgba(103,232,249,.1); border-radius: 50%; box-shadow: 0 0 0 30px rgba(103,232,249,.025), 0 0 0 60px rgba(103,232,249,.018); pointer-events: none; }
                .lna-analytics-header, .lna-analytics-title, .lna-analytics-tags, .lna-analytics-metrics, .lna-analytics-ranked-heading, .lna-analytics-ranked-row, .lna-analytics-footer { display: flex; align-items: center; }.lna-analytics-header { position: relative; z-index: 1; justify-content: space-between; gap: .8rem; padding-bottom: .75rem; border-bottom: 1px solid rgba(103,232,249,.13); }.lna-analytics-title { gap: .6rem; }.lna-analytics-icon { display: grid; width: 35px; height: 35px; place-items: center; color: #cffafe; border: 1px solid rgba(103,232,249,.35); border-radius: 11px; background: linear-gradient(145deg, rgba(8,145,178,.42), rgba(79,70,229,.28)); box-shadow: 0 0 20px rgba(34,211,238,.12); }.lna-analytics-title span, .lna-analytics-label, .lna-analytics-metrics span, .lna-analytics-ranked-heading, .lna-analytics-probability span { color: #67e8f9; font-size: .59rem; font-weight: 850; letter-spacing: .1em; }.lna-analytics-title h3 { margin: .15rem 0 0; color: var(--lna-ink); font-size: .88rem; }.lna-analytics-model { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .55rem; color: #c4b5fd; border: 1px solid rgba(167,139,250,.22); border-radius: 999px; background: rgba(124,58,237,.1); font-size: .62rem; }.lna-analytics-model i { color: #a78bfa; }
                .lna-analytics-body { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 1rem; padding: .95rem 0; }.lna-analytics-summary { display: grid; gap: .35rem; }.lna-analytics-summary strong { max-width: 680px; color: var(--lna-ink); font-size: .98rem; line-height: 1.35; }.lna-analytics-summary p { max-width: 720px; margin: 0; color: var(--admin-text-secondary); font-size: .72rem; line-height: 1.55; }.lna-analytics-tags { flex-wrap: wrap; gap: .4rem; margin-top: .25rem; }.lna-analytics-tags span { display: inline-flex; align-items: center; gap: .3rem; color: var(--admin-text-muted); font-size: .61rem; }.lna-analytics-tags i { color: #86efac; }.lna-analytics-score { position: relative; z-index: 1; display: grid; width: 104px; height: 104px; flex: 0 0 auto; place-items: center; border-radius: 50%; background: conic-gradient(#67e8f9 var(--analytics-score), rgba(148,163,184,.14) 0); box-shadow: 0 0 28px rgba(34,211,238,.13); }.lna-analytics-score-inner { display: grid; width: 86px; height: 86px; place-items: center; align-content: center; border-radius: 50%; background: var(--admin-card); text-align: center; }.lna-analytics-score-inner strong { color: var(--lna-ink); font-size: 1rem; }.lna-analytics-score-inner span { max-width: 58px; margin-top: .15rem; color: var(--admin-text-muted); font-size: .55rem; line-height: 1.25; }
                .lna-analytics-metrics { position: relative; z-index: 1; display: grid; grid-template-columns: .75fr 1.6fr .8fr; gap: .5rem; padding: .75rem 0; border-top: 1px solid rgba(103,232,249,.1); border-bottom: 1px solid rgba(103,232,249,.1); }.lna-analytics-metrics > div { display: grid; gap: .2rem; padding: .55rem .65rem; border: 1px solid var(--admin-border); border-radius: 10px; background: rgba(15,23,42,.16); }.lna-analytics-metrics span { color: var(--admin-text-muted); font-size: .56rem; letter-spacing: .07em; }.lna-analytics-metrics strong { overflow: hidden; color: var(--lna-ink); font-size: .68rem; line-height: 1.35; text-overflow: ellipsis; }.lna-analytics-metrics strong.is-needed { color: #fbbf24; }.lna-analytics-metrics strong.is-clear { color: #86efac; }
                .lna-analytics-ranked { position: relative; z-index: 1; margin-top: .75rem; overflow: hidden; border: 1px solid var(--admin-border); border-radius: 12px; background: rgba(15,23,42,.16); }.lna-analytics-ranked-heading { justify-content: space-between; padding: .6rem .7rem; color: #bae6fd; background: rgba(56,189,248,.07); font-size: .58rem; }.lna-analytics-ranked-heading span:last-child { color: var(--admin-text-muted); font-size: .56rem; letter-spacing: .04em; }.lna-analytics-ranked-row { gap: .55rem; padding: .62rem .7rem; border-top: 1px solid var(--admin-border); }.lna-analytics-rank { display: grid; width: 26px; height: 26px; flex: 0 0 auto; place-items: center; color: #67e8f9; border-radius: 8px; background: rgba(103,232,249,.09); font-size: .62rem; font-weight: 850; }.lna-analytics-ranked-copy { display: grid; flex: 1; gap: .14rem; min-width: 0; }.lna-analytics-ranked-copy strong { overflow: hidden; color: var(--admin-text-secondary); font-size: .69rem; text-overflow: ellipsis; white-space: nowrap; }.lna-analytics-ranked-copy span { overflow: hidden; color: var(--admin-text-muted); font-size: .61rem; text-overflow: ellipsis; white-space: nowrap; }.lna-analytics-probability { display: grid; width: 110px; flex: 0 0 auto; gap: .25rem; }.lna-analytics-probability strong { color: #cffafe; font-size: .68rem; text-align: right; }.lna-analytics-probability > span { display: block; height: 5px; overflow: hidden; border-radius: 999px; background: rgba(148,163,184,.14); }.lna-analytics-probability > span i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #60a5fa, #67e8f9); }.lna-analytics-footer { position: relative; z-index: 1; gap: .35rem; margin-top: .7rem; color: var(--admin-text-muted); font-size: .62rem; line-height: 1.4; }.lna-analytics-footer i { color: #7dd3fc; }
                .lna-analytics-panel { margin-top: .75rem; padding: .65rem; border-color: rgba(56,189,248,.2); border-radius: 13px; background: linear-gradient(180deg, rgba(8,47,73,.18), rgba(15,23,42,.16)); box-shadow: 0 0 0 1px rgba(56,189,248,.035), 0 12px 26px rgba(2,8,23,.12); }.lna-analytics-header { min-height: 27px; padding: 0 .25rem .55rem; border-bottom: 1px solid rgba(56,189,248,.14); }.lna-analytics-title { gap: .35rem; }.lna-analytics-title > i { color: #38bdf8; font-size: .78rem; }.lna-analytics-title span { color: #7dd3fc; font-size: .62rem; letter-spacing: .08em; }.lna-analytics-model { padding: .25rem .45rem; color: #94a3b8; border: 0; background: transparent; font-size: .58rem; }.lna-analytics-model i { color: #60a5fa; }.lna-analytics-table { overflow: hidden; margin-top: .55rem; border: 1px solid rgba(56,189,248,.13); border-radius: 9px; background: rgba(2,8,23,.24); }.lna-analytics-table-head, .lna-analytics-table-row { display: grid; grid-template-columns: 1.05fr 1.3fr 110px; align-items: center; gap: .65rem; }.lna-analytics-table-head { padding: .48rem .6rem; color: #7dd3fc; background: rgba(8,47,73,.55); font-size: .59rem; font-weight: 850; }.lna-analytics-table-row { min-height: 39px; padding: .5rem .6rem; border-top: 1px solid rgba(148,163,184,.09); }.lna-analytics-gap { display: flex; align-items: baseline; gap: .42rem; min-width: 0; }.lna-analytics-gap strong { color: #bae6fd; font-size: .72rem; }.lna-analytics-gap span, .lna-analytics-training { overflow: hidden; color: #e2e8f0; font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }.lna-analytics-training { color: #f8fafc; }.lna-analytics-probability { width: auto; color: #f8fafc; font-size: .78rem; text-align: right; }.lna-analytics-probability.high { color: #f8fafc; }.lna-analytics-probability.medium { color: #cbd5e1; }.lna-analytics-probability.low { color: #94a3b8; }.lna-analytics-empty { padding: 1rem; color: var(--admin-text-muted); font-size: .7rem; text-align: center; }.lna-analytics-footer { padding: .55rem .25rem 0; color: var(--admin-text-muted); font-size: .59rem; }.lna-analytics-footer i { color: #38bdf8; }.lna-review-form { display: grid; grid-template-columns: minmax(180px, .7fr) minmax(260px, 1.4fr) minmax(180px, .7fr); align-items: end; gap: .7rem; margin-top: .75rem; padding: 1rem; border: 1px solid rgba(56,189,248,.2); border-radius: 15px; background: linear-gradient(145deg, rgba(56,189,248,.045), transparent 48%); }.lna-review-form > div { display: grid; gap: .3rem; }.lna-review-form .lna-review-wide { grid-column: 1 / -1; }.lna-review-form label { color: var(--admin-text-muted); font-size: .67rem; font-weight: 700; }.lna-review-form select, .lna-review-form textarea, .lna-review-form input { width: 100%; padding: .62rem .7rem; border: 1px solid var(--admin-border-strong); border-radius: 10px; outline: none; background: var(--admin-bg-secondary); color: var(--lna-ink); font: inherit; font-size: .73rem; }.lna-review-form textarea { min-height: 76px; resize: vertical; }.lna-supervisor-ratings { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .45rem; }.lna-supervisor-rating { display: grid; grid-template-columns: 1fr minmax(150px, .55fr); align-items: center; gap: .65rem; padding: .5rem .6rem; border: 1px solid var(--admin-border); border-radius: 10px; }.lna-supervisor-rating > span { color: var(--admin-text-secondary); font-size: .69rem; }.lna-recommendation-table { overflow: hidden; border: 1px solid var(--admin-border); border-radius: 11px; }.lna-recommendation-head, .lna-recommendation-row { display: grid; grid-template-columns: 1fr 1.2fr 140px; gap: .55rem; padding: .55rem; }.lna-recommendation-head { color: #bae6fd; background: rgba(56,189,248,.08); font-size: .64rem; font-weight: 800; }.lna-recommendation-row + .lna-recommendation-row { border-top: 1px solid var(--admin-border); }.lna-method-list { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .45rem; }.lna-method { display: flex !important; align-items: flex-start; gap: .42rem !important; padding: .5rem .55rem; border: 1px solid var(--admin-border); border-radius: 9px; color: var(--admin-text-secondary) !important; font-weight: 600 !important; line-height: 1.35; }.lna-method input { width: auto; margin-top: .1rem; padding: 0; accent-color: #0284c7; }.lna-review-form button { display: flex; align-items: center; justify-content: center; gap: .4rem; min-height: 40px; padding: .65rem .9rem; border: 0; border-radius: 11px; background: #0284c7; color: #fff; font-size: .73rem; font-weight: 800; cursor: pointer; }.lna-review-form button:disabled { cursor: wait; opacity: .65; }.lna-error { color: #fca5a5; font-size: .66rem; }
                .lna-empty { display: grid; min-height: 220px; place-items: center; align-content: center; gap: .4rem; color: var(--admin-text-muted); text-align: center; }.lna-empty i { margin-bottom: .25rem; color: #38bdf8; font-size: 1.8rem; opacity: .7; }.lna-empty strong { color: var(--lna-ink); font-size: .85rem; }.lna-empty span { font-size: .72rem; }
                @media (max-width: 1180px) { .lna-summary-grid { grid-template-columns: repeat(4, 1fr); }.lna-completion { grid-column: span 4; }.lna-entry-header { grid-template-columns: 42px 1fr 1fr auto 18px; }.lna-status { display: none; }.lna-method-list { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
                @media (max-width: 780px) { .lna-hero { align-items: flex-start; flex-direction: column; }.lna-summary-grid { grid-template-columns: repeat(2, 1fr); }.lna-completion { grid-column: span 2; }.lna-toolbar, .lna-filters { align-items: stretch; flex-direction: column; }.lna-search input { width: 100%; }.lna-queue-tabs { padding-inline: .7rem; }.lna-entry-header { grid-template-columns: 42px 1fr auto 18px; }.lna-focus, .lna-status { display: none; }.lna-entry-body { padding-left: 1.2rem; }.lna-assessment-grid, .lna-review-form, .lna-supervisor-ratings, .lna-rating-reference, .lna-analytics-metrics { grid-template-columns: 1fr; }.lna-review-form .lna-review-wide { grid-column: auto; }.lna-recommendation-head { display: none; }.lna-recommendation-row { grid-template-columns: 1fr; }.lna-recommendation-row + .lna-recommendation-row { padding-top: .8rem; }.lna-section-heading { align-items: flex-start; flex-direction: column; }.lna-analytics-header { align-items: flex-start; flex-direction: column; }.lna-analytics-model { align-self: flex-start; }.lna-analytics-body { grid-template-columns: 1fr; }.lna-analytics-score { width: 88px; height: 88px; }.lna-analytics-score-inner { width: 72px; height: 72px; }.lna-analytics-ranked-row { align-items: flex-start; }.lna-analytics-probability { width: 82px; }.lna-analytics-footer { align-items: flex-start; } }
                @media (max-width: 500px) { .lna-summary-grid { grid-template-columns: 1fr; }.lna-completion { grid-column: auto; }.lna-entry-header { grid-template-columns: 38px 1fr 16px; padding-inline: .85rem; }.lna-priority { display: none; }.lna-entry-body { padding-inline: .85rem; }.lna-hero { padding: 1.25rem; } }
            `}</style>
        </AppLayout>
    );
}
