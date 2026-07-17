export default function GuestLayout({ children }) {
    return (
        <div style={{ minHeight: '100vh', background: 'var(--admin-bg-primary)', fontFamily: "'Inter', system-ui, sans-serif" }}>
            {children}
        </div>
    );
}
