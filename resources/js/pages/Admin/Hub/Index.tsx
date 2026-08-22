import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

// L&D only connects to PMS — one pillar entry
const PILLAR_CFG = {
    pms: {
        label: 'PMS',
        full: 'Performance Management System',
        icon: 'bi-bar-chart-fill',
        color: '#10b981',
        desc: 'UWP → OPCR → IPCR → IDP → Training Referral',
    },
};

interface PmsConnection {
    status: 'disconnected' | 'pending' | 'connected' | 'rejected';
    pms_base_url: string | null;
    requested_at: string | null;
    accepted_at: string | null;
}

interface LndCredentials {
    base_url: string;
    api_token: string | null;
    hmac_secret: string | null;
}

interface Props {
    pmsConnection: PmsConnection;
    lndCredentials: LndCredentials;
}

// ── Copy-to-clipboard credential row ─────────────────────────────────────────

function CredentialRow({ label, value, secret = false }: { label: string; value: string | null; secret?: boolean }) {
    const [copied, setCopied] = useState(false);
    const [revealed, setRevealed] = useState(false);

    if (!value) {
        return (
            <div style={{ marginBottom: '0.85rem' }}>
                <div style={credLabel}>{label}</div>
                <div style={{ ...credBox, color: '#f87171', fontSize: '0.72rem' }}>
                    Not configured — set this in L&amp;D <code>.env</code>
                </div>
            </div>
        );
    }

    function copy() {
        navigator.clipboard.writeText(value!);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    const display = secret && !revealed ? '•'.repeat(Math.min(value.length, 32)) : value;

    return (
        <div style={{ marginBottom: '0.85rem' }}>
            <div style={credLabel}>{label}</div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <div style={{ ...credBox, flex: 1, fontFamily: 'monospace', fontSize: '0.75rem', wordBreak: 'break-all' }}>
                    {display}
                </div>
                {secret && (
                    <button type="button" onClick={() => setRevealed(r => !r)} style={credBtn} title={revealed ? 'Hide' : 'Reveal'}>
                        <i className={`bi ${revealed ? 'bi-eye-slash' : 'bi-eye'}`} />
                    </button>
                )}
                <button type="button" onClick={copy} style={credBtn} title="Copy">
                    <i className={`bi ${copied ? 'bi-check-lg' : 'bi-clipboard'}`} style={{ color: copied ? '#22c55e' : undefined }} />
                </button>
            </div>
        </div>
    );
}

// ── Side Panel ────────────────────────────────────────────────────────────────

function SidePanel({ connection, credentials, onClose }: {
    connection: PmsConnection;
    credentials: LndCredentials;
    onClose: () => void;
}) {
    const cfg = PILLAR_CFG.pms;
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    const isConnected    = connection.status === 'connected';
    const isPending      = connection.status === 'pending';
    const isRejected     = connection.status === 'rejected';
    const isDisconnected = connection.status === 'disconnected';

    function post(url: string, label: string) {
        setLoading(true);
        setMessage(null);
        router.post(url, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setMessage({ type: 'success', text: label + ' successful.' });
                // Reload the page after a short delay so fresh DB state is shown
                setTimeout(() => router.reload({ only: ['pmsConnection'] }), 800);
            },
            onError: (errors) => setMessage({ type: 'error', text: Object.values(errors).flat().join(', ') }),
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 1000, display: 'flex', justifyContent: 'flex-end' }}>
            {/* Backdrop */}
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.3)', backdropFilter: 'blur(2px)' }} />

            {/* Panel */}
            <div style={{
                position: 'relative', width: '100%', maxWidth: 440, height: '100%',
                background: 'var(--admin-card)', borderLeft: '1px solid var(--admin-border-strong)',
                boxShadow: '-8px 0 30px rgba(0,0,0,0.15)', display: 'flex', flexDirection: 'column',
                animation: 'slideIn 0.2s ease-out',
            }}>
                {/* Header */}
                <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid var(--admin-border)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                        <div style={{ width: 40, height: 40, borderRadius: 10, background: `${cfg.color}12`, border: `1px solid ${cfg.color}25`, display: 'flex', alignItems: 'center', justifyContent: 'center', color: cfg.color }}>
                            <i className={`bi ${cfg.icon}`} style={{ fontSize: '1.1rem' }} />
                        </div>
                        <div>
                            <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--admin-text-primary)' }}>{cfg.full}</div>
                            <div style={{ fontSize: '0.72rem', color: 'var(--admin-text-muted)', marginTop: 2 }}>{cfg.desc}</div>
                        </div>
                    </div>
                    <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--admin-text-muted)', fontSize: '1.3rem', padding: 4, lineHeight: 1 }}>×</button>
                </div>

                {/* Body */}
                <div style={{ flex: 1, overflow: 'auto', padding: '1.25rem 1.5rem' }}>

                    {/* Status banner */}
                    {isConnected && (
                        <div style={{ padding: '1rem', borderRadius: 12, background: 'rgba(59,130,246,0.06)', border: '1px solid rgba(59,130,246,0.15)', marginBottom: '1.25rem' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 700, fontSize: '0.85rem', color: 'var(--admin-accent)' }}>
                                <i className="bi bi-check-circle-fill" /> Connected
                            </div>
                            {connection.accepted_at && (
                                <div style={{ fontSize: '0.72rem', color: 'var(--admin-text-muted)', marginTop: '0.35rem' }}>
                                    Accepted {connection.accepted_at}
                                </div>
                            )}
                            <p style={{ fontSize: '0.75rem', color: 'var(--admin-text-muted)', marginTop: '0.5rem', lineHeight: 1.5, marginBottom: 0 }}>
                                Smart PMS is connected. Employee training referrals and completion callbacks are active.
                            </p>
                        </div>
                    )}

                    {isPending && (
                        <div style={{ padding: '1rem', borderRadius: 12, background: 'rgba(234,179,8,0.06)', border: '1px solid rgba(234,179,8,0.25)', marginBottom: '1.25rem' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 700, fontSize: '0.85rem', color: '#ca8a04' }}>
                                <i className="bi bi-hourglass-split" /> Pending Acceptance
                            </div>
                            <p style={{ fontSize: '0.75rem', color: 'var(--admin-text-muted)', marginTop: '0.35rem', lineHeight: 1.5, marginBottom: 0 }}>
                                Smart PMS has sent a connection request. Accept to enable the integration.
                            </p>
                            {connection.pms_base_url && (
                                <div style={{ fontSize: '0.72rem', color: 'var(--admin-text-muted)', marginTop: '0.5rem' }}>
                                    From: <strong style={{ color: 'var(--admin-text-secondary)' }}>{connection.pms_base_url}</strong>
                                </div>
                            )}
                            {connection.requested_at && (
                                <div style={{ fontSize: '0.72rem', color: 'var(--admin-text-muted)', marginTop: '0.25rem' }}>
                                    Received {connection.requested_at}
                                </div>
                            )}
                        </div>
                    )}

                    {isRejected && (
                        <div style={{ padding: '1rem', borderRadius: 12, background: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)', marginBottom: '1.25rem' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 700, fontSize: '0.85rem', color: '#f87171' }}>
                                <i className="bi bi-x-circle-fill" /> Connection Rejected
                            </div>
                            <p style={{ fontSize: '0.75rem', color: 'var(--admin-text-muted)', marginTop: '0.35rem', lineHeight: 1.5, marginBottom: 0 }}>
                                The connection request was rejected. PMS can send a new request at any time.
                            </p>
                        </div>
                    )}

                    {isDisconnected && (
                        <div style={{ padding: '1rem', borderRadius: 12, background: 'rgba(100,100,100,0.05)', border: '1px solid var(--admin-border)', marginBottom: '1.25rem' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 700, fontSize: '0.85rem', color: 'var(--admin-text-muted)' }}>
                                <i className="bi bi-plug" /> Not Connected
                            </div>
                            <p style={{ fontSize: '0.75rem', color: 'var(--admin-text-muted)', marginTop: '0.35rem', marginBottom: 0 }}>
                                Waiting for Smart PMS to initiate a connection request via their HRMO Hub.
                            </p>
                        </div>
                    )}

                    {/* Inline message */}
                    {message && (
                        <div style={{
                            padding: '0.65rem 0.85rem', borderRadius: 10, marginBottom: '1rem',
                            fontSize: '0.78rem', fontWeight: 600,
                            background: message.type === 'success' ? 'rgba(34,197,94,0.08)' : 'rgba(239,68,68,0.08)',
                            border: `1px solid ${message.type === 'success' ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)'}`,
                            color: message.type === 'success' ? '#22c55e' : '#f87171',
                        }}>
                            {message.text}
                        </div>
                    )}

                    {/* Credentials for PMS — always visible so admin can share them */}
                    <div style={{ borderTop: '1px solid var(--admin-border)', paddingTop: '1rem', marginTop: '0.25rem', marginBottom: '1.25rem' }}>
                        <div style={{ fontWeight: 700, fontSize: '0.75rem', color: 'var(--admin-text-muted)', letterSpacing: '0.07em', textTransform: 'uppercase', marginBottom: '0.85rem' }}>
                            <i className="bi bi-key-fill" style={{ marginRight: '0.4rem' }} />
                            Credentials for PMS Admin
                        </div>
                        <p style={{ fontSize: '0.72rem', color: 'var(--admin-text-muted)', marginBottom: '1rem', lineHeight: 1.5 }}>
                            Share these values with the PMS admin. They enter them in their HRMO Hub when connecting to L&amp;D.
                        </p>
                        <CredentialRow label="L&D Base URL  (LND_BASE_URL in PMS)" value={credentials.base_url} />
                        <CredentialRow label="API Token  (LND_API_TOKEN in PMS)" value={credentials.api_token} secret />
                    </div>

                    {/* Action buttons */}
                    <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                        {isPending && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => post('/admin/hub/accept', 'Connection accepted')}
                                    disabled={loading}
                                    style={{ ...btnPrimary, opacity: loading ? 0.5 : 1, cursor: loading ? 'not-allowed' : 'pointer' }}>
                                    <i className="bi bi-check-lg" style={{ marginRight: '0.3rem' }} />
                                    {loading ? 'Processing…' : 'Accept Connection'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => post('/admin/hub/reject', 'Connection rejected')}
                                    disabled={loading}
                                    style={{ ...btnOutline, borderColor: 'rgba(239,68,68,0.3)', background: 'rgba(239,68,68,0.06)', color: '#f87171', cursor: loading ? 'not-allowed' : 'pointer' }}>
                                    Reject
                                </button>
                            </>
                        )}
                        {isConnected && (
                            <button
                                type="button"
                                onClick={() => post('/admin/hub/disconnect', 'Disconnected')}
                                disabled={loading}
                                style={{ ...btnOutline, borderColor: 'rgba(239,68,68,0.3)', background: 'rgba(239,68,68,0.06)', color: '#f87171', cursor: loading ? 'not-allowed' : 'pointer' }}>
                                {loading ? 'Processing…' : 'Disconnect'}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <style>{`@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }`}</style>
        </div>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function Index({ pmsConnection, lndCredentials }: Props) {
    const [open, setOpen] = useState(false);

    const cfg = PILLAR_CFG.pms;
    const isConnected = pmsConnection.status === 'connected';
    const isPending   = pmsConnection.status === 'pending';
    const isRejected  = pmsConnection.status === 'rejected';

    return (
        <AppLayout title="HRMO Hub">
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>

                {/* Page header card — identical to PMS */}
                <div style={card}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem' }}>
                        <div style={{ ...iconBox, width: 42, height: 42 }}>
                            <i className="bi bi-grid-3x3-gap-fill" style={{ fontSize: '1.1rem' }} />
                        </div>
                        <div>
                            <p style={statLabel}>System Integration</p>
                            <h1 style={{ ...cardHeader, fontSize: '1.6rem', marginBottom: 0 }}>HRMO Hub</h1>
                        </div>
                    </div>
                    <p style={{ ...statCaption, marginTop: '0.75rem', maxWidth: 760 }}>
                        Manage connections between Smart L&amp;D and the other HRMO pillars. Click a pillar to view its connection status and take action.
                    </p>
                </div>

                {/* Pillar list card — same structure as PMS, one row */}
                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {/* L&D itself — built-in */}
                    <div style={{
                        display: 'flex', alignItems: 'center', gap: '1rem',
                        padding: '1rem 1.25rem',
                        borderBottom: '1px solid var(--admin-border)',
                    }}>
                        <div style={{ width: 40, height: 40, borderRadius: 10, background: 'rgba(139,92,246,0.1)', border: '1px solid rgba(139,92,246,0.2)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#8b5cf6', flexShrink: 0 }}>
                            <i className="bi bi-book-fill" style={{ fontSize: '1.1rem' }} />
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ fontWeight: 700, fontSize: '0.9rem', color: 'var(--admin-text-primary)' }}>L&amp;D</div>
                            <div style={{ fontSize: '0.73rem', color: 'var(--admin-text-muted)', marginTop: 1 }}>Learning &amp; Development</div>
                        </div>
                        <span style={badgeGreen}>Built-in</span>
                        <i className="bi bi-chevron-right" style={{ fontSize: '0.8rem', color: 'var(--admin-border)', flexShrink: 0 }} />
                    </div>

                    {/* PMS pillar — clickable */}
                    <div
                        onClick={() => setOpen(true)}
                        style={{
                            display: 'flex', alignItems: 'center', gap: '1rem',
                            padding: '1rem 1.25rem', cursor: 'pointer',
                            transition: 'background 0.15s',
                        }}
                        onMouseEnter={e => (e.currentTarget.style.background = 'var(--admin-bg-secondary)')}
                        onMouseLeave={e => (e.currentTarget.style.background = '')}>
                        <div style={{ width: 40, height: 40, borderRadius: 10, background: `${cfg.color}10`, border: `1px solid ${cfg.color}20`, display: 'flex', alignItems: 'center', justifyContent: 'center', color: cfg.color, flexShrink: 0 }}>
                            <i className={`bi ${cfg.icon}`} style={{ fontSize: '1.1rem' }} />
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ fontWeight: 700, fontSize: '0.9rem', color: 'var(--admin-text-primary)' }}>{cfg.label}</div>
                            <div style={{ fontSize: '0.73rem', color: 'var(--admin-text-muted)', marginTop: 1 }}>{cfg.full}</div>
                        </div>
                        {isConnected ? (
                            <span style={badgeBlue}>Connected</span>
                        ) : isPending ? (
                            <span style={badgeYellow}>Pending</span>
                        ) : isRejected ? (
                            <span style={badgeRed}>Rejected</span>
                        ) : (
                            <span style={badgeGray}>Not Connected</span>
                        )}
                        <i className="bi bi-chevron-right" style={{ fontSize: '0.8rem', color: 'var(--admin-text-muted)', flexShrink: 0 }} />
                    </div>
                </div>
            </div>

            {/* Side panel — opens when PMS row is clicked */}
            {open && (
                <SidePanel connection={pmsConnection} credentials={lndCredentials} onClose={() => setOpen(false)} />
            )}
        </AppLayout>
    );
}

// ── Styles (identical to PMS) ─────────────────────────────────────────────────

const card: React.CSSProperties = { background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 'var(--admin-radius)', padding: '1.25rem 1.5rem', boxShadow: 'var(--admin-shadow)' };
const statLabel: React.CSSProperties = { fontSize: '0.72rem', fontWeight: 600, color: 'var(--admin-text-muted)', letterSpacing: '0.08em', textTransform: 'uppercase', marginBottom: '0.25rem' };
const statCaption: React.CSSProperties = { fontSize: '0.75rem', color: 'var(--admin-text-secondary)', marginTop: '0.1rem' };
const cardHeader: React.CSSProperties = { fontWeight: 700, fontSize: '0.9rem', color: 'var(--admin-text-primary)', marginBottom: '0.75rem' };
const iconBox: React.CSSProperties = { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 34, height: 34, borderRadius: 10, border: '1px solid var(--admin-border)', background: 'rgba(59,130,246,0.08)', color: 'var(--admin-accent)', flexShrink: 0 };

const badgeGreen: React.CSSProperties  = { padding: '0.2rem 0.7rem', borderRadius: 99, fontSize: '0.67rem', fontWeight: 700, background: 'rgba(34,197,94,0.1)',   color: '#22c55e',               border: '1px solid rgba(34,197,94,0.2)',  whiteSpace: 'nowrap' };
const badgeBlue: React.CSSProperties   = { padding: '0.2rem 0.7rem', borderRadius: 99, fontSize: '0.67rem', fontWeight: 700, background: 'rgba(59,130,246,0.1)',  color: 'var(--admin-accent)',   border: '1px solid rgba(59,130,246,0.2)', whiteSpace: 'nowrap' };
const badgeYellow: React.CSSProperties = { padding: '0.2rem 0.7rem', borderRadius: 99, fontSize: '0.67rem', fontWeight: 700, background: 'rgba(234,179,8,0.1)',   color: '#ca8a04',               border: '1px solid rgba(234,179,8,0.25)', whiteSpace: 'nowrap' };
const badgeRed: React.CSSProperties    = { padding: '0.2rem 0.7rem', borderRadius: 99, fontSize: '0.67rem', fontWeight: 700, background: 'rgba(239,68,68,0.1)',   color: '#f87171',               border: '1px solid rgba(239,68,68,0.2)',  whiteSpace: 'nowrap' };
const badgeGray: React.CSSProperties   = { padding: '0.2rem 0.7rem', borderRadius: 99, fontSize: '0.67rem', fontWeight: 700, background: 'rgba(100,100,100,0.08)', color: 'var(--admin-text-muted)', border: '1px solid var(--admin-border)', whiteSpace: 'nowrap' };

const btnPrimary: React.CSSProperties = { padding: '0.55rem 1.25rem', borderRadius: 8, border: 'none', background: 'var(--admin-accent)', color: '#fff', fontSize: '0.8rem', fontWeight: 700, fontFamily: 'inherit' };
const btnOutline: React.CSSProperties = { padding: '0.55rem 1rem', borderRadius: 8, border: '1px solid var(--admin-border-strong)', background: 'transparent', color: 'var(--admin-text-primary)', fontSize: '0.8rem', fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer' };

const credLabel: React.CSSProperties = { fontSize: '0.68rem', fontWeight: 600, color: 'var(--admin-text-muted)', letterSpacing: '0.06em', textTransform: 'uppercase', marginBottom: '0.3rem' };
const credBox: React.CSSProperties   = { padding: '0.5rem 0.75rem', borderRadius: 8, background: 'var(--admin-bg-secondary)', border: '1px solid var(--admin-border)', color: 'var(--admin-text-secondary)', fontSize: '0.8rem' };
const credBtn: React.CSSProperties   = { flexShrink: 0, width: 30, height: 30, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 7, border: '1px solid var(--admin-border)', background: 'var(--admin-bg-secondary)', color: 'var(--admin-text-muted)', cursor: 'pointer', fontSize: '0.85rem' };
