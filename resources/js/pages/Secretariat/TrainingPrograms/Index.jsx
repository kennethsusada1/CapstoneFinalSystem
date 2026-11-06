import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, PageHero, Panel, SecretariatStyles, StatCard, StatusPill } from '../Shared';

const blank = {
    title: '',
    category: '',
    description: '',
    provider: '',
    training_type: 'Invitational',
    delivery_mode: 'Face-to-face',
    venue: '',
    start_date: '',
    end_date: '',
    slots: '20',
    status: 'draft',
};

const toneFor = (status) => status === 'published' ? 'success' : status === 'cancelled' ? 'danger' : 'warning';

export default function Index({ programs }) {
    const { props } = usePage();
    const [editing, setEditing] = useState(null);
    const form = useForm(blank);

    const reset = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(blank);
    };

    const edit = (program) => {
        setEditing(program);
        form.clearErrors();
        form.setData({
            title: program.title ?? '',
            category: program.category ?? '',
            description: program.description ?? '',
            provider: program.provider ?? '',
            training_type: program.training_type ?? 'Invitational',
            delivery_mode: program.delivery_mode ?? 'Face-to-face',
            venue: program.venue ?? '',
            start_date: program.start_date ?? '',
            end_date: program.end_date ?? '',
            slots: String(program.slots ?? 1),
            status: program.status ?? 'draft',
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editing) form.patch(`/secretariat/training-programs/${editing.id}`, options);
        else form.post('/secretariat/training-programs', options);
    };

    const set = (key) => (event) => form.setData(key, event.target.value);
    const total = (programs ?? []).length;
    const published = (programs ?? []).filter((item) => item.status === 'published').length;
    const openSeats = (programs ?? []).filter((item) => item.status === 'published').reduce((sum, item) => sum + item.remaining_slots, 0);

    return (
        <AppLayout title="Training Programs" description="Secretariat / Program Catalog">
            <div className="sec-page">
                <PageHero kicker="PROGRAM CATALOG" title="Publish real programs and schedules." description="Maintain the training calendar used by employees, supervisors, and application processing. Only published programs with remaining seats appear as current offerings." href="/secretariat/training-monitor" action="Open training monitor" icon="bi-activity" />
                {props?.flash?.success && <div className="sec-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
                <section className="sec-stats">
                    <StatCard label="Catalog Programs" value={total} icon="bi-journal-bookmark" />
                    <StatCard label="Published" value={published} icon="bi-calendar2-check" color="#34d399" />
                    <StatCard label="Open Seats" value={openSeats} icon="bi-person-check" color="#38bdf8" />
                </section>
                <section className="sec-grid-2">
                    <Panel title={editing ? 'Edit Training Program' : 'Add Training Program'} subtitle="Program details and schedule shown across the L&D portals">
                        <form onSubmit={submit} className="sec-form">
                            <div className="sec-field full"><label>Program title</label><input required value={form.data.title} onChange={set('title')} />{form.errors.title && <span className="sec-error">{form.errors.title}</span>}</div>
                            <div className="sec-field"><label>Category</label><input required value={form.data.category} onChange={set('category')} placeholder="Leadership, digital skills..." />{form.errors.category && <span className="sec-error">{form.errors.category}</span>}</div>
                            <div className="sec-field"><label>Provider</label><input value={form.data.provider} onChange={set('provider')} />{form.errors.provider && <span className="sec-error">{form.errors.provider}</span>}</div>
                            <div className="sec-field"><label>Training type</label><select value={form.data.training_type} onChange={set('training_type')}><option>Invitational</option><option>In-house</option></select></div>
                            <div className="sec-field"><label>Delivery mode</label><select value={form.data.delivery_mode} onChange={set('delivery_mode')}><option>Face-to-face</option><option>Online</option><option>Hybrid</option></select></div>
                            <div className="sec-field"><label>Start date</label><input type="date" value={form.data.start_date} onChange={set('start_date')} />{form.errors.start_date && <span className="sec-error">{form.errors.start_date}</span>}</div>
                            <div className="sec-field"><label>End date</label><input type="date" value={form.data.end_date} onChange={set('end_date')} />{form.errors.end_date && <span className="sec-error">{form.errors.end_date}</span>}</div>
                            <div className="sec-field"><label>Venue</label><input value={form.data.venue} onChange={set('venue')} /></div>
                            <div className="sec-field"><label>Available seats</label><input type="number" min="1" value={form.data.slots} onChange={set('slots')} />{form.errors.slots && <span className="sec-error">{form.errors.slots}</span>}</div>
                            <div className="sec-field"><label>Publication status</label><select value={form.data.status} onChange={set('status')}><option value="draft">Draft</option><option value="published">Published</option><option value="cancelled">Cancelled</option></select></div>
                            <div className="sec-field full"><label>Description</label><textarea rows="4" value={form.data.description} onChange={set('description')} />{form.errors.description && <span className="sec-error">{form.errors.description}</span>}</div>
                            {form.errors.training_program_id && <div className="sec-field full"><span className="sec-error">{form.errors.training_program_id}</span></div>}
                            <div className="sec-field full sec-row" style={{ justifyContent: 'flex-start' }}><button className="sec-button" disabled={form.processing}><i className="bi bi-save" />{form.processing ? 'Saving...' : editing ? 'Save changes' : 'Add program'}</button>{editing && <button type="button" className="sec-button" onClick={reset} style={{ background: 'transparent', border: '1px solid var(--admin-border)' }}>Cancel edit</button>}</div>
                        </form>
                    </Panel>
                    <Panel title="Published Offerings" subtitle="The employee and supervisor views use these live catalog records">
                        <div className="sec-list">
                            {(programs ?? []).filter((program) => program.status === 'published').length === 0 && <EmptyState icon="bi-calendar2-x" title="No published programs" text="Publish a program with seats available to show it in the training calendar." />}
                            {(programs ?? []).filter((program) => program.status === 'published').map((program) => <article className="sec-item" key={program.id}><div className="sec-row"><StatusPill tone="info">{program.category}</StatusPill><StatusPill tone="success">{program.remaining_slots} seats available</StatusPill></div><div className="sec-title" style={{ marginTop: '.55rem' }}>{program.title}</div><div className="sec-muted">{program.start_date || 'Schedule to be confirmed'}{program.end_date ? ` to ${program.end_date}` : ''} · {program.delivery_mode}</div><div className="sec-copy">{program.provider || 'Provider to be confirmed'}{program.venue ? ` · ${program.venue}` : ''}</div></article>)}
                        </div>
                    </Panel>
                </section>
                <Panel title="All Catalog Records" subtitle="Draft, published, and cancelled programs">
                    <div className="sec-table-wrap"><table className="sec-table"><thead><tr><th>Program</th><th>Schedule</th><th>Seats</th><th>Status</th><th>Action</th></tr></thead><tbody>{(programs ?? []).map((program) => <tr key={program.id}><td><strong>{program.title}</strong><div className="sec-muted">{program.category} · {program.provider || 'No provider'}</div></td><td>{program.start_date || 'Not scheduled'}{program.end_date ? ` to ${program.end_date}` : ''}</td><td>{program.claimed_slots} / {program.slots} claimed</td><td><StatusPill tone={toneFor(program.status)}>{program.status}</StatusPill></td><td><button type="button" className="sec-button" onClick={() => edit(program)} style={{ padding: '.45rem .7rem' }}>Edit</button></td></tr>)}</tbody></table>{total === 0 && <EmptyState icon="bi-journal-plus" title="Catalog is empty" text="Add a program to start maintaining the training schedule." />}</div>
                </Panel>
            </div>
            <SecretariatStyles />
        </AppLayout>
    );
}
