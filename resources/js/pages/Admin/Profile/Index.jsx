import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { AdminStyles, PageHero, Panel, StatusPill } from '../Shared';

export default function Index({ profile }) {
    const { props } = usePage();
    const form = useForm({ name: profile?.name ?? '', email: profile?.email ?? '', address: profile?.address ?? '' });
    const submit = (event) => { event.preventDefault(); form.patch('/admin/profile', { preserveScroll: true }); };
    return <AppLayout title="Administrator Profile" description="System Administrator / Account Profile"><div className="sys-page">
        <PageHero kicker="ADMINISTRATOR IDENTITY" title="Keep the system owner profile current." description="Maintain the contact details associated with administrative actions and review the organizational assignment attached to this account." href="/settings/security" action="Security settings" icon="bi-shield-lock" />
        {props?.flash?.success && <div className="sys-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}
        <section className="sys-grid-2">
            <Panel title="Account Overview" subtitle="System access and organizational identity"><div className="sys-list"><div className="sys-item"><div className="sys-muted">ACCOUNT ROLE</div><StatusPill>System Administrator</StatusPill></div><div className="sys-item"><div className="sys-muted">EMPLOYEE ID</div><div className="sys-title" style={{ marginTop: '.25rem' }}>{profile.employee_id || 'Administrative account'}</div></div><div className="sys-item"><div className="sys-muted">OFFICE / POSITION</div><div className="sys-title" style={{ marginTop: '.25rem' }}>{profile.office || 'System Administration'} | {profile.position || 'Administrator'}</div></div><div className="sys-item"><div className="sys-muted">EMAIL VERIFICATION</div><StatusPill tone="success">{profile.verified_on ? `Verified ${profile.verified_on}` : 'Account active'}</StatusPill></div></div></Panel>
            <Panel title="Contact Information" subtitle="Editable administrator details"><form className="sys-form" onSubmit={submit}><div className="sys-field full"><label>Full name</label><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />{form.errors.name && <span className="sys-error">{form.errors.name}</span>}</div><div className="sys-field full"><label>Email address</label><input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />{form.errors.email && <span className="sys-error">{form.errors.email}</span>}</div><div className="sys-field full"><label>Address</label><textarea rows="4" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} placeholder="Office or mailing address" /></div><div className="sys-field"><label>Employee ID</label><input value={profile.employee_id || ''} disabled /></div><div className="sys-field"><label>Office</label><input value={profile.office || ''} disabled /></div><div className="sys-field full"><button className="sys-button" disabled={form.processing}><i className="bi bi-save" />{form.processing ? 'Saving...' : 'Save profile'}</button></div></form></Panel>
        </section>
    </div><AdminStyles /></AppLayout>;
}
