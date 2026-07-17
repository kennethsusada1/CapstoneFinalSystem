import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const cardStyle = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 18,
    padding: '1rem 1.1rem',
    boxShadow: 'var(--admin-shadow)',
};

const textareaStyle = {
    width: '100%',
    minHeight: 180,
    padding: '0.8rem 0.9rem',
    fontSize: '0.86rem',
    background: 'var(--admin-bg-secondary)',
    color: 'var(--admin-text-primary)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 12,
    resize: 'vertical',
};

export default function Index({ employees, filters }) {
    const form = useForm({
        csv: 'employee_id,first_name,last_name,middle_name,email,office,position,employment_status\n',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/offices/import');
    };

    return (
        <AppLayout title="Offices" description="Admin / HRMS Records">
            <div style={{ display: 'grid', gap: '1rem' }}>
                <div style={cardStyle}>
                    <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)', marginBottom: '0.35rem' }}>Import Employee Records from HRMS</div>
                    <div style={{ fontSize: '0.82rem', color: 'var(--admin-text-muted)', marginBottom: '1rem' }}>
                        Paste CSV content using the sample headers to import and refresh employee records.
                    </div>
                    <form onSubmit={submit} style={{ display: 'grid', gap: '0.75rem' }}>
                        <textarea value={form.data.csv} onChange={(e) => form.setData('csv', e.target.value)} style={textareaStyle} />
                        <div>
                            <button type="submit" style={{ border: 'none', background: 'var(--admin-accent)', color: '#fff', borderRadius: 12, padding: '0.75rem 1rem', cursor: 'pointer', fontWeight: 700 }}>
                                Import Records
                            </button>
                        </div>
                    </form>
                </div>

                <div style={cardStyle}>
                    <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)', marginBottom: '1rem' }}>
                        Employee Records {filters?.search ? `for "${filters.search}"` : ''}
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ textAlign: 'left', color: 'var(--admin-text-muted)', fontSize: '0.76rem' }}>
                                    <th style={{ paddingBottom: '0.65rem' }}>Employee ID</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Name</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Office</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Position</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Status</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Account</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(employees ?? []).map((employee) => (
                                    <tr key={employee.id} style={{ borderTop: '1px solid var(--admin-border)' }}>
                                        <td style={{ padding: '0.8rem 0' }}>{employee.employee_id}</td>
                                        <td style={{ padding: '0.8rem 0' }}>
                                            <div style={{ color: 'var(--admin-text-primary)', fontWeight: 600 }}>{employee.name}</div>
                                            <div style={{ color: 'var(--admin-text-muted)', fontSize: '0.78rem' }}>{employee.email || 'No email'}</div>
                                        </td>
                                        <td style={{ padding: '0.8rem 0', color: 'var(--admin-text-secondary)' }}>{employee.office || '-'}</td>
                                        <td style={{ padding: '0.8rem 0', color: 'var(--admin-text-secondary)' }}>{employee.position || '-'}</td>
                                        <td style={{ padding: '0.8rem 0' }}>{employee.employment_status}</td>
                                        <td style={{ padding: '0.8rem 0', color: employee.has_account ? '#34d399' : 'var(--admin-text-muted)' }}>
                                            {employee.has_account ? 'Linked' : 'Not linked'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
