import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, StatCard, StatusPill, SupervisorStyles } from './Shared';

export default function Dashboard({ supervisor, stats, teamProgress, attentionItems }) {
    const pendingItems = (attentionItems ?? []).slice(0, 5);
    const teamMembers = (teamProgress ?? []).slice(0, 6);

    return (
        <AppLayout title="Supervisor Dashboard" description={`Supervisor / ${supervisor?.office ?? 'Team Overview'}`}>
            <div className="sup-page">
                <PageHero
                    kicker="TEAM LEARNING"
                    title={`Welcome, ${supervisor?.name?.split(' ')[0] ?? 'Supervisor'}.`}
                    description="Review learning needs and keep your team's training requests moving."
                    href="/supervisor/lna-reviews"
                    action="Review LNA assessments"
                    icon="bi-arrow-right"
                />

                <section className="sup-stats" aria-label="Team summary">
                    <StatCard label="Team members" value={stats?.team_members ?? 0} icon="bi-people" />
                    <StatCard label="Pending LNA" value={stats?.pending_lna ?? 0} icon="bi-ui-checks-grid" color="#d97706" />
                    <StatCard label="Active training" value={stats?.active_trainings ?? 0} icon="bi-mortarboard" color="#0284c7" />
                    <StatCard label="Submitted LAP" value={stats?.submitted_lap ?? 0} icon="bi-journal-check" color="#16834b" />
                </section>

                <section className="sup-grid-2">
                    <Panel title="Needs attention" subtitle="The next items in your queue" action={<Link className="sup-link" href="/supervisor/lna-reviews">Open review queue</Link>}>
                        <div className="sup-list">
                            {pendingItems.length === 0 && <EmptyState icon="bi-check2-circle" title="All caught up" text="There are no pending development actions." />}
                            {pendingItems.map((item, index) => (
                                <Link href={item.href} className="sup-list-item" style={{ textDecoration: 'none' }} key={`${item.type}-${index}`}>
                                    <div className="sup-row">
                                        <StatusPill tone={item.priority === 'high' ? 'danger' : 'warning'}>{item.type}</StatusPill>
                                        <i className="bi bi-arrow-up-right sup-muted" />
                                    </div>
                                    <div className="sup-title" style={{ marginTop: '.45rem' }}>{item.title}</div>
                                    <div className="sup-muted" style={{ marginTop: '.15rem' }}>{item.detail}</div>
                                </Link>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Team progress" subtitle="Training completion by team member" action={<Link className="sup-link" href="/supervisor/team">View full team</Link>}>
                        <div className="sup-list">
                            {teamMembers.length === 0 && <EmptyState icon="bi-people" title="No team members found" text="Employees assigned to your office will appear here." />}
                            {teamMembers.map((member) => (
                                <div className="sup-list-item" key={member.id}>
                                    <div className="sup-row">
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.65rem', minWidth: 0 }}>
                                            <Initials name={member.name} />
                                            <div style={{ minWidth: 0 }}>
                                                <div className="sup-title">{member.name}</div>
                                                <div className="sup-muted">{member.position || 'Team member'}</div>
                                            </div>
                                        </div>
                                        <strong className="sup-title">{member.training_progress}%</strong>
                                    </div>
                                    <div className="sup-progress" style={{ marginTop: '.55rem' }}><span style={{ width: `${member.training_progress}%` }} /></div>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </section>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
