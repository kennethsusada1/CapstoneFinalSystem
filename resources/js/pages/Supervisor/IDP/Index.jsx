import { Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, StatCard, StatusPill, SupervisorStyles } from '../Shared';

const toneFor = (status) => status === 'completed' ? 'success' : status === 'submitted' ? 'warning' : 'info';

export default function Index({ plans }) {
    const [query, setQuery] = useState('');
    const visible = (plans ?? []).filter((plan) => `${plan.employee_name} ${plan.training_title}`.toLowerCase().includes(query.toLowerCase()));

    return (
        <AppLayout title="Team IDP" description="Supervisor / Learning Action Plans">
            <div className="sup-page">
                <PageHero kicker="WORKPLACE APPLICATION" title="Turn training into visible performance." description="Review employee Learning Action Plans, monitor workplace application commitments, and identify where coaching can strengthen training transfer." href="/supervisor/trainings" action="View team trainings" icon="bi-mortarboard" />
                <section className="sup-stats">
                    <StatCard label="All Plans" value={(plans ?? []).length} icon="bi-journals" />
                    <StatCard label="Draft" value={(plans ?? []).filter((item) => item.status === 'draft').length} icon="bi-pencil-square" color="#94a3b8" />
                    <StatCard label="Submitted" value={(plans ?? []).filter((item) => item.status === 'submitted').length} icon="bi-send-check-fill" color="#facc15" />
                    <StatCard label="Completed" value={(plans ?? []).filter((item) => item.status === 'completed').length} icon="bi-check2-circle" color="#34d399" />
                </section>
                <Panel title="Learning Action Plans" subtitle="Actual post-training employee submissions" action={<div className="sup-search"><i className="bi bi-search" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search employee or training" /></div>}>
                    {visible.length === 0 ? <EmptyState icon="bi-journal-x" title="No matching action plans" text="Completed training LAP submissions will appear here." /> : (
                        <div className="sup-list">
                            {visible.map((plan) => (
                                <Link href={`/supervisor/idp/${plan.id}`} className="sup-list-item" style={{ textDecoration: 'none' }} key={plan.id}>
                                    <div className="sup-row">
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.65rem', minWidth: 0 }}><Initials name={plan.employee_name} /><div style={{ minWidth: 0 }}><div className="sup-title">{plan.employee_name}</div><div className="sup-muted">{plan.position || 'Employee'} · {plan.employee_id}</div></div></div>
                                        <StatusPill tone={toneFor(plan.status)}>{plan.status}</StatusPill>
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(180px, .8fr) minmax(240px, 1.2fr) auto', gap: '.8rem', marginTop: '.7rem', paddingTop: '.65rem', borderTop: '1px solid var(--admin-border)' }}>
                                        <div><div className="sup-muted">TRAINING</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{plan.training_title}</div></div>
                                        <div><div className="sup-muted">IMPLEMENTATION</div><div className="sup-copy" style={{ marginTop: '.2rem' }}>{plan.implementation_summary}</div></div>
                                        <div className="sup-muted">{plan.submitted_on || 'Draft'}</div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </Panel>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
