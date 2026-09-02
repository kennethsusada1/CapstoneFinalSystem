import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatCard, StatusPill } from './Shared';

function MiniBars({ data = [] }) {
    const max = Math.max(1, ...data.map((item) => item.completed));
    return <div style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.max(data.length, 1)},1fr)`, gap: '.65rem', alignItems: 'end', minHeight: 155 }}>{data.map((item) => <div key={item.label} style={{ display: 'grid', gap: '.45rem', justifyItems: 'center' }}><strong className="emp-muted">{item.completed}</strong><div style={{ width: '100%', maxWidth: 46, height: Math.max(12, (item.completed / max) * 100), borderRadius: '10px 10px 4px 4px', background: 'linear-gradient(180deg,#5eead4,#0891b2)' }} /><span className="emp-muted">{item.label}</span></div>)}</div>;
}

function percentage(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return null;
    return Math.round(Math.max(0, Math.min(1, parsed)) * 100);
}

function PredictiveSignal({ recommendation }) {
    const signal = percentage(recommendation?.confidence_score ?? recommendation?.training_need_probability);
    const radius = 46;
    const circumference = 2 * Math.PI * radius;
    const offset = signal == null ? circumference : circumference - (signal / 100) * circumference;

    if (!recommendation) {
        return <EmptyState icon="bi-activity" title="Awaiting predictive signal" text="A chart will appear after supervisor review." />;
    }

    return <div className="emp-signal-chart">
        <div className="emp-signal-ring">
            <svg viewBox="0 0 120 120" role="img" aria-label={signal == null ? 'Confidence unavailable' : `${signal}% training need confidence`}>
                <defs><linearGradient id="emp-signal-gradient" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stopColor="#22d3ee" /><stop offset="65%" stopColor="#2dd4bf" /><stop offset="100%" stopColor="#facc15" /></linearGradient></defs>
                <circle className="emp-ring-track" cx="60" cy="60" r={radius} />
                <circle className="emp-ring-value" cx="60" cy="60" r={radius} strokeDasharray={circumference} strokeDashoffset={offset} />
            </svg>
            <div className="emp-ring-label"><strong>{signal == null ? '--' : `${signal}%`}</strong><span>need signal</span></div>
        </div>
        <div className="emp-signal-copy">
            <StatusPill tone={recommendation.training_needed ? 'warning' : 'success'}>{recommendation.training_needed ? 'Training indicated' : 'No immediate need'}</StatusPill>
            <div><span>Identified skills gap</span><strong>{recommendation.predictive_skills_gap || 'Not available'}</strong></div>
            <div><span>Model status</span><strong>{recommendation.model_version || 'Static fallback'}</strong></div>
        </div>
    </div>;
}

function RecommendationRanking({ recommendations = [] }) {
    const items = recommendations.slice(0, 4);

    if (items.length === 0) {
        return <EmptyState icon="bi-bar-chart-line" title="Awaiting recommendation ranking" text="Recommended training options will appear here." />;
    }

    return <div className="emp-ranking-chart" role="list" aria-label="Training recommendation ranking">
        {items.map((item) => {
            const probability = percentage(item.probability);
            const width = probability == null ? 6 : Math.max(6, probability);
            return <div className="emp-ranking-item" role="listitem" key={`${item.rank}-${item.training_title}`}>
                <div className="emp-ranking-label"><span><b>#{item.rank}</b> {item.competency_name}</span><strong>{probability == null ? '--' : `${probability}%`}</strong></div>
                <div className="emp-ranking-track"><span style={{ width: `${width}%` }} /></div>
                <small>{item.training_title}</small>
            </div>;
        })}
    </div>;
}

export default function Dashboard({ stats, progressCards, charts, recentTrainings, highlights, recommendations, notifications }) {
    const name = usePage().props.auth?.user?.name?.split(' ')[0] ?? 'Employee';
    return <AppLayout title="Employee Dashboard" description="Employee / Learning Journey"><div className="emp-page">
        <PageHero kicker="MY LEARNING JOURNEY" title={`Welcome back, ${name}.`} description="Turn your capability needs into focused training, monitor every application, and document how learning is applied in your work." href="/employee/learning-needs-analysis" action="Start LNA assessment" icon="bi-ui-checks-grid" />
        <section className="emp-stats">
            <StatCard label="LNA Assessments" value={stats?.lnaSubmitted ?? 0} icon="bi-ui-checks-grid" />
            <StatCard label="Skills Gaps" value={stats?.skillsGapCount ?? 0} icon="bi-bullseye" color="#fb923c" />
            <StatCard label="Recommendations" value={stats?.recommendedTrainings ?? 0} icon="bi-lightbulb-fill" color="#facc15" />
            <StatCard label="Completed LAP" value={stats?.lapCompleted ?? 0} icon="bi-journal-check" color="#4ade80" />
        </section>
        <section className="emp-grid-2">
            <Panel title="Learning Momentum" subtitle="Completed training activity in the last six months"><MiniBars data={charts?.monthlyCompletion} /></Panel>
            <Panel title="Next Actions" subtitle="Items that need your attention"><div className="emp-list">{(notifications ?? []).length === 0 && <EmptyState icon="bi-check2-circle" title="You are all caught up" text="New recommendations and reminders will appear here." />}{(notifications ?? []).slice(0, 5).map((item, index) => <div className="emp-item" key={`${item.title}-${index}`}><div className="emp-row"><StatusPill tone="warning">{item.title}</StatusPill><i className="bi bi-arrow-up-right emp-muted" /></div><div className="emp-copy" style={{ marginTop: '.45rem' }}>{item.message}</div></div>)}</div></Panel>
        </section>
        <section className="emp-grid-3">{(progressCards ?? []).map((item, index) => <StatCard key={item.label} label={item.label} value={`${item.value}${item.suffix}`} icon={['bi-bandaid-fill','bi-send-check-fill','bi-journal-arrow-up'][index] ?? 'bi-activity'} color={['#fb923c','#38bdf8','#facc15'][index]} />)}</section>
        <section className="emp-grid-2 emp-analytics-grid">
            <Panel title="Predictive Signal Matrix" subtitle="Latest reviewed LNA probability"><PredictiveSignal recommendation={recommendations?.[0]} /></Panel>
            <Panel title="Prescriptive Ranking Graph" subtitle="Top training actions for the identified gap"><RecommendationRanking recommendations={recommendations?.[0]?.ranked_recommendations ?? []} /></Panel>
        </section>
        <section className="emp-grid-2">
            <Panel title="Predictive Skills Gap" subtitle="Model output from your reviewed LNA">
                <div className="emp-list">
                    {(recommendations ?? []).length === 0 && <EmptyState icon="bi-bullseye" title="No skills gap analysis yet" text="Your supervisor must complete the LNA review first." />}
                    {(recommendations ?? []).slice(0, 4).map((item, index) => <div className="emp-item" key={`${item.lna_id}-gap-${index}`}>
                        <div className="emp-row">
                            <StatusPill tone={item.training_needed ? 'warning' : 'success'}>{item.training_needed ? 'Training indicated' : 'No immediate need'}</StatusPill>
                            <span className="emp-muted">{item.confidence_score == null ? 'Confidence unavailable' : `${(Number(item.confidence_score) * 100).toFixed(1)}% confidence`}</span>
                        </div>
                        <div className="emp-muted" style={{ marginTop: '.55rem' }}>IDENTIFIED SKILLS GAP</div>
                        <div className="emp-copy" style={{ marginTop: '.3rem' }}>{item.predictive_skills_gap}</div>
                        <div className="emp-muted" style={{ marginTop: '.45rem' }}>{item.model_version || 'Static fallback'}</div>
                    </div>)}
                </div>
            </Panel>
            <Panel title="Prescriptive Training Recommendation" subtitle="Recommended action based on the identified gap" action={<Link className="emp-link" href="/employee/training-applications">Apply for training</Link>}>
                <div className="emp-list">
                    {(recommendations ?? []).length === 0 && <EmptyState icon="bi-lightbulb" title="No training recommendation yet" text="A recommendation will appear after supervisor review." />}
                    {(recommendations ?? []).slice(0, 4).map((item, index) => <div className="emp-item" key={`${item.lna_id}-training-${index}`}>
                        <div className="emp-title">{item.prescriptive_training_recommendation}</div>
                        <div className="emp-muted" style={{ marginTop: '.3rem' }}>{item.provider} · {item.training_type}</div>
                        <div className="emp-copy" style={{ marginTop: '.45rem' }}>{item.rationale}</div>
                        {(item.ranked_recommendations ?? []).length > 1 && <div className="emp-list" style={{ marginTop: '.65rem' }}>{item.ranked_recommendations.slice(1, 4).map((ranked) => <div className="emp-row" key={`${item.lna_id}-${ranked.rank}`}><span className="emp-copy">#{ranked.rank} {ranked.training_title}</span><StatusPill tone="info">{(Number(ranked.probability) * 100).toFixed(1)}%</StatusPill></div>)}</div>}
                    </div>)}
                </div>
            </Panel>
        </section>
        <section className="emp-grid-2">
            <Panel title="Recent Applications" subtitle="Progress of your submitted requests" action={<Link className="emp-link" href="/employee/training-applications">Manage applications</Link>}><div className="emp-list">{(recentTrainings ?? []).length === 0 && <EmptyState icon="bi-journal-plus" title="No applications yet" text="Your submitted training requests will appear here." />}{(recentTrainings ?? []).map((item) => <Link href={`/employee/training-applications/${item.id}`} className="emp-item" style={{ textDecoration: 'none' }} key={item.id}><div className="emp-row"><div className="emp-title">{item.training_title}</div><StatusPill tone={item.status === 'completed' ? 'success' : item.status === 'ongoing' ? 'warning' : 'info'}>{item.status}</StatusPill></div><div className="emp-muted" style={{ margin: '.3rem 0 .55rem' }}>{item.training_type}</div><div className="emp-progress"><span style={{ width: `${item.progress_percent}%` }} /></div></Link>)}</div></Panel>
            <Panel title="Recommendation Note" subtitle="How to use this dashboard"><div className="emp-item"><div className="emp-copy">These analytics support your learning decision. Your supervisor remains the final validator, and the recommended training still follows the normal Secretariat and HRDC approval workflow.</div></div></Panel>
        </section>
        <Panel title="Employee Module Guide" subtitle="A simple path from assessment to workplace application"><div className="emp-grid-3">{(highlights ?? []).map((item, index) => <div className="emp-item" key={item}><StatusPill>{`Step ${index + 1}`}</StatusPill><div className="emp-copy" style={{ marginTop: '.55rem' }}>{item}</div></div>)}</div></Panel>
    </div><EmployeeStyles /></AppLayout>;
}
