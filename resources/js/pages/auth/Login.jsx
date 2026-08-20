import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';

function Field({ label, icon, error, children }) {
    return (
        <div style={{ marginBottom: '1rem' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.35rem', fontSize: '0.82rem', fontWeight: 600, color: 'var(--admin-text-secondary)', marginBottom: '0.4rem' }}>
                {icon && <span style={{ color: 'var(--admin-accent)', display: 'flex' }}>{icon}</span>}
                {label}
            </label>
            {children}
            {error && <p style={{ fontSize: '0.78rem', color: '#ef4444', marginTop: '0.3rem' }}>{error}</p>}
        </div>
    );
}

const inputStyle = (hasError = false) => ({
    width: '100%',
    padding: '0.6rem 0.85rem',
    fontSize: '0.9rem',
    background: 'var(--admin-bg-secondary)',
    color: 'var(--admin-text-primary)',
    border: `1px solid ${hasError ? '#ef4444' : 'var(--admin-border-strong)'}`,
    borderRadius: 'var(--admin-radius)',
    transition: 'border-color 0.15s, box-shadow 0.15s',
});

const primaryBtn = {
    width: '100%',
    padding: '0.7rem',
    fontSize: '0.92rem',
    fontWeight: 600,
    background: 'var(--admin-accent)',
    color: '#fff',
    border: 'none',
    borderRadius: 'var(--admin-radius)',
    cursor: 'pointer',
    transition: 'opacity 0.15s',
};

const ghostBtn = {
    width: '100%',
    padding: '0.65rem',
    fontSize: '0.88rem',
    fontWeight: 500,
    background: 'transparent',
    color: 'var(--admin-text-secondary)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 'var(--admin-radius)',
    cursor: 'pointer',
};

const outlineBtn = {
    ...ghostBtn,
    color: 'var(--admin-accent)',
    borderColor: 'var(--admin-accent)',
};

const alertBaseStyle = {
    borderRadius: 14,
    padding: '0.9rem 1rem',
    marginBottom: '1rem',
    fontSize: '0.84rem',
    lineHeight: 1.6,
    border: '1px solid transparent',
};

const slides = ['/slides/1.png', '/slides/2.png', '/slides/3.png'];

export default function Login({ canResetPassword = true, status, mode: initialMode = 'login', token = '', email = '' }) {
    const page = usePage();
    const params = new URLSearchParams(page.url.split('?')[1] || '');
    const queryMode = params.get('mode');
    const queryToken = params.get('token') || token;
    const queryEmail = params.get('email') || email;
    const queryEmployeeId = params.get('employee_id') || '';
    const resolvedMode = useMemo(() => {
        if (queryToken && queryEmail) {
            return 'activate-complete';
        }

        return queryMode || initialMode || 'login';
    }, [initialMode, queryEmail, queryMode, queryToken]);
    const [mode, setMode] = useState(resolvedMode);
    const [darkMode, setDarkMode] = useState(() => (localStorage.getItem('theme') ?? 'dark') === 'dark');
    const [activeSlide, setActiveSlide] = useState(0);
    const loginForm = useForm({ email: '', password: '', remember: false });
    const verifyForm = useForm({ employee_id: queryEmployeeId });
    const completeForm = useForm({ token: queryToken, email: queryEmail, name: '', address: '', office: '', password: '', password_confirmation: '', photo: null });
    const forgotForm = useForm({ email: queryEmail });
    const { setData: setVerifyFormData } = verifyForm;
    const { setData: setCompleteFormData } = completeForm;
    const { setData: setForgotFormData } = forgotForm;

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light');
        localStorage.setItem('theme', darkMode ? 'dark' : 'light');
    }, [darkMode]);

    useEffect(() => {
        setMode(resolvedMode);
    }, [resolvedMode]);

    useEffect(() => {
        setVerifyFormData('employee_id', queryEmployeeId);
        setCompleteFormData((current) => ({ ...current, token: queryToken, email: queryEmail }));
        setForgotFormData('email', queryEmail);
    }, [queryEmail, queryEmployeeId, queryToken, setCompleteFormData, setForgotFormData, setVerifyFormData]);

    useEffect(() => {
        const interval = setInterval(() => setActiveSlide((index) => (index + 1) % slides.length), 6000);
        return () => clearInterval(interval);
    }, []);

    const meta = useMemo(() => ({
        login: { title: 'Sign in', subtitle: 'Enter your credentials to continue' },
        'activate-verify': { title: 'Activate account', subtitle: 'Enter your employee ID to continue' },
        'activate-complete': { title: 'Complete activation', subtitle: 'Verify your account details, upload your photo, and create a secure password' },
        forgot: { title: 'Forgot password', subtitle: 'We will help you recover your account access' },
    }[mode] ?? { title: 'Sign in', subtitle: 'Enter your credentials to continue' }), [mode]);

    const submit = (event) => {
        event.preventDefault();
        if (mode === 'login') {
            loginForm.post('/login', { resetOnSuccess: ['password'] });
            return;
        }
        if (mode === 'activate-verify') {
            verifyForm.post('/send/id');
            return;
        }
        if (mode === 'activate-complete') {
            completeForm.post('/activate/complete');
            return;
        }
        forgotForm.post('/forgot-password');
    };

    const errors =
        mode === 'activate-verify'
            ? verifyForm.errors
            : mode === 'activate-complete'
                ? completeForm.errors
                : mode === 'forgot'
                    ? forgotForm.errors
                    : loginForm.errors;

    const errorMessages = Object.values(errors || {}).filter(Boolean);

    const activationErrorTitle =
        mode === 'activate-verify'
            ? 'Verification failed'
            : mode === 'activate-complete'
                ? 'Activation could not be completed'
                : 'Please check your input';

    return (
        <GuestLayout>
            <Head title={meta.title} />
            <div style={{ position: 'fixed', top: 18, right: 18, zIndex: 10 }}>
                <button type="button" onClick={() => setDarkMode((value) => !value)} style={{ width: 48, height: 32, borderRadius: 999, border: '1px solid var(--admin-border)', background: 'var(--admin-card)', color: 'var(--admin-text-secondary)', cursor: 'pointer' }}>
                    <i className={`bi ${darkMode ? 'bi-sun' : 'bi-moon-stars'}`} />
                </button>
            </div>
            <div className="auth-page-shell" style={{ minHeight: '100vh', display: 'grid', gridTemplateColumns: 'minmax(0, 1.05fr) minmax(0, 1fr)' }}>
                <div className="auth-hero" style={{ position: 'relative', minHeight: '100vh', overflow: 'hidden' }}>
                    {slides.map((slide, index) => (
                        <div key={slide} className={`auth-split-slide${activeSlide === index ? ' is-active' : ''}`}>
                            <img src={slide} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover', transition: 'transform 6s ease' }} />
                        </div>
                    ))}
                    <div className="auth-split-slideshow-overlay" />
                    <div style={{ position: 'relative', zIndex: 2, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', minHeight: '100vh', padding: '2rem 3rem', color: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.9rem' }}>
                            <img src="/images/ld-logo.png" alt="Smart L&D" style={{ width: 42, height: 42, borderRadius: 14 }} />
                            <div style={{ fontSize: '1.1rem', fontWeight: 800 }}>Smart L&amp;D</div>
                        </div>
                        <div style={{ maxWidth: 420 }}>
                            <div style={{ display: 'inline-flex', padding: '0.35rem 0.75rem', borderRadius: 10, border: '1px solid rgba(191,219,254,0.35)', background: 'rgba(37,99,235,0.12)', fontSize: '0.76rem', fontWeight: 700, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '1.25rem' }}>Welcome back!</div>
                            <h1 style={{ fontSize: '3.5rem', lineHeight: 1.05, margin: 0, letterSpacing: '-0.04em' }}>Learning, made smarter.</h1>
                            <p style={{ fontSize: '1.05rem', color: 'rgba(255,255,255,0.82)', lineHeight: 1.75, marginTop: '1rem' }}>Manage training programs, development plans, and learning workflows with clarity and confidence.</p>
                            <div style={{ display: 'grid', gap: '1rem', marginTop: '2rem' }}>
                                {[
                                    ['bi-graph-up-arrow', 'Real-Time Insights', 'Track learning participation and progress across the organization.'],
                                    ['bi-people', 'Collaborative Reviews', 'Connect supervisors, secretariat, and HRDC in one workflow.'],
                                    ['bi-shield-check', 'Secure & Reliable', 'Role-based access keeps every learning record protected.'],
                                ].map(([icon, title, text]) => (
                                    <div key={title} style={{ display: 'flex', gap: '0.9rem' }}>
                                        <div style={{ width: 44, height: 44, borderRadius: 14, background: 'rgba(59,130,246,0.18)', border: '1px solid rgba(191,219,254,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                            <i className={`bi ${icon}`} />
                                        </div>
                                        <div>
                                            <div style={{ fontWeight: 700, fontSize: '1rem' }}>{title}</div>
                                            <div style={{ fontSize: '0.95rem', color: 'rgba(255,255,255,0.74)', lineHeight: 1.6 }}>{text}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div style={{ fontSize: '0.82rem', color: 'rgba(255,255,255,0.72)' }}>Ã‚Â© 2026 Smart L&amp;D. All rights reserved.</div>
                    </div>
                </div>
                <div className="auth-form-column" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '2rem 1.25rem' }}>
                    <div className="auth-form-card" style={{ width: '100%', maxWidth: 420, background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 'var(--admin-radius)', boxShadow: 'var(--admin-shadow)', padding: '2.5rem 2rem' }}>
                        <div style={{ marginBottom: '2rem', paddingBottom: '1.25rem', borderBottom: '1px solid var(--admin-border)' }}>
                            <h4 style={{ fontWeight: 700, fontSize: '1.5rem', color: 'var(--admin-text-primary)', letterSpacing: '-0.02em', margin: 0 }}>{meta.title}</h4>
                            <p style={{ fontSize: '0.875rem', color: 'var(--admin-text-muted)', marginTop: '0.5rem' }}>{meta.subtitle}</p>
                        </div>

                        {status && (
                            <div
                                style={{
                                    ...alertBaseStyle,
                                    background: 'rgba(16, 185, 129, 0.12)',
                                    borderColor: 'rgba(16, 185, 129, 0.28)',
                                    color: '#b7f7db',
                                }}
                            >
                                <div style={{ fontWeight: 700, marginBottom: '0.2rem' }}>Success</div>
                                <div>{status}</div>
                            </div>
                        )}

                        {errorMessages.length > 0 && (
                            <div
                                style={{
                                    ...alertBaseStyle,
                                    background: 'rgba(239, 68, 68, 0.12)',
                                    borderColor: 'rgba(239, 68, 68, 0.28)',
                                    color: '#fecaca',
                                }}
                            >
                                <div style={{ fontWeight: 700, marginBottom: '0.35rem' }}>{activationErrorTitle}</div>
                                <div style={{ display: 'grid', gap: '0.2rem' }}>
                                    {errorMessages.map((message, index) => (
                                        <div key={`${message}-${index}`}>{message}</div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <form onSubmit={submit}>
                            {mode === 'login' && (
                                <>
                                    <Field label="Email" icon={<i className="bi bi-person" />} error={loginForm.errors.email}>
                                        <input value={loginForm.data.email} onChange={(e) => loginForm.setData('email', e.target.value)} style={inputStyle(!!loginForm.errors.email)} type="email" placeholder="Your email" />
                                    </Field>
                                    <Field label="Password" icon={<i className="bi bi-lock" />} error={loginForm.errors.password}>
                                        <input value={loginForm.data.password} onChange={(e) => loginForm.setData('password', e.target.value)} style={inputStyle(!!loginForm.errors.password)} type="password" placeholder="Your password" />
                                    </Field>
                                </>
                            )}
                            {mode === 'activate-verify' && (
                                <>
                                    <Field label="Employee ID" icon={<i className="bi bi-person-vcard" />} error={verifyForm.errors.employee_id}>
                                        <input value={verifyForm.data.employee_id} onChange={(e) => verifyForm.setData('employee_id', e.target.value)} style={inputStyle(!!verifyForm.errors.employee_id)} type="text" placeholder="Employee ID" />
                                    </Field>
                                </>
                            )}
                            {mode === 'activate-complete' && (
                                <>
                                    <input type="hidden" value={completeForm.data.token} />
                                    <input type="hidden" value={completeForm.data.email} />
                                    <div
                                        style={{
                                            ...alertBaseStyle,
                                            background: 'rgba(37, 99, 235, 0.12)',
                                            borderColor: 'rgba(37, 99, 235, 0.24)',
                                            color: 'var(--admin-text-primary)',
                                        }}
                                    >
                                        <div style={{ fontWeight: 700, marginBottom: '0.2rem' }}>Employee verified</div>
                                        <div style={{ color: 'var(--admin-text-secondary)' }}>
                                            Continue by filling in your profile details, uploading your photo, and setting your password.
                                        </div>
                                    </div>
                                    <Field label="Name" icon={<i className="bi bi-person-badge" />} error={completeForm.errors.name}>
                                        <input value={completeForm.data.name} onChange={(e) => completeForm.setData('name', e.target.value)} style={inputStyle(!!completeForm.errors.name)} type="text" placeholder="Your full name" />
                                    </Field>
                                    <Field label="Address" icon={<i className="bi bi-geo-alt" />} error={completeForm.errors.address}>
                                        <input value={completeForm.data.address} onChange={(e) => completeForm.setData('address', e.target.value)} style={inputStyle(!!completeForm.errors.address)} type="text" placeholder="Your address" />
                                    </Field>
                                    <Field label="Office" icon={<i className="bi bi-building" />} error={completeForm.errors.office}>
                                        <input value={completeForm.data.office} onChange={(e) => completeForm.setData('office', e.target.value)} style={inputStyle(!!completeForm.errors.office)} type="text" placeholder="Your office" />
                                    </Field>
                                    <Field label="Password" icon={<i className="bi bi-lock" />} error={completeForm.errors.password}>
                                        <input value={completeForm.data.password} onChange={(e) => completeForm.setData('password', e.target.value)} style={inputStyle(!!completeForm.errors.password)} type="password" placeholder="Create password" />
                                    </Field>
                                    <p style={{ margin: '-0.35rem 0 1rem', fontSize: '0.78rem', color: 'var(--admin-text-muted)', lineHeight: 1.5 }}>
                                        Password must be at least 8 characters and include uppercase, lowercase, and a number.
                                    </p>
                                    <Field label="Confirm Password" icon={<i className="bi bi-shield-lock" />} error={completeForm.errors.password_confirmation}>
                                        <input value={completeForm.data.password_confirmation} onChange={(e) => completeForm.setData('password_confirmation', e.target.value)} style={inputStyle(!!completeForm.errors.password_confirmation)} type="password" placeholder="Confirm password" />
                                    </Field>
                                    <Field label="Photo" icon={<i className="bi bi-camera" />} error={completeForm.errors.photo}>
                                        <input onChange={(e) => completeForm.setData('photo', e.target.files?.[0] ?? null)} style={inputStyle(!!completeForm.errors.photo)} type="file" />
                                    </Field>
                                </>
                            )}
                            {mode === 'forgot' && (
                                <Field label="Email" icon={<i className="bi bi-envelope" />} error={forgotForm.errors.email}>
                                    <input value={forgotForm.data.email} onChange={(e) => forgotForm.setData('email', e.target.value)} style={inputStyle(!!forgotForm.errors.email)} type="email" placeholder="name@example.com" />
                                </Field>
                            )}

                            <button type="submit" style={primaryBtn} disabled={loginForm.processing || verifyForm.processing || completeForm.processing || forgotForm.processing}>
                                {mode === 'login' ? 'Sign in' : mode === 'forgot' ? 'Send reset link' : mode === 'activate-verify' ? 'Verify account' : 'Complete activation'}
                            </button>
                        </form>

                        <div style={{ display: 'grid', gap: '0.75rem', marginTop: '1.5rem' }}>
                            {mode !== 'forgot' && canResetPassword && <button type="button" onClick={() => setMode('forgot')} style={ghostBtn}>Forgot password?</button>}
                            {mode !== 'activate-verify' && <button type="button" onClick={() => setMode('activate-verify')} style={outlineBtn}>Activate L&amp;D Account</button>}
                            {mode !== 'login' && <button type="button" onClick={() => setMode('login')} style={ghostBtn}>Back to sign in</button>}
                        </div>

                        <div style={{ marginTop: '1rem', fontSize: '0.78rem', color: 'var(--admin-text-muted)', textAlign: 'center' }}>
                            <Link href="/" style={{ color: 'var(--admin-accent)', textDecoration: 'none' }}>Return home</Link>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                .auth-split-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1s ease; }
                .auth-split-slide.is-active { opacity: 1; }
                .auth-split-slide.is-active img { transform: scale(1.02); }
                .auth-split-slideshow-overlay {
                    position: absolute; inset: 0; z-index: 1;
                    background: linear-gradient(180deg, rgba(4,10,24,0.54) 0%, rgba(7,14,30,0.68) 100%);
                }
                .auth-form-column { min-width: 0; }
                .auth-form-card { max-height: calc(100dvh - 2rem); overflow-y: auto; }
                @media (max-width: 1023px) {
                    .auth-page-shell { grid-template-columns: minmax(0, 1fr) !important; }
                    .auth-hero { display: none; }
                }
                @media (max-width: 767px) {
                    .auth-form-column { align-items: flex-start !important; padding: 4.5rem .75rem 1rem !important; }
                    .auth-form-card { max-width: 520px; padding: 1.5rem 1rem !important; border-radius: 16px !important; }
                    .auth-form-card > div:first-child { margin-bottom: 1.35rem !important; padding-bottom: 1rem !important; }
                    .auth-form-card h4 { font-size: 1.3rem !important; }
                    .auth-form-card input { min-height: 44px; }
                    .auth-form-card button { min-height: 44px; }
                }
                @media (max-width: 380px) {
                    .auth-form-column { padding-inline: .5rem !important; }
                    .auth-form-card { padding-inline: .85rem !important; }
                }
            `}</style>
        </GuestLayout>
    );
}
