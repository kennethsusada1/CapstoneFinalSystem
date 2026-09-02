import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, StatCard, StatusPill, SupervisorStyles } from './Shared';

function TrainingDonut({ data }) {
    const total = Math.max(1, data.reduce((sum, item) => sum + item.value, 0));
    let offset = 0;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
            <svg viewBox="0 0 150 150" style={{ width: 145, height: 145 }}>
                <g transform="translate(75 75) rotate(-90)">
                    <circle r="48" fill="none" stroke="rgba(148,163,184,.12)" strokeWidth="15" />
                    {data.map((item) => {
                        const length = (item.value / total) * 302;
                        const dashOffset = -offset;
                        offset += length;
                        return <circle key={item.label} r="48" fill="none" stroke={item.color} strokeWidth="15" strokeDasharray={`${length} ${302 - length}`} strokeDashoffset={dashOffset} />;
                    })}
                </g>
                <text x="75" y="72" textAnchor="middle" fill="var(--admin-text-primary)" fontSize="23" fontWeight="800">{data.reduce((sum, item) => sum + item.value, 0)}</text>
                <text x="75" y="89" textAnchor="middle" fill="var(--admin-text-muted)" fontSize="9">Team trainings</text>
            </svg>
            <div className="sup-list" style={{ flex: 1 }}>
                {data.map((item) => <div className="sup-row" key={item.label}><span className="sup-copy"><i className="bi bi-circle-fill" style={{ color: item.color, fontSize: '.5rem', marginRight: '.45rem' }} />{item.label}</span><strong className="sup-title">{item.value}</strong></div>)}
            </div>
        </div>
    );
}

function percentage(value) {
    const numeric = Number(value ?? 0);

    return Number.isFinite(numeric) ? Math.max(0, Math.min(100, numeric * 100)) : 0;
}

function PredictiveSignal({ analytics }) {
    if (!analytics?.reviewed_count) {
        return <EmptyState icon="bi-activity" title="Awaiting predictive signals" text="Signals will appear after reviewed LNA assessments." />;
    }

    const score = percentage(analytics.average_probability);
    const circumference = 2 * Math.PI * 46;
    const dashOffset = circumference - (circumference * score) / 100;

    return (
        <div className="sup-analytics-overview">
            <div className="sup-signal-ring" aria-label={`${score.toFixed(1)} percent average training need probability`}>
                <svg viewBox="0 0 120 120" role="img" aria-hidden="true">
                    <circle cx="60" cy="60" r="46" fill="none" stroke="rgba(148,163,184,.13)" strokeWidth="8" />
                    <circle cx="60" cy="60" r="46" fill="none" stroke="#67e8f9" strokeWidth="8" strokeLinecap="round" strokeDasharray={circumference} strokeDashoffset={dashOffset} transform="rotate(-90 60 60)" />
                </svg>
                <div className="sup-signal-ring-label"><strong>{score.toFixed(0)}%</strong><span>need signal</span></div>
            </div>
            <div className="sup-analytics-metrics">
                <div><span>Reviewed LNAs</span><strong>{analytics.reviewed_count}</strong></div>
                <div><span>Team members</span><strong>{analytics.members_with_signal}</strong></div>
                <div><span>Training flagged</span><strong className="is-warning">{analytics.training_needed_count}</strong></div>
            </div>
        </div>
    );
}

function RecommendationRanking({ recommendations = [] }) {
    if (!recommendations.length) {
        return <EmptyState icon="bi-bar-chart-line" title="Awaiting recommendations" text="Ranked training actions will appear after supervisor review." />;
    }

    const maxProbability = Math.max(...recommendations.map((item) => Number(item.average_probability) || 0), 0.01);

    return (
        <div className="sup-recommendation-chart" role="list" aria-label="Team training recommendation ranking">
            {recommendations.map((item, index) => {
                const probability = percentage(item.average_probability);
                const width = Math.max(8, (Number(item.average_probability || 0) / maxProbability) * 100);

                return (
                    <div className="sup-recommendation-row" key={item.training_title} role="listitem">
                        <div className="sup-recommendation-copy">
                            <div className="sup-row"><strong>{index + 1}. {item.training_title}</strong><StatusPill tone={item.priority === 'high' ? 'warning' : 'info'}>{item.priority}</StatusPill></div>
                            <span>{item.competency_name} · {item.employee_count} team member{item.employee_count === 1 ? '' : 's'}</span>
                        </div>
                        <div className="sup-recommendation-track"><i style={{ width: `${width}%` }} /></div>
                        <strong className="sup-recommendation-score">{probability.toFixed(1)}%</strong>
                    </div>
                );
            })}
        </div>
    );
}

function AnalyticsWatchlist({ entries = [] }) {
    if (!entries.length) {
        return <EmptyState icon="bi-bullseye" title="No team analytics yet" text="Reviewed employee LNAs will populate this watchlist." />;
    }

    return (
        <div className="sup-analytics-watchlist">
            {entries.map((entry) => (
                <div className="sup-analytics-watch-item" key={entry.lna_id}>
                    <div className="sup-row">
                        <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', minWidth: 0 }}>
                            <Initials name={entry.employee_name} />
                            <div style={{ minWidth: 0 }}><strong className="sup-title">{entry.employee_name}</strong><div className="sup-muted">{entry.employee_id} · {entry.focus_area}</div></div>
                        </div>
                        <StatusPill tone={entry.training_needed ? 'warning' : 'success'}>{entry.training_needed ? 'Training need' : 'Monitor'}</StatusPill>
                    </div>
                    <div className="sup-analytics-watch-grid">
                        <div><span>Predicted skills gap</span><strong>{entry.predictive_skills_gap}</strong></div>
                        <div><span>Prescribed training</span><strong>{entry.prescriptive_training_recommendation}</strong></div>
                        <div><span>Probability</span><strong className="sup-analytics-watch-score">{percentage(entry.training_need_probability).toFixed(1)}%</strong></div>
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function Dashboard({ supervisor, stats, teamProgress, attentionItems, trainingMix, upcomingPrograms, teamAnalytics }) {
    return (
        <AppLayout title="Supervisor Dashboard" description={`Supervisor / ${supervisor?.office ?? 'Team Overview'}`}>
            <div className="sup-page">
                <PageHero kicker="SUPERVISOR COMMAND CENTER" title={`Welcome back, ${supervisor?.name?.split(' ')[0] ?? 'Supervisor'}.`} description="Monitor team development, review capability needs, and keep learning commitments moving from assessment to workplace application." href="/supervisor/lna-reviews" action="Review pending LNA" icon="bi-clipboard2-check" />

                <section className="sup-stats">
                    <StatCard label="Team Members" value={stats?.team_members ?? 0} icon="bi-people-fill" />
                    <StatCard label="Pending LNA" value={stats?.pending_lna ?? 0} icon="bi-ui-checks-grid" color="#facc15" />
                    <StatCard label="Active Trainings" value={stats?.active_trainings ?? 0} icon="bi-mortarboard-fill" color="#fb923c" />
                    <StatCard label="Submitted LAP" value={stats?.submitted_lap ?? 0} icon="bi-journal-check" color="#34d399" />
                </section>

                <section className="sup-grid-2 sup-analytics-grid">
                    <Panel title="Predictive Skills-Gap Signal" subtitle="Average training-need probability from reviewed team LNAs">
                        <PredictiveSignal analytics={teamAnalytics} />
                    </Panel>
                    <Panel title="Prescriptive Training Ranking" subtitle="Most relevant training actions across the team">
                        <RecommendationRanking recommendations={teamAnalytics?.top_recommendations ?? []} />
                    </Panel>
                </section>

                <Panel title="Team Analytics Watchlist" subtitle="Employee-level skills gaps and recommended interventions" action={<Link className="sup-link" href="/supervisor/lna-reviews">Open LNA review desk</Link>}>
                    <AnalyticsWatchlist entries={teamAnalytics?.watchlist ?? []} />
                </Panel>

                <section className="sup-grid-2">
                    <Panel title="Team Development Pulse" subtitle="Completion and learning activity by team member" action={<Link className="sup-link" href="/supervisor/team">View full team</Link>}>
                        <div className="sup-list">
                            {(teamProgress ?? []).length === 0 && <EmptyState icon="bi-people" title="No team members found" text="Employees assigned to your office will appear here." />}
                            {(teamProgress ?? []).map((member) => (
                                <div className="sup-list-item" key={member.id}>
                                    <div className="sup-row">
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.65rem', minWidth: 0 }}>
                                            <Initials name={member.name} />
                                            <div style={{ minWidth: 0 }}><div className="sup-title">{member.name}</div><div className="sup-muted">{member.position || 'Team member'} · {member.lna_count} LNA · {member.lap_count} LAP</div></div>
                                        </div>
                                        <strong className="sup-title">{member.training_progress}%</strong>
                                    </div>
                                    <div className="sup-progress" style={{ marginTop: '.65rem' }}><span style={{ width: `${member.training_progress}%` }} /></div>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Training Portfolio" subtitle="Current application status mix">
                        <TrainingDonut data={trainingMix ?? []} />
                    </Panel>
                </section>

                <section className="sup-grid-2">
                    <Panel title="Needs Attention" subtitle="Items where supervisor follow-through is helpful">
                        <div className="sup-list">
                            {(attentionItems ?? []).length === 0 && <EmptyState icon="bi-check2-circle" title="All caught up" text="No pending development actions at this time." />}
                            {(attentionItems ?? []).map((item, index) => (
                                <Link href={item.href} className="sup-list-item" style={{ textDecoration: 'none' }} key={`${item.type}-${index}`}>
                                    <div className="sup-row"><StatusPill tone={item.priority === 'high' ? 'danger' : 'warning'}>{item.type}</StatusPill><i className="bi bi-arrow-up-right sup-muted" /></div>
                                    <div className="sup-title" style={{ marginTop: '.55rem' }}>{item.title}</div>
                                    <div className="sup-muted" style={{ marginTop: '.2rem' }}>{item.detail}</div>
                                </Link>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Upcoming Programs" subtitle="Static training calendar for planning">
                        <div className="sup-list">
                            {(upcomingPrograms ?? []).map((program) => (
                                <div className="sup-list-item" key={program.id}>
                                    <div className="sup-row"><StatusPill>{program.category}</StatusPill><span className="sup-muted">{program.schedule}</span></div>
                                    <div className="sup-title" style={{ marginTop: '.55rem' }}>{program.title}</div>
                                    <div className="sup-muted" style={{ marginTop: '.25rem' }}>{program.provider} · {program.mode}</div>
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
