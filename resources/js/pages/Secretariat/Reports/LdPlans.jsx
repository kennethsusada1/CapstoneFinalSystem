import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from '../Shared';

const reviewTone = (status) => status === 'approved' || status === 'partially-approved' ? 'success' : status === 'disapproved' ? 'danger' : 'warning';

export default function LdPlans({ plans, summary, planningYear, availableYears }) {
    const exportHref = `/secretariat/reports/ld-plans/export?planning_year=${encodeURIComponent(planningYear)}`;

    return (
        <AppLayout title="L&D Plan Reports" description="Secretariat / Annual Plan Reporting">
            <div className="sec-page">
                <PageHero kicker="ANNUAL PLAN REPORTING" title={`Learning and Development Plans for ${planningYear}.`} description="Review plan objectives, priority programs, budget notes, submission status, and HRDC decisions by planning year." href={exportHref} action="Download PDF report" icon="bi-file-earmark-pdf" />
                <section className="sec-stats">
                    <StatCard label="Plans in Year" value={summary?.plans ?? 0} icon="bi-journal-text" />
                    <StatCard label="Submitted to HRDC" value={summary?.submitted ?? 0} icon="bi-send-check" color="#38bdf8" />
                    <StatCard label="HRDC Approved" value={summary?.approved ?? 0} icon="bi-check-circle" color="#34d399" />
                    <StatCard label="Drafts" value={summary?.drafts ?? 0} icon="bi-pencil-square" color="#facc15" />
                </section>
                <Panel title="Plan Report" subtitle="Filter by the planning year recorded on each L&D Plan" action={<div className="sec-row"><label className="sec-muted" htmlFor="planning-year">Planning year</label><select id="planning-year" className="sec-pill" value={planningYear} onChange={(event) => router.get('/secretariat/reports/ld-plans', { planning_year: event.target.value }, { preserveState: true, preserveScroll: true })}>{(availableYears ?? []).map((year) => <option key={year} value={year}>{year}</option>)}</select></div>}>
                    {(plans ?? []).length === 0 ? <EmptyState icon="bi-journal-x" title="No plans for this year" text="Plans will appear after the Secretariat prepares them for this planning year." /> : (
                        <div className="sec-list">
                            {(plans ?? []).map((plan) => <details className="sec-item" key={plan.id}>
                                <summary style={{ cursor: 'pointer', listStyle: 'none' }}>
                                    <div className="sec-row">
                                        <div><div className="sec-title">{plan.title}</div><div className="sec-muted">{plan.submitted_by} · {plan.employee_name || 'Organization plan'} · {plan.planning_year}</div></div>
                                        <div className="sec-row"><StatusPill tone={plan.status === 'submitted' ? 'success' : 'warning'}>{plan.status}</StatusPill><StatusPill tone={reviewTone(plan.review_status)}>{plan.review_status || 'pending'}</StatusPill></div>
                                    </div>
                                </summary>
                                <div className="sec-grid-2" style={{ marginTop: '.8rem' }}>
                                    <div><div className="sec-muted">OBJECTIVES</div><p className="sec-copy">{plan.objectives}</p></div>
                                    <div><div className="sec-muted">PRIORITY PROGRAMS</div><p className="sec-copy">{plan.priority_programs}</p></div>
                                    <div><div className="sec-muted">BUDGET AND IMPLEMENTATION NOTES</div><p className="sec-copy">{plan.budget_notes || 'No budget notes recorded.'}</p></div>
                                    <div><div className="sec-muted">HRDC REVIEW</div><p className="sec-copy">{plan.review_remarks || 'No review remarks recorded.'}</p><div className="sec-muted">{plan.submitted_at ? `Submitted ${plan.submitted_at}` : 'Not submitted'}</div></div>
                                </div>
                                {(plan.program_decisions ?? []).length > 0 && <div className="sec-list" style={{ marginTop: '.75rem' }}><div className="sec-muted">PROGRAM DECISIONS</div>{plan.program_decisions.map((program, index) => <div className="sec-row" key={`${program.title}-${index}`}><span className="sec-copy">{program.title}{program.review_remarks ? ` · ${program.review_remarks}` : ''}</span><StatusPill tone={reviewTone(program.status)}>{program.status}</StatusPill></div>)}</div>}
                            </details>)}
                        </div>
                    )}
                </Panel>
                <div className="sec-row" style={{ justifyContent: 'flex-end' }}><a className="sec-button" href={exportHref}><i className="bi bi-download" />Download filtered PDF</a><a className="sec-button" href="/secretariat/reports" style={{ background: 'transparent', border: '1px solid var(--admin-border)' }}>Terminal activity reports</a></div>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
