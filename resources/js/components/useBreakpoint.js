import { useEffect, useState } from 'react';

function getBreakpoint() {
    if (typeof window === 'undefined') return 'desktop';
    if (window.innerWidth < 768) return 'mobile';
    if (window.innerWidth < 1024) return 'tablet';
    return 'desktop';
}

export default function useBreakpoint() {
    const [breakpoint, setBreakpoint] = useState(getBreakpoint);

    useEffect(() => {
        const onResize = () => setBreakpoint(getBreakpoint());
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    return breakpoint;
}
