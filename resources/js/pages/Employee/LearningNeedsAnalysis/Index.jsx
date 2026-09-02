import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { EmployeeStyles, EmptyState, PageHero, Panel, StatusPill } from '../Shared';

const skillGroups = [
    {
        title: 'Functional Competencies',
        items: ['Communication Skills', 'Public Speaking', 'Negotiation Skills', 'Presentation Skills', 'Technical Writing', 'Active Listening'],
    },
    {
        title: 'Interpersonal Competencies',
        items: ['Teamwork', 'Behavioral Management', 'Client-relationship Management'],
    },
    {
        title: 'Ethical and Professional Competencies',
        items: ['Integrity and Honesty', 'Adherence to legal and regulatory requirements', 'Professionalism'],
    },
    {
        title: 'Leadership Competencies',
        items: ['Team Management', 'Conflict Resolution', 'Delegation', 'Motivation and Coaching'],
    },
    {
        title: 'Adaptability Competencies',
        items: ['Flexibility', 'Resilience', 'Stress Management'],
    },
    {
        title: 'Problem Solving and Decision Making',
        items: ['Analytical Thinking', 'Creative Thinking', 'Critical Thinking', 'Troubleshooting', 'Innovation'],
    },
    {
        title: 'Organizational Competencies',
        items: ['Time Management', 'Multitasking', 'Compliance and Risk Awareness', 'Resource Management', 'Strategic Planning', 'Project Planning and Scheduling'],
    },
];

const learningMethods = [
    ['Mentorship/Coaching', 'Mentorship / Coaching'],
    ['Self-paced Learning', 'Self-paced Learning (e-learning, reading materials)'],
    ['Workshops/Seminars/Trainings', 'Workshops / Seminars / Trainings'],
    ['Others', 'Others'],
];

const assessmentMethods = [
    'Employee Self-Assessment',
    'Questionnaire',
    'Feedback',
    'Observation',
    'Reflection',
    'Customer Feedback',
    'Performance Review',
    'Performance Evaluation (MPOR)',
];

const emptySkills = Object.fromEntries(skillGroups.flatMap((group) => group.items.map((skill) => [skill, 'N/A'])));

function FieldError({ error }) {
    return error ? <span className="emp-error">{error}</span> : null;
}

function CheckboxList({ items, selected, onChange, namePrefix }) {
    const toggle = (value) => {
        onChange(selected.includes(value) ? selected.filter((item) => item !== value) : [...selected, value]);
    };

    return (
        <div className="lna-checkbox-list">
            {items.map(([value, label]) => (
                <label key={value} className="lna-check">
                    <input name={namePrefix} type="checkbox" checked={selected.includes(value)} onChange={() => toggle(value)} />
                    <span>{label}</span>
                </label>
            ))}
        </div>
    );
}

export default function Index({ lnaEntries, employeeProfile }) {
    const { props } = usePage();
    const form = useForm({
        workbook_form: true,
        core_functions: ['', '', '', '', '', ''],
        support_functions: ['', '', '', ''],
        skill_assessments: emptySkills,
        preferred_learning_methods: [],
        preferred_learning_methods_other: '',
        assessment_methods: ['Employee Self-Assessment'],
        employee_signature: employeeProfile?.name ?? '',
    });

    const setListValue = (field, index, value) => {
        const next = [...form.data[field]];
        next[index] = value;
        form.setData(field, next);
    };

    const setSkillRating = (skill, value) => {
        form.setData('skill_assessments', { ...form.data.skill_assessments, [skill]: value });
    };

    const submit = (event) => {
        event.preventDefault();
        form.post('/employee/learning-needs-analysis', { preserveScroll: true, onSuccess: () => form.reset('core_functions', 'support_functions', 'skill_assessments', 'preferred_learning_methods', 'preferred_learning_methods_other', 'assessment_methods') });
    };

    return (
        <AppLayout title="Learning Needs Analysis" description="Employee / Learning Needs Analysis">
            <div className="emp-page">
                <PageHero
                    kicker="LEARNING NEEDS ANALYSIS"
                    title="Tell us what support will help you perform at your best."
                    description="Complete the employee sections of the official LNA form. Supervisor recommendations are handled separately and are not part of this submission."
                    href="/employee/recommendations"
                    action="View training recommendations"
                    icon="bi-lightbulb"
                />

                {props?.flash?.success && <div className="emp-success"><i className="bi bi-check-circle-fill" />{props.flash.success}</div>}

                <Panel title="Employee Information" subtitle="These details are shown on your LNA form">
                    <div className="lna-profile-grid">
                        <div><span>Name</span><strong>{employeeProfile?.name || 'Not available'}</strong></div>
                        <div><span>Position</span><strong>{employeeProfile?.position || 'Not available'}</strong></div>
                        <div><span>Department</span><strong>{employeeProfile?.department || 'Not available'}</strong></div>
                        <div><span>Employee ID</span><strong>{employeeProfile?.employee_id || 'Not available'}</strong></div>
                        <div><span>Date</span><strong>{new Date().toLocaleDateString('en-CA')}</strong></div>
                    </div>
                </Panel>

                <form onSubmit={submit} className="lna-workbook-form">
                    <Panel title="1. Current Job Responsibilities" subtitle="List your current core and support functions.">
                        <div className="lna-responsibility-grid">
                            <div>
                                <h3>Core Functions</h3>
                                {form.data.core_functions.map((value, index) => <div className="lna-numbered-field" key={`core-${index}`}><span>{index + 1}</span><input value={value} onChange={(event) => setListValue('core_functions', index, event.target.value)} placeholder="Describe a core function" /></div>)}
                                <FieldError error={form.errors['core_functions.0']} />
                            </div>
                            <div>
                                <h3>Support Functions</h3>
                                {form.data.support_functions.map((value, index) => <div className="lna-numbered-field" key={`support-${index}`}><span>{index + 1}</span><input value={value} onChange={(event) => setListValue('support_functions', index, event.target.value)} placeholder="Describe a support function" /></div>)}
                                <FieldError error={form.errors['support_functions.0']} />
                            </div>
                        </div>
                    </Panel>

                    <Panel title="2. Skills Assessment" subtitle="Rate your current skill level: 1 = Not Demonstrated, 2 = Basic, 3 = Intermediate, 4 = Advance, N/A = Not Applicable.">
                        <div className="lna-rating-key"><span>N/A</span><span>1</span><span>2</span><span>3</span><span>4</span></div>
                        <div className="lna-skills-table">
                            {skillGroups.map((group) => <div key={group.title} className="lna-skill-group"><h3>{group.title}</h3>{group.items.map((skill) => <div className="lna-skill-row" key={skill}><strong>{skill}</strong><select aria-label={`${skill} employee assessment`} value={form.data.skill_assessments[skill]} onChange={(event) => setSkillRating(skill, event.target.value)}><option value="N/A">N/A</option><option value="1">1 - Not Demonstrated</option><option value="2">2 - Basic</option><option value="3">3 - Intermediate</option><option value="4">4 - Advance</option></select></div>)}</div>)}
                        </div>
                        <FieldError error={form.errors.skill_assessments} />
                    </Panel>

                    <Panel title="3. Preferred Learning Methods" subtitle="Select the ways you prefer to learn.">
                        <CheckboxList items={learningMethods} selected={form.data.preferred_learning_methods} onChange={(value) => form.setData('preferred_learning_methods', value)} namePrefix="preferred_learning_methods" />
                        {form.data.preferred_learning_methods.includes('Others') && <div className="lna-other-field"><label htmlFor="preferred_learning_methods_other">Please specify other method</label><input id="preferred_learning_methods_other" value={form.data.preferred_learning_methods_other} onChange={(event) => form.setData('preferred_learning_methods_other', event.target.value)} /><FieldError error={form.errors.preferred_learning_methods_other} /></div>}
                    </Panel>

                    <Panel title="4. Assessment Methods" subtitle="Select the methods that may support your employee self-assessment. Supervisor assessment is intentionally excluded.">
                        <CheckboxList items={assessmentMethods.map((method) => [method, method])} selected={form.data.assessment_methods} onChange={(value) => form.setData('assessment_methods', value)} namePrefix="assessment_methods" />
                    </Panel>

                    <Panel title="Employee Confirmation" subtitle="Confirm the employee portion of this LNA before submitting.">
                        <div className="lna-signature-field"><label htmlFor="employee_signature">Employee Signature / Typed Name</label><input id="employee_signature" value={form.data.employee_signature} onChange={(event) => form.setData('employee_signature', event.target.value)} placeholder="Type your name as your signature" /><FieldError error={form.errors.employee_signature} /></div>
                        <div className="lna-submit-row"><span>Your responses will be submitted for the next workflow step.</span><button className="emp-button" disabled={form.processing}><i className="bi bi-send-check" />{form.processing ? 'Submitting...' : 'Submit Employee LNA'}</button></div>
                    </Panel>
                </form>

                <Panel title="Submission History" subtitle="Your completed employee LNA submissions">
                    <div className="emp-table-wrap"><table className="emp-table"><thead><tr><th>Date</th><th>IPCR Rating</th><th>Preferred Learning</th><th>Status</th></tr></thead><tbody>{(lnaEntries ?? []).map((item) => <tr key={item.id}><td>{item.submitted_on || 'Draft'}</td><td>{item.ipcr_rating || 'Not provided'}</td><td>{(item.preferred_learning_methods ?? []).join(', ') || 'Not provided'}</td><td><StatusPill tone={item.status === 'returned' ? 'danger' : item.status === 'reviewed' ? 'success' : 'info'}>{item.status}</StatusPill></td></tr>)}</tbody></table>{(lnaEntries ?? []).length === 0 && <EmptyState title="No LNA submissions" text="Your completed employee forms will be listed here." />}</div>
                </Panel>
            </div>
            <EmployeeStyles />
            <style>{`
                .lna-workbook-form { display: grid; gap: 1rem; }
                .lna-profile-grid, .lna-responsibility-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; }
                .lna-profile-grid > div { display: grid; gap: .22rem; padding: .72rem .8rem; border: 1px solid var(--admin-border); border-radius: 11px; background: rgba(45,212,191,.025); }
                .lna-profile-grid span, .lna-profile-grid label, .lna-responsibility-grid h3, .lna-signature-field label, .lna-other-field label { color: var(--admin-text-muted); font-size: .67rem; font-weight: 700; }
                .lna-profile-grid strong { color: var(--admin-text-primary); font-size: .76rem; }
                .lna-signature-field input, .lna-numbered-field input, .lna-other-field input { width: 100%; padding: .62rem .7rem; color: var(--admin-text-primary); border: 1px solid var(--admin-border-strong); border-radius: 10px; background: var(--admin-bg-secondary); outline: none; font: inherit; font-size: .74rem; }
                .lna-responsibility-grid h3 { margin: 0 0 .55rem; color: var(--admin-text-primary); font-size: .78rem; }.lna-responsibility-grid > div { display: grid; gap: .5rem; }.lna-numbered-field { display: grid; grid-template-columns: 26px 1fr; align-items: center; gap: .45rem; }.lna-numbered-field > span { display: grid; width: 25px; height: 25px; place-items: center; color: #99f6e4; border-radius: 8px; background: rgba(45,212,191,.1); font-size: .68rem; font-weight: 800; }
                .lna-rating-key { display: flex; justify-content: flex-end; gap: .45rem; margin-bottom: .55rem; }.lna-rating-key span { display: grid; width: 30px; height: 25px; place-items: center; color: var(--admin-text-muted); border: 1px solid var(--admin-border); border-radius: 7px; font-size: .66rem; font-weight: 750; }
                .lna-skills-table { display: grid; gap: .65rem; }.lna-skill-group { overflow: hidden; border: 1px solid var(--admin-border); border-radius: 12px; }.lna-skill-group h3 { margin: 0; padding: .62rem .75rem; color: #99f6e4; background: rgba(45,212,191,.07); font-size: .72rem; }.lna-skill-row { display: grid; grid-template-columns: 1fr 190px; align-items: center; gap: .8rem; padding: .48rem .75rem; border-top: 1px solid var(--admin-border); }.lna-skill-row strong { color: var(--admin-text-secondary); font-size: .72rem; font-weight: 600; }.lna-skill-row select { padding: .48rem .55rem; color: var(--admin-text-primary); border: 1px solid var(--admin-border-strong); border-radius: 8px; background: var(--admin-bg-secondary); font-size: .7rem; }
                .lna-checkbox-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; }.lna-check { display: flex; align-items: flex-start; gap: .45rem; color: var(--admin-text-secondary); font-size: .74rem; line-height: 1.4; }.lna-check input { margin-top: .15rem; accent-color: #14b8a6; }.lna-other-field, .lna-signature-field { display: grid; gap: .35rem; margin-top: .75rem; max-width: 520px; }.lna-submit-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1rem; padding-top: .85rem; border-top: 1px dashed var(--admin-border-strong); }.lna-submit-row > span { color: var(--admin-text-muted); font-size: .7rem; }
                @media (max-width: 700px) { .lna-profile-grid, .lna-responsibility-grid, .lna-checkbox-list { grid-template-columns: 1fr; }.lna-skill-row { grid-template-columns: 1fr; gap: .35rem; }.lna-skill-row select { width: 100%; }.lna-submit-row { align-items: stretch; flex-direction: column; }.lna-submit-row .emp-button { width: 100%; } }
            `}</style>
        </AppLayout>
    );
}
