import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from '../Shared';

function ActivityUpdateForm({ item, onSaved }) {
    const form = useForm({
        activity_status: item.status,
        start_date: item.start_date ?? '',
        end_date: item.end_date ?? '',
        progress_percent: String(item.progress_percent ?? 0),
        is_attended: Boolean(item.is_attended),
    });

    return (
        <form className="sec-form" onSubmit={(event) => { event.preventDefault(); form.patch(`/secretariat/training-monitor/${item.id}`, { preserveScroll: true, onSuccess: onSaved }); }} style={{ marginTop: '.8rem', paddingTop: '.8rem', borderTop: '1px dashed var(--admin-border-strong)' }}>
            <div className="sec-field"><label>Start date</label><input type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} />{form.errors.start_date && <span className="sec-error">{form.errors.start_date}</span>}</div>
            <div className="sec-field"><label>End date</label><input type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} />{form.errors.end_date && <span className="sec-error">{form.errors.end_date}</span>}</div>
            <div className="sec-field"><label>Progress (%)</label><input type="number" min="0" max="100" value={form.data.progress_percent} onChange={(event) => form.setData('progress_percent', event.target.value)} />{form.errors.progress_percent && <span className="sec-error">{form.errors.progress_percent}</span>}</div>
            <div className="sec-field"><label>Activity status</label><select value={form.data.activity_status} onChange={(event) => form.setData('activity_status', event.target.value)} disabled={item.status === 'completed'}>{item.status === 'completed' ? <option value="completed">Completed</option> : <><option value="ongoing">Ongoing</option><option value="completed">Completed</option></>}</select>{form.errors.activity_status && <span className="sec-error">{form.errors.activity_status}</span>}</div>
            <label className="sec-field full" style={{ display: 'flex', alignItems: 'center', gap: '.55rem' }}><input type="checkbox" checked={form.data.is_attended} onChange={(event) => form.setData('is_attended', event.target.checked)} />Attendance confirmed</label>
            <div className="sec-field full"><button className="sec-button" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save activity update'}</button></div>
        </form>
    );
}

export default function Index({ activities, programs }) {
    const { props } = usePage();
    const [filter, setFilter] = useState('all');
    const [editing, setEditing] = useState(null);
    const visible = (activities ?? []).filter((item) => filter === 'all' || item.status === filter);
    const averageProgress = (activities ?? []).length
        ? Math.round((activities ?? []).reduce((sum, item) => sum + Number(item.progress_percent ?? 0), 0) / activities.length)
        : 0;

    return (
        <AppLayout title="Training Monitor" description="Secretariat / Approved Activities">
            <div className="sec-page">
                <PageHero kicker="ACTIVITY CONTROL BOARD" title="Monitor approved training from start to finish." description="Maintain schedules, attendance, and progress for activities HRDC approved through the L&D Plan workflow." href="/secretariat/applications" action="Return to application inbox" icon="bi-inbox" />
                {props?.flash?.success && <div className="sec-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
                <section className="sec-stats">
                    <StatCard label="Approved Activities" value={(activities ?? []).length} icon="bi-calendar2-check" />
                    <StatCard label="Ongoing" value={(activities ?? []).filter((item) => item.status === 'ongoing').length} icon="bi-play-circle" color="#38bdf8" />
                    <StatCard label="Completed" value={(activities ?? []).filter((item) => item.status === 'completed').length} icon="bi-patch-check" color="#34d399" />
                    <StatCard label="Average Progress" value={`${averageProgress}%`} icon="bi-speedometer2" color="#facc15" />
                </section>
                <Panel title="Approved Training Activities" subtitle="Schedule, attendance, and progress from real applications" action={<select className="sec-pill" value={filter} onChange={(event) => setFilter(event.target.value)} style={{ outline: 0 }}><option value="all">All activities</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option></select>}>
                    {visible.length === 0 ? <EmptyState icon="bi-activity" title="No approved activities" text="Approved applications will appear here after HRDC decisions." /> : (
                        <div className="sec-list">
                            {visible.map((item) => <article className="sec-item" key={item.id}>
                                <div className="sec-row">
                                    <div><div className="sec-title">{item.employee_name} · {item.training_title}</div><div className="sec-muted">{item.office || item.employee_id} · {item.provider || item.training_type}</div></div>
                                    <StatusPill tone={item.status === 'completed' ? 'success' : 'info'}>{item.status}</StatusPill>
                                </div>
                                <div className="sec-grid-3" style={{ marginTop: '.75rem' }}>
                                    <div><div className="sec-muted">SCHEDULE</div><div className="sec-copy">{item.start_date || 'To be scheduled'}{item.end_date ? ` to ${item.end_date}` : ''}</div><div className="sec-muted">{item.delivery_mode || 'Mode TBA'}{item.venue ? ` · ${item.venue}` : ''}</div></div>
                                    <div><div className="sec-muted">ATTENDANCE</div><div className="sec-copy">{item.is_attended ? 'Confirmed' : 'Not confirmed'}</div></div>
                                    <div><div className="sec-muted">PROGRESS</div><div className="sec-row"><strong>{item.progress_percent}%</strong><button type="button" className="sec-button" onClick={() => setEditing(editing === item.id ? null : item.id)} style={{ padding: '.4rem .65rem' }}>{editing === item.id ? 'Close' : 'Update'}</button></div><div className="sec-progress" style={{ marginTop: '.35rem' }}><span style={{ width: `${item.progress_percent}%` }} /></div></div>
                                </div>
                                {editing === item.id && <ActivityUpdateForm item={item} onSaved={() => setEditing(null)} />}
                            </article>)}
                        </div>
                    )}
                </Panel>
                <Panel title="Published Program Schedule" subtitle="Live catalog records with open seats">
                    <div className="sec-grid-3">
                        {(programs ?? []).map((program) => <div className="sec-item" key={program.id}><div className="sec-row"><StatusPill tone="info">{program.category}</StatusPill><span className="sec-muted">{program.slots} seats</span></div><div className="sec-title" style={{ marginTop: '.6rem' }}>{program.title}</div><div className="sec-muted" style={{ marginTop: '.25rem' }}>{program.schedule}<br />{program.provider} · {program.mode}</div></div>)}
                        {(programs ?? []).length === 0 && <EmptyState icon="bi-calendar2-x" title="No published programs" text="Programs published by the Secretariat will appear here." />}
                    </div>
                </Panel>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
