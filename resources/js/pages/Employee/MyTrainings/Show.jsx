import AppLayout from '@/layouts/AppLayout';
import { EmployeeStyles, PageHero, Panel, StatCard, StatusPill } from '../Shared';

export default function Show({ training }) {
    const tone = training.status === 'completed' ? 'success' : training.status === 'ongoing' ? 'warning' : 'info';
    return <AppLayout title="Training Detail" description="Employee / Application Record"><div className="emp-page">
        <PageHero kicker="APPLICATION RECORD" title={training.training_title} description="Review the full request, processing decision, schedule, and completion information for this training activity." href="/employee/training-applications" action="Back to applications" icon="bi-arrow-left" />
        <section className="emp-grid-3"><StatCard label="Progress" value={`${training.progress_percent}%`} icon="bi-activity" /><StatCard label="Status" value={training.status} icon="bi-signpost-split-fill" color="#facc15" /><StatCard label="Attendance" value={training.is_attended ? 'Recorded' : 'Pending'} icon="bi-person-check-fill" color="#4ade80" /></section>
        <section className="emp-grid-2">
            <Panel title="Training Information" subtitle="Program and schedule"><div className="emp-list"><div className="emp-item"><div className="emp-muted">TRAINING TYPE</div><div className="emp-title" style={{ marginTop: '.25rem' }}>{training.training_type}</div></div><div className="emp-item"><div className="emp-muted">PROVIDER / ORGANIZER</div><div className="emp-title" style={{ marginTop: '.25rem' }}>{training.provider || 'To be confirmed'}</div></div><div className="emp-item"><div className="emp-muted">SCHEDULE</div><div className="emp-title" style={{ marginTop: '.25rem' }}>{training.start_date || 'TBA'}{training.end_date ? ` to ${training.end_date}` : ''}</div></div><div className="emp-item"><div className="emp-muted">OFFICE</div><div className="emp-title" style={{ marginTop: '.25rem' }}>{training.office || 'Not specified'}</div></div></div></Panel>
            <Panel title="Processing Status" subtitle="Secretariat decision and progress"><div className="emp-row"><StatusPill tone={tone}>{training.status}</StatusPill><span className="emp-muted">{training.processed_at || 'Awaiting processing'}</span></div><div className="emp-progress" style={{ marginTop: '.8rem' }}><span style={{ width: `${training.progress_percent}%` }} /></div><div className="emp-copy" style={{ marginTop: '.8rem' }}>{training.process_remarks || 'No processing remarks have been added yet.'}</div>{training.completed_on && <div className="emp-item" style={{ marginTop: '.8rem' }}><div className="emp-muted">COMPLETED ON</div><div className="emp-title" style={{ marginTop: '.25rem' }}>{training.completed_on}</div></div>}</Panel>
        </section>
    </div><EmployeeStyles /></AppLayout>;
}
