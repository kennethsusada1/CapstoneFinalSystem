import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
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

const textareaStyle = {
    ...inputStyle,
    minHeight: 180,
    resize: 'vertical',
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

export default function Index({ employees, filters }) {
    const { props } = usePage();
    const success = props?.flash?.success;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [editingEmployee, setEditingEmployee] = useState(null);
    const importForm = useForm({
        csv: 'employee_id,first_name,last_name,middle_name,email,office,position,employment_status\n',
    });
    const editForm = useForm({
        first_name: '',
        last_name: '',
        middle_name: '',
        email: '',
        office: '',
        position: '',
        employment_status: 'Active',
    });

    useEffect(() => {
        setSearch(filters?.search ?? '');
    }, [filters?.search]);

    const submitImport = (e) => {
        e.preventDefault();
        importForm.post('/admin/employees/import');
    };

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(
            '/admin/employees',
            { search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openEditor = (employee) => {
        setEditingEmployee(employee);
        editForm.setData({
            first_name: employee.first_name ?? '',
            last_name: employee.last_name ?? '',
            middle_name: employee.middle_name ?? '',
            email: employee.email ?? '',
            office: employee.office ?? '',
            position: employee.position ?? '',
            employment_status: employee.employment_status ?? 'Active',
        });
        editForm.clearErrors();
    };

    const submitEdit = (e) => {
        e.preventDefault();

        if (!editingEmployee) {
            return;
        }

        editForm.patch(`/admin/employees/${editingEmployee.id}`, {
            onSuccess: () => {
                setEditingEmployee(null);
            },
        });
    };

    return (
        <AppLayout title="Employee Records" description="Admin / HRMS Employee Records">
            <div className="sys-page">
                <PageHero kicker="EMPLOYEE DATA HUB" title="Keep the organization directory accurate and account-ready." description="Import HRMS records, find employees quickly, and maintain the office, position, and employment data used throughout Smart L&D." href="/admin/users" action="Manage linked accounts" icon="bi-people-fill" />
                <div style={cardStyle}>
                    <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)', marginBottom: '0.35rem' }}>Import Employee Records from HRMS</div>
                    <div style={{ fontSize: '0.82rem', color: 'var(--admin-text-muted)', marginBottom: '1rem' }}>
                        Paste CSV content using the HRMS headers to import new employees or refresh existing employee records.
                    </div>
                    {success && <div style={{ marginBottom: '1rem', padding: '0.75rem 0.9rem', borderRadius: 12, background: 'rgba(16,185,129,0.14)', color: '#86efac', border: '1px solid rgba(16,185,129,0.25)', fontSize: '0.82rem' }}>{success}</div>}
                    <form onSubmit={submitImport} style={{ display: 'grid', gap: '0.75rem' }}>
                        <textarea value={importForm.data.csv} onChange={(e) => importForm.setData('csv', e.target.value)} style={textareaStyle} />
                        {importForm.errors.csv && <div style={{ color: '#fca5a5', fontSize: '0.78rem' }}>{importForm.errors.csv}</div>}
                        <div>
                            <button type="submit" style={buttonStyle} disabled={importForm.processing}>
                                Import Records
                            </button>
                        </div>
                    </form>
                </div>

                <div style={cardStyle}>
                    <div style={{ display: 'flex', alignItems: 'end', justifyContent: 'space-between', gap: '1rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
                        <div>
                            <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)' }}>Employee Records</div>
                            <div style={{ fontSize: '0.82rem', color: 'var(--admin-text-muted)', marginTop: '0.25rem' }}>
                                Review imported HRMS records and keep employee details up to date.
                            </div>
                        </div>
                        <form className="admin-employee-search" onSubmit={submitSearch} style={{ display: 'flex', gap: '0.6rem', minWidth: 280 }}>
                            <input value={search} onChange={(e) => setSearch(e.target.value)} style={inputStyle} type="text" placeholder="Search employee, office, position..." />
                            <button type="submit" style={{ ...buttonStyle, paddingInline: '0.9rem' }}>
                                Search
                            </button>
                        </form>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table className="admin-employees-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ textAlign: 'left', color: 'var(--admin-text-muted)', fontSize: '0.76rem' }}>
                                    <th style={{ paddingBottom: '0.65rem' }}>Employee ID</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Name</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Office</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Position</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Status</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Account</th>
                                    <th style={{ paddingBottom: '0.65rem' }}>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(employees ?? []).map((employee) => (
                                    <tr key={employee.id} style={{ borderTop: '1px solid var(--admin-border)' }}>
                                        <td style={{ padding: '0.8rem 0', fontWeight: 700 }}>{employee.employee_id}</td>
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
                                        <td style={{ padding: '0.8rem 0' }}>
                                            <button type="button" onClick={() => openEditor(employee)} style={{ ...buttonStyle, padding: '0.5rem 0.8rem', fontSize: '0.8rem' }}>
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {editingEmployee && (
                <div className="admin-modal-backdrop" style={{ position: 'fixed', inset: 0, zIndex: 1400, background: 'rgba(4,10,24,0.64)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
                    <div className="admin-modal-card" style={{ width: '100%', maxWidth: 620, background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 20, boxShadow: 'var(--admin-shadow)', padding: '1.25rem' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '1rem', marginBottom: '1rem' }}>
                            <div>
                                <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)' }}>Edit Employee Record</div>
                                <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-muted)', marginTop: '0.25rem' }}>{editingEmployee.employee_id}</div>
                            </div>
                            <button type="button" onClick={() => setEditingEmployee(null)} style={{ border: '1px solid var(--admin-border)', background: 'transparent', color: 'var(--admin-text-secondary)', width: 34, height: 34, borderRadius: 10, cursor: 'pointer' }}>
                                <i className="bi bi-x-lg" />
                            </button>
                        </div>

                        <form onSubmit={submitEdit} style={{ display: 'grid', gap: '0.85rem' }}>
                            <div className="admin-form-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>First name</div>
                                    <input value={editForm.data.first_name} onChange={(e) => editForm.setData('first_name', e.target.value)} style={inputStyle} type="text" />
                                    {editForm.errors.first_name && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.first_name}</div>}
                                </div>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Last name</div>
                                    <input value={editForm.data.last_name} onChange={(e) => editForm.setData('last_name', e.target.value)} style={inputStyle} type="text" />
                                    {editForm.errors.last_name && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.last_name}</div>}
                                </div>
                            </div>

                            <div className="admin-form-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Middle name</div>
                                    <input value={editForm.data.middle_name} onChange={(e) => editForm.setData('middle_name', e.target.value)} style={inputStyle} type="text" />
                                    {editForm.errors.middle_name && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.middle_name}</div>}
                                </div>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Email</div>
                                    <input value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} style={inputStyle} type="email" />
                                    {editForm.errors.email && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.email}</div>}
                                </div>
                            </div>

                            <div className="admin-form-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Office</div>
                                    <input value={editForm.data.office} onChange={(e) => editForm.setData('office', e.target.value)} style={inputStyle} type="text" />
                                    {editForm.errors.office && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.office}</div>}
                                </div>
                                <div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Position</div>
                                    <input value={editForm.data.position} onChange={(e) => editForm.setData('position', e.target.value)} style={inputStyle} type="text" />
                                    {editForm.errors.position && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.position}</div>}
                                </div>
                            </div>

                            <div>
                                <div style={{ fontSize: '0.8rem', color: 'var(--admin-text-secondary)', marginBottom: '0.35rem' }}>Employment status</div>
                                <select value={editForm.data.employment_status} onChange={(e) => editForm.setData('employment_status', e.target.value)} style={inputStyle}>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Separated">Separated</option>
                                </select>
                                {editForm.errors.employment_status && <div style={{ marginTop: '0.35rem', color: '#fca5a5', fontSize: '0.78rem' }}>{editForm.errors.employment_status}</div>}
                            </div>

                            <div className="admin-modal-actions" style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '0.35rem' }}>
                                <button type="button" onClick={() => setEditingEmployee(null)} style={{ border: '1px solid var(--admin-border-strong)', background: 'transparent', color: 'var(--admin-text-secondary)', borderRadius: 12, padding: '0.7rem 1rem', cursor: 'pointer', fontWeight: 700 }}>
                                    Cancel
                                </button>
                                <button type="submit" style={buttonStyle} disabled={editForm.processing}>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            <style>{`
                .admin-employees-table { min-width: 820px; }
                .admin-modal-card { max-height: calc(100dvh - 2rem); overflow-y: auto; }
                @media (max-width: 700px) {
                    .admin-employee-search { width: 100%; min-width: 0 !important; }
                    .admin-form-grid { grid-template-columns: 1fr !important; }
                    .admin-modal-actions { align-items: stretch; flex-direction: column-reverse; }
                    .admin-modal-actions button { width: 100%; min-height: 44px; }
                }
                @media (max-width: 480px) {
                    .admin-employee-search { flex-direction: column; }
                    .admin-employee-search button { width: 100%; }
                    .admin-modal-backdrop { align-items: flex-start !important; padding: .65rem !important; }
                    .admin-modal-card { padding: 1rem !important; border-radius: 16px !important; }
                }
            `}</style>
            <AdminStyles />
        </AppLayout>
    );
}
