import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from './Shared';

const toneFor = (status) => status === 'completed' || status === 'received' ? 'success' : status === 'ongoing' ? 'info' : status === 'rejected' ? 'danger' : 'warning';

export default function Dashboard({ stats, recentApplications, recentPlans, programs, activityMix }) {
    return (
        <AppLayout title="Secretariat Dashboard" description="Secretariat / Learning Operations">
            <div className="sec-page">
                <PageHero kicker="LEARNING OPERATIONS DESK" title="Move every learning request forward." description="Receive employee applications, coordinate approved activities, collect post-training action plans, and prepare organization-wide L&D reports from one workspace." href="/secretariat/applications" action="Process applications" icon="bi-inbox" />
                <section className="sec-stats">
                    <StatCard label="Training Requests" value={stats?.requests ?? 0} icon="bi-files" />
                    <StatCard label="Pending Processing" value={stats?.pending_requests ?? 0} icon="bi-hourglass-split" color="#facc15" />
                    <StatCard label="Approved Activities" value={stats?.approved_activities ?? 0} icon="bi-calendar2-check-fill" color="#38bdf8" />
                    <StatCard label="LAP Received" value={stats?.lap_received ?? 0} icon="bi-journal-check" color="#34d399" />
                </section>
                <section className="sec-grid-2">
                    <Panel title="Recent Application Requests" subtitle="Latest employee submissions awaiting Secretariat action" action={<Link href="/secretariat/applications" className="sec-button secondary">Open inbox</Link>}>
                        <div className="sec-list">
                            {(recentApplications ?? []).length === 0 && <EmptyState title="No application requests" text="Employee training applications will appear here." />}
                            {(recentApplications ?? []).map((item) => <div className="sec-item" key={item.id}><div className="sec-row"><div style={{ display: 'flex', alignItems: 'center', gap: '.65rem' }}><Initials name={item.employee_name} /><div><div className="sec-title">{item.employee_name}</div><div className="sec-muted">{item.office || item.employee_id}</div></div></div><StatusPill tone={toneFor(item.status)}>{item.status}</StatusPill></div><div className="sec-title" style={{ marginTop: '.6rem' }}>{item.training_title}</div><div className="sec-muted" style={{ marginTop: '.2rem' }}>{item.training_type} · {item.provider || 'Provider not specified'}</div></div>)}
                        </div>
                    </Panel>
                    <Panel title="Activity Portfolio" subtitle="Current training workflow distribution">
                        <div className="sec-list">{(activityMix ?? []).map((item) => <div className="sec-item" key={item.label}><div className="sec-row"><span className="sec-copy"><i className="bi bi-circle-fill" style={{ color: item.color, fontSize: '.5rem', marginRight: '.45rem' }} />{item.label}</span><strong className="sec-title">{item.value}</strong></div></div>)}</div>
                    </Panel>
                </section>
                <section className="sec-grid-2">
                    <Panel title="Employee LAP Intake" subtitle="Recent post-training Learning Action Plans" action={<Link href="/secretariat/lap-submissions" className="sec-button secondary">Receive plans</Link>}>
                        <div className="sec-list">{(recentPlans ?? []).length === 0 && <EmptyState icon="bi-journal" title="No LAP submissions" text="Submitted employee action plans will appear here." />}{(recentPlans ?? []).map((plan) => <div className="sec-item" key={plan.id}><div className="sec-row"><div><div className="sec-title">{plan.employee_name}</div><div className="sec-muted">{plan.training_title}</div></div><StatusPill tone={toneFor(plan.receipt_status)}>{plan.receipt_status}</StatusPill></div></div>)}</div>
                    </Panel>
                    <Panel title="Planning Calendar" subtitle="Static 2026 program reference">
                        <div className="sec-list">{(programs ?? []).slice(0, 3).map((program) => <div className="sec-item" key={program.id}><div className="sec-row"><StatusPill tone="info">{program.category}</StatusPill><span className="sec-muted">{program.schedule}</span></div><div className="sec-title" style={{ marginTop: '.55rem' }}>{program.title}</div></div>)}</div>
                    </Panel>
                </section>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
