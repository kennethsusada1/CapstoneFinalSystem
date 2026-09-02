import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
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
    const [activeQueue, setActiveQueue] = useState('pending');
    const [query, setQuery] = useState('');
    const [expanded, setExpanded] = useState(null);
    const filtered = (applications ?? []).filter((item) => {
        const haystack = `${item.employee_name} ${item.employee_id} ${item.office} ${item.training_title} ${item.training_type}`.toLowerCase();
        return haystack.includes(query.trim().toLowerCase());
    });
    const pendingApplications = filtered.filter((item) => item.secretariat_status === 'pending');
    const reviewedApplications = filtered.filter((item) => ['processed', 'returned'].includes(item.secretariat_status));
    const visible = activeQueue === 'pending' ? pendingApplications : reviewedApplications;

    const changeQueue = (queue) => {
        setActiveQueue(queue);
        setExpanded(null);
    };

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
                <Panel
                    title={activeQueue === 'pending' ? 'For Review' : 'Reviewed Employee Requests'}
                    subtitle={activeQueue === 'pending' ? 'Employee requests waiting for Secretariat processing' : 'Processed and returned employee requests'}
                    action={<div className="sec-search"><i className="bi bi-search sec-muted" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search employee or training" /></div>}
                >
                    <div className="sec-queue-tabs" role="tablist" aria-label="Employee request queues">
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeQueue === 'pending'}
                            className={activeQueue === 'pending' ? 'is-active' : ''}
                            onClick={() => changeQueue('pending')}
                        >
                            <i className="bi bi-inbox-fill" />
                            <span>For Review</span>
                            <strong>{pendingApplications.length}</strong>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeQueue === 'reviewed'}
                            className={activeQueue === 'reviewed' ? 'is-active' : ''}
                            onClick={() => changeQueue('reviewed')}
                        >
                            <i className="bi bi-check2-circle" />
                            <span>Reviewed</span>
                            <strong>{reviewedApplications.length}</strong>
                        </button>
                    </div>
                    <div className="sec-list">
                        {visible.length === 0 && (
                            <EmptyState
                                icon={activeQueue === 'pending' ? 'bi-clipboard2-check' : 'bi-archive'}
                                title={activeQueue === 'pending' ? 'No employee requests for review' : 'No reviewed employee requests yet'}
                                text={query.trim() ? 'Try a different employee name or training title.' : activeQueue === 'pending' ? 'New employee requests will appear here.' : 'Processed requests will be stored here.'}
                            />
                        )}
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
            <style>{`
                .sec-queue-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: .85rem; border: 1px solid var(--admin-border); border-radius: 12px; overflow: hidden; background: rgba(245,158,11,.025); }
                .sec-queue-tabs button { display: flex; align-items: center; justify-content: center; gap: .45rem; padding: .72rem .8rem; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--admin-text-muted); font-size: .7rem; font-weight: 750; cursor: pointer; }
                .sec-queue-tabs button:hover { color: var(--admin-text-primary); background: rgba(245,158,11,.05); }
                .sec-queue-tabs button.is-active { border-bottom-color: #f59e0b; color: #fcd34d; background: rgba(245,158,11,.08); }
                .sec-queue-tabs button strong { display: grid; min-width: 23px; height: 21px; padding: 0 .3rem; place-items: center; border-radius: 999px; background: rgba(148,163,184,.13); color: inherit; font-size: .62rem; }
                .sec-queue-tabs button.is-active strong { background: rgba(245,158,11,.15); }
                @media (max-width: 700px) { .sec-queue-tabs { width: 100%; } }
            `}</style>
            <SecretariatStyles />
        </AppLayout>
    );
}
