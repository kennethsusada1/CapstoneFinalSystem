import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, HrdcStyles, PageHero, Panel, StatCard, StatusPill } from '../Shared';

const tone = (status) => status === 'approved' ? 'success' : status === 'disapproved' ? 'danger' : 'warning';

const queueDetails = {
    proposed: {
        title: 'Proposed Training Programs',
        subtitle: 'Programs from submitted L&D Plans waiting for an HRDC decision',
        emptyTitle: 'No proposed programs awaiting approval',
        emptyText: 'New proposed training programs will appear here.',
        emptyIcon: 'bi-clipboard2-check',
    },
    approved: {
        title: 'Approved Training Programs',
        subtitle: 'Programs authorized by HRDC for Secretariat implementation',
        emptyTitle: 'No approved training programs yet',
        emptyText: 'Programs approved by HRDC will be stored here.',
        emptyIcon: 'bi-patch-check',
    },
    disapproved: {
        title: 'Disapproved Training Programs',
        subtitle: 'Programs not authorized by HRDC, including the recorded rationale',
        emptyTitle: 'No disapproved training programs',
        emptyText: 'Programs disapproved by HRDC will be stored here.',
        emptyIcon: 'bi-x-octagon',
    },
};

function DecisionForm({ program }) {
    const form = useForm({
        status: program.status,
        review_remarks: program.review_remarks ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.patch(`/hrdc/program-approvals/${program.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="hr-form" style={{ marginTop: '.75rem', paddingTop: '.75rem', borderTop: '1px dashed var(--admin-border-strong)' }}>
            <div className="hr-field">
                <label>HRDC decision</label>
                <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                    <option value="pending">Keep pending</option>
                    <option value="approved">Approve program</option>
                    <option value="disapproved">Disapprove program</option>
                </select>
            </div>
            <div className="hr-field">
                <label>Review remarks {form.data.status === 'disapproved' ? '(required)' : '(optional)'}</label>
                <textarea
                    rows="3"
                    value={form.data.review_remarks}
                    onChange={(event) => form.setData('review_remarks', event.target.value)}
                    placeholder="Record rationale, conditions, or required revisions."
                />
                {form.errors.review_remarks && <span className="hr-error">{form.errors.review_remarks}</span>}
            </div>
            <div className="hr-field full">
                <button className="hr-button" disabled={form.processing}>
                    <i className="bi bi-save" />
                    {form.processing ? 'Saving...' : 'Save HRDC decision'}
                </button>
            </div>
        </form>
    );
}

export default function Index({ programs }) {
    const { props } = usePage();
    const [activeQueue, setActiveQueue] = useState('proposed');
    const [expanded, setExpanded] = useState(null);
    const proposedPrograms = (programs ?? []).filter((item) => item.status === 'pending');
    const approvedPrograms = (programs ?? []).filter((item) => item.status === 'approved');
    const disapprovedPrograms = (programs ?? []).filter((item) => item.status === 'disapproved');
    const visible = activeQueue === 'proposed'
        ? proposedPrograms
        : activeQueue === 'approved'
            ? approvedPrograms
            : disapprovedPrograms;
    const currentQueue = queueDetails[activeQueue];

    const changeQueue = (queue) => {
        setActiveQueue(queue);
        setExpanded(null);
    };

    return (
        <AppLayout title="Program Approvals" description="HRDC / Proposed Training Programs">
            <div className="hr-page">
                <PageHero
                    kicker="PROGRAM DECISION BOARD"
                    title="Authorize training that advances the plan."
                    description="Evaluate each proposed training program, record an approval or disapproval decision, and maintain clear rationale for Secretariat implementation."
                    href="/hrdc/ld-plans"
                    action="Review source plans"
                    icon="bi-journals"
                />
                {props?.flash?.success && <div className="hr-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}

                <section className="hr-stats">
                    <StatCard label="All Programs" value={(programs ?? []).length} icon="bi-list-check" />
                    <StatCard label="Pending" value={proposedPrograms.length} icon="bi-hourglass" color="#facc15" />
                    <StatCard label="Approved" value={approvedPrograms.length} icon="bi-patch-check" color="#34d399" />
                    <StatCard label="Disapproved" value={disapprovedPrograms.length} icon="bi-x-octagon" color="#f87171" />
                </section>

                <Panel title={currentQueue.title} subtitle={currentQueue.subtitle}>
                    <div className="hr-program-tabs" role="tablist" aria-label="Training program decision queues">
                        <button type="button" role="tab" aria-selected={activeQueue === 'proposed'} className={activeQueue === 'proposed' ? 'is-active' : ''} onClick={() => changeQueue('proposed')}>
                            <i className="bi bi-journal-text" />
                            <span>Proposed Programs</span>
                            <strong>{proposedPrograms.length}</strong>
                        </button>
                        <button type="button" role="tab" aria-selected={activeQueue === 'approved'} className={activeQueue === 'approved' ? 'is-active' : ''} onClick={() => changeQueue('approved')}>
                            <i className="bi bi-patch-check-fill" />
                            <span>Approved Programs</span>
                            <strong>{approvedPrograms.length}</strong>
                        </button>
                        <button type="button" role="tab" aria-selected={activeQueue === 'disapproved'} className={activeQueue === 'disapproved' ? 'is-active' : ''} onClick={() => changeQueue('disapproved')}>
                            <i className="bi bi-x-octagon-fill" />
                            <span>Disapproved</span>
                            <strong>{disapprovedPrograms.length}</strong>
                        </button>
                    </div>

                    <div className="hr-list">
                        {visible.length === 0 && <EmptyState icon={currentQueue.emptyIcon} title={currentQueue.emptyTitle} text={currentQueue.emptyText} />}
                        {visible.map((program) => (
                            <article className="hr-item" key={program.id}>
                                <button type="button" onClick={() => setExpanded(expanded === program.id ? null : program.id)} style={{ width: '100%', padding: 0, border: 0, background: 'transparent', textAlign: 'left', cursor: 'pointer' }}>
                                    <div className="hr-row">
                                        <div>
                                            <div className="hr-title">{program.title}</div>
                                            <div className="hr-muted">{program.plan_title} / {program.planning_year}</div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.55rem' }}>
                                            <StatusPill tone={tone(program.status)}>{program.status}</StatusPill>
                                            <i className={`bi bi-chevron-${expanded === program.id ? 'up' : 'down'} hr-muted`} />
                                        </div>
                                    </div>
                                    <div className="hr-muted" style={{ marginTop: '.4rem' }}>Submitted by {program.submitted_by}</div>
                                </button>
                                {expanded === program.id && <DecisionForm program={program} />}
                            </article>
                        ))}
                    </div>
                </Panel>
            </div>

            <style>{`
                .hr-program-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: .85rem; overflow: hidden; border: 1px solid var(--admin-border); border-radius: 12px; background: rgba(16,185,129,.025); }
                .hr-program-tabs button { display: flex; align-items: center; justify-content: center; gap: .45rem; padding: .72rem .8rem; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--admin-text-muted); font-size: .7rem; font-weight: 750; cursor: pointer; }
                .hr-program-tabs button:hover { color: var(--admin-text-primary); background: rgba(16,185,129,.05); }
                .hr-program-tabs button.is-active { border-bottom-color: #34d399; color: #6ee7b7; background: rgba(16,185,129,.08); }
                .hr-program-tabs button strong { display: grid; min-width: 23px; height: 21px; padding: 0 .3rem; place-items: center; border-radius: 999px; background: rgba(148,163,184,.13); color: inherit; font-size: .62rem; }
                .hr-program-tabs button.is-active strong { background: rgba(16,185,129,.15); }
                @media (max-width: 700px) { .hr-program-tabs { grid-template-columns: 1fr; } }
            `}</style>
            <HrdcStyles />
        </AppLayout>
    );
}
