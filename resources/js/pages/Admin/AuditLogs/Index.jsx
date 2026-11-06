import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { AdminStyles, PageHero } from '../Shared';

const panelStyle = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 18,
    padding: '1rem 1.1rem',
    boxShadow: 'var(--admin-shadow)',
};

const cellStyle = {
    padding: '0.85rem 0.75rem',
    borderBottom: '1px solid var(--admin-border)',
    color: 'var(--admin-text-primary)',
    verticalAlign: 'top',
    textAlign: 'left',
};

export default function Index({ logs }) {
    return (
        <AppLayout title="Audit Trail" description="Admin / Audit Trail">
            <Head title="Audit Trail" />
            <div className="sys-page">
                <AdminStyles />
                <PageHero
                    kicker="SYSTEM ACTIVITY"
                    title="Review recorded changes across the L&D workflow."
                    description="Successful updates, submissions, imports, and decisions are listed with the actor, target record, and request details."
                    href="/admin/users"
                    action="Manage users"
                    icon="bi-journal-text"
                />
                <section style={panelStyle}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 850 }}>
                            <thead>
                                <tr style={{ color: 'var(--admin-text-muted)', fontSize: '0.76rem', textTransform: 'uppercase' }}>
                                    <th style={cellStyle}>Date and time</th>
                                    <th style={cellStyle}>User</th>
                                    <th style={cellStyle}>Action</th>
                                    <th style={cellStyle}>Record</th>
                                    <th style={cellStyle}>Request</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.length === 0 ? (
                                    <tr><td colSpan={5} style={{ ...cellStyle, color: 'var(--admin-text-muted)' }}>No activity has been recorded yet.</td></tr>
                                ) : logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td style={cellStyle}>{log.created_at ? new Date(log.created_at).toLocaleString() : '—'}</td>
                                        <td style={cellStyle}>
                                            {log.user?.name ?? 'External integration'}
                                            {log.user?.employee_id && <div style={{ color: 'var(--admin-text-muted)', fontSize: '0.78rem' }}>{log.user.employee_id}</div>}
                                        </td>
                                        <td style={cellStyle}>
                                            <div style={{ fontWeight: 700 }}>{log.action}</div>
                                            <div style={{ color: 'var(--admin-text-muted)', fontSize: '0.8rem' }}>{log.description}</div>
                                        </td>
                                        <td style={cellStyle}>{log.entity_type ? `${log.entity_type.split('\\').pop()} #${log.entity_id ?? '—'}` : '—'}</td>
                                        <td style={cellStyle}>
                                            <div>{log.properties?.method} {log.properties?.path}</div>
                                            <div style={{ color: 'var(--admin-text-muted)', fontSize: '0.78rem' }}>{log.ip_address ?? 'IP unavailable'} · HTTP {log.properties?.response_status ?? '—'}</div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '1rem', color: 'var(--admin-text-muted)', fontSize: '0.82rem' }}>
                        <span>Page {logs.current_page} of {logs.last_page} · {logs.total} events</span>
                        <div style={{ display: 'flex', gap: '0.75rem' }}>
                            {logs.prev_page_url && <Link href={logs.prev_page_url} style={{ color: 'var(--admin-accent)' }}>Previous</Link>}
                            {logs.next_page_url && <Link href={logs.next_page_url} style={{ color: 'var(--admin-accent)' }}>Next</Link>}
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
