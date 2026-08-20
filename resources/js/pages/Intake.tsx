import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

type Props = {
    employeeName: string | null;
    employeeOffice: string | null;
    officialRating: string | null;
    periodName: string | null;
    plan: string | null;
    pmsUserId: string | null;
    found: boolean;
};

export default function Intake({
    employeeName,
    employeeOffice,
    officialRating,
    periodName,
    found,
}: Props) {
    return (
        <>
            <Head title="L&D Training Enrollment" />

            <div className="intake-page" style={styles.page}>
                <div className="intake-card" style={styles.card}>

                    {/* Top accent bar */}
                    <div style={styles.accentBar} />

                    <div className="intake-body" style={styles.body}>

                        {/* Logo */}
                        <div style={styles.logoWrap}>
                            <div style={styles.logoBox}>
                                <AppLogoIcon style={{ width: 28, height: 28, fill: 'var(--admin-accent)' }} />
                            </div>
                            <span style={styles.systemLabel}>Learning &amp; Development System</span>
                        </div>

                        {/* Status badge */}
                        <div style={styles.badge}>
                            <span style={styles.badgeDot} />
                            Training Enrollment Active
                        </div>

                        {/* Headline */}
                        <div style={styles.headlineWrap}>
                            <h1 style={styles.headline}>
                                {found && employeeName
                                    ? <>Welcome, <span style={{ color: 'var(--admin-accent)' }}>{employeeName}</span></>
                                    : 'You Are Enrolled in Training'}
                            </h1>
                            <p style={styles.subtext}>
                                You are currently enrolled in an <strong>L&amp;D Training Program</strong>.
                                Your PMS account is temporarily restricted while you complete your training program.
                            </p>
                        </div>

                        {/* Info rows — only shown when referral is found */}
                        {found && (
                            <div style={styles.infoCard}>
                                {employeeOffice && (
                                    <InfoRow icon="bi-building" label="Office" value={employeeOffice} />
                                )}
                                {periodName && (
                                    <InfoRow icon="bi-calendar3" label="Performance Period" value={periodName} />
                                )}
                                {officialRating && (
                                    <InfoRow
                                        icon="bi-bar-chart-fill"
                                        label="IPCR Rating"
                                        value={officialRating}
                                        valueColor={ratingColor(officialRating)}
                                    />
                                )}
                                <InfoRow
                                    icon="bi-mortarboard-fill"
                                    label="Program"
                                    value="Individual Development Plan (IDP) Training"
                                />
                            </div>
                        )}

                        {/* What to do next */}
                        <div style={styles.nextCard}>
                            <p style={styles.nextTitle}>What to do next</p>
                            <ol style={styles.nextList}>
                                <li>Log in to your L&amp;D account below</li>
                                <li>Complete the assigned training program</li>
                                <li>Your PMS access will be restored automatically upon completion</li>
                            </ol>
                        </div>

                        {/* CTA */}
                        <div style={styles.ctaWrap}>
                            <Link href="/login" style={styles.ctaButton}>
                                <i className="bi bi-box-arrow-in-right" style={{ marginRight: 8 }} />
                                Log in to L&amp;D System
                            </Link>
                            <p style={styles.ctaNote}>
                                Don't have an account yet?{' '}
                                <strong style={{ color: 'var(--admin-text-primary)' }}>
                                    Contact your Secretariat office for account activation.
                                </strong>
                            </p>
                        </div>

                    </div>

                    {/* Footer */}
                    <div className="intake-footer" style={styles.footer}>
                        For assistance, contact your HR / Secretariat office.
                    </div>
                </div>

                {/* Below-card note */}
                <p style={styles.belowNote}>
                    This page is for employees referred from the <strong>smart-pms</strong> performance management
                    system. If you believe you received this in error, please contact your supervisor.
                </p>
            </div>
            <style>{`
                @media (max-width: 520px) {
                    .intake-page { justify-content: flex-start !important; padding: 12px 8px !important; }
                    .intake-card { border-radius: 14px !important; }
                    .intake-body { gap: 18px !important; padding: 28px 18px 24px !important; }
                    .intake-body h1 { font-size: 22px !important; }
                    .intake-footer { padding: 14px 18px !important; }
                }
            `}</style>
        </>
    );
}

// ─── Sub-components ────────────────────────────────────────────────────────

function InfoRow({
    icon,
    label,
    value,
    valueColor,
}: {
    icon: string;
    label: string;
    value: string;
    valueColor?: string;
}) {
    return (
        <div style={styles.infoRow}>
            <i className={`bi ${icon}`} style={styles.infoIcon} />
            <div>
                <div style={styles.infoLabel}>{label}</div>
                <div style={{ ...styles.infoValue, ...(valueColor ? { color: valueColor } : {}) }}>
                    {value}
                </div>
            </div>
        </div>
    );
}

function ratingColor(rating: string): string {
    const r = rating.toLowerCase();
    if (r === 'outstanding')       return '#10b981';
    if (r === 'very satisfactory') return '#3b82f6';
    if (r === 'satisfactory')      return '#38bdf8';
    if (r === 'unsatisfactory')    return '#f59e0b';
    if (r === 'poor')              return '#ef4444';
    return 'var(--admin-text-primary)';
}

// ─── Styles ────────────────────────────────────────────────────────────────

const styles: Record<string, React.CSSProperties> = {
    page: {
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '24px 16px',
        background: 'radial-gradient(circle at top left, rgba(59,130,246,0.12), transparent 26%), linear-gradient(180deg, var(--admin-bg-primary) 0%, var(--admin-bg-secondary) 100%)',
    },
    card: {
        width: '100%',
        maxWidth: 520,
        background: 'var(--admin-card)',
        border: '1px solid var(--admin-border)',
        borderRadius: 'var(--admin-radius-lg)',
        boxShadow: 'var(--admin-shadow)',
        overflow: 'hidden',
    },
    accentBar: {
        height: 4,
        background: 'linear-gradient(90deg, var(--admin-accent), rgba(59,130,246,0.4))',
    },
    body: {
        padding: '40px 40px 32px',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 24,
        textAlign: 'center',
    },
    logoWrap: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 10,
    },
    logoBox: {
        width: 56,
        height: 56,
        borderRadius: 14,
        background: 'rgba(59,130,246,0.12)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
    },
    systemLabel: {
        fontSize: 11,
        fontWeight: 700,
        letterSpacing: '0.12em',
        textTransform: 'uppercase' as const,
        color: 'var(--admin-text-muted)',
    },
    badge: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 7,
        padding: '5px 14px',
        borderRadius: 999,
        background: 'rgba(245,158,11,0.12)',
        border: '1px solid rgba(245,158,11,0.25)',
        fontSize: 12,
        fontWeight: 600,
        color: '#f59e0b',
    },
    badgeDot: {
        display: 'inline-block',
        width: 7,
        height: 7,
        borderRadius: '50%',
        background: '#f59e0b',
    },
    headlineWrap: {
        display: 'flex',
        flexDirection: 'column',
        gap: 8,
    },
    headline: {
        margin: 0,
        fontSize: 26,
        fontWeight: 700,
        color: 'var(--admin-text-primary)',
        letterSpacing: '-0.3px',
    },
    subtext: {
        margin: 0,
        fontSize: 14,
        lineHeight: 1.6,
        color: 'var(--admin-text-secondary)',
        maxWidth: 400,
    },
    infoCard: {
        width: '100%',
        background: 'rgba(255,255,255,0.03)',
        border: '1px solid var(--admin-border)',
        borderRadius: 'var(--admin-radius)',
        padding: '16px 20px',
        display: 'flex',
        flexDirection: 'column',
        gap: 14,
        textAlign: 'left',
    },
    infoRow: {
        display: 'flex',
        alignItems: 'flex-start',
        gap: 12,
    },
    infoIcon: {
        fontSize: 15,
        color: 'var(--admin-text-muted)',
        marginTop: 2,
        flexShrink: 0,
    },
    infoLabel: {
        fontSize: 11,
        color: 'var(--admin-text-muted)',
        marginBottom: 1,
    },
    infoValue: {
        fontSize: 14,
        fontWeight: 500,
        color: 'var(--admin-text-primary)',
    },
    nextCard: {
        width: '100%',
        background: 'rgba(59,130,246,0.06)',
        border: '1px solid rgba(59,130,246,0.18)',
        borderRadius: 'var(--admin-radius)',
        padding: '16px 20px',
        textAlign: 'left',
    },
    nextTitle: {
        margin: '0 0 8px',
        fontSize: 11,
        fontWeight: 700,
        letterSpacing: '0.1em',
        textTransform: 'uppercase' as const,
        color: 'var(--admin-accent)',
    },
    nextList: {
        margin: 0,
        paddingLeft: 20,
        display: 'flex',
        flexDirection: 'column',
        gap: 6,
        fontSize: 13,
        color: 'var(--admin-text-secondary)',
        lineHeight: 1.5,
    },
    ctaWrap: {
        width: '100%',
        display: 'flex',
        flexDirection: 'column',
        gap: 12,
    },
    ctaButton: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '100%',
        padding: '12px 24px',
        background: 'var(--admin-accent)',
        color: '#fff',
        borderRadius: 'var(--admin-radius)',
        fontWeight: 600,
        fontSize: 14,
        textDecoration: 'none',
        transition: 'opacity 0.15s',
        boxSizing: 'border-box' as const,
    },
    ctaNote: {
        margin: 0,
        fontSize: 12,
        color: 'var(--admin-text-muted)',
        textAlign: 'center' as const,
    },
    footer: {
        borderTop: '1px solid var(--admin-border)',
        background: 'rgba(255,255,255,0.02)',
        padding: '14px 40px',
        textAlign: 'center' as const,
        fontSize: 12,
        color: 'var(--admin-text-muted)',
    },
    belowNote: {
        marginTop: 24,
        fontSize: 12,
        color: 'var(--admin-text-muted)',
        textAlign: 'center' as const,
        maxWidth: 440,
        lineHeight: 1.6,
    },
};
