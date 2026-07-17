import { Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatCard, StatusPill } from '../Shared';

const label = (status) => ({ applied: 'On Review', ongoing: 'Approved', completed: 'Completed' }[status] ?? status);
const tone = (status) => status === 'completed' ? 'success' : status === 'ongoing' ? 'warning' : 'info';

export default function Index({ trainings, recommendations }) {
    const { props } = usePage();
    const form = useForm({ training_title: '', training_type: 'Invitational', provider: '', office: '', start_date: '', end_date: '', is_attended: false });
    const submit = (event) => { event.preventDefault(); form.post('/employee/training-applications', { onSuccess: () => form.reset() }); };
    const counts = { review: (trainings ?? []).filter((item) => item.status === 'applied').length, approved: (trainings ?? []).filter((item) => item.status === 'ongoing').length, completed: (trainings ?? []).filter((item) => item.status === 'completed').length };
    return <AppLayout title="Training Applications" description="Employee / Application Tracking"><div className="emp-page">
        <PageHero kicker="TRAINING REQUESTS" title="Move a learning recommendation into action." description="Submit a training request, track Secretariat processing, and open any record to review its schedule, remarks, and completion details." href="/employee/recommendations" action="Review recommendations" icon="bi-lightbulb" />
        {props?.flash?.success && <div className="emp-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
        <section className="emp-grid-3"><StatCard label="On Review" value={counts.review} icon="bi-hourglass-split" color="#38bdf8" /><StatCard label="Approved / Ongoing" value={counts.approved} icon="bi-play-circle-fill" color="#facc15" /><StatCard label="Completed" value={counts.completed} icon="bi-patch-check-fill" color="#4ade80" /></section>
        <section className="emp-grid-2">
            <Panel title="Submit Training Application" subtitle="Request a recommended or self-identified program">
                <form className="emp-form" onSubmit={submit}>
                    <div className="emp-field full"><label>Training title</label><input list="recommended-trainings" value={form.data.training_title} onChange={(e) => form.setData('training_title', e.target.value)} placeholder="Select or enter a training title" /><datalist id="recommended-trainings">{(recommendations ?? []).map((item) => <option key={item.lna_id} value={item.predicted_training_recommendation} />)}</datalist>{form.errors.training_title && <span className="emp-error">{form.errors.training_title}</span>}</div>
                    <div className="emp-field"><label>Training type</label><select value={form.data.training_type} onChange={(e) => form.setData('training_type', e.target.value)}><option value="Invitational">Invitational</option><option value="In-house">In-house</option></select></div>
                    <div className="emp-field"><label>Provider / organizer</label><input value={form.data.provider} onChange={(e) => form.setData('provider', e.target.value)} placeholder="Training provider" /></div>
                    <div className="emp-field"><label>Office</label><input value={form.data.office} onChange={(e) => form.setData('office', e.target.value)} placeholder="Requesting office" /></div>
                    <div className="emp-field"><label>Start date</label><input type="date" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} /></div>
                    <div className="emp-field"><label>End date</label><input type="date" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} /></div>
                    <div className="emp-field" style={{ alignContent: 'end' }}><label style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}><input style={{ width: 'auto' }} type="checkbox" checked={form.data.is_attended} onChange={(e) => form.setData('is_attended', e.target.checked)} />Already attended</label></div>
                    <div className="emp-field full"><button className="emp-button" disabled={form.processing}><i className="bi bi-send-check" />{form.processing ? 'Submitting...' : 'Submit application'}</button></div>
                </form>
            </Panel>
            <Panel title="Application Readiness" subtitle="Before sending your request"><div className="emp-list"><div className="emp-item"><div className="emp-title">Confirm development relevance</div><div className="emp-copy">Connect the program to an LNA focus area or a clear work requirement.</div></div><div className="emp-item"><div className="emp-title">Provide schedule details</div><div className="emp-copy">Dates and provider information help the Secretariat process your request faster.</div></div><div className="emp-item"><div className="emp-title">Monitor processing remarks</div><div className="emp-copy">Open the application record after submission to see decisions and follow-up notes.</div></div></div></Panel>
        </section>
        <Panel title="Submitted Applications" subtitle="Current processing and training progress"><div className="emp-table-wrap"><table className="emp-table"><thead><tr><th>Training</th><th>Type / Provider</th><th>Schedule</th><th>Progress</th><th>Status</th><th>Details</th></tr></thead><tbody>{(trainings ?? []).map((item) => <tr key={item.id}><td><strong>{item.training_title}</strong><div className="emp-muted">{item.office || 'Office not specified'}</div></td><td>{item.training_type}<div className="emp-muted">{item.provider || 'Provider pending'}</div></td><td>{item.start_date || 'TBA'}{item.end_date ? ` to ${item.end_date}` : ''}</td><td style={{ minWidth: 150 }}><div className="emp-progress"><span style={{ width: `${item.progress_percent}%` }} /></div><div className="emp-muted" style={{ marginTop: '.3rem' }}>{item.progress_percent}%</div></td><td><StatusPill tone={tone(item.status)}>{label(item.status)}</StatusPill></td><td><Link className="emp-link" href={`/employee/training-applications/${item.id}`}>Open record</Link></td></tr>)}</tbody></table>{(trainings ?? []).length === 0 && <EmptyState icon="bi-journal-plus" title="No training applications" text="Your first application will appear here after submission." />}</div></Panel>
    </div><EmployeeStyles /></AppLayout>;
}
