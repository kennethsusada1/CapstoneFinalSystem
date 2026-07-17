import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/components/ConfirmDialog';
import { AdminStyles, PageHero } from '../Shared';

const cardStyle = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 18,
    padding: '1rem 1.1rem',
    boxShadow: 'var(--admin-shadow)',
};

const inputStyle = {
    width: '100%',
    padding: '0.7rem 0.85rem',
    fontSize: '0.88rem',
    background: 'var(--admin-bg-secondary)',
    color: 'var(--admin-text-primary)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 12,
};

const buttonStyle = {
    border: 'none',
    background: 'var(--admin-accent)',
    color: '#fff',
    borderRadius: 12,
    padding: '0.7rem 1rem',
    cursor: 'pointer',
    fontWeight: 700,
};

export default function Index({ users, employees, mailer, assignableRoles }) {
    const { props } = usePage();
    const { confirm } = useConfirm();
    const success = props?.flash?.success;
    const [showModal, setShowModal] = useState(false);
    const [selectedRoles, setSelectedRoles] = useState(
        Object.fromEntries((users ?? []).map((user) => [user.id, user.is_manageable ? user.roles?.[0] || 'employee' : user.roles?.[0] || ''])),
    );
    const activationForm = useForm({ employee_id: '', role: 'employee' });
    const roleForm = useForm({ role: 'employee' });
    const manualForm = useForm({ name: '', role: 'employee', email: '', office: '' });
    const deleteForm = useForm({});

    const submitManual = (e) => {
        e.preventDefault();
        manualForm.post('/admin/users/manual-employee', {
            onSuccess: () => {
                setShowModal(false);
                manualForm.reset();
            },
        });
    };

    const send = (row) => {
        const selectedRole = selectedRoles[row.id] || row.roles?.[0] || 'employee';

        if (row.source === 'employee') {
            activationForm
                .transform(() => ({
                    employee_id: row.employee_id,
                    role: selectedRole,
                }))
                .post('/admin/users');
            return;
        }

        activationForm.post(`/admin/users/${row.id}/resend-activation`);
    };

    const hasExplicitActivationHistory = (row) => {
        if (!row.activation_sent_at) return false;
        if (row.employee_source !== 'Manual Admin Entry') return true;
        if (!row.created_at) return true;

        const createdAt = new Date(row.created_at).getTime();
        const sentAt = new Date(row.activation_sent_at).getTime();

        if (Number.isNaN(createdAt) || Number.isNaN(sentAt)) return true;

        return Math.abs(sentAt - createdAt) >= 60000;
    };

    const getActivationActionLabel = (row) => {
        if (row.source === 'employee') return 'Activate';

        return hasExplicitActivationHistory(row) ? 'Resend' : 'Send';
    };

    const saveRole = (row) => {
        const selectedRole = selectedRoles[row.id] || 'employee';
        roleForm
            .transform(() => ({
                role: selectedRole,
            }))
            .patch(`/admin/users/${row.id}/role`);
    };

    const removeAccount = async (row) => {
        const approved = await confirm({
            title: 'Delete record?',
            message: `This will permanently delete ${row.name}${row.employee_id ? ` (${row.employee_id})` : ''} from user management.`,
            confirmText: 'Delete',
        });

        if (!approved) return;

        deleteForm.delete(`/admin/users/${row.id}`);
    };

    return (
        <AppLayout title="Users" description="Admin / User Accounts">
            <div className="sys-page">
                <PageHero kicker="ACCESS AND ACTIVATION" title="Put the right people in the right portal." description="Create employee records, send activation credentials, assign access roles, and monitor account readiness from one secure workspace." href="/admin/employees" action="Open employee registry" icon="bi-person-vcard-fill" />
                <div style={cardStyle}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '1rem', marginBottom: '0.35rem' }}>
                        <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)' }}>User Accounts</div>
                        <button type="button" onClick={() => setShowModal(true)} style={{ ...buttonStyle, padding: '0.6rem 0.9rem', fontSize: '0.82rem' }}>
                            <i className="bi bi-plus-lg" style={{ marginRight: 6 }} />
                            Add Employee
                        </button>
                    </div>
                    <div style={{ fontSize: '0.82rem', color: 'var(--admin-text-muted)', marginBottom: '1rem' }}>
                        Activation credentials will be sent through the configured mailer: <strong>{mailer}</strong>.
                    </div>
                    {success && <div style={{ marginBottom: '1rem', padding: '0.75rem 0.9rem', borderRadius: 12, background: 'rgba(16,185,129,0.14)', color: '#86efac', border: '1px solid rgba(16,185,129,0.25)', fontSize: '0.82rem' }}>{success}</div>}
                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-muted)' }}>
                        Activate accounts for secretariat, HRDC, supervisor, and employee users, then manage their assigned roles from the same list.
                    </div>
                </div>

                <div style={cardStyle}>
                    <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)', marginBottom: '1rem' }}>Managed User Accounts</div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ textAlign: 'left', color: 'var(--admin-text-muted)', fontSize: '0.76rem' }}>
                                    <th style={{ paddingBottom: '0.65rem' }}>Name</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Email</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Employee ID</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Role</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Activation</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(users ?? []).map((user) => (
                                    <tr key={user.id} style={{ borderTop: '1px solid var(--admin-border)' }}>
                                        <td style={{ padding: '0.8rem 0' }}>{user.name}</td>
                                        <td style={{ padding: '0.8rem 0', color: 'var(--admin-text-secondary)' }}>{user.email}</td>
                                        <td style={{ padding: '0.8rem 0', color: 'var(--admin-text-secondary)', fontWeight: 700 }}>{user.employee_id || '-'}</td>
                                        <td style={{ padding: '0.8rem 0' }}>
                                            {user.source === 'employee' || user.is_manageable ? (
                                                <select
                                                    value={selectedRoles[user.id] || user.roles?.[0] || 'employee'}
                                                    onChange={(e) => setSelectedRoles((current) => ({ ...current, [user.id]: e.target.value }))}
                                                    style={{ ...inputStyle, minWidth: 150, padding: '0.5rem 0.65rem' }}
                                                >
                                                    {(assignableRoles ?? []).map((role) => (
                                                        <option key={role} value={role}>
                                                            {role.replace('-', ' ')}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <div style={{ color: 'var(--admin-text-secondary)', fontWeight: 600 }}>{user.roles?.[0] || '-'}</div>
                                            )}
                                        </td>
                                        <td style={{ padding: '0.8rem 0', color: 'var(--admin-text-secondary)' }}>
                                            <div>{user.activation_sent_at || (user.source === 'employee' ? 'No account yet' : 'Not sent')}</div>
                                            <div style={{ fontSize: '0.74rem', color: user.email_verified_at ? '#34d399' : '#facc15' }}>
                                                {user.email_verified_at ? 'Activated' : user.source === 'employee' ? 'Pending account creation' : 'Pending activation'}
                                            </div>
                                        </td>
                                        <td style={{ padding: '0.8rem 0' }}>
                                            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                                                {user.source !== 'employee' && user.is_manageable && (
                                                    <button type="button" onClick={() => saveRole(user)} style={{ ...buttonStyle, padding: '0.5rem 0.8rem', fontSize: '0.8rem', background: '#0f766e' }}>
                                                        Save Role
                                                    </button>
                                                )}
                                                <button type="button" onClick={() => send(user)} style={{ ...buttonStyle, padding: '0.5rem 0.8rem', fontSize: '0.8rem' }}>
                                                    {getActivationActionLabel(user)}
                                                </button>
                                                {user.roles?.[0] !== 'system-admin' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removeAccount(user)}
                                                        title="Delete"
                                                        aria-label={`Delete ${user.name}`}
                                                        style={{
                                                            border: 'none',
                                                            background: '#b91c1c',
                                                            color: '#fff',
                                                            borderRadius: 10,
                                                            width: 34,
                                                            height: 34,
                                                            cursor: 'pointer',
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                        }}
                                                    >
                                                        <i className="bi bi-trash3" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {showModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 1400, background: 'rgba(4,10,24,0.64)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
                    <div style={{ width: '100%', maxWidth: 520, background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 20, boxShadow: 'var(--admin-shadow)', padding: '1.25rem' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '1rem', marginBottom: '1rem' }}>
                            <div>
                                <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)' }}>Add Employee</div>
                                <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-muted)', marginTop: '0.25rem' }}>Create a manual employee record that can be used for account activation.</div>
                            </div>
                            <button type="button" onClick={() => setShowModal(false)} style={{ border: '1px solid var(--admin-border)', background: 'transparent', color: 'var(--admin-text-secondary)', width: 34, height: 34, borderRadius: 10, cursor: 'pointer' }}>
                                <i className="bi bi-x-lg" />
                            </button>
                        </div>

                        <form onSubmit={submitManual} style={{ display: 'grid', gap: '0.85rem' }}>
                            <div>
                                <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Name</div>
                                <input value={manualForm.data.name} onChange={(e) => manualForm.setData('name', e.target.value)} style={inputStyle} type="text" placeholder="Full name" />
                                {manualForm.errors.name && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{manualForm.errors.name}</div>}
                            </div>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Role</div>
                                    <select value={manualForm.data.role} onChange={(e) => manualForm.setData('role', e.target.value)} style={inputStyle}>
                                        <option value="employee">Employee</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="secretariat">Secretariat</option>
                                        <option value="hrdc">HRDC</option>
                                    </select>
                                    {manualForm.errors.role && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{manualForm.errors.role}</div>}
                                </div>

                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Office</div>
                                    <input value={manualForm.data.office} onChange={(e) => manualForm.setData('office', e.target.value)} style={inputStyle} type="text" placeholder="Office" />
                                    {manualForm.errors.office && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{manualForm.errors.office}</div>}
                                </div>
                            </div>

                            <div>
                                <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Gmail</div>
                                <input value={manualForm.data.email} onChange={(e) => manualForm.setData('email', e.target.value)} style={inputStyle} type="email" placeholder="name@gmail.com" />
                                {manualForm.errors.email && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{manualForm.errors.email}</div>}
                            </div>

                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '0.35rem' }}>
                                <button type="button" onClick={() => setShowModal(false)} style={{ border: '1px solid var(--admin-border-strong)', background: 'transparent', color: 'var(--admin-text-secondary)', borderRadius: 12, padding: '0.7rem 1rem', cursor: 'pointer', fontWeight: 700 }}>
                                    Cancel
                                </button>
                                <button type="submit" style={buttonStyle} disabled={manualForm.processing}>
                                    Save Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            <AdminStyles />
        </AppLayout>
    );
}
