import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Initials, PageHero, Panel, StatusPill, SupervisorStyles } from '../Shared';

export default function Show({ plan }) {
    return (
        <AppLayout title="Team IDP Detail" description="Supervisor / Learning Action Plan">
            <div className="sup-page">
                <PageHero kicker="ACTION PLAN REVIEW" title={plan.training_title} description="Review how the employee intends to transfer learning into workplace outputs and use the milestones as coaching checkpoints." href="/supervisor/idp" action="Back to Team IDP" icon="bi-arrow-left" />
                <section className="sup-grid-2">
                    <Panel title="Employee and Plan" subtitle="Learning Action Plan record">
                        <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem', marginBottom: '1rem' }}><Initials name={plan.employee_name} /><div><div className="sup-title">{plan.employee_name}</div><div className="sup-muted">{plan.position || 'Employee'} · {plan.employee_id} · {plan.office}</div></div></div>
                        <div className="sup-list">
                            <div className="sup-list-item"><div className="sup-row"><div><div className="sup-muted">PLAN STATUS</div><div style={{ marginTop: '.3rem' }}><StatusPill tone={plan.status === 'completed' ? 'success' : 'warning'}>{plan.status}</StatusPill></div></div><div style={{ textAlign: 'right' }}><div className="sup-muted">SUBMITTED</div><div className="sup-title" style={{ marginTop: '.3rem' }}>{plan.submitted_on || 'Draft'}</div></div></div></div>
                            <div className="sup-list-item"><div className="sup-muted">IMPLEMENTATION SUMMARY</div><p className="sup-copy">{plan.implementation_summary}</p></div>
                            <div className="sup-list-item"><div className="sup-muted">LEARNING OUTCOMES</div><p className="sup-copy">{plan.learning_outcomes || 'The employee has not recorded learning outcomes yet.'}</p></div>
                        </div>
                    </Panel>
                    <Panel title="Coaching Milestones" subtitle="Suggested follow-through checkpoints">
                        <div className="sup-list">
                            {(plan.milestones ?? []).map((milestone, index) => (
                                <div className="sup-list-item" key={milestone.label}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '.65rem' }}><div className="sup-avatar" style={{ width: 32, height: 32, borderRadius: 10 }}>{milestone.status === 'completed' ? <i className="bi bi-check-lg" /> : index + 1}</div><div style={{ flex: 1 }}><div className="sup-title">{milestone.label}</div><div className="sup-muted" style={{ marginTop: '.2rem' }}>{milestone.status === 'completed' ? 'Checkpoint completed or ready for validation.' : 'Pending employee action and supervisor follow-up.'}</div></div><StatusPill tone={milestone.status === 'completed' ? 'success' : 'warning'}>{milestone.status}</StatusPill></div>
                                </div>
                            ))}
                        </div>
                        <div style={{ marginTop: '.8rem' }}><Link href="/supervisor/team" className="sup-button secondary"><i className="bi bi-people" />Open Team Directory</Link></div>
                    </Panel>
                </section>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
