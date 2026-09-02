import AppLayout from '@/layouts/AppLayout';
import { AdminStyles, EmptyState, PageHero, Panel, StatCard } from './Shared';

function Trend({ data = [] }) {
    const width = 640, height = 210, pad = 28;
    const max = Math.max(1, ...data.flatMap((x) => [x.employees, x.accounts, x.trainings]));
    const x = (i) => pad + (i * (width - pad * 2)) / Math.max(1, data.length - 1);
    const y = (v) => height - pad - (v / max) * (height - pad * 2);
    const path = (key) => data.map((item, i) => `${i ? 'L' : 'M'} ${x(i)} ${y(item[key])}`).join(' ');
    return data.length === 0 ? <EmptyState title="No activity data" text="System activity will appear here." /> : <div style={{ overflowX: 'auto' }}><svg viewBox={`0 0 ${width} ${height}`} style={{ width: '100%', minWidth: 520, height: 220 }}>{[0,1,2,3].map((row) => <line key={row} x1={pad} x2={width-pad} y1={pad+((height-pad*2)/3)*row} y2={pad+((height-pad*2)/3)*row} stroke="rgba(148,163,184,.13)" />)}{[['employees','#60a5fa'],['accounts','#34d399'],['trainings','#fbbf24']].map(([key,color]) => <path key={key} d={path(key)} fill="none" stroke={color} strokeWidth="3" strokeLinecap="round" />)}{data.map((item,i) => <text key={item.label} x={x(i)} y={height-5} textAnchor="middle" fill="var(--admin-text-muted)" fontSize="10">{item.label}</text>)}</svg></div>;
}

function Bars({ data = [], colors = 'linear-gradient(90deg,#2563eb,#f59e0b)' }) {
    const max = Math.max(1, ...data.map((x) => x.value));
    return <div className="sys-list">{data.map((item) => <div key={item.label}><div className="sys-row" style={{ marginBottom: '.35rem' }}><span className="sys-copy" style={{ textTransform: 'capitalize' }}>{item.label.replace('-', ' ')}</span><strong className="sys-title">{item.value}</strong></div><div className="sys-progress"><span style={{ width: `${(item.value/max)*100}%`, background: colors }} /></div></div>)}</div>;
}

export default function Dashboard({ stats, charts }) {
    return <AppLayout title="Admin Dashboard" description="System Administrator / Control Center"><div className="sys-page">
        <PageHero kicker="SYSTEM CONTROL CENTER" title="Keep every learning workflow visible and accountable." description="Monitor employee records, account readiness, and organization-wide learning activity from one administrative workspace." href="/admin/users" action="Manage user accounts" icon="bi-people-fill" />
        <section className="sys-stats"><StatCard label="Employee Records" value={stats?.employeeRecords ?? 0} icon="bi-person-vcard-fill" color="#60a5fa" /><StatCard label="User Accounts" value={stats?.userAccounts ?? 0} icon="bi-people-fill" /><StatCard label="Pending Activations" value={stats?.activationPending ?? 0} icon="bi-envelope-exclamation-fill" color="#fb923c" /><StatCard label="LNA Submissions" value={stats?.lnaSubmissions ?? 0} icon="bi-ui-checks-grid" color="#38bdf8" /><StatCard label="Trainings Attended" value={stats?.trainingAttended ?? 0} icon="bi-mortarboard-fill" color="#a78bfa" /><StatCard label="Completed LAP" value={stats?.lapCompleted ?? 0} icon="bi-journal-check" color="#4ade80" /></section>
        <Panel title="7-Day Activity Trend" subtitle="Employee imports, account creation, and training applications"><Trend data={charts?.activityTrend} /><div className="sys-row" style={{ justifyContent: 'flex-start', flexWrap: 'wrap' }}><span className="sys-copy" style={{ color: '#60a5fa' }}>Employee Records</span><span className="sys-copy" style={{ color: '#34d399' }}>User Accounts</span><span className="sys-copy" style={{ color: '#fbbf24' }}>Training Applications</span></div></Panel>
        <section className="sys-grid-2"><Panel title="Role Distribution" subtitle="Accounts grouped by access role"><Bars data={charts?.roleDistribution} /></Panel><Panel title="Account Activation" subtitle="Verified versus pending accounts"><Bars data={charts?.accountStatus} colors="linear-gradient(90deg,#22c55e,#facc15)" /></Panel></section>
        <section className="sys-grid-2"><Panel title="Learning Records" subtitle="Current volume across the L&D lifecycle"><Bars data={charts?.learningOverview} colors="linear-gradient(90deg,#38bdf8,#a78bfa)" /></Panel><Panel title="Training Status" subtitle="Application pipeline distribution"><Bars data={charts?.trainingStatus} colors="linear-gradient(90deg,#f59e0b,#22c55e)" /></Panel></section>
        <Panel title="Top Office Distribution" subtitle="Employee record concentration by office"><Bars data={charts?.officeDistribution} /></Panel>
    </div><AdminStyles /></AppLayout>;
}
