import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatCard, StatusPill } from '../Shared';

export default function Index({ history }) {
    const completed = (history ?? []).filter((item) => item.status === 'completed').length;
    return <AppLayout title="Learning History" description="Employee / Completed Activities"><div className="emp-page">
        <PageHero kicker="LEARNING RECORD" title="Your completed development journey, in one place." description="Review attended programs, completion dates, and progress records that support your Learning Action Plan submissions." href="/employee/learning-action-plan" action="Submit a LAP" icon="bi-journal-check" />
        <section className="emp-grid-3"><StatCard label="History Records" value={(history ?? []).length} icon="bi-clock-history" /><StatCard label="Completed" value={completed} icon="bi-patch-check-fill" color="#4ade80" /><StatCard label="Attendance Records" value={(history ?? []).length - completed} icon="bi-person-check-fill" color="#fb923c" /></section>
        <Panel title="Training Attendance and Completion" subtitle="Verified learning activities"><div className="emp-list">{(history ?? []).length === 0 && <EmptyState icon="bi-clock-history" title="No learning history yet" text="Attended and completed training activities will appear here." />}{(history ?? []).map((item) => <Link href={`/employee/training-applications/${item.id}`} className="emp-item" style={{ textDecoration: 'none' }} key={item.id}><div className="emp-row"><div><div className="emp-title">{item.training_title}</div><div className="emp-muted" style={{ marginTop: '.25rem' }}>{item.training_type} | {item.completed_on ? `Completed on ${item.completed_on}` : 'Attendance recorded'}</div></div><StatusPill tone={item.status === 'completed' ? 'success' : 'warning'}>{item.status}</StatusPill></div><div className="emp-progress" style={{ marginTop: '.7rem' }}><span style={{ width: `${item.progress_percent}%` }} /></div><div className="emp-muted" style={{ marginTop: '.3rem' }}>Recorded progress: {item.progress_percent}%</div></Link>)}</div></Panel>
    </div><EmployeeStyles /></AppLayout>;
}
