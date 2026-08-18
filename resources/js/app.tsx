import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import { useEffect, useRef, useState } from 'react';

const appName = import.meta.env.VITE_APP_NAME || 'Smart L&D';

function NavigationLoader() {
    const [visible, setVisible] = useState(false);
    const startedAt = useRef(0);
    const hideTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const removeStartListener = router.on('start', () => {
            if (hideTimer.current) clearTimeout(hideTimer.current);
            startedAt.current = Date.now();
            setVisible(true);
        });
        const removeFinishListener = router.on('finish', () => {
            const elapsed = Date.now() - startedAt.current;
            hideTimer.current = setTimeout(() => setVisible(false), Math.max(0, 450 - elapsed));
        });

        return () => {
            removeStartListener();
            removeFinishListener();
            if (hideTimer.current) clearTimeout(hideTimer.current);
        };
    }, []);

    if (!visible) return null;

    return (
        <div className="navigation-loader" role="status" aria-live="polite" aria-label="Loading page">
            <div className="navigation-loader-card">
                <div className="navigation-loader-mark">
                    <img src="/images/ld-logo.png" alt="" />
                    <span />
                </div>
                <div className="navigation-loader-copy">
                    <strong>Loading Smart L&amp;D</strong>
                    <span>Preparing your workspace</span>
                </div>
                <div className="navigation-loader-track"><span /></div>
            </div>
            <style>{`
                .navigation-loader { position: fixed; inset: 0; z-index: 20000; display: grid; place-items: center; background: rgba(5,11,20,.78); backdrop-filter: blur(8px); animation: navigation-fade-in .16s ease-out; }
                .navigation-loader-card { display: grid; width: min(330px, calc(100vw - 2rem)); grid-template-columns: 54px 1fr; align-items: center; gap: .85rem; padding: 1rem; border: 1px solid rgba(96,165,250,.24); border-radius: 18px; background: linear-gradient(145deg, rgba(15,31,52,.98), rgba(8,18,32,.98)); box-shadow: 0 24px 70px rgba(0,0,0,.45); }
                .navigation-loader-mark { position: relative; display: grid; width: 54px; height: 54px; place-items: center; }
                .navigation-loader-mark img { width: 42px; height: 42px; border-radius: 12px; object-fit: cover; }
                .navigation-loader-mark span { position: absolute; inset: 0; border: 2px solid rgba(96,165,250,.16); border-top-color: #60a5fa; border-right-color: #2dd4bf; border-radius: 50%; animation: navigation-spin .8s linear infinite; }
                .navigation-loader-copy { display: grid; gap: .18rem; }.navigation-loader-copy strong { color: #f8fafc; font-size: .88rem; }.navigation-loader-copy > span { color: #8fa5c5; font-size: .7rem; }
                .navigation-loader-track { grid-column: 1 / -1; height: 5px; overflow: hidden; border-radius: 999px; background: rgba(148,163,184,.12); }
                .navigation-loader-track span { display: block; width: 42%; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2563eb, #2dd4bf, #fb923c); animation: navigation-slide 1s ease-in-out infinite; }
                @keyframes navigation-spin { to { transform: rotate(360deg); } }
                @keyframes navigation-slide { from { transform: translateX(-110%); } to { transform: translateX(340%); } }
                @keyframes navigation-fade-in { from { opacity: 0; } to { opacity: 1; } }
                @media (prefers-reduced-motion: reduce) { .navigation-loader-mark span, .navigation-loader-track span { animation-duration: 2s; } }
            `}</style>
        </div>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.{jsx,tsx}');
        const directMatch = pages[`./pages/${name}.jsx`] ?? pages[`./pages/${name}.tsx`];

        const match =
            directMatch ??
            Object.entries(pages).find(([key]) => key.toLowerCase() === `./pages/${name}.jsx`.toLowerCase() || key.toLowerCase() === `./pages/${name}.tsx`.toLowerCase())?.[1];

        if (!match) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }

        const page = await match();
        return page.default;
    },
    strictMode: true,
    setup({ el, App, props }) {
        if (!el) throw new Error('Inertia root element was not found.');

        createRoot(el).render(
            <>
                <NavigationLoader />
                <App {...props} />
            </>,
        );
    },
    progress: {
        color: '#2563eb',
    },
});
