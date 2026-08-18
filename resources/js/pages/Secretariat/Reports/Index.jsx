import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from '../Shared';

export default function Index({ summary, activities, offices }) {
    return (
        <AppLayout title="Terminal Activity Reports" description="Secretariat / Reports and L&D Plan Inputs">
            <div className="sec-page">
                <PageHero
                    kicker="TERMINAL REPORTING DESK"
                    title="Turn activity records into accountable results."
                    description="Generate terminal training activity summaries, review participation and completion, and use the same evidence as input to the next Learning and Development Plan."
                    href="/secretariat/reports/export"
                    action="Download PDF report"
                    icon="bi-file-earmark-pdf"
                />

                <section className="sec-stats">
                    <StatCard label="Report Ready" value={summary?.total_applications ?? 0} icon="bi-files" />
                    <StatCard label="Approved Activities" value={summary?.approved_activities ?? 0} icon="bi-calendar2-check" color="#38bdf8" />
                    <StatCard label="Completed" value={summary?.completed_activities ?? 0} icon="bi-patch-check" color="#34d399" />
                    <StatCard label="Completion Rate" value={`${summary?.completion_rate ?? 0}%`} icon="bi-graph-up-arrow" color="#facc15" />
                </section>

                <section className="sec-grid-2">
                    <Panel title="Terminal Activity Summary" subtitle="Consolidated training outcomes by activity">
                        {(activities ?? []).length === 0 ? (
                            <EmptyState icon="bi-bar-chart" title="No reportable activities" text="A completed training becomes reportable after the Secretariat receives its submitted LAP." />
                        ) : (
                            <div className="sec-list">
                                {(activities ?? []).map((item) => (
                                    <div className="sec-item" key={item.training_title}>
                                        <div className="sec-row">
                                            <div className="sec-title">{item.training_title}</div>
                                            <StatusPill tone={item.completed > 0 ? 'success' : 'info'}>{item.completed} completed</StatusPill>
                                        </div>
                                        <div className="sec-grid-3" style={{ marginTop: '.7rem' }}>
                                            <div><div className="sec-muted">PARTICIPANTS</div><strong className="sec-title">{item.participants}</strong></div>
                                            <div><div className="sec-muted">APPROVED</div><strong className="sec-title">{item.approved}</strong></div>
                                            <div><div className="sec-muted">AVG. PROGRESS</div><strong className="sec-title">{item.average_progress}%</strong></div>
                                        </div>
                                        <div className="sec-progress" style={{ marginTop: '.6rem' }}><span style={{ width: `${item.average_progress}%` }} /></div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Panel>

                    <Panel title="Office Participation" subtitle="Applications and completion by office">
                        <div className="sec-list">
                            {(offices ?? []).length === 0 && <EmptyState icon="bi-building" title="No office data" text="Office participation will appear here." />}
                            {(offices ?? []).map((item) => (
                                <div className="sec-item" key={item.office}>
                                    <div className="sec-row">
                                        <div><div className="sec-title">{item.office}</div><div className="sec-muted">{item.applications} applications</div></div>
                                        <StatusPill tone="success">{item.completed} completed</StatusPill>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </section>

                <Panel title="Learning Development Plan Inputs" subtitle="Key metrics for annual planning">
                    <div className="sec-grid-3">
                        <div className="sec-item">
                            <div className="sec-muted">LAP SUBMISSIONS</div>
                            <div style={{ marginTop: '.35rem', fontSize: '1.5rem', fontWeight: 800, color: 'var(--admin-text-primary)' }}>{summary?.lap_submissions ?? 0}</div>
                            <p className="sec-copy">Submitted action plans indicate where employees intend to apply new learning.</p>
                        </div>
                        <div className="sec-item">
                            <div className="sec-muted">LAP RECEIVED</div>
                            <div style={{ marginTop: '.35rem', fontSize: '1.5rem', fontWeight: 800, color: 'var(--admin-text-primary)' }}>{summary?.lap_received ?? 0}</div>
                            <p className="sec-copy">Receipt status supports document completeness and terminal reporting.</p>
                        </div>
                        <div className="sec-item">
                            <div className="sec-muted">PLANNING ACTION</div>
                            <div className="sec-title" style={{ marginTop: '.45rem' }}>Prioritize low-completion and high-demand capability areas.</div>
                            <p className="sec-copy">Use participation, completion, and LAP evidence when preparing the next L&D Plan.</p>
                        </div>
                    </div>
                </Panel>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
