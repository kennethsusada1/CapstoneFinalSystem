import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, StatCard, StatusPill, SupervisorStyles } from '../Shared';

export default function Index({ office, members }) {
    const [query, setQuery] = useState('');
    const filtered = (members ?? []).filter((member) => `${member.name} ${member.employee_id} ${member.position}`.toLowerCase().includes(query.toLowerCase()));
    const ready = (members ?? []).filter((member) => member.development_readiness >= 70).length;

    return (
        <AppLayout title="My Team" description={`Supervisor / ${office ?? 'Team Directory'}`}>
            <div className="sup-page">
                <PageHero kicker="PEOPLE AND CAPABILITY" title="Know the team behind the work." description="View each employee's learning activity, current development status, and readiness for the next capability-building opportunity." href="/supervisor/nominations" action="Open nominations" icon="bi-person-plus" />
                <section className="sup-stats">
                    <StatCard label="Team Size" value={(members ?? []).length} icon="bi-people-fill" />
                    <StatCard label="LNA Completed" value={(members ?? []).filter((item) => item.lna_count > 0).length} icon="bi-clipboard2-check" color="#34d399" />
                    <StatCard label="In Training" value={(members ?? []).filter((item) => item.active_training).length} icon="bi-mortarboard-fill" color="#fb923c" />
                    <StatCard label="Development Ready" value={ready} icon="bi-lightning-charge-fill" color="#facc15" />
                </section>

                <Panel title="Team Directory" subtitle={`${office ?? 'Unassigned office'} employee development view`} action={<div className="sup-search"><i className="bi bi-search" /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search team member" /></div>}>
                    {filtered.length === 0 ? <EmptyState icon="bi-person-lines-fill" title="No matching team members" text="Try another search or verify office assignments." /> : (
                        <div className="sup-table-wrap">
                            <table className="sup-table">
                                <thead><tr><th>Employee</th><th>LNA</th><th>Current Training</th><th>Completed</th><th>LAP</th><th>Readiness</th></tr></thead>
                                <tbody>
                                    {filtered.map((member) => (
                                        <tr key={member.id}>
                                            <td><div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}><Initials name={member.name} /><div><strong>{member.name}</strong><div className="sup-muted">{member.position || 'Employee'} · {member.employee_id}</div></div></div></td>
                                            <td><StatusPill tone={member.lna_status === 'reviewed' ? 'success' : member.lna_status === 'returned' ? 'danger' : 'warning'}>{member.lna_status}</StatusPill></td>
                                            <td>{member.active_training || <span className="sup-muted">No active training</span>}</td>
                                            <td>{member.completed_trainings}</td>
                                            <td>{member.lap_count}</td>
                                            <td style={{ minWidth: 150 }}><div className="sup-row"><span className="sup-muted">Score</span><strong>{member.development_readiness}%</strong></div><div className="sup-progress" style={{ marginTop: '.35rem' }}><span style={{ width: `${member.development_readiness}%` }} /></div></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
