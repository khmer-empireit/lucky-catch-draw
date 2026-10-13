import { useEffect } from 'react';
import confetti from 'canvas-confetti';
import { motion } from 'motion/react';
import type { DrawResult } from '@/types/draw';

interface WinnerRevealProps {
    result: DrawResult;
    prizeName: string | null;
    winnersToSelect: number;
    winnersRemaining: number;
    onNext: () => void;
}

export default function WinnerReveal({ result, prizeName, winnersToSelect, winnersRemaining, onNext }: WinnerRevealProps) {
    useEffect(() => {
        const colors = ['#f2b33d', '#ff6b2c', '#ffffff'];
        const duration = 2200;
        const end = Date.now() + duration;

        (function frame() {
            confetti({ particleCount: 4, angle: 60, spread: 70, origin: { x: 0 }, colors });
            confetti({ particleCount: 4, angle: 120, spread: 70, origin: { x: 1 }, colors });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        })();

        confetti({ particleCount: 140, spread: 100, origin: { y: 0.5 }, colors, startVelocity: 55 });
    }, [result.draw_id]);

    return (
        <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, ease: 'easeOut' }}
            className="flex flex-col items-center gap-6 text-center"
        >
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,rgba(242,179,61,0.25),transparent_60%)]" />

            <p className="text-sm font-semibold uppercase tracking-[0.3em] text-gold-400">
                Prize {result.sequence} of {winnersToSelect}
            </p>

            <h2 className="gold-gradient-text text-6xl font-extrabold leading-tight sm:text-7xl">{result.winner.name}</h2>

            <a
                href={result.winner.profile_url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-lg text-white/70 underline decoration-gold-400/60 underline-offset-4 hover:text-white"
            >
                {result.winner.profile_url}
            </a>

            {prizeName && <p className="text-2xl font-medium text-white">{prizeName}</p>}

            <p className="font-mono text-xs text-white/30">pool {result.pool_size} · hash {result.pool_hash}</p>

            <button
                type="button"
                onClick={onNext}
                className="mt-4 gold-gradient-bg rounded-full px-10 py-4 text-lg font-bold text-stage-bg shadow-lg transition-transform hover:scale-105"
            >
                {winnersRemaining > 0 ? 'Next prize' : 'Draw complete'}
            </button>
        </motion.div>
    );
}
