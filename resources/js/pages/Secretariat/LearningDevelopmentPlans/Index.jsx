import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from '../Shared';

export default function Index({ plans, processedApplications, currentYear }) {
    const { props } = usePage();
    const form = useForm({
        training_application_id: '',
        title: `Learning and Development Plan ${currentYear}`,
        planning_year: String(currentYear),
        objectives: '',
        priority_programs: '',
        budget_notes: '',
        status: 'submitted',
    });

    const selectApplication = (id) => {
        const application = (processedApplications ?? []).find((item) => String(item.id) === id);
        form.setData((data) => ({
            ...data,
            training_application_id: id,
            title: application ? `L&D Plan - ${application.training_title}` : `Learning and Development Plan ${currentYear}`,
            priority_programs: application?.training_title ?? '',
        }));
    };

    const submit = (event) => {
        event.preventDefault();
        form.post('/secretariat/ld-plans', {
            preserveScroll: true,
            onSuccess: () => form.reset('training_application_id', 'objectives', 'priority_programs', 'budget_notes'),
        });
    };

    return (
        <AppLayout title="Learning & Development Plans" description="Secretariat / L&D Planning">
            <div className="sec-page">
                <PageHero kicker="APPLICATION-BASED LEARNING PLAN" title="Translate a processed request into HRDC's decision basis." description="Select a processed employee application, document the objectives and implementation requirements, then submit the proposed training program to HRDC." href="/secretariat/applications" action="Back to applications" icon="bi-inbox" />
                {props?.flash?.success && <div className="sec-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
                <section className="sec-stats">
                    <StatCard label="Plans Created" value={(plans ?? []).length} icon="bi-journals" />
                    <StatCard label="Ready for Planning" value={(processedApplications ?? []).length} icon="bi-clipboard-check" color="#facc15" />
                    <StatCard label="Submitted to HRDC" value={(plans ?? []).filter((item) => item.status === 'submitted').length} icon="bi-send-check" color="#34d399" />
                    <StatCard label="Draft" value={(plans ?? []).filter((item) => item.status === 'draft').length} icon="bi-pencil-square" color="#94a3b8" />
                </section>
                <section className="sec-grid-2">
                    <Panel title="Prepare L&D Plan" subtitle="One processed application per plan">
                        <form onSubmit={submit} className="sec-form">
                            <div className="sec-field full">
                                <label>Processed employee application</label>
                                <select value={form.data.training_application_id} onChange={(event) => selectApplication(event.target.value)}>
                                    <option value="">Select an application</option>
                                    {(processedApplications ?? []).map((application) => <option key={application.id} value={application.id}>{application.employee_name} - {application.training_title}</option>)}
                                </select>
                                {form.errors.training_application_id && <span className="sec-error">{form.errors.training_application_id}</span>}
                            </div>
                            <div className="sec-field full"><label>Plan title</label><input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />{form.errors.title && <span className="sec-error">{form.errors.title}</span>}</div>
                            <div className="sec-field"><label>Planning year</label><input value={form.data.planning_year} onChange={(event) => form.setData('planning_year', event.target.value)} maxLength="4" /></div>
                            <div className="sec-field"><label>Status</label><select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="draft">Save as draft</option><option value="submitted">Submit to HRDC</option></select></div>
                            <div className="sec-field full"><label>Learning objectives</label><textarea rows="5" value={form.data.objectives} onChange={(event) => form.setData('objectives', event.target.value)} placeholder="Describe the competency outcomes and workplace impact." />{form.errors.objectives && <span className="sec-error">{form.errors.objectives}</span>}</div>
                            <div className="sec-field full"><label>Proposed training program</label><textarea rows="3" value={form.data.priority_programs} onChange={(event) => form.setData('priority_programs', event.target.value)} placeholder="Automatically based on the employee's endorsed application." />{form.errors.priority_programs && <span className="sec-error">{form.errors.priority_programs}</span>}</div>
                            <div className="sec-field full"><label>Budget and implementation notes</label><textarea rows="4" value={form.data.budget_notes} onChange={(event) => form.setData('budget_notes', event.target.value)} placeholder="Budget estimate, schedule, provider, and implementation conditions." /></div>
                            <div className="sec-field full"><button className="sec-button" disabled={form.processing || !form.data.training_application_id}><i className="bi bi-send" />{form.processing ? 'Saving...' : form.data.status === 'submitted' ? 'Submit Plan to HRDC' : 'Save Draft'}</button></div>
                        </form>
                    </Panel>
                    <Panel title="Requests Ready for Planning" subtitle="Processed applications without an L&D Plan">
                        <div className="sec-list">
                            {(processedApplications ?? []).length === 0 && <EmptyState icon="bi-check2-circle" title="No requests waiting" text="Process an employee application before preparing its L&D Plan." />}
                            {(processedApplications ?? []).map((application) => <button type="button" className="sec-item" key={application.id} onClick={() => selectApplication(String(application.id))} style={{ textAlign: 'left', cursor: 'pointer' }}><div className="sec-row"><div><div className="sec-title">{application.employee_name}</div><div className="sec-muted">{application.employee_id} · {application.office || 'Office unassigned'}</div></div><StatusPill tone="success">processed</StatusPill></div><div className="sec-title" style={{ marginTop: '.55rem' }}>{application.training_title}</div><div className="sec-muted" style={{ marginTop: '.25rem' }}>{application.lna_focus_area || 'Linked reviewed LNA'}</div></button>)}
                        </div>
                    </Panel>
                </section>
                <Panel title="Plan Records" subtitle="Traceable Secretariat submissions to HRDC">
                    <div className="sec-list">
                        {(plans ?? []).length === 0 && <EmptyState icon="bi-journal-plus" title="No L&D Plan yet" text="A processed employee application is required for the first plan." />}
                        {(plans ?? []).map((plan) => <div className="sec-item" key={plan.id}><div className="sec-row"><div><div className="sec-title">{plan.title}</div><div className="sec-muted">{plan.employee_name || 'Organization plan'} · {plan.training_title || `Planning year ${plan.planning_year}`}</div></div><StatusPill tone={plan.status === 'submitted' ? 'success' : 'warning'}>{plan.status}</StatusPill></div><p className="sec-copy">{plan.objectives}</p><div className="sec-muted">{plan.submitted_at || 'Draft not yet submitted'}</div></div>)}
                    </div>
                </Panel>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
