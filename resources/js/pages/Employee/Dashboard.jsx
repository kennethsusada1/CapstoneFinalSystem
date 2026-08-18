import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatCard, StatusPill } from './Shared';

function MiniBars({ data = [] }) {
    const max = Math.max(1, ...data.map((item) => item.completed));
    return <div style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.max(data.length, 1)},1fr)`, gap: '.65rem', alignItems: 'end', minHeight: 155 }}>{data.map((item) => <div key={item.label} style={{ display: 'grid', gap: '.45rem', justifyItems: 'center' }}><strong className="emp-muted">{item.completed}</strong><div style={{ width: '100%', maxWidth: 46, height: Math.max(12, (item.completed / max) * 100), borderRadius: '10px 10px 4px 4px', background: 'linear-gradient(180deg,#5eead4,#0891b2)' }} /><span className="emp-muted">{item.label}</span></div>)}</div>;
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
        <section className="emp-grid-2">
            <Panel title="Supervisor-Generated Recommendations" subtitle="Based on your predictive skills gap" action={<Link className="emp-link" href="/employee/recommendations">View all</Link>}><div className="emp-list">{(recommendations ?? []).length === 0 && <EmptyState icon="bi-lightbulb" title="No recommendations yet" text="Your supervisor must complete the LNA review first." />}{(recommendations ?? []).slice(0, 4).map((item, index) => <div className="emp-item" key={`${item.prescriptive_training_recommendation}-${index}`}><div className="emp-title">{item.prescriptive_training_recommendation}</div><div className="emp-muted" style={{ marginTop: '.25rem' }}>Predictive skills gap</div><div className="emp-copy" style={{ marginTop: '.4rem' }}>{item.predictive_skills_gap}</div></div>)}</div></Panel>
            <Panel title="Recent Applications" subtitle="Progress of your submitted requests" action={<Link className="emp-link" href="/employee/training-applications">Manage applications</Link>}><div className="emp-list">{(recentTrainings ?? []).length === 0 && <EmptyState icon="bi-journal-plus" title="No applications yet" text="Your submitted training requests will appear here." />}{(recentTrainings ?? []).map((item) => <Link href={`/employee/training-applications/${item.id}`} className="emp-item" style={{ textDecoration: 'none' }} key={item.id}><div className="emp-row"><div className="emp-title">{item.training_title}</div><StatusPill tone={item.status === 'completed' ? 'success' : item.status === 'ongoing' ? 'warning' : 'info'}>{item.status}</StatusPill></div><div className="emp-muted" style={{ margin: '.3rem 0 .55rem' }}>{item.training_type}</div><div className="emp-progress"><span style={{ width: `${item.progress_percent}%` }} /></div></Link>)}</div></Panel>
        </section>
        <Panel title="Employee Module Guide" subtitle="A simple path from assessment to workplace application"><div className="emp-grid-3">{(highlights ?? []).map((item, index) => <div className="emp-item" key={item}><StatusPill>{`Step ${index + 1}`}</StatusPill><div className="emp-copy" style={{ marginTop: '.55rem' }}>{item}</div></div>)}</div></Panel>
    </div><EmployeeStyles /></AppLayout>;
}
