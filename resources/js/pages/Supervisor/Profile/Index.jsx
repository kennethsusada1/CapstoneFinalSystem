import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Initials, PageHero, Panel, SupervisorStyles } from '../Shared';

export default function Index({ profile }) {
    const { props } = usePage();
    const form = useForm({
        name: profile?.name ?? '',
        email: profile?.email ?? '',
        address: profile?.address ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.patch('/supervisor/profile', { preserveScroll: true });
    };

    return (
        <AppLayout title="Supervisor Profile" description="Supervisor / Account Profile">
            <div className="sup-page">
                <PageHero kicker="SUPERVISOR IDENTITY" title="Keep your leadership profile current." description="Update your contact details while reviewing the office and position assignments used to scope your team records." href="/settings/security" action="Security settings" icon="bi-shield-lock" />
                {props?.flash?.success && <div className="sup-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
                <section className="sup-grid-2">
                    <Panel title="Profile Overview" subtitle="System and organizational assignment">
                        <div style={{ display: 'flex', alignItems: 'center', gap: '.8rem', paddingBottom: '1rem', borderBottom: '1px solid var(--admin-border)' }}><Initials name={profile.name} /><div><div className="sup-title">{profile.name}</div><div className="sup-muted">{profile.position || 'Supervisor'} · {profile.office || 'Office not assigned'}</div></div></div>
                        <div className="sup-list" style={{ marginTop: '.8rem' }}>
                            <div className="sup-list-item"><div className="sup-muted">EMPLOYEE ID</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{profile.employee_id || 'Not assigned'}</div></div>
                            <div className="sup-list-item"><div className="sup-muted">OFFICE / TEAM SCOPE</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{profile.office || 'Not assigned'}</div></div>
                            <div className="sup-list-item"><div className="sup-muted">POSITION</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{profile.position || 'Not assigned'}</div></div>
                        </div>
                    </Panel>
                    <Panel title="Contact Information" subtitle="Editable supervisor account details">
                        <form onSubmit={submit}>
                            <div className="sup-form-grid">
                                <div className="sup-field full"><label>Full name</label><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />{form.errors.name && <span style={{ color: '#fca5a5', fontSize: '.65rem' }}>{form.errors.name}</span>}</div>
                                <div className="sup-field full"><label>Email address</label><input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />{form.errors.email && <span style={{ color: '#fca5a5', fontSize: '.65rem' }}>{form.errors.email}</span>}</div>
                                <div className="sup-field full"><label>Address</label><textarea rows="4" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} placeholder="Office or mailing address" /></div>
                                <div className="sup-field"><label>Employee ID</label><input value={profile.employee_id || ''} disabled /></div>
                                <div className="sup-field"><label>Office</label><input value={profile.office || ''} disabled /></div>
                            </div>
                            <button className="sup-button" type="submit" disabled={form.processing} style={{ marginTop: '.85rem' }}><i className="bi bi-save" />{form.processing ? 'Saving...' : 'Save profile'}</button>
                        </form>
                    </Panel>
                </section>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
