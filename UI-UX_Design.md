# UI/UX Design System — Smart PMS / Learning & Development

> This document defines the complete frontend design system used in Smart PMS built with **Laravel + Inertia.js + React (JSX)**. Use this as the single source of truth to replicate the same look, feel, and component patterns in any sibling system (e.g. Learning & Development portal).

---

## Table of Contents

1. [Tech Stack](#1-tech-stack)
2. [Color System & Theme Variables](#2-color-system--theme-variables)
3. [Typography](#3-typography)
4. [Layout Structure](#4-layout-structure)
5. [Authentication Pages](#5-authentication-pages)
6. [Sidebar](#6-sidebar)
7. [Topbar / Navbar](#7-topbar--navbar)
8. [Reusable Components](#8-reusable-components)
9. [Icons](#9-icons)
10. [Responsive Breakpoints](#10-responsive-breakpoints)
11. [Animation & Transitions](#11-animation--transitions)
12. [Auth Flow — Laravel Fortify + Spatie RBAC](#12-auth-flow--laravel-fortify--spatie-rbac)

---

## 1. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 |
| Frontend bridge | Inertia.js v2 |
| Frontend framework | React 18 (JSX, no TypeScript) |
| CSS approach | **Inline styles only** — no Tailwind, no CSS modules, no external UI library |
| Icons | Bootstrap Icons (`bi-*`) via CDN + inline SVGs for custom icons |
| HTTP client | Axios (for background calls), Inertia `useForm` (for form submissions) |
| Font | Inter (system fallback stack) |

> **Important:** All styling is done with inline `style={{}}` props. No className-based utility frameworks. The only exceptions are Bootstrap Icons classes (`bi bi-*`) and a handful of layout/sidebar CSS classes injected via `<style>` tags inside components.

---

## 2. Color System & Theme Variables

The system supports **dark mode** (default) and **light mode**, toggled via `data-theme` attribute on `<html>`. Theme is persisted in `localStorage` under the key `'theme'`.

### CSS Custom Properties

Inject these into `:root` and `:root[data-theme="light"]` — either in `app.css` or inside the `AppLayout` `<style>` block:

```css
/* ── Dark mode (default) ── */
:root[data-theme="dark"], :root {
    --admin-bg-primary:    #0a0f1a;
    --admin-bg-secondary:  #0f1724;
    --admin-sidebar:       rgba(7,16,25,0.98);
    --admin-card:          rgba(16,23,34,0.96);
    --admin-border:        rgba(140,171,214,0.12);
    --admin-border-strong: rgba(59,130,246,0.22);
    --admin-text-primary:  #f4f8ff;
    --admin-text-secondary:#a5b4cf;
    --admin-text-muted:    #6f83a6;
    --admin-accent:        #3b82f6;
    --admin-radius:        12px;
    --admin-radius-lg:     18px;
    --admin-shadow:        0 18px 40px rgba(0,0,0,0.28);
}

/* ── Light mode ── */
:root[data-theme="light"] {
    --admin-bg-primary:    #f0f4ff;
    --admin-bg-secondary:  #e8edf8;
    --admin-sidebar:       rgba(255,255,255,0.98);
    --admin-card:          rgba(255,255,255,0.96);
    --admin-border:        rgba(59,130,246,0.14);
    --admin-border-strong: rgba(59,130,246,0.32);
    --admin-text-primary:  #0f172a;
    --admin-text-secondary:#334155;
    --admin-text-muted:    #64748b;
    --admin-accent:        #2563eb;
    --admin-shadow:        0 18px 40px rgba(0,0,0,0.08);
}
```

### Semantic Color Palette (used inline)

| Purpose | Dark value | Light value |
|---|---|---|
| Accent / primary blue | `#3b82f6` | `#2563eb` |
| Success green | `#4ade80` / `#10b981` | same |
| Warning yellow | `#facc15` / `#ca8a04` | same |
| Danger red | `#f87171` / `#ef4444` | same |
| Purple (endorsed) | `#c084fc` | same |
| Orange (risk/warn) | `#f97316` | same |

### Status Badge Colors (copy-paste pattern)

```js
const STATUS_COLORS = {
    draft:     { bg: 'rgba(100,116,139,0.15)', color: '#94a3b8', border: 'rgba(100,116,139,0.3)' },
    submitted: { bg: 'rgba(37,99,235,0.12)',   color: '#60a5fa', border: 'rgba(37,99,235,0.3)'   },
    approved:  { bg: 'rgba(5,150,105,0.12)',   color: '#34d399', border: 'rgba(5,150,105,0.3)'   },
    returned:  { bg: 'rgba(239,68,68,0.12)',   color: '#f87171', border: 'rgba(239,68,68,0.3)'   },
    rejected:  { bg: 'rgba(239,68,68,0.12)',   color: '#f87171', border: 'rgba(239,68,68,0.3)'   },
    endorsed:  { bg: 'rgba(168,85,247,0.12)',  color: '#c084fc', border: 'rgba(168,85,247,0.3)'  },
    success:   { bg: 'rgba(16,185,129,0.12)',  color: '#10b981', border: 'rgba(16,185,129,0.3)'  },
    failed:    { bg: 'rgba(239,68,68,0.12)',   color: '#f87171', border: 'rgba(239,68,68,0.3)'   },
    running:   { bg: 'rgba(59,130,246,0.12)',  color: '#3b82f6', border: 'rgba(59,130,246,0.3)'  },
};
```

---

## 3. Typography

### Font Stack

```css
font-family: 'Inter', system-ui, sans-serif;
```

Import via bunny fonts in auth pages:
```css
@import url('https://fonts.bunny.net/css?family=inter:400,500,600,700,800');
```

### Scale

| Use | Size | Weight |
|---|---|---|
| Page title / card heading | `1rem` – `1.05rem` | `700` |
| Section label | `0.95rem` | `700` |
| Body text | `0.875rem` – `0.9rem` | `400–500` |
| Small label / meta | `0.78rem` – `0.82rem` | `500–600` |
| Badge / pill label | `0.65rem` – `0.72rem` | `700` |
| Uppercase section header | `0.62rem` – `0.68rem` | `700`, `letter-spacing: 0.06–0.09em`, `text-transform: uppercase` |

---


## 4. Layout Structure

### Overall Shell

```
┌─────────────────────────────────────────────┐
│  Sidebar (fixed, 280px / 68px collapsed)    │
│  ┌───────────────────────────────────────┐  │
│  │  Topbar (sticky, ~44px)               │  │
│  ├───────────────────────────────────────┤  │
│  │  <main> Page content                  │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

On mobile (`< 768px`): sidebar becomes a slide-in overlay, main takes full width.

### AppLayout.jsx

```jsx
// Layouts/AppLayout.jsx
import { useState, useEffect, useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import { ToastProvider } from '@/Components/Snackbar';
import { ConfirmProvider } from '@/Components/ConfirmDialog';

export default function AppLayout({ children, title, description }) {
    const [darkMode, setDarkMode] = useState(
        () => (localStorage.getItem('theme') ?? 'dark') === 'dark'
    );
    const [collapsed, setCollapsed] = useState(
        () => localStorage.getItem('sb-collapsed') === '1'
    );
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light');
        localStorage.setItem('theme', darkMode ? 'dark' : 'light');
    }, [darkMode]);

    useEffect(() => {
        const t = setTimeout(() => window.dispatchEvent(new Event('resize')), 220);
        return () => clearTimeout(t);
    }, [collapsed]);

    useEffect(() => {
        const h = () => { if (window.innerWidth >= 768) setMobileOpen(false); };
        window.addEventListener('resize', h);
        return () => window.removeEventListener('resize', h);
    }, []);

    const sidebarWidth = collapsed ? 68 : 280;

    return (
        <ToastProvider>
          <ConfirmProvider>
            <div style={{ display: 'flex', minHeight: '100vh' }}>
                {/* Mobile backdrop */}
                {mobileOpen && (
                    <div onClick={() => setMobileOpen(false)} style={{
                        position: 'fixed', inset: 0, zIndex: 999,
                        background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(2px)'
                    }} />
                )}

                <Sidebar
                    collapsed={collapsed}
                    onToggle={() => setCollapsed(v => {
                        const next = !v;
                        localStorage.setItem('sb-collapsed', next ? '1' : '0');
                        return next;
                    })}
                    mobileOpen={mobileOpen}
                    onMobileClose={() => setMobileOpen(false)}
                />

                <div className="app-main" style={{
                    marginLeft: sidebarWidth, flex: 1,
                    display: 'flex', flexDirection: 'column',
                    transition: 'margin-left 0.2s ease', minWidth: 0
                }}>
                    <Topbar
                        title={title}
                        description={description}
                        darkMode={darkMode}
                        onToggleDarkMode={() => setDarkMode(v => !v)}
                        onMobileMenuToggle={() => setMobileOpen(v => !v)}
                    />
                    <main className="admin-content">
                        {children}
                    </main>
                </div>
            </div>

            <style>{`
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: 'Inter', system-ui, sans-serif;
                    color: var(--admin-text-primary);
                    min-height: 100vh;
                    background:
                        radial-gradient(circle at top left, rgba(59,130,246,0.12), transparent 26%),
                        linear-gradient(180deg, var(--admin-bg-primary) 0%, var(--admin-bg-secondary) 100%);
                }
                .admin-content { flex: 1; padding: 1rem 1.5rem; overflow: auto; }

                @media (max-width: 767px) {
                    .app-main { margin-left: 0 !important; }
                    .admin-content { padding: 0.75rem 1rem; }
                }
            `}</style>
          </ConfirmProvider>
        </ToastProvider>
    );
}
```

### GuestLayout.jsx (Auth pages)

```jsx
// Layouts/GuestLayout.jsx
export default function GuestLayout({ children }) {
    return (
        <div style={{
            minHeight: '100vh',
            background: 'var(--admin-bg-primary)',
            fontFamily: "'Inter', system-ui, sans-serif",
        }}>
            {children}
        </div>
    );
}
```

### Page usage

Every page wraps its content in AppLayout:

```jsx
export default function MyPage() {
    return (
        <AppLayout title="Page Title" description="Section / Sub-section">
            {/* content */}
        </AppLayout>
    );
}
```

- `title` → shown bold in topbar breadcrumb (current page)
- `description` → intermediate breadcrumb segments, separated by `/` or `>`

---

## 5. Authentication Pages

### Split-screen layout

```
┌──────────────────┬────────────────────┐
│  Hero panel      │  Form panel        │
│  (slideshow bg)  │  (login card)      │
│  hidden mobile   │  full width mobile │
└──────────────────┴────────────────────┘
```

- Left: full-height hero with photo slideshow, dark overlay, branding, feature bullets
- Right: centered card with form (max-width 420px)
- Dark mode toggle fixed top-right

### Auth card pattern

```jsx
<div style={{
    width: '100%',
    maxWidth: 420,
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 'var(--admin-radius)',
    boxShadow: 'var(--admin-shadow)',
    padding: '2.5rem 2rem',
}}>
    {/* header */}
    <div style={{ marginBottom: '2rem', paddingBottom: '1.25rem', borderBottom: '1px solid var(--admin-border)' }}>
        <h4 style={{ fontWeight: 700, fontSize: '1.5rem', color: 'var(--admin-text-primary)', letterSpacing: '-0.02em' }}>
            Sign in
        </h4>
        <p style={{ fontSize: '0.875rem', color: 'var(--admin-text-muted)' }}>
            Enter your credentials to continue
        </p>
    </div>
    <form>...</form>
</div>
```

### Form field pattern

```jsx
function Field({ label, icon, error, children }) {
    return (
        <div style={{ marginBottom: '1rem' }}>
            <label style={{
                display: 'flex', alignItems: 'center', gap: '0.35rem',
                fontSize: '0.82rem', fontWeight: 600,
                color: 'var(--admin-text-secondary)', marginBottom: '0.4rem'
            }}>
                {icon && <span style={{ color: 'var(--admin-accent)', display: 'flex' }}>{icon}</span>}
                {label}
            </label>
            {children}
            {error && <p style={{ fontSize: '0.78rem', color: '#ef4444', marginTop: '0.3rem' }}>{error}</p>}
        </div>
    );
}
```

### Input style function

```js
const inputStyle = (hasError = false) => ({
    width: '100%',
    padding: '0.6rem 0.85rem',
    fontSize: '0.9rem',
    background: 'var(--admin-bg-secondary)',
    color: 'var(--admin-text-primary)',
    border: `1px solid ${hasError ? '#ef4444' : 'var(--admin-border-strong)'}`,
    borderRadius: 'var(--admin-radius)',
    transition: 'border-color 0.15s, box-shadow 0.15s',
});
// focus via CSS: input:focus { outline: none; border-color: var(--admin-accent) !important; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
```

### Button styles

```js
const primaryBtn = {
    width: '100%', padding: '0.7rem',
    fontSize: '0.92rem', fontWeight: 600,
    background: 'var(--admin-accent)', color: '#fff',
    border: 'none', borderRadius: 'var(--admin-radius)',
    cursor: 'pointer', transition: 'opacity 0.15s',
};

const ghostBtn = {
    width: '100%', padding: '0.65rem',
    fontSize: '0.88rem', fontWeight: 500,
    background: 'transparent', color: 'var(--admin-text-secondary)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 'var(--admin-radius)', cursor: 'pointer',
};

const outlineBtn = {
    ...ghostBtn,
    color: 'var(--admin-accent)',
    borderColor: 'var(--admin-accent)',
};
```

### Slideshow (hero panel)

```jsx
const slides = ['/slides/1.png', '/slides/2.png', '/slides/3.png'];
const [activeSlide, setActiveSlide] = useState(0);

useEffect(() => {
    const interval = setInterval(() => setActiveSlide(i => (i + 1) % slides.length), 6000);
    return () => clearInterval(interval);
}, []);

// CSS:
// .auth-split-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1s ease; }
// .auth-split-slide.is-active { opacity: 1; }
// .auth-split-slide.is-active img { transform: scale(1.02); }
// .auth-split-slideshow-overlay { position: absolute; inset: 0; z-index: 1;
//   background: linear-gradient(180deg, rgba(4,10,24,0.54) 0%, rgba(7,14,30,0.68) 100%); }
```

---


## 6. Sidebar

### Behavior
- Fixed left, `z-index: 1000`
- Desktop expanded: `280px` | Collapsed: `68px`
- Collapse state persisted in `localStorage` key `'sb-collapsed'`
- Mobile (`< 768px`): overlay slide-in via `transform: translateX(-100%)` → `translateX(0)`
- Active link detection: `url === href || url.startsWith(href + '/')`

### Structure

```jsx
<aside className={`admin-sidebar${collapsed ? ' sb-collapsed' : ''}${mobileOpen ? ' sb-mobile-open' : ''}`}>
    {/* Brand */}
    <div className="sb-brand">
        <i className={`bi ${roleIcon} sb-brand-icon`} />
        {showFull && <div className="sb-brand-text">
            <div className="sb-app-name">Your App Name</div>
            <div className="sb-sub">{roleLabel}</div>
        </div>}
        <button className="sb-toggle sb-desktop-only" onClick={onToggle}>
            <i className={`bi ${collapsed ? 'bi-chevron-right' : 'bi-chevron-left'}`} />
        </button>
        <button className="sb-toggle sb-mobile-only" onClick={onMobileClose}>
            <i className="bi bi-x-lg" />
        </button>
    </div>

    {/* Nav links */}
    <nav className="sb-nav">
        {links.map(({ href, label, icon }) => (
            <Link key={href} href={href}
                className={`sb-link${isActive(href) ? ' sb-link-active' : ''}`}>
                <i className={`bi ${icon} sb-link-icon`} />
                {showFull && <span>{label}</span>}
            </Link>
        ))}
    </nav>
</aside>
```

### Key CSS classes (inject via `<style>` in Sidebar component)

```css
.admin-sidebar {
    position: fixed; top: 0; left: 0; bottom: 0; width: 280px;
    padding: 1.5rem 1rem;
    background: linear-gradient(180deg, var(--admin-sidebar), rgba(8,14,22,0.98));
    border-right: 1px solid var(--admin-border);
    backdrop-filter: blur(16px);
    display: flex; flex-direction: column; gap: 1rem;
    overflow-y: auto; z-index: 1000;
    transition: width 0.2s ease, padding 0.2s ease;
}
.admin-sidebar.sb-collapsed { width: 68px; padding: 1.5rem 0.5rem; }

.sb-brand { display: flex; align-items: center; gap: 0.75rem;
    padding: 0.25rem 0.25rem 1rem; border-bottom: 1px solid var(--admin-border); }
.sb-brand-icon { font-size: 1.75rem; color: var(--admin-accent); }
.sb-app-name { font-weight: 700; font-size: 1rem; color: var(--admin-text-primary); }
.sb-sub { font-size: 0.72rem; color: var(--admin-text-muted); }

.sb-toggle {
    margin-left: auto; background: rgba(59,130,246,0.08);
    border: 1px solid var(--admin-border); border-radius: 8px;
    width: 28px; height: 28px; display: flex; align-items: center;
    justify-content: center; cursor: pointer; color: var(--admin-text-muted);
}
.sb-toggle:hover { background: rgba(59,130,246,0.18); color: var(--admin-accent); }

.sb-nav { display: flex; flex-direction: column; gap: 0.15rem; flex: 1; }

.sb-link {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.8rem 0.95rem; border-radius: 12px;
    color: var(--admin-text-secondary); text-decoration: none;
    font-size: 0.875rem; font-weight: 500;
    border: 1px solid transparent;
    transition: background 0.15s, color 0.15s;
}
.sb-link:hover { background: rgba(59,130,246,0.08); color: var(--admin-text-primary); }
.sb-link-active {
    background: rgba(59,130,246,0.12);
    border-color: rgba(59,130,246,0.22);
    color: var(--admin-accent);
}
.sb-link-icon { font-size: 1.1rem; width: 1.25rem; text-align: center; flex-shrink: 0; }

/* Mobile */
@media (max-width: 767px) {
    .admin-sidebar { transform: translateX(-100%); transition: transform 0.22s ease; width: 280px !important; }
    .admin-sidebar.sb-mobile-open { transform: translateX(0); }
    .sb-desktop-only { display: none !important; }
}
@media (min-width: 768px) { .sb-mobile-only { display: none !important; } }
```

### Role-based nav links pattern

```js
const roleLinks = {
    admin: [
        { href: '/administrator', label: 'Dashboard', icon: 'bi-grid-1x2-fill' },
        { href: '/administrator/users', label: 'Users', icon: 'bi-people-fill' },
        // ...
    ],
    'dept-head': [ /* ... */ ],
    employee: [ /* ... */ ],
};

const roleHeaders = {
    admin:     { icon: 'bi-shield-lock-fill', label: 'Admin Portal' },
    employee:  { icon: 'bi-person-fill',      label: 'Employee Portal' },
};
```

---

## 7. Topbar / Navbar

### Structure

```
┌─────────────────────────────────────────────────────────────────┐
│ [≡ hamburger]  [🏠 Home › Section › Current Page]    [🔔] [👤▾] │
└─────────────────────────────────────────────────────────────────┘
```

- Sticky top, `z-index: 900`
- `backdrop-filter: blur(16px)`
- Background: `rgba(10,15,26,0.88)` dark / `var(--admin-sidebar)` light

### Breadcrumb

```jsx
function Breadcrumb({ title, description }) {
    const segments = description
        ? description.split(/[\/\·>]+/).map(s => s.trim()).filter(Boolean)
        : [];

    return (
        <nav style={{ display: 'flex', alignItems: 'center', fontSize: '0.78rem' }}>
            <Link href="/" className="tb-bc-home" style={{ display: 'flex', alignItems: 'center', gap: 4, color: 'var(--admin-text-muted)', textDecoration: 'none' }}>
                <i className="bi bi-house-door" style={{ fontSize: '0.72rem' }} />
                <span>Home</span>
            </Link>
            {segments.map((seg, i) => (
                <span key={i} style={{ display: 'flex', alignItems: 'center' }}>
                    <i className="bi bi-chevron-right" style={{ fontSize: '0.58rem', color: 'var(--admin-text-muted)', margin: '0 3px' }} />
                    <span style={{ color: 'var(--admin-text-muted)', fontWeight: 500 }}>{seg}</span>
                </span>
            ))}
            <span style={{ display: 'flex', alignItems: 'center' }}>
                <i className="bi bi-chevron-right" style={{ fontSize: '0.58rem', color: 'var(--admin-text-muted)', margin: '0 3px' }} />
                <span style={{ color: 'var(--admin-text-primary)', fontWeight: 700, fontSize: '0.8rem' }}>{title}</span>
            </span>
        </nav>
    );
}
```

### Mobile breadcrumb fix

On mobile, hide Home link and past segments — show only the current page title with truncation:

```css
@media (max-width: 767px) {
    .tb-hamburger { display: flex; }
    .tb-root { padding: 0.4rem 0.85rem; }
    .tb-info { display: none; }          /* user name/role in pill */
    .tb-bc-home { display: none; }       /* hide Home link */
    .tb-bc-past { display: none; }       /* hide intermediate segments */
    .tb-bc-sep  { display: none; }       /* hide all separators */
    .tb-bc-current {
        white-space: nowrap; overflow: hidden;
        text-overflow: ellipsis; max-width: 40vw; display: block;
    }
}
```

### User pill (topbar right)

```jsx
<button style={{
    display: 'flex', alignItems: 'center', gap: '0.5rem',
    background: 'var(--admin-card)', border: '1px solid var(--admin-border)',
    borderRadius: 50, padding: '0.2rem 0.65rem 0.2rem 0.2rem', cursor: 'pointer',
}}>
    <div style={{ width: 28, height: 28, borderRadius: '50%', overflow: 'hidden',
        border: '2px solid rgba(59,130,246,0.45)', flexShrink: 0 }}>
        <img src={avatar} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
    </div>
    <div className="tb-info" style={{ display: 'flex', flexDirection: 'column', textAlign: 'left' }}>
        <span style={{ fontSize: '0.78rem', fontWeight: 600, color: 'var(--admin-text-primary)' }}>{name}</span>
        <span style={{ fontSize: '0.65rem', color: 'var(--admin-text-muted)', textTransform: 'capitalize' }}>{role}</span>
    </div>
    <i className="bi bi-chevron-down" style={{ fontSize: '0.65rem', color: 'var(--admin-text-muted)' }} />
</button>
```

---

## 8. Reusable Components

### 8.1 StatusBadge / Pill

```jsx
function StatusBadge({ status }) {
    const map = {
        draft:     { label: 'Draft',     bg: 'rgba(100,116,139,0.15)', color: '#94a3b8', border: 'rgba(100,116,139,0.3)' },
        submitted: { label: 'Submitted', bg: 'rgba(37,99,235,0.12)',   color: '#60a5fa', border: 'rgba(37,99,235,0.3)'   },
        approved:  { label: 'Approved',  bg: 'rgba(5,150,105,0.12)',   color: '#34d399', border: 'rgba(5,150,105,0.3)'   },
        returned:  { label: 'Returned',  bg: 'rgba(239,68,68,0.12)',   color: '#f87171', border: 'rgba(239,68,68,0.3)'   },
    };
    const c = map[status] ?? map.draft;
    return (
        <span style={{
            padding: '0.15rem 0.6rem', borderRadius: 99,
            fontSize: '0.65rem', fontWeight: 700,
            letterSpacing: '0.06em', textTransform: 'uppercase',
            background: c.bg, color: c.color, border: `1px solid ${c.border}`,
            whiteSpace: 'nowrap',
        }}>
            {c.label}
        </span>
    );
}
```

### 8.2 Toast / Snackbar

```jsx
// Components/Snackbar.jsx
// Provides ToastProvider (wrap AppLayout children) and useToast() hook

import { createContext, useContext, useState, useCallback, useRef } from 'react';
const ToastCtx = createContext(null);

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const counter = useRef(0);
    const toast = useCallback((message, type = 'info', duration = 3500) => {
        const id = ++counter.current;
        setToasts(t => [...t, { id, message, type }]);
        setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), duration);
    }, []);
    return (
        <ToastCtx.Provider value={toast}>
            {children}
            {/* Render toasts fixed bottom-right */}
        </ToastCtx.Provider>
    );
}
export const useToast = () => useContext(ToastCtx);

// Usage:
// const toast = useToast();
// toast('Saved successfully!', 'success');
// toast('Something went wrong.', 'error');
// toast('Please review.', 'warning');
```

Toast type → color map:
```js
const TOAST_STYLES = {
    success:  { bg: 'rgba(22,163,74,0.95)',  border: '#16a34a', icon: '✓' },
    error:    { bg: 'rgba(220,38,38,0.95)',  border: '#dc2626', icon: '✕' },
    warning:  { bg: 'rgba(202,138,4,0.95)', border: '#ca8a04', icon: '⚠' },
    info:     { bg: 'rgba(37,99,235,0.95)', border: '#2563eb', icon: 'ℹ' },
};
// Toast entry style: bottom-right fixed, slide up + fade in on mount
// { opacity: visible ? 1 : 0, transform: visible ? 'translateY(0)' : 'translateY(12px)', transition: 'opacity 0.2s, transform 0.2s' }
```

### 8.3 ConfirmDialog

```jsx
// Components/ConfirmDialog.jsx
// Provides ConfirmProvider and useConfirm() hook (returns a Promise<boolean>)

export function ConfirmProvider({ children }) { /* ... */ }
export const useConfirm = () => useContext(ConfirmCtx);

// Usage:
// const confirm = useConfirm();
// const ok = await confirm('Are you sure you want to delete this?');
// if (ok) { /* proceed */ }
```

Dialog style:
```js
// Overlay + centered card
{
    overlay: { position: 'fixed', inset: 0, zIndex: 9000, background: 'rgba(0,0,0,0.6)',
               display: 'flex', alignItems: 'center', justifyContent: 'center' },
    dialog:  { background: 'var(--admin-card)', border: '1px solid var(--admin-border-strong)',
               borderRadius: 14, padding: '1.75rem 2rem', maxWidth: 400, width: '90%',
               boxShadow: '0 24px 60px rgba(0,0,0,0.5)' },
    cancel:  { padding: '0.5rem 1.25rem', borderRadius: 8, border: '1px solid var(--admin-border-strong)',
               background: 'transparent', color: 'var(--admin-text-secondary)', fontWeight: 600 },
    confirm: { padding: '0.5rem 1.25rem', borderRadius: 8, border: 'none',
               background: '#ef4444', color: '#fff', fontWeight: 600 },
}
```

### 8.4 ReturnRemarksBanner

Shown when a record has been returned with remarks:

```jsx
export default function ReturnRemarksBanner({ remarks, label = 'Returned' }) {
    const [dismissed, setDismissed] = useState(false);
    if (!remarks || dismissed) return null;
    return (
        <div style={{
            display: 'flex', alignItems: 'flex-start', gap: '0.75rem',
            background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.3)',
            borderRadius: 10, padding: '0.85rem 1rem', marginBottom: '1rem',
        }}>
            <span style={{ fontSize: '1rem', marginTop: 2 }}>↩</span>
            <div style={{ flex: 1 }}>
                <span style={{ fontSize: '0.75rem', fontWeight: 700, color: '#f87171',
                    textTransform: 'uppercase', letterSpacing: '0.06em' }}>{label}</span>
                <p style={{ margin: '0.25rem 0 0', fontSize: '0.85rem',
                    color: 'var(--admin-text-primary)', lineHeight: 1.6 }}>{remarks}</p>
            </div>
            <button onClick={() => setDismissed(true)}
                style={{ background: 'none', border: 'none', cursor: 'pointer',
                    color: 'var(--admin-text-muted)', fontSize: '1rem' }}>✕</button>
        </div>
    );
}
```

### 8.5 Card pattern

Every content section sits in a card:

```jsx
const card = {
    background: 'var(--admin-card)',
    border: '1px solid var(--admin-border-strong)',
    borderRadius: 'var(--admin-radius)',   // 12px
    padding: '1.25rem',
    boxShadow: 'var(--admin-shadow)',
};

// Larger card (detail pages)
const cardLg = {
    ...card,
    borderRadius: 'var(--admin-radius-lg)',  // 18px
    overflow: 'clip',
};
```

### 8.6 Modal overlay pattern

```jsx
// All modals use this overlay + modal shell
const modalStyles = {
    overlay: {
        position: 'fixed', inset: 0, zIndex: 9500,
        background: 'rgba(0,0,0,0.6)',
        display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem',
    },
    modal: {
        background: 'var(--admin-card)',
        border: '1px solid var(--admin-border-strong)',
        borderRadius: 14,
        width: '100%', maxWidth: 520, maxHeight: '85vh',
        display: 'flex', flexDirection: 'column',
        boxShadow: '0 24px 60px rgba(0,0,0,0.5)',
    },
    header: {
        display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start',
        padding: '1.1rem 1.25rem', borderBottom: '1px solid var(--admin-border)',
    },
    footer: {
        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        padding: '0.85rem 1.25rem', borderTop: '1px solid var(--admin-border)',
    },
};
// Close on backdrop click: onClick={e => e.target === e.currentTarget && onClose()}
```

### 8.7 Sticky page header bar

Used on detail pages (show pages with tabs):

```jsx
<div style={{
    position: 'sticky', top: 0, zIndex: 40,
    background: 'var(--admin-card)',
    borderBottom: '1px solid var(--admin-border)',
    padding: '0.6rem 1rem',
}}>
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem' }}>
        {/* Left: back button + title + status badge */}
        {/* Right: action buttons */}
    </div>
</div>
```

### 8.8 Tabs

```jsx
// Tab bar
<div style={{ display: 'flex', gap: '0.35rem', borderBottom: '1px solid var(--admin-border)' }}>
    {tabs.map(([key, icon, label]) => (
        <button key={key} onClick={() => setTab(key)} style={{
            display: 'inline-flex', alignItems: 'center', gap: '0.4rem',
            padding: '0.6rem 1rem', fontSize: '0.82rem', fontWeight: 600,
            background: 'none', border: 'none', cursor: 'pointer',
            borderBottom: `2px solid ${tab === key ? 'var(--admin-accent)' : 'transparent'}`,
            color: tab === key ? 'var(--admin-accent)' : 'var(--admin-text-muted)',
            marginBottom: '-1px', transition: 'color 0.15s',
        }}>
            <i className={`bi ${icon}`} />
            {label}
        </button>
    ))}
</div>
```

### 8.9 useBreakpoint hook

```js
// Components/useBreakpoint.js
import { useState, useEffect } from 'react';

export default function useBreakpoint() {
    const [w, setW] = useState(() => window.innerWidth);
    useEffect(() => {
        const h = () => setW(window.innerWidth);
        window.addEventListener('resize', h);
        return () => window.removeEventListener('resize', h);
    }, []);
    if (w >= 1024) return 'desktop';
    if (w >= 768)  return 'tablet';
    return 'mobile';
}

// Usage:
// const bp = useBreakpoint();
// if (bp === 'mobile') { ... }
```

### 8.10 defaultAvatar utility

```js
// Components/defaultAvatar.js
export const avatarSrc = (avatar, photoUrl) =>
    avatar
        ? (avatar.startsWith('http') ? avatar : `/storage/${avatar}`)
        : (photoUrl ?? '/images/default-avatar.png');

export const onAvatarError = (e) => {
    e.target.src = '/images/default-avatar.png';
};
```

### 8.11 Section label / panel label pattern

```jsx
// Uppercase muted label used above content groups
<div style={{
    padding: '0 1rem 0.65rem',
    fontSize: '0.62rem', fontWeight: 700,
    letterSpacing: '0.09em', color: 'var(--admin-text-muted)',
    textTransform: 'uppercase',
}}>
    SECTION TITLE
</div>
```

---


## 9. Icons

Two icon sources are used — never mix with any third-party icon library:

### Bootstrap Icons (primary)

Used for nav links, buttons, and UI chrome. Load via CDN in `app.blade.php`:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

Usage: `<i className="bi bi-grid-1x2-fill" />`

Common icons used:

| Purpose | Class |
|---|---|
| Dashboard | `bi-grid-1x2-fill` |
| Users | `bi-people-fill` |
| Building/Office | `bi-building-fill` |
| Document/Form | `bi-file-earmark-text-fill` |
| Checklist | `bi-clipboard-check-fill` |
| Award | `bi-award-fill` |
| Chart | `bi-bar-chart-steps` |
| Profile | `bi-person-badge-fill` |
| Settings | `bi-gear-fill` |
| Bell (notification) | `bi-bell-fill` |
| Hamburger | `bi-list` |
| Close | `bi-x-lg` |
| Chevron left/right | `bi-chevron-left` / `bi-chevron-right` |
| Save/floppy | `bi-floppy-fill` |
| Download | `bi-download` |
| Upload | `bi-upload` / `bi-cloud-upload` |
| Refresh | `bi-arrow-clockwise` |
| CPU/ML | `bi-cpu-fill` |
| Database | `bi-hdd-stack-fill` |
| Clock/History | `bi-clock-history` |
| House | `bi-house-door` |
| IDP/Bookmark | `bi-journal-bookmark-fill` |
| Kanban | `bi-kanban-fill` |
| Activity | `bi-activity` |

### Inline SVGs (secondary)

Used for custom icons in action buttons, feature bullets, and contextual UI. Always `width="13-18"`, `fill="none"`, `stroke="currentColor"`, `strokeWidth="2"`, `strokeLinecap="round"`.

Common patterns:
```jsx
// Back arrow
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
    <polyline points="15 18 9 12 15 6"/>
</svg>

// Send / Submit
<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
</svg>

// Download
<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
</svg>

// Warning triangle
<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
</svg>

// People / contributors
<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
</svg>

// Spinner (animated)
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--admin-accent)" strokeWidth="2.5"
    strokeLinecap="round" style={{ animation: 'spin 0.7s linear infinite' }}>
    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
</svg>
// @keyframes spin { to { transform: rotate(360deg); } }
```

---

## 10. Responsive Breakpoints

| Breakpoint | Width | Behavior |
|---|---|---|
| Mobile | `< 768px` | Sidebar overlay, bottom action bars, icon-only buttons, breadcrumb = title only |
| Tablet | `768px – 1023px` | Sidebar visible, compact layouts, some labels hidden |
| Desktop | `≥ 1024px` | Full sidebar, full labels, side-by-side panels |

### useBreakpoint return values

```js
'mobile'  // < 768px
'tablet'  // 768–1023px
'desktop' // ≥ 1024px
```

### Mobile-specific patterns

**Sticky bottom action bar** (replace top-right actions on mobile):
```jsx
{bp === 'mobile' && (
    <div style={{
        position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 99,
        background: 'var(--admin-card)', borderTop: '1px solid var(--admin-border)',
        padding: '0.75rem 1rem',
        display: 'flex', gap: '0.5rem', justifyContent: 'flex-end', alignItems: 'center',
    }}>
        <Actions />
    </div>
)}
```

**Icon-only buttons on mobile/tablet:**
```jsx
<button style={btnStyle} title="Export OPCR">
    <DownloadIcon />
    {bp === 'desktop' && 'Export OPCR'}
</button>
```

**Grid → single column on mobile:**
```jsx
<div style={{ display: 'grid', gridTemplateColumns: bp === 'mobile' ? '1fr' : '1fr 1fr', gap: '1rem' }}>
```

---

## 11. Animation & Transitions

### Keyframes (inject once per component that needs them)

```css
@keyframes spin       { to { transform: rotate(360deg); } }
@keyframes slideUp    { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn     { from { opacity: 0; } to { opacity: 1; } }
```

### Transition defaults

```js
// Sidebar width
transition: 'width 0.2s ease, padding 0.2s ease'

// Color/background hover
transition: 'background 0.15s, color 0.15s'

// Border focus
transition: 'border-color 0.15s, box-shadow 0.15s'

// Margin/layout shift
transition: 'margin-left 0.2s ease'

// Opacity
transition: 'opacity 0.15s'
```

### Hover pattern (buttons)

```css
button:hover:not(:disabled) { opacity: 0.88; }
button:disabled { opacity: 0.55; cursor: not-allowed; }
```

---

## 12. Auth Flow — Laravel Fortify + Spatie RBAC

### Overview

```
New employee created by Admin
        ↓
Account is inactive (no password set)
        ↓
Employee receives email with employee_id
        ↓
Goes to /login → clicks "Activate PMS Account"
        ↓
Step 1: Enter employee_id + email → POST /send/id
        ↓
System verifies HRIS match → sends activation token to email
        ↓
Step 2: Enter token + set password + optional photo → POST /activate/complete
        ↓
Account activated, can now log in normally
```

### Login page modes (single-page, mode-switched)

```js
// All modes handled in one Login.jsx component, no separate pages
const [mode, setMode] = useState('login'); // 'login' | 'activate-verify' | 'activate-complete' | 'forgot'
```

| Mode | Fields | Submits to |
|---|---|---|
| `login` | name, password | `POST /login` (Fortify) |
| `activate-verify` | employee_id, email | `POST /send/id` (custom) |
| `activate-complete` | token (hidden), password, password_confirmation, profile_photo | `POST /activate/complete` |
| `forgot` | email | `POST /forgot-password` (Fortify) |

### Routes (web.php)

```php
// Auth (Fortify handles /login, /logout, /forgot-password, /reset-password)
Route::post('/send/id', [ActivationController::class, 'sendId']);
Route::post('/activate/complete', [ActivationController::class, 'complete']);

// Role-based portals (Spatie middleware)
Route::prefix('administrator')->middleware(['auth', 'role:admin'])->group(function () { ... });
Route::prefix('dept-head')->middleware(['auth', 'role:dept-head'])->group(function () { ... });
Route::prefix('supervisor')->middleware(['auth', 'role:supervisor'])->group(function () { ... });
Route::prefix('employee')->middleware(['auth', 'role:employee'])->group(function () { ... });
Route::prefix('pmt')->middleware(['auth', 'role:pmt'])->group(function () { ... });
```

### Role redirect after login (Fortify)

```php
// In FortifyServiceProvider or AuthenticatedSessionController
protected function redirectTo(Request $request): string
{
    $role = $request->user()->getRoleNames()->first();
    return match ($role) {
        'admin'     => '/administrator',
        'dept-head' => '/dept-head',
        'supervisor'=> '/supervisor',
        'pmt'       => '/pmt',
        default     => '/employee',
    };
}
```

### Spatie roles used

| Role slug | Portal |
|---|---|
| `admin` | `/administrator` |
| `dept-head` | `/dept-head` |
| `supervisor` | `/supervisor` |
| `pmt` | `/pmt` |
| `employee` | `/employee` |

### Inertia shared props

Pass auth user + role to every page via `HandleInertiaRequests` middleware:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user() ? [
                'id'                => $request->user()->id,
                'name'              => $request->user()->name,
                'email'             => $request->user()->email,
                'avatar'            => $request->user()->avatar,
                'profile_photo_url' => $request->user()->profile_photo_url,
                'roles'             => $request->user()->getRoleNames(),
            ] : null,
        ],
        'flash' => [
            'just_logged_in' => $request->session()->pull('just_logged_in', false),
        ],
    ];
}
```

Access in React:
```js
const { auth } = usePage().props;
const user = auth?.user;
const role = user?.roles?.[0]; // 'admin', 'dept-head', etc.
```

### Login loading screen

After successful login, flash `just_logged_in = true` in session. AppLayout detects this and shows a full-screen branded loader before mounting content:

```php
// In login controller after authentication:
$request->session()->put('just_logged_in', true);
```

```jsx
// In AppLayout:
const [showLoader] = useState(() => !!page?.props?.flash?.just_logged_in);
// Render <LoginLoadingScreen /> overlay if showLoader is true
```

---

## 13. Summary Checklist for New System

When building a new system (e.g. Learning & Development) that must match this design:

- [ ] Copy the CSS variables into `app.css` or AppLayout
- [ ] Use `Inter` font
- [ ] Build `AppLayout.jsx` with Sidebar + Topbar + `ToastProvider` + `ConfirmProvider`
- [ ] Build `Sidebar.jsx` with role-based links and collapse behavior
- [ ] Build `Topbar.jsx` with breadcrumb, user pill, dark mode toggle
- [ ] Copy `Snackbar.jsx` (ToastProvider + useToast)
- [ ] Copy `ConfirmDialog.jsx` (ConfirmProvider + useConfirm)
- [ ] Copy `useBreakpoint.js`
- [ ] Copy `defaultAvatar.js`
- [ ] Use Bootstrap Icons CDN — no other icon library
- [ ] All styling via inline `style={{}}` — no Tailwind
- [ ] Auth: Laravel Fortify for login/reset, custom activation flow (send/id → activate/complete)
- [ ] Spatie roles with `role:rolename` middleware on route groups
- [ ] Share `auth.user` + `auth.user.roles` via `HandleInertiaRequests`
- [ ] Match breakpoints: mobile `<768`, tablet `768–1023`, desktop `≥1024`
- [ ] Mobile: sidebar overlay, bottom action bar, icon-only buttons, truncated breadcrumb


## 9. Icons

Two sources only — never use any third-party icon library:

### Bootstrap Icons (primary)

Load via CDN in `app.blade.php`:
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```
Usage: `<i className="bi bi-grid-1x2-fill" />`

| Purpose | Class |
|---|---|
| Dashboard | `bi-grid-1x2-fill` |
| Users / People | `bi-people-fill` |
| Office / Building | `bi-building-fill` |
| Clipboard / Review | `bi-clipboard-check-fill` |
| Award | `bi-award-fill` |
| Chart | `bi-bar-chart-steps` |
| Profile | `bi-person-badge-fill` |
| Bell | `bi-bell-fill` |
| Hamburger | `bi-list` |
| Close | `bi-x-lg` |
| Chevrons | `bi-chevron-left` / `bi-chevron-right` |
| Save | `bi-floppy-fill` |
| Download | `bi-download` |
| Upload | `bi-upload` / `bi-cloud-upload` |
| Refresh | `bi-arrow-clockwise` |
| History | `bi-clock-history` |
| Kanban | `bi-kanban-fill` |
| IDP | `bi-journal-bookmark-fill` |
| CPU/ML | `bi-cpu-fill` |
| Database | `bi-hdd-stack-fill` |
| House | `bi-house-door` |

### Inline SVGs (secondary)

Always: `width="13–18"`, `fill="none"`, `stroke="currentColor"`, `strokeWidth="2"`, `strokeLinecap="round"`.

```jsx
// Download
<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
    <polyline points="7 10 12 15 17 10"/>
    <line x1="12" y1="15" x2="12" y2="3"/>
</svg>

// Back arrow
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
    <polyline points="15 18 9 12 15 6"/>
</svg>

// Send / Submit
<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="22" y1="2" x2="11" y2="13"/>
    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
</svg>

// Spinner (loading)
<svg width="18" height="18" viewBox="0 0 24 24" fill="none"
    stroke="var(--admin-accent)" strokeWidth="2.5" strokeLinecap="round"
    style={{ animation: 'spin 0.7s linear infinite' }}>
    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
</svg>
// @keyframes spin { to { transform: rotate(360deg); } }
```

---

## 10. Responsive Breakpoints

| Name | Width | Key behaviors |
|---|---|---|
| Mobile | `< 768px` | Sidebar = overlay, breadcrumb = title only, icon-only buttons, bottom action bar |
| Tablet | `768–1023px` | Sidebar visible, compact layouts |
| Desktop | `≥ 1024px` | Full sidebar + labels, side panels |

### useBreakpoint hook

```js
// Components/useBreakpoint.js
import { useState, useEffect } from 'react';
export default function useBreakpoint() {
    const [w, setW] = useState(() => window.innerWidth);
    useEffect(() => {
        const h = () => setW(window.innerWidth);
        window.addEventListener('resize', h);
        return () => window.removeEventListener('resize', h);
    }, []);
    if (w >= 1024) return 'desktop';
    if (w >= 768)  return 'tablet';
    return 'mobile';
}
```

### Mobile action bar (replace top-right buttons)

```jsx
{bp === 'mobile' && (
    <div style={{
        position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 99,
        background: 'var(--admin-card)', borderTop: '1px solid var(--admin-border)',
        padding: '0.75rem 1rem', display: 'flex', gap: '0.5rem',
        justifyContent: 'flex-end', alignItems: 'center',
    }}>
        <Actions />
    </div>
)}
```

### Icon-only on mobile/tablet

```jsx
<button title="Export">
    <DownloadIcon />
    {bp === 'desktop' && 'Export'}
</button>
```

### Responsive grid

```jsx
<div style={{ display: 'grid', gridTemplateColumns: bp === 'mobile' ? '1fr' : '1fr 1fr', gap: '1rem' }}>
```

---

## 11. Animations & Transitions

### Keyframes

```css
@keyframes spin    { to { transform: rotate(360deg); } }
@keyframes slideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
```

### Transition defaults

```js
'width 0.2s ease, padding 0.2s ease'   // sidebar collapse
'background 0.15s, color 0.15s'         // hover states
'border-color 0.15s, box-shadow 0.15s'  // input focus
'margin-left 0.2s ease'                 // main content offset
'opacity 0.15s'                         // button hover
```

### Button hover/disabled (global CSS)

```css
button:hover:not(:disabled) { opacity: 0.88; }
button:disabled { opacity: 0.55; cursor: not-allowed; }
```

### Toast slide-in

```js
// On mount set visible=true via requestAnimationFrame
{ opacity: visible ? 1 : 0, transform: visible ? 'translateY(0)' : 'translateY(12px)', transition: 'opacity 0.2s, transform 0.2s' }
```

---

## 12. Auth Flow — Laravel Fortify + Spatie RBAC

### Account activation flow

```
Admin creates employee record (inactive, no password)
        ↓
Employee goes to /login → "Activate PMS Account"
        ↓
Step 1: employee_id + email → POST /send/id
        → server verifies against HRIS, emails activation token
        ↓
Step 2: token + password + password_confirmation + photo (optional)
        → POST /activate/complete
        ↓
Account activated → redirect to /login
```

### Single Login.jsx — mode switching (no separate pages)

```js
const [mode, setMode] = useState('login');
// modes: 'login' | 'activate-verify' | 'activate-complete' | 'forgot'
```

| Mode | Fields | Endpoint |
|---|---|---|
| `login` | name, password | `POST /login` (Fortify) |
| `activate-verify` | employee_id, email | `POST /send/id` |
| `activate-complete` | token (hidden), password, confirm, photo | `POST /activate/complete` |
| `forgot` | email | `POST /forgot-password` (Fortify) |

### Routes

```php
// web.php
Route::post('/send/id',          [ActivationController::class, 'sendId']);
Route::post('/activate/complete', [ActivationController::class, 'complete']);

// Role-gated portals
Route::prefix('administrator')->middleware(['auth', 'role:admin'])->group(fn() => ...);
Route::prefix('dept-head')->middleware(['auth', 'role:dept-head'])->group(fn() => ...);
Route::prefix('supervisor')->middleware(['auth', 'role:supervisor'])->group(fn() => ...);
Route::prefix('pmt')->middleware(['auth', 'role:pmt'])->group(fn() => ...);
Route::prefix('employee')->middleware(['auth', 'role:employee'])->group(fn() => ...);
```

### Role redirect after login

```php
// FortifyServiceProvider or LoginResponse
return match ($user->getRoleNames()->first()) {
    'admin'      => redirect('/administrator'),
    'dept-head'  => redirect('/dept-head'),
    'supervisor' => redirect('/supervisor'),
    'pmt'        => redirect('/pmt'),
    default      => redirect('/employee'),
};
```

### Spatie roles

| Slug | Portal prefix |
|---|---|
| `admin` | `/administrator` |
| `dept-head` | `/dept-head` |
| `supervisor` | `/supervisor` |
| `pmt` | `/pmt` |
| `employee` | `/employee` |

### HandleInertiaRequests — share auth user

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user() ? [
                'id'                => $request->user()->id,
                'name'              => $request->user()->name,
                'email'             => $request->user()->email,
                'avatar'            => $request->user()->avatar,
                'profile_photo_url' => $request->user()->profile_photo_url,
                'roles'             => $request->user()->getRoleNames(),
            ] : null,
        ],
        'flash' => [
            'just_logged_in' => $request->session()->pull('just_logged_in', false),
        ],
    ]);
}
```

Access in any React page:
```js
const { auth } = usePage().props;
const role = auth?.user?.roles?.[0]; // 'admin', 'dept-head', etc.
```

### Login loading screen

Flash `just_logged_in` after login, AppLayout reads it once and shows a branded full-screen loader:

```php
// After successful authentication:
$request->session()->put('just_logged_in', true);
```

```jsx
// AppLayout.jsx
const [showLoader] = useState(() => !!page?.props?.flash?.just_logged_in);
// if showLoader: render full-screen overlay with logo + progress bar animation
```

---

## 13. New System Checklist

When building a sibling system (e.g. Learning & Development) to match this design:

- [ ] Copy CSS variables block into `app.css` or AppLayout `<style>`
- [ ] Use `Inter` font (bunny fonts CDN)
- [ ] Add Bootstrap Icons CDN link in `app.blade.php`
- [ ] Build `AppLayout.jsx` — Sidebar + Topbar + ToastProvider + ConfirmProvider
- [ ] Build `Sidebar.jsx` — role links, collapse, mobile overlay, active state
- [ ] Build `Topbar.jsx` — breadcrumb, user pill, dark mode toggle, hamburger
- [ ] Copy `Snackbar.jsx` — `ToastProvider` + `useToast()`
- [ ] Copy `ConfirmDialog.jsx` — `ConfirmProvider` + `useConfirm()`
- [ ] Copy `useBreakpoint.js`
- [ ] Copy `defaultAvatar.js`
- [ ] **All styling inline `style={{}}`** — no Tailwind, no CSS modules
- [ ] **Icons: Bootstrap Icons + inline SVG only** — no Heroicons, no Lucide
- [ ] Auth: Fortify for login/reset + custom `/send/id` + `/activate/complete`
- [ ] Spatie RBAC: one role per user, `role:rolename` middleware on route groups
- [ ] Share `auth.user.roles` via `HandleInertiaRequests`
- [ ] Breadcrumb: mobile shows title only (truncated), desktop shows full path
- [ ] Sidebar: desktop collapsible (68px / 280px), mobile slide-in overlay
- [ ] Mobile bottom action bar for page-level actions
- [ ] Dark mode default, toggleable, persisted in `localStorage` key `'theme'`
- [ ] Sidebar collapse persisted in `localStorage` key `'sb-collapsed'`


---

## 14. Notification Component

### Usage

`NotificationPanel` is placed inside the Topbar, receives `notifications` array and an `onNotificationsChange` setter from AppLayout state (which polls `/api/notifications`).

```jsx
// In Topbar.jsx
import NotificationPanel from '@/Components/NotificationPanel';

<NotificationPanel
    notifications={notifications}
    onNotificationsChange={setNotifications}
/>
```

### Data shape (from `/api/notifications`)

```js
{
    id:       1,
    event:    'uwp.submitted',       // domain.action
    type:     'info',                // 'info' | 'success' | 'alert'
    title:    'UWP Submitted',
    body:     'Jose Reyes submitted a UWP for review.',
    time:     '2 mins ago',
    url:      '/dept-head/uwp',
    is_read:  false,
}
```

### Bell button + badge

```jsx
<button className="np-bell" onClick={() => setOpen(v => !v)}>
    <i className="bi bi-bell-fill" />
    {unread > 0 && (
        <span className="np-badge">{unread > 9 ? '9+' : unread}</span>
    )}
</button>
```

### Key CSS

```css
.np-bell {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--admin-card); border: 1px solid var(--admin-border);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--admin-text-secondary); font-size: 0.85rem;
    transition: background 0.15s, color 0.15s;
}
.np-bell:hover { background: rgba(59,130,246,0.08); color: var(--admin-accent); }

.np-badge {
    position: absolute; top: -2px; right: -2px;
    min-width: 15px; height: 15px; padding: 0 3px; border-radius: 8px;
    background: #f43f5e; color: #fff;
    font-size: 0.58rem; font-weight: 700; line-height: 15px;
    border: 2px solid var(--admin-bg-primary);
}

.np-panel {
    position: absolute; top: calc(100% + 0.5rem); right: 0;
    width: 340px; max-width: calc(100vw - 2rem);
    background: var(--admin-card); border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius); box-shadow: var(--admin-shadow);
    z-index: 1100; display: flex; flex-direction: column;
}

/* Icon colour variants */
.np-icon-alert   { background: rgba(244,63,94,0.12);  color: #fda4af; }
.np-icon-success { background: rgba(16,185,129,0.12); color: #6ee7b7; }
.np-icon-info    { background: rgba(14,165,233,0.12); color: #7dd3fc; }

/* Unread dot colours */
.np-dot-alert   { background: #f43f5e; }
.np-dot-success { background: #10b981; }
.np-dot-info    { background: #0ea5e9; }

/* Mobile: fixed position panel */
@media (max-width: 480px) {
    .np-panel { position: fixed; top: 60px; left: 0.75rem; right: 0.75rem; width: auto; }
}
```

### Event → icon mapping

```js
// Domain (event prefix) → Bootstrap Icon
const DOMAIN_ICON = {
    uwp:              'bi bi-file-earmark-text-fill',
    opcr:             'bi bi-clipboard-data-fill',
    ipcr:             'bi bi-person-vcard-fill',
    mpor:             'bi bi-calendar-check-fill',
    ors:              'bi bi-list-check',
    accomplishment:   'bi bi-trophy-fill',
    development_plan: 'bi bi-mortarboard-fill',
    // Add your own domains here
};

// Action verb (event suffix) → icon override
const ACTION_ICON = {
    approved:  'bi bi-check-circle-fill',
    returned:  'bi bi-arrow-counterclockwise',
    submitted: 'bi bi-upload',
};

function iconForEvent(event) {
    const [domain, ...rest] = (event ?? '').split('.');
    return ACTION_ICON[rest.join('.')] ?? DOMAIN_ICON[domain] ?? 'bi bi-bell-fill';
}
```

### Backend — API routes needed

```php
Route::middleware('auth')->prefix('api/notifications')->group(function () {
    Route::get('/',              [NotificationController::class, 'index']);
    Route::post('/{id}/read',    [NotificationController::class, 'markRead']);
    Route::post('/read-all',     [NotificationController::class, 'markAllRead']);
});
```

### Polling (AppLayout)

```js
// Poll on mount, re-poll when real-time event received
const fetchNotifications = useCallback(async () => {
    const { data } = await axios.get('/api/notifications');
    setNotifications(data);
}, []);

useEffect(() => { fetchNotifications(); }, []);
// Also wire to Laravel Echo / Reverb for real-time push
```

---

## 15. Responsive Behaviour

### Breakpoints reference

| Name | Range | Sidebar | Topbar | Content |
|---|---|---|---|---|
| **Mobile** | `< 768px` | Overlay (off-screen, hamburger to open) | Hamburger + title only + icon buttons | Full width, 0.75rem padding |
| **Tablet** | `768–1023px` | Visible, collapsible | Breadcrumb visible, some labels hidden | Offset by sidebar |
| **Desktop** | `≥ 1024px` | Visible, collapsible (280px / 68px) | Full breadcrumb + labels | Offset by sidebar |

> iPad (768px) falls into the **tablet** breakpoint. iPad Pro landscape (1024px+) falls into **desktop**.

### Sidebar responsiveness

| State | Desktop | Tablet | Mobile |
|---|---|---|---|
| Default | 280px expanded | 280px expanded | Hidden (translateX -100%) |
| Collapsed | 68px icon-only | 68px icon-only | N/A |
| Open trigger | Toggle button in sidebar | Toggle button | Hamburger in topbar |
| Close trigger | Toggle button | Toggle button | Backdrop click / close button |
| Labels | Visible / hidden | Visible / hidden | Always visible when open |

```css
/* Sidebar base */
.admin-sidebar { width: 280px; transition: width 0.2s ease; }
.admin-sidebar.sb-collapsed { width: 68px; }

/* Mobile override */
@media (max-width: 767px) {
    .admin-sidebar {
        width: 280px !important;
        transform: translateX(-100%);
        transition: transform 0.22s ease;
    }
    .admin-sidebar.sb-mobile-open { transform: translateX(0); }
}
```

Main content offset:
```jsx
// Dynamic margin — 0 on mobile, sidebar width on tablet/desktop
<div className="app-main" style={{ marginLeft: sidebarWidth }}>
// CSS override for mobile:
// @media (max-width: 767px) { .app-main { margin-left: 0 !important; } }
```

### Topbar responsiveness

| Element | Desktop | Tablet | Mobile |
|---|---|---|---|
| Hamburger button | Hidden | Hidden | Visible |
| Breadcrumb | Full path (Home › Section › Page) | Full path | Current page title only (truncated, max 40vw) |
| User name + role | Visible | Visible | Hidden |
| User avatar | Visible | Visible | Visible |
| Notification bell | Visible | Visible | Visible |
| Action buttons | Labels + icons | Labels + icons | Icons only |

```css
@media (max-width: 767px) {
    .tb-hamburger { display: flex; }
    .tb-bc-home   { display: none; }   /* hide "Home" link */
    .tb-bc-past   { display: none; }   /* hide middle segments */
    .tb-bc-sep    { display: none; }   /* hide separators */
    .tb-info      { display: none; }   /* hide name/role in pill */
    .tb-bc-current {
        white-space: nowrap; overflow: hidden;
        text-overflow: ellipsis; max-width: 40vw; display: block;
    }
}
```

### Content layout responsiveness

**Standard page grid (2-col → 1-col):**
```jsx
<div style={{ display: 'grid', gridTemplateColumns: bp === 'mobile' ? '1fr' : '1fr 1fr', gap: '1rem' }}>
```

**Detail page — side panel + main:**
```jsx
<div style={{ display: 'flex', flexDirection: bp !== 'desktop' ? 'column' : 'row' }}>
    {bp === 'desktop' && <aside style={{ width: 270 }}>...</aside>}
    <main style={{ flex: 1 }}>...</main>
</div>
```

**Tab navigation on mobile/tablet:**
```jsx
<div style={{ overflowX: 'auto', scrollbarWidth: 'none', display: 'flex' }}>
    {tabs.map(tab => <button key={tab} style={{ flexShrink: 0, whiteSpace: 'nowrap' }}>...</button>)}
</div>
```

**Mobile sticky bottom action bar:**
```jsx
{bp === 'mobile' && (
    <div style={{
        position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 99,
        background: 'var(--admin-card)', borderTop: '1px solid var(--admin-border)',
        padding: '0.75rem 1rem', display: 'flex', gap: '0.5rem', justifyContent: 'flex-end',
    }}>
        <Actions />
    </div>
)}
// Add paddingBottom: '5rem' to main content to avoid overlap
```

### Notification panel responsiveness

```css
/* Mobile: panel becomes full-width fixed (not absolute dropdown) */
@media (max-width: 480px) {
    .np-panel { position: fixed; top: 60px; left: 0.75rem; right: 0.75rem; width: auto; }
}
```

### Modal responsiveness

Modals use `padding: '1rem'` on the overlay so they don't touch screen edges. On mobile, `maxWidth` fills available space:

```jsx
<div style={{ position: 'fixed', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
    <div style={{ width: '100%', maxWidth: 520, maxHeight: '85vh' }}>
        ...
    </div>
</div>
```

### Table → card list on mobile

Desktop/tablet: `<table>` with full columns.
Mobile: card list — each row becomes a stacked card:

```jsx
{bp === 'mobile' ? (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.65rem' }}>
        {items.map(item => (
            <div key={item.id} style={{
                background: 'var(--admin-bg-secondary)', borderRadius: 10,
                padding: '0.85rem 1rem', border: '1px solid var(--admin-border)',
            }}>
                ...stacked fields...
            </div>
        ))}
    </div>
) : (
    <table>...</table>
)}
```

---

## 16. Role Portals — Learning & Development System

The following defines the sidebar navigation and placeholder page structure for each portal in the **Learning & Development** system. All pages are stubs (`Under Development`) until implemented.

### Roles

| Role | Portal prefix | Description |
|---|---|---|
| `system-admin` | `/admin` | System administrator |
| `secretariat` | `/secretariat` | L&D secretariat / coordinator |
| `hrdc` | `/hrdc` | Human Resource Development Committee |
| `supervisor` | `/supervisor` | Direct supervisor of employees |
| `employee` | `/employee` | Regular employee |

---

### System Admin Portal (`/admin`)

```js
const adminLinks = [
    { href: '/admin',          label: 'Dashboard',      icon: 'bi-grid-1x2-fill' },
    { href: '/admin/users',    label: 'Users',          icon: 'bi-people-fill' },
    { href: '/admin/offices',  label: 'Offices',        icon: 'bi-building-fill' },
    { href: '/admin/settings', label: 'Settings',       icon: 'bi-gear-fill' },
    { href: '/admin/profile',  label: 'Profile',        icon: 'bi-person-badge-fill' },
];
```

**Pages:**
- `/admin` — Dashboard (stats cards, recent activity)
- `/admin/users` — User list, create/edit/deactivate
- `/admin/offices` — Office/department management
- `/admin/settings` — System settings (app name, logo, etc.)
- `/admin/profile` — Profile settings

---

### Secretariat Portal (`/secretariat`)

```js
const secretariatLinks = [
    { href: '/secretariat',                   label: 'Dashboard',         icon: 'bi-grid-1x2-fill' },
    { href: '/secretariat/training-programs', label: 'Training Programs', icon: 'bi-mortarboard-fill' },
    { href: '/secretariat/schedules',         label: 'Schedules',         icon: 'bi-calendar3' },
    { href: '/secretariat/nominations',       label: 'Nominations',       icon: 'bi-person-plus-fill' },
    { href: '/secretariat/attendance',        label: 'Attendance',        icon: 'bi-check2-square' },
    { href: '/secretariat/reports',           label: 'Reports',           icon: 'bi-bar-chart-fill' },
    { href: '/secretariat/profile',           label: 'Profile',           icon: 'bi-person-badge-fill' },
];
```

**Pages:**
- `/secretariat` — Dashboard
- `/secretariat/training-programs` — List + create training programs
- `/secretariat/training-programs/{id}` — Program detail / edit
- `/secretariat/schedules` — Training schedule calendar
- `/secretariat/nominations` — Manage employee nominations per training
- `/secretariat/attendance` — Track attendance per training
- `/secretariat/reports` — Training completion reports
- `/secretariat/profile` — Profile settings

---

### HRDC Portal (`/hrdc`)

```js
const hrdcLinks = [
    { href: '/hrdc',                label: 'Dashboard',       icon: 'bi-grid-1x2-fill' },
    { href: '/hrdc/training-plans', label: 'Training Plans',  icon: 'bi-journal-bookmark-fill' },
    { href: '/hrdc/nominations',    label: 'Nominations',     icon: 'bi-person-check-fill' },
    { href: '/hrdc/evaluations',    label: 'Evaluations',     icon: 'bi-clipboard-data-fill' },
    { href: '/hrdc/reports',        label: 'Reports',         icon: 'bi-bar-chart-fill' },
    { href: '/hrdc/profile',        label: 'Profile',         icon: 'bi-person-badge-fill' },
];
```

**Pages:**
- `/hrdc` — Dashboard
- `/hrdc/training-plans` — Annual training plan management
- `/hrdc/training-plans/{id}` — Plan detail / approval workflow
- `/hrdc/nominations` — Review and approve nominations
- `/hrdc/evaluations` — Training effectiveness evaluations
- `/hrdc/reports` — Overall L&D performance reports
- `/hrdc/profile` — Profile settings

---

### Supervisor Portal (`/supervisor`)

```js
const supervisorLinks = [
    { href: '/supervisor',              label: 'Dashboard',      icon: 'bi-grid-1x2-fill' },
    { href: '/supervisor/team',         label: 'My Team',        icon: 'bi-people-fill' },
    { href: '/supervisor/nominations',  label: 'Nominations',    icon: 'bi-person-plus-fill' },
    { href: '/supervisor/trainings',    label: 'Trainings',      icon: 'bi-mortarboard-fill' },
    { href: '/supervisor/idp',          label: 'Team IDP',       icon: 'bi-journal-check' },
    { href: '/supervisor/profile',      label: 'Profile',        icon: 'bi-person-badge-fill' },
];
```

**Pages:**
- `/supervisor` — Dashboard
- `/supervisor/team` — Team member list + training status
- `/supervisor/nominations` — Nominate team members for trainings
- `/supervisor/nominations/{id}` — Nomination detail
- `/supervisor/trainings` — View upcoming/ongoing trainings for team
- `/supervisor/idp` — Review team Individual Development Plans
- `/supervisor/idp/{id}` — IDP detail / endorse
- `/supervisor/profile` — Profile settings

---

### Employee Portal (`/employee`)

```js
const employeeLinks = [
    { href: '/employee',               label: 'Dashboard',   icon: 'bi-grid-1x2-fill' },
    { href: '/employee/my-trainings',  label: 'My Trainings', icon: 'bi-mortarboard-fill' },
    { href: '/employee/my-idp',        label: 'My IDP',      icon: 'bi-journal-bookmark-fill' },
    { href: '/employee/history',       label: 'History',     icon: 'bi-clock-history' },
    { href: '/employee/profile',       label: 'Profile',     icon: 'bi-person-badge-fill' },
];
```

**Pages:**
- `/employee` — Dashboard
- `/employee/my-trainings` — List of enrolled/completed trainings
- `/employee/my-trainings/{id}` — Training detail + attendance status
- `/employee/my-idp` — Personal Individual Development Plan
- `/employee/history` — Training history + certificates
- `/employee/profile` — Profile settings

---

### Placeholder page template

Every unimplemented page uses this standard stub:

```jsx
// Pages/[Role]/[Module]/Index.jsx
import AppLayout from '@/Layouts/AppLayout';

export default function Index() {
    return (
        <AppLayout title="Page Title" description="Section">
            <div style={{
                display: 'flex', flexDirection: 'column',
                alignItems: 'center', justifyContent: 'center',
                minHeight: 320, gap: '0.75rem',
                color: 'var(--admin-text-muted)',
            }}>
                <i className="bi bi-tools" style={{ fontSize: '2rem', opacity: 0.4 }} />
                <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--admin-text-primary)' }}>
                    Under Development
                </div>
                <div style={{ fontSize: '0.82rem' }}>
                    This section is coming soon.
                </div>
            </div>
        </AppLayout>
    );
}
```

### Role headers for Sidebar

```js
const roleHeaders = {
    'system-admin': { icon: 'bi-shield-lock-fill', label: 'Admin Portal' },
    secretariat:    { icon: 'bi-people-fill',       label: 'Secretariat Portal' },
    hrdc:           { icon: 'bi-building-fill',      label: 'HRDC Portal' },
    supervisor:     { icon: 'bi-person-workspace',   label: 'Supervisor Portal' },
    employee:       { icon: 'bi-person-fill',        label: 'Employee Portal' },
};
```
