import AppLayout from '@/layouts/AppLayout';

export default function UnderDevelopmentPage({ title, description, icon = 'bi bi-tools', message = 'This section is coming soon.' }) {
    return (
        <AppLayout title={title} description={description}>
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    minHeight: 320,
                    gap: '0.75rem',
                    color: 'var(--admin-text-muted)',
                    textAlign: 'center',
                    padding: '1rem',
                }}
            >
                <i className={icon} style={{ fontSize: '2rem', opacity: 0.4 }} />
                <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--admin-text-primary)' }}>Under Development</div>
                <div style={{ fontSize: '0.82rem', maxWidth: 420 }}>{message}</div>
            </div>
        </AppLayout>
    );
}
