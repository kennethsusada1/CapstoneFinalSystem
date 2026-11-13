import { Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatusPill } from '../Shared';

export default function Index({ recommendations, notifications, programs }) {
    const [showAllNotices, setShowAllNotices] = useState(false);
    const noticeItems = notifications ?? [];
    const visibleNotices = showAllNotices ? noticeItems : noticeItems.slice(0, 3);

    return (
        <AppLayout title="Training Recommendations" description="Employee / LNA Gap Analysis">
            <div className="emp-page">
                <PageHero kicker="RECOMMENDATIONS" title="Training for your learning needs." description="Review your supervisor-endorsed recommendations and available programs." href="/employee/training-applications" action="Apply for training" icon="bi-journal-arrow-up" />
                <section>
                    <Panel
                        title="Training Notices"
                        subtitle="Current reminders and required actions"
                        action={noticeItems.length > 3 && <button className="emp-link" type="button" onClick={() => setShowAllNotices((visible) => !visible)} aria-expanded={showAllNotices} style={{ border: 0, padding: 0, background: 'transparent', cursor: 'pointer' }}>{showAllNotices ? 'Show fewer' : `Show all (${noticeItems.length})`}</button>}
                    >
                        <div className="emp-list">
                            {noticeItems.length === 0 && <EmptyState icon="bi-bell" title="No notices yet" text="Complete your LNA assessment to begin your learning path." />}
                            {visibleNotices.map((item, index) => <div className="emp-item" key={`${item.title}-${index}`}>
                                <div className="emp-row"><StatusPill tone={item.type === 'warning' ? 'warning' : item.type === 'notice' ? 'success' : 'info'}>{item.title}</StatusPill><i className="bi bi-bell emp-muted" /></div>
                                <div className="emp-copy" style={{ marginTop: '.5rem' }}>{item.message}</div>
                            </div>)}
                        </div>
                    </Panel>
                </section>
                <Panel title="Supervisor-Reviewed Recommendations" subtitle="Actionable recommendations generated after LNA review">
                    <div className="emp-grid-3">
                        {(recommendations ?? []).length === 0 && <EmptyState icon="bi-lightbulb" title="No training recommendations yet" text="Recommendations appear when your assigned supervisor reviews an LNA and confirms a training need." />}
                        {(recommendations ?? []).map((item) => <div className="emp-item" key={`${item.lna_id}-${item.predictive_training_recommendation}`}>
                            <div className="emp-row"><StatusPill>{item.track}</StatusPill><StatusPill tone={item.has_application ? 'success' : 'warning'}>{item.has_application ? 'Applied' : 'Available'}</StatusPill></div>
                            <div className="emp-muted" style={{ marginTop: '.65rem' }}>IDENTIFIED SKILLS GAP</div><div className="emp-copy" style={{ marginTop: '.25rem' }}>{item.prescriptive_skills_gap}</div>
                            <div className="emp-muted" style={{ marginTop: '.7rem' }}>PREDICTED TRAINING RECOMMENDATION</div><div className="emp-title" style={{ marginTop: '.25rem', fontSize: '.84rem' }}>{item.predictive_training_recommendation}</div>
                            <div className="emp-muted" style={{ marginTop: '.35rem' }}>{item.provider || 'Provider to be confirmed'} / {item.training_type}</div>
                            {!item.has_application && <Link className="emp-button" style={{ marginTop: '.7rem' }} href="/employee/training-applications"><i className="bi bi-send" />Apply now</Link>}
                        </div>)}
                    </div>
                </Panel>
                <Panel title="Published Training Programs" subtitle="Current offerings maintained by the Secretariat">
                    <div className="emp-grid-3">{(programs ?? []).length === 0 && <EmptyState icon="bi-calendar2-x" title="No current offerings" text="Published programs with open seats will appear here." />}{(programs ?? []).map((program) => <div className="emp-item" key={program.id}><div className="emp-row"><StatusPill>{program.category}</StatusPill><StatusPill tone="success">{program.remaining_slots} seats</StatusPill></div><div className="emp-title" style={{ marginTop: '.65rem' }}>{program.title}</div><div className="emp-copy" style={{ marginTop: '.35rem' }}>{program.provider || 'Provider to be confirmed'}</div><div className="emp-muted" style={{ marginTop: '.25rem' }}>{program.start_date || 'Schedule to be confirmed'}{program.end_date ? ` to ${program.end_date}` : ''} · {program.delivery_mode}{program.venue ? ` · ${program.venue}` : ''}</div></div>)}</div>
                </Panel>
            </div>
            <EmployeeStyles />
        </AppLayout>
    );
}
