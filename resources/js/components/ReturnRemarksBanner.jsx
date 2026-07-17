export default function ReturnRemarksBanner({ remarks }) {
    if (!remarks) return null;

    return (
        <div style={{ marginBottom: '1rem', padding: '0.9rem 1rem', borderRadius: 14, background: 'rgba(245, 158, 11, 0.12)', border: '1px solid rgba(245, 158, 11, 0.35)', color: '#fbbf24' }}>
            <div style={{ fontWeight: 700, marginBottom: '0.25rem' }}>Returned with remarks</div>
            <div style={{ fontSize: '0.88rem', lineHeight: 1.6 }}>{remarks}</div>
        </div>
    );
}
