import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatCard, StatusPill } from './Shared';

const applicationTone = (status) => status === 'completed' || status === 'ongoing' ? 'success' : status === 'rejected' ? 'danger' : 'info';
const applicationLabel = (item) => item.status === 'ongoing' ? 'HRDC approved' : item.status === 'completed' ? 'Completed' : item.status === 'rejected' ? 'HRDC disapproved' : item.secretariat_status === 'processed' ? 'Preparing L&D Plan' : 'With Secretariat';

export default function Dashboard({ stats, recentTrainings, recommendations, notifications }) {
    const name = usePage().props.auth?.user?.name?.split(' ')[0] ?? 'Employee';
    const actionItems = (notifications ?? []).slice(0, 4);
    const latestRecommendations = (recommendations ?? []).slice(0, 3);
    const applications = (recentTrainings ?? []).slice(0, 4);

    return (
        <AppLayout title="Employee Dashboard" description="Employee / Learning Journey">
            <div className="emp-page">
                <PageHero
                    kicker="LEARNING JOURNEY"
                    title={`Welcome, ${name}.`}
                    description="Start with your learning needs assessment. Your supervisor's review unlocks training recommendations."
                    href="/employee/learning-needs-analysis"
                    action="Open LNA assessment"
                    icon="bi-arrow-right"
                />

                <section className="emp-stats" aria-label="Learning summary">
                    <StatCard label="Assessments" value={stats?.lnaSubmitted ?? 0} icon="bi-ui-checks-grid" />
                    <StatCard label="Skills gaps" value={stats?.skillsGapCount ?? 0} icon="bi-bullseye" color="#f59e0b" />
                    <StatCard label="Recommendations" value={stats?.recommendedTrainings ?? 0} icon="bi-lightbulb" color="#0ea5e9" />
                    <StatCard label="Completed LAPs" value={stats?.lapCompleted ?? 0} icon="bi-journal-check" color="#22a06b" />
                </section>

                <section className="emp-grid-2">
                    <Panel title="Next actions" subtitle="Items that need your attention">
                        <div className="emp-list">
                            {actionItems.length === 0 && <EmptyState icon="bi-check2-circle" title="You're all caught up" text="New reminders will appear here." />}
                            {actionItems.map((item, index) => (
                                <div className="emp-item" key={`${item.title}-${index}`}>
                                    <div className="emp-row">
                                        <StatusPill tone="warning">{item.title}</StatusPill>
                                    </div>
                                    <div className="emp-copy" style={{ marginTop: '.4rem' }}>{item.message}</div>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Recommended training" subtitle="From your latest reviewed assessment" action={<Link className="emp-link" href="/employee/recommendations">View recommendations</Link>}>
                        <div className="emp-list">
                            {latestRecommendations.length === 0 && <EmptyState icon="bi-lightbulb" title="No recommendation yet" text="Your supervisor's LNA review will appear here." />}
                            {latestRecommendations.map((item) => (
                                <div className="emp-item" key={`${item.lna_id}-${item.predictive_training_recommendation}`}>
                                    <div className="emp-row">
                                        <div className="emp-title">{item.predictive_training_recommendation || 'Training recommendation pending'}</div>
                                        <StatusPill tone={item.has_application ? 'success' : 'info'}>{item.has_application ? 'Applied' : item.track || 'Reviewed'}</StatusPill>
                                    </div>
                                    <div className="emp-copy" style={{ marginTop: '.35rem' }}>Skills gap: {item.prescriptive_skills_gap || 'See your LNA review'}</div>
                                    {!item.has_application && item.training_needed !== false && (
                                        <Link className="emp-link" style={{ display: 'inline-flex', marginTop: '.55rem' }} href="/employee/training-applications">Apply for training</Link>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Panel>
                </section>

                <Panel title="Recent applications" subtitle="Current training request status" action={<Link className="emp-link" href="/employee/training-applications">Manage applications</Link>}>
                    <div className="emp-list">
                        {applications.length === 0 && <EmptyState icon="bi-journal-plus" title="No applications yet" text="Supervisor-reviewed training will be available here." />}
                        {applications.map((item) => (
                            <Link href={`/employee/training-applications/${item.id}`} className="emp-item emp-application-row" style={{ textDecoration: 'none' }} key={item.id}>
                                <div className="emp-row">
                                    <div className="emp-title">{item.training_title}</div>
                                    <StatusPill tone={applicationTone(item.status)}>{applicationLabel(item)}</StatusPill>
                                </div>
                                <div className="emp-row" style={{ marginTop: '.55rem' }}>
                                    <div className="emp-progress" style={{ flex: 1 }}><span style={{ width: `${item.progress_percent}%` }} /></div>
                                    <span className="emp-muted">{item.progress_percent}%</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </Panel>
            </div>
            <EmployeeStyles />
        </AppLayout>
    );
}
