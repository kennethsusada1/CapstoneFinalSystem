import { createContext, useContext, useMemo, useState } from 'react';

const ToastContext = createContext({ showToast: () => {} });

export function ToastProvider({ children }) {
    const [toast, setToast] = useState(null);

    const value = useMemo(
        () => ({
            showToast(message, type = 'info') {
                setToast({ message, type });
                window.clearTimeout(window.__smartldToastTimer);
                window.__smartldToastTimer = window.setTimeout(() => setToast(null), 2800);
            },
        }),
        [],
    );

    return (
        <ToastContext.Provider value={value}>
            {children}
            {toast && (
                <div style={{ position: 'fixed', right: 20, bottom: 20, left: 20, width: 'fit-content', maxWidth: 'calc(100vw - 40px)', marginLeft: 'auto', zIndex: 15000, padding: '0.85rem 1rem', borderRadius: 14, background: toast.type === 'error' ? '#7f1d1d' : 'var(--admin-card)', color: '#fff', border: '1px solid var(--admin-border-strong)', boxShadow: 'var(--admin-shadow)', overflowWrap: 'anywhere' }}>
                    {toast.message}
                </div>
            )}
        </ToastContext.Provider>
    );
}

export function useToast() {
    return useContext(ToastContext);
}

export default ToastProvider;
