import { Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { EmptyState, Initials, PageHero, Panel, StatCard, StatusPill, SupervisorStyles } from '../Shared';

const toneFor = (status) => status === 'endorsed' ? 'success' : status === 'draft' ? 'info' : 'warning';

export default function Index({ nominations, programs }) {
    const [filter, setFilter] = useState('all');
    const visible = (nominations ?? []).filter((item) => filter === 'all' || item.status === filter);

    return (
        <AppLayout title="Nominations" description="Supervisor / Training Nominations">
            <div className="sup-page">
                <PageHero kicker="TALENT OPPORTUNITY BOARD" title="Match people with the right opportunity." description="Prepare and monitor team nominations against the static training calendar. Records shown here are prototype nomination entries until the nomination workflow table is enabled." href="/supervisor/lna-reviews" action="Review skills gaps" icon="bi-compass" />
                <section className="sup-stats">
                    <StatCard label="All Nominations" value={(nominations ?? []).length} icon="bi-person-plus-fill" />
                    <StatCard label="Draft" value={(nominations ?? []).filter((item) => item.status === 'draft').length} icon="bi-pencil-square" color="#94a3b8" />
                    <StatCard label="For Endorsement" value={(nominations ?? []).filter((item) => item.status === 'for endorsement').length} icon="bi-send-fill" color="#facc15" />
                    <StatCard label="Endorsed" value={(nominations ?? []).filter((item) => item.status === 'endorsed').length} icon="bi-check-circle-fill" color="#34d399" />
                </section>

                <section className="sup-grid-2">
                    <Panel title="Nomination Pipeline" subtitle="Static prototype records based on available programs" action={
                        <select className="sup-pill" value={filter} onChange={(event) => setFilter(event.target.value)} style={{ outline: 0 }}>
                            <option value="all">All statuses</option><option value="draft">Draft</option><option value="for endorsement">For endorsement</option><option value="endorsed">Endorsed</option>
                        </select>
                    }>
                        <div className="sup-list">
                            {visible.length === 0 && <EmptyState icon="bi-person-plus" title="No matching nominations" text="Change the status filter to view other records." />}
                            {visible.map((item) => (
                                <Link href={`/supervisor/nominations/${item.id}`} className="sup-list-item" style={{ textDecoration: 'none' }} key={item.id}>
                                    <div className="sup-row">
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '.65rem', minWidth: 0 }}>
                                            <Initials name={item.employee_name} />
                                            <div style={{ minWidth: 0 }}><div className="sup-title">{item.employee_name}</div><div className="sup-muted">{item.position} · {item.employee_id}</div></div>
                                        </div>
                                        <StatusPill tone={toneFor(item.status)}>{item.status}</StatusPill>
                                    </div>
                                    <div style={{ marginTop: '.65rem', paddingTop: '.6rem', borderTop: '1px solid var(--admin-border)' }}>
                                        <div className="sup-title">{item.training_title}</div>
                                        <div className="sup-muted" style={{ marginTop: '.25rem' }}>{item.schedule} · {item.mode}</div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Available Programs" subtitle="Static 2026 planning calendar">
                        <div className="sup-list">
                            {(programs ?? []).map((program) => (
                                <div className="sup-list-item" key={program.id}>
                                    <div className="sup-row"><StatusPill>{program.category}</StatusPill><span className="sup-muted">{program.slots} slots</span></div>
                                    <div className="sup-title" style={{ marginTop: '.55rem' }}>{program.title}</div>
                                    <div className="sup-muted" style={{ marginTop: '.25rem' }}>{program.schedule}</div>
                                    <div className="sup-muted">{program.provider} · {program.mode}</div>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </section>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
