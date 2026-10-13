function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function shortenUrl(url: string): string {
    try {
        const parsed = new URL(url);
        const path = parsed.pathname.replace(/\/$/, '');
        return `${parsed.hostname.replace(/^www\./, '')}${path}`;
    } catch {
        return url;
    }
}

export default function ParticipantCard({ name, profileUrl, highlighted = false }: { name: string; profileUrl: string; highlighted?: boolean }) {
    return (
        <div
            className={[
                'flex h-24 items-center gap-4 rounded-2xl border px-6 transition-colors',
                highlighted
                    ? 'border-gold-400/70 bg-white/10 shadow-[0_0_40px_rgba(242,179,61,0.35)]'
                    : 'border-white/5 bg-white/[0.03]',
            ].join(' ')}
        >
            <div
                className={[
                    'flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-bold',
                    highlighted ? 'gold-gradient-bg text-stage-bg' : 'bg-white/10 text-white/70',
                ].join(' ')}
            >
                {initials(name)}
            </div>
            <div className="min-w-0">
                <p className={['truncate text-xl font-semibold', highlighted ? 'text-white' : 'text-white/80'].join(' ')}>{name}</p>
                <p className="truncate text-sm text-white/40">{shortenUrl(profileUrl)}</p>
            </div>
        </div>
    );
}
