import { type ReactNode } from 'react';
import type { DrawCampaign } from '@/types/draw';

interface StageLayoutProps {
    campaign: DrawCampaign;
    eligibleCount: number;
    winnersDrawn: number;
    isMuted: boolean;
    onToggleMute: () => void;
    onToggleFullscreen: () => void;
    children: ReactNode;
}

export default function StageLayout({
    campaign,
    eligibleCount,
    winnersDrawn,
    isMuted,
    onToggleMute,
    onToggleFullscreen,
    children,
}: StageLayoutProps) {
    return (
        <div className="relative flex min-h-screen flex-col overflow-hidden bg-stage-bg text-white">
            <div className="pointer-events-none absolute -top-40 left-1/2 h-96 w-[60rem] -translate-x-1/2 rounded-full bg-gold-400/10 blur-3xl" />
            <div className="pointer-events-none absolute bottom-[-10rem] right-[-10rem] h-96 w-96 rounded-full bg-orange-500/10 blur-3xl" />

            <header className="relative z-10 flex flex-wrap items-center justify-between gap-4 px-6 py-5 sm:px-12">
                <div className="flex items-center gap-4">
                    {campaign.prize_image && (
                        <img src={campaign.prize_image} alt="" className="h-14 w-14 rounded-xl object-cover ring-2 ring-gold-400/40" />
                    )}
                    <div>
                        <h1 className="text-xl font-bold sm:text-2xl">{campaign.name}</h1>
                        {campaign.prize_name && <p className="text-sm text-white/50">{campaign.prize_name}</p>}
                    </div>
                </div>

                <div className="flex items-center gap-6 text-sm text-white/60">
                    <span>{eligibleCount.toLocaleString()} eligible participants</span>
                    <span>
                        Prize {Math.min(winnersDrawn + 1, campaign.winners_to_select)} of {campaign.winners_to_select}
                    </span>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={onToggleMute}
                            className="rounded-full border border-white/10 p-2 text-white/70 hover:border-gold-400/50 hover:text-white"
                            aria-label={isMuted ? 'Unmute' : 'Mute'}
                            title="M"
                        >
                            {isMuted ? '🔇' : '🔊'}
                        </button>
                        <button
                            type="button"
                            onClick={onToggleFullscreen}
                            className="rounded-full border border-white/10 p-2 text-white/70 hover:border-gold-400/50 hover:text-white"
                            aria-label="Toggle fullscreen"
                            title="F"
                        >
                            ⛶
                        </button>
                    </div>
                </div>
            </header>

            <main className="relative z-10 flex flex-1 flex-col items-center justify-center px-6 pb-16">{children}</main>

            <footer className="relative z-10 pb-4 text-center text-xs text-white/20">
                Space = Start/Next &nbsp;·&nbsp; F = Fullscreen &nbsp;·&nbsp; M = Mute &nbsp;·&nbsp; Esc = Exit
            </footer>
        </div>
    );
}
