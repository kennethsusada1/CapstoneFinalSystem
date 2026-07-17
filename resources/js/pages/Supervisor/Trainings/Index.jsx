import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, PageHero, Panel, StatCard, StatusPill, SupervisorStyles } from '../Shared';

const toneFor = (status) => status === 'completed' ? 'success' : status === 'ongoing' ? 'warning' : 'info';

export default function Index({ trainingApplications, programs }) {
    const [status, setStatus] = useState('all');
    const visible = (trainingApplications ?? []).filter((item) => status === 'all' || item.status === status);

    return (
        <AppLayout title="Trainings" description="Supervisor / Team Training Monitor">
            <div className="sup-page">
                <PageHero kicker="LEARNING OPERATIONS" title="Keep every learning commitment visible." description="Monitor team training applications and progress, then use the planning calendar to prepare future participation and workload coverage." href="/supervisor/nominations" action="Plan nominations" icon="bi-calendar-plus" />
                <section className="sup-stats">
                    <StatCard label="Applications" value={(trainingApplications ?? []).length} icon="bi-files" />
                    <StatCard label="Applied" value={(trainingApplications ?? []).filter((item) => item.status === 'applied').length} icon="bi-hourglass-split" color="#38bdf8" />
                    <StatCard label="Ongoing" value={(trainingApplications ?? []).filter((item) => item.status === 'ongoing').length} icon="bi-play-circle-fill" color="#facc15" />
                    <StatCard label="Completed" value={(trainingApplications ?? []).filter((item) => item.status === 'completed').length} icon="bi-patch-check-fill" color="#34d399" />
                </section>
                <Panel title="Team Training Applications" subtitle="Actual employee application records" action={<select className="sup-pill" value={status} onChange={(event) => setStatus(event.target.value)} style={{ outline: 0 }}><option value="all">All statuses</option><option value="applied">Applied</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option></select>}>
                    {visible.length === 0 ? <EmptyState icon="bi-mortarboard" title="No training applications found" text="Team applications will appear here once submitted." /> : (
                        <div className="sup-table-wrap">
                            <table className="sup-table"><thead><tr><th>Employee</th><th>Training</th><th>Schedule</th><th>Progress</th><th>Status</th></tr></thead>
                                <tbody>{visible.map((item) => <tr key={item.id}>
                                    <td><strong>{item.employee_name}</strong><div className="sup-muted">{item.employee_id}</div></td>
                                    <td><strong>{item.training_title}</strong><div className="sup-muted">{item.provider || item.training_type}</div></td>
                                    <td>{item.start_date || 'To be scheduled'}{item.end_date && <div className="sup-muted">to {item.end_date}</div>}</td>
                                    <td style={{ minWidth: 160 }}><div className="sup-row"><span className="sup-muted">Completion</span><strong>{item.progress_percent}%</strong></div><div className="sup-progress" style={{ marginTop: '.35rem' }}><span style={{ width: `${item.progress_percent}%` }} /></div></td>
                                    <td><StatusPill tone={toneFor(item.status)}>{item.status}</StatusPill></td>
                                </tr>)}</tbody>
                            </table>
                        </div>
                    )}
                </Panel>
                <Panel title="2026 Training Calendar" subtitle="Static programs for team planning">
                    <div className="sup-grid-3">
                        {(programs ?? []).map((program) => <div className="sup-list-item" key={program.id}><div className="sup-row"><StatusPill>{program.category}</StatusPill><span className="sup-muted">{program.slots} slots</span></div><div className="sup-title" style={{ marginTop: '.65rem' }}>{program.title}</div><div className="sup-muted" style={{ marginTop: '.3rem' }}>{program.schedule}</div><div className="sup-copy" style={{ marginTop: '.45rem' }}>{program.provider}<br />{program.mode}</div></div>)}
                    </div>
                </Panel>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
