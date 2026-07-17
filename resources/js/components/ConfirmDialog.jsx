import { createContext, useContext, useMemo, useState } from 'react';

const ConfirmContext = createContext({ confirm: async () => false });

export function ConfirmProvider({ children }) {
    const [dialog, setDialog] = useState(null);

    const value = useMemo(
        () => ({
            confirm(options) {
                return new Promise((resolve) => setDialog({ ...options, resolve }));
            },
        }),
        [],
    );

    return (
        <ConfirmContext.Provider value={value}>
            {children}
            {dialog && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 16000, background: 'rgba(3, 7, 18, 0.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
                    <div style={{ width: '100%', maxWidth: 420, background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)', borderRadius: 18, padding: '1.25rem', boxShadow: 'var(--admin-shadow)' }}>
                        <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--admin-text-primary)', marginBottom: '0.5rem' }}>{dialog.title || 'Please confirm'}</div>
                        <div style={{ fontSize: '0.88rem', color: 'var(--admin-text-muted)', lineHeight: 1.6 }}>{dialog.message || 'Are you sure you want to continue?'}</div>
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1rem' }}>
                            <button type="button" onClick={() => { dialog.resolve(false); setDialog(null); }} style={{ padding: '0.65rem 0.9rem', borderRadius: 10, border: '1px solid var(--admin-border)', background: 'transparent', color: 'var(--admin-text-secondary)', cursor: 'pointer' }}>Cancel</button>
                            <button type="button" onClick={() => { dialog.resolve(true); setDialog(null); }} style={{ padding: '0.65rem 0.9rem', borderRadius: 10, border: 'none', background: 'var(--admin-accent)', color: '#fff', cursor: 'pointer' }}>{dialog.confirmText || 'Confirm'}</button>
                        </div>
                    </div>
                </div>
            )}
        </ConfirmContext.Provider>
    );
}

export function useConfirm() {
    return useContext(ConfirmContext);
}

export default ConfirmProvider;
