import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatusPill } from '../Shared';

const tone = (priority) => priority === 'high' ? 'danger' : priority === 'medium' ? 'warning' : 'info';

export default function Index({ lnaEntries, recommendations }) {
    const { props } = usePage();
    const form = useForm({ focus_area: '', competency_gap: '', proposed_intervention: '', priority_level: 'medium' });
    const submit = (event) => { event.preventDefault(); form.post('/employee/learning-needs-analysis', { onSuccess: () => form.reset() }); };
    return <AppLayout title="LNA Assessment" description="Employee / Learning Needs Analysis"><div className="emp-page">
        <PageHero kicker="CAPABILITY CHECK-IN" title="Identify where growth will make the biggest difference." description="Submit your competency gap for supervisor evaluation. Training recommendations become available for application only after the supervisor endorses the assessment." href="/employee/recommendations" action="View recommendations" icon="bi-lightbulb" />
        {props?.flash?.success && <div className="emp-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
        <section className="emp-grid-2">
            <Panel title="New LNA Assessment" subtitle="Provide enough context for supervisor review">
                <form className="emp-form" onSubmit={submit}>
                    <div className="emp-field full"><label>Focus area or competency</label><input value={form.data.focus_area} onChange={(e) => form.setData('focus_area', e.target.value)} placeholder="Example: Technical writing" />{form.errors.focus_area && <span className="emp-error">{form.errors.focus_area}</span>}</div>
                    <div className="emp-field full"><label>Current competency gap</label><textarea rows="5" value={form.data.competency_gap} onChange={(e) => form.setData('competency_gap', e.target.value)} placeholder="Describe the skill or performance gap you experience." />{form.errors.competency_gap && <span className="emp-error">{form.errors.competency_gap}</span>}</div>
                    <div className="emp-field full"><label>Proposed intervention</label><textarea rows="4" value={form.data.proposed_intervention} onChange={(e) => form.setData('proposed_intervention', e.target.value)} placeholder="Training, coaching, workshop, or another intervention." />{form.errors.proposed_intervention && <span className="emp-error">{form.errors.proposed_intervention}</span>}</div>
                    <div className="emp-field"><label>Priority level</label><select value={form.data.priority_level} onChange={(e) => form.setData('priority_level', e.target.value)}><option value="low">Low priority</option><option value="medium">Medium priority</option><option value="high">High priority</option></select></div>
                    <div className="emp-field" style={{ alignContent: 'end' }}><button className="emp-button" disabled={form.processing}><i className="bi bi-send-check" />{form.processing ? 'Submitting...' : 'Submit assessment'}</button></div>
                </form>
            </Panel>
            <Panel title="Supervisor-Endorsed Analytics" subtitle="Available after supervisor evaluation"><div className="emp-list">{(recommendations ?? []).length === 0 && <EmptyState icon="bi-stars" title="Awaiting supervisor endorsement" text="Your prescribed skills gap and training recommendation will appear here after supervisor review." />}{(recommendations ?? []).map((item) => <div className="emp-item" key={`${item.lna_id}-${item.predicted_training_recommendation}`}><div className="emp-row"><div className="emp-title">{item.focus_area}</div><StatusPill tone={tone(item.priority_level)}>{item.priority_level} priority</StatusPill></div><div className="emp-muted" style={{ marginTop: '.55rem' }}>PRESCRIPTIVE ANALYTICS</div><div className="emp-copy">{item.prescribed_skills_gap}</div><div className="emp-muted" style={{ marginTop: '.55rem' }}>PREDICTIVE ANALYTICS</div><div className="emp-title">{item.predicted_training_recommendation}</div></div>)}</div></Panel>
        </section>
        <Panel title="Assessment History" subtitle="Submitted records and supervisor review status"><div className="emp-table-wrap"><table className="emp-table"><thead><tr><th>Focus Area</th><th>Competency Gap</th><th>Priority</th><th>Submitted</th><th>Supervisor Evaluation</th></tr></thead><tbody>{(lnaEntries ?? []).map((item) => <tr key={item.id}><td><strong>{item.focus_area}</strong></td><td>{item.competency_gap}{item.review_remarks && <div className="emp-muted" style={{ marginTop: '.35rem' }}>Remarks: {item.review_remarks}</div>}</td><td><StatusPill tone={tone(item.priority_level)}>{item.priority_level}</StatusPill></td><td>{item.submitted_on || 'Draft'}</td><td><StatusPill tone={item.status === 'reviewed' ? 'success' : item.status === 'returned' ? 'danger' : 'info'}>{item.status}</StatusPill></td></tr>)}</tbody></table>{(lnaEntries ?? []).length === 0 && <EmptyState title="No LNA records" text="Your submitted assessments will be listed here." />}</div></Panel>
    </div><EmployeeStyles /></AppLayout>;
}
