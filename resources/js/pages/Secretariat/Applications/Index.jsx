import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from '../Shared';

const toneFor = (status) => status === 'processed' ? 'success' : status === 'returned' ? 'danger' : 'warning';

function ProcessForm({ application }) {
    const form = useForm({
        secretariat_status: application.secretariat_status,
        activity_status: ['ongoing', 'completed'].includes(application.status) ? application.status : '',
        process_remarks: application.process_remarks ?? '',
    });

    return (
        <form onSubmit={(event) => { event.preventDefault(); form.patch(`/secretariat/applications/${application.id}`, { preserveScroll: true }); }} className="sec-form" style={{ marginTop: '.75rem', paddingTop: '.75rem', borderTop: '1px dashed var(--admin-border-strong)' }}>
            <div className="sec-field">
                <label>Secretariat processing</label>
                <select value={form.data.secretariat_status} onChange={(event) => form.setData('secretariat_status', event.target.value)}>
                    <option value="pending">Keep pending</option>
                    <option value="processed">Mark processed for L&D planning</option>
                    <option value="returned">Return to employee</option>
                </select>
            </div>
            {['ongoing', 'completed'].includes(application.status) && (
                <div className="sec-field">
                    <label>Approved activity status</label>
                    <select value={form.data.activity_status} onChange={(event) => form.setData('activity_status', event.target.value)}>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            )}
            <div className="sec-field">
                <label>Remarks {form.data.secretariat_status === 'returned' ? '(required)' : '(optional)'}</label>
                <textarea rows="3" value={form.data.process_remarks} onChange={(event) => form.setData('process_remarks', event.target.value)} placeholder="Validation notes, requirements, or coordination details" />
                {form.errors.process_remarks && <span className="sec-error">{form.errors.process_remarks}</span>}
            </div>
            <div className="sec-field full">
                <button className="sec-button" disabled={form.processing}><i className="bi bi-check2-square" />{form.processing ? 'Saving...' : 'Save processing result'}</button>
            </div>
        </form>
    );
}

export default function Index({ applications }) {
    const { props } = usePage();
    const [filter, setFilter] = useState('all');
    const [expanded, setExpanded] = useState(null);
    const visible = (applications ?? []).filter((item) => filter === 'all' || item.secretariat_status === filter);

    return (
        <AppLayout title="Training Applications" description="Secretariat / Application Processing">
            <div className="sec-page">
                <PageHero kicker="APPLICATION INBOX" title="Receive, validate, and process employee requests." description="Confirm the supervisor-reviewed LNA basis, record Secretariat remarks, then move processed requests into Learning and Development Plan preparation for HRDC." href="/secretariat/ld-plans" action="Prepare L&D Plans" icon="bi-journal-plus" />
                {props?.flash?.success && <div className="sec-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
                <section className="sec-stats">
                    <StatCard label="All Requests" value={(applications ?? []).length} icon="bi-files" />
                    <StatCard label="Pending Processing" value={(applications ?? []).filter((item) => item.secretariat_status === 'pending').length} icon="bi-hourglass" color="#facc15" />
                    <StatCard label="Processed" value={(applications ?? []).filter((item) => item.secretariat_status === 'processed').length} icon="bi-check-circle" color="#38bdf8" />
                    <StatCard label="With L&D Plan" value={(applications ?? []).filter((item) => item.has_ld_plan).length} icon="bi-journal-check" color="#34d399" />
                </section>
                <Panel title="Employee Requests" subtitle="Training applications backed by supervisor-reviewed LNA assessments" action={<select className="sec-pill" value={filter} onChange={(event) => setFilter(event.target.value)} style={{ outline: 0 }}><option value="all">All processing statuses</option><option value="pending">Pending</option><option value="processed">Processed</option><option value="returned">Returned</option></select>}>
                    <div className="sec-list">
                        {visible.length === 0 && <EmptyState title="No matching requests" text="Change the filter or wait for employee applications." />}
                        {visible.map((item) => (
                            <article className="sec-item" key={item.id}>
                                <button type="button" onClick={() => setExpanded(expanded === item.id ? null : item.id)} style={{ width: '100%', padding: 0, border: 0, background: 'transparent', textAlign: 'left', cursor: 'pointer' }}>
                                    <div className="sec-row">
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.65rem' }}>
                                            <Initials name={item.employee_name} />
                                            <div><div className="sec-title">{item.employee_name}</div><div className="sec-muted">{item.employee_id} · {item.office || 'Office unassigned'}</div></div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.55rem' }}><StatusPill tone={toneFor(item.secretariat_status)}>{item.secretariat_status}</StatusPill><i className={`bi bi-chevron-${expanded === item.id ? 'up' : 'down'} sec-muted`} /></div>
                                    </div>
                                    <div className="sec-title" style={{ marginTop: '.65rem' }}>{item.training_title}</div>
                                    <div className="sec-muted" style={{ marginTop: '.2rem' }}>{item.training_type} · {item.provider || 'Provider not specified'}</div>
                                </button>
                                {expanded === item.id && (
                                    <>
                                        <div className="sec-item" style={{ marginTop: '.7rem', background: 'rgba(56,189,248,.04)' }}>
                                            <div className="sec-muted">SUPERVISOR-EVALUATED LNA BASIS</div>
                                            <div className="sec-title" style={{ marginTop: '.35rem' }}>{item.lna_focus_area || 'Legacy application without linked LNA'}</div>
                                            <p className="sec-copy">{item.supervisor_remarks || 'No supervisor remarks recorded.'}</p>
                                            <div className="sec-muted">{item.supervisor_reviewed_by ? `Evaluated by ${item.supervisor_reviewed_by}` : 'Reviewer not recorded'}{item.has_ld_plan ? ' · L&D Plan already created' : ''}</div>
                                        </div>
                                        <ProcessForm application={item} />
                                    </>
                                )}
                            </article>
                        ))}
                    </div>
                </Panel>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
