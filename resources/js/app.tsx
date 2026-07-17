import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Smart L&D';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/**/*.{jsx,tsx}');
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
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
    },
});
