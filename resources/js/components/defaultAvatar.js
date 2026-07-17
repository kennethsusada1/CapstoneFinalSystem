export default function defaultAvatar(name = 'Smart L&D User') {
    const initials = name
        .split(' ')
        .map((part) => part[0] ?? '')
        .join('')
        .slice(0, 2)
        .toUpperCase();

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect width="96" height="96" rx="24" fill="#1d4ed8"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="34" font-weight="700" fill="white">${initials || 'SL'}</text></svg>`;
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}
