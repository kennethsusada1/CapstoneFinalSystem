import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatCard, StatusPill } from '../Shared';

const label = (item) => item.status === 'ongoing' ? 'HRDC Approved' : item.status === 'completed' ? 'Completed' : item.status === 'rejected' ? 'HRDC Disapproved' : item.secretariat_status === 'processed' ? 'Preparing L&D Plan' : item.secretariat_status === 'returned' ? 'Returned by Secretariat' : 'With Secretariat';
const tone = (status) => status === 'completed' || status === 'ongoing' ? 'success' : status === 'rejected' ? 'danger' : 'info';

export default function Index({ trainings, recommendations }) {
    const { props } = usePage();
    const form = useForm({ lna_id: '' });
    const submit = (event) => { event.preventDefault(); form.post('/employee/training-applications', { onSuccess: () => form.reset() }); };
    const counts = {
        review: (trainings ?? []).filter((item) => item.status === 'applied').length,
        approved: (trainings ?? []).filter((item) => item.status === 'ongoing').length,
        completed: (trainings ?? []).filter((item) => item.status === 'completed').length,
    };

    return (
        <AppLayout title="Training Applications" description="Employee / Application Tracking">
            <div className="emp-page">
                <PageHero kicker="TRAINING REQUESTS" title="Move an endorsed LNA recommendation into action." description="Apply from a supervisor-reviewed LNA recommendation, let the Secretariat confirm a program and schedule, then wait for HRDC's decision." href="/employee/recommendations" action="Review recommendations" icon="bi-lightbulb" />
                {props?.flash?.success && <div className="emp-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
                <section className="emp-grid-3"><StatCard label="On Review" value={counts.review} icon="bi-hourglass-split" color="#38bdf8" /><StatCard label="Approved / Ongoing" value={counts.approved} icon="bi-play-circle-fill" color="#facc15" /><StatCard label="Completed" value={counts.completed} icon="bi-patch-check-fill" color="#4ade80" /></section>
                <section className="emp-grid-2">
                    <Panel title="Submit Training Application" subtitle="Request a supervisor-reviewed LNA recommendation">
                        <form className="emp-form" onSubmit={submit}>
                            <div className="emp-field full"><label>Supervisor-reviewed recommendation</label><select value={form.data.lna_id} onChange={(event) => form.setData('lna_id', event.target.value)}><option value="">Select a reviewed LNA recommendation</option>{(recommendations ?? []).map((item) => <option key={item.lna_id} value={item.lna_id}>{item.prescriptive_training_recommendation} ({item.focus_area})</option>)}</select>{form.errors.lna_id && <span className="emp-error">{form.errors.lna_id}</span>}</div>
                            <div className="emp-field full"><div className="emp-copy">The Secretariat confirms the provider and schedule against the published training catalog before preparing the L&amp;D Plan.</div></div>
                            <div className="emp-field full"><button className="emp-button" disabled={form.processing || !form.data.lna_id}><i className="bi bi-send-check" />{form.processing ? 'Submitting...' : 'Submit to Secretariat'}</button></div>
                        </form>
                    </Panel>
                    <Panel title="Workflow Readiness" subtitle="Required approval path"><div className="emp-list"><div className="emp-item"><div className="emp-title">1. Supervisor endorsement</div><div className="emp-copy">Only evaluated LNA recommendations can be submitted as training applications.</div></div><div className="emp-item"><div className="emp-title">2. Secretariat processing and plan</div><div className="emp-copy">Secretariat confirms the training program, provider, and schedule before preparing the L&amp;D Plan.</div></div><div className="emp-item"><div className="emp-title">3. HRDC program decision</div><div className="emp-copy">HRDC approves or disapproves the proposed training program using the submitted plan.</div></div></div></Panel>
                </section>
                <Panel title="Submitted Applications" subtitle="Current processing and approval progress">
                    <div className="emp-table-wrap"><table className="emp-table"><thead><tr><th>Training</th><th>Type / Provider</th><th>Schedule</th><th>Progress</th><th>Workflow Status</th><th>Details</th></tr></thead><tbody>{(trainings ?? []).map((item) => <tr key={item.id}><td><strong>{item.training_title}</strong><div className="emp-muted">{item.office || 'Office not specified'}</div></td><td>{item.training_type}<div className="emp-muted">{item.provider || 'Provider pending'}</div></td><td>{item.start_date || 'TBA'}{item.end_date ? ` to ${item.end_date}` : ''}<div className="emp-muted">{item.delivery_mode || 'Mode TBA'}{item.venue ? ` · ${item.venue}` : ''}</div></td><td style={{ minWidth: 150 }}><div className="emp-progress"><span style={{ width: `${item.progress_percent}%` }} /></div><div className="emp-muted" style={{ marginTop: '.3rem' }}>{item.progress_percent}%</div></td><td><StatusPill tone={tone(item.status)}>{label(item)}</StatusPill></td><td><Link className="emp-link" href={`/employee/training-applications/${item.id}`}>Open record</Link></td></tr>)}</tbody></table>{(trainings ?? []).length === 0 && <EmptyState icon="bi-journal-plus" title="No training applications" text="A supervisor-endorsed recommendation is required before applying." />}</div>
                </Panel>
            </div>
            <EmployeeStyles />
        </AppLayout>
    );
}
