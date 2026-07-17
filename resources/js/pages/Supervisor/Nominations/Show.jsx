import { Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Initials, PageHero, Panel, StatusPill, SupervisorStyles } from '../Shared';

export default function Show({ nomination }) {
    const [decision, setDecision] = useState(nomination.status);
    const [remarks, setRemarks] = useState('');
    const [saved, setSaved] = useState(false);

    const savePrototype = () => {
        setSaved(true);
        window.setTimeout(() => setSaved(false), 2500);
    };

    return (
        <AppLayout title="Nomination Detail" description="Supervisor / Nominations / Prototype">
            <div className="sup-page">
                <PageHero kicker="NOMINATION REVIEW" title={nomination.training_title} description="Review the employee-program match, expected workplace output, and endorsement details before forwarding the nomination." href="/supervisor/nominations" action="Back to nominations" icon="bi-arrow-left" />
                {saved && <div className="sup-success"><i className="bi bi-check-circle-fill" />Prototype decision updated on this screen. Database persistence will be enabled with the nomination workflow table.</div>}
                <section className="sup-grid-2">
                    <Panel title="Nominee Profile" subtitle="Employee selected for this opportunity">
                        <div style={{ display: 'flex', alignItems: 'center', gap: '.8rem', marginBottom: '1rem' }}><Initials name={nomination.employee_name} /><div><div className="sup-title">{nomination.employee_name}</div><div className="sup-muted">{nomination.position} · {nomination.employee_id}</div></div></div>
                        <div className="sup-list">
                            <div className="sup-list-item"><div className="sup-muted">Program</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{nomination.training_title}</div></div>
                            <div className="sup-list-item"><div className="sup-row"><div><div className="sup-muted">Schedule</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{nomination.schedule}</div></div><StatusPill>{nomination.mode}</StatusPill></div></div>
                            <div className="sup-list-item"><div className="sup-muted">Provider</div><div className="sup-title" style={{ marginTop: '.25rem' }}>{nomination.provider}</div></div>
                        </div>
                    </Panel>
                    <Panel title="Development Rationale" subtitle="Supervisor planning notes">
                        <div className="sup-list">
                            <div><div className="sup-muted">JUSTIFICATION</div><p className="sup-copy">{nomination.justification}</p></div>
                            <div><div className="sup-muted">EXPECTED WORKPLACE OUTPUT</div><p className="sup-copy">{nomination.expected_output}</p></div>
                        </div>
                    </Panel>
                </section>
                <Panel title="Supervisor Decision" subtitle="Static prototype control">
                    <div className="sup-form-grid">
                        <div className="sup-field"><label>Decision</label><select value={decision} onChange={(event) => setDecision(event.target.value)}><option value="draft">Keep as draft</option><option value="for endorsement">For endorsement</option><option value="endorsed">Endorsed</option></select></div>
                        <div className="sup-field"><label>Current state</label><div style={{ padding: '.65rem 0' }}><StatusPill tone={decision === 'endorsed' ? 'success' : 'warning'}>{decision}</StatusPill></div></div>
                        <div className="sup-field full"><label>Supervisor remarks</label><textarea rows="4" value={remarks} onChange={(event) => setRemarks(event.target.value)} placeholder="Record selection rationale, conditions, or scheduling notes." /></div>
                    </div>
                    <div style={{ display: 'flex', gap: '.6rem', marginTop: '.8rem', flexWrap: 'wrap' }}><button className="sup-button" type="button" onClick={savePrototype}><i className="bi bi-save" />Save prototype decision</button><Link href="/supervisor/nominations" className="sup-button secondary">Cancel</Link></div>
                </Panel>
            </div>
            <SupervisorStyles />
        </AppLayout>
    );
}
