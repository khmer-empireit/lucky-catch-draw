import { useCallback, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { AnimatePresence, motion } from 'motion/react';
import Countdown from '@/Components/Draw/Countdown';
import Reel from '@/Components/Draw/Reel';
import StageLayout from '@/Components/Draw/StageLayout';
import WinnerReveal from '@/Components/Draw/WinnerReveal';
import { useDrawController } from '@/hooks/useDrawController';
import type { DrawShowProps } from '@/types/draw';

export default function Show({ campaign, eligibleCount, winnersDrawn, winnersRemaining, canDraw, sample }: DrawShowProps) {
    const controller = useDrawController({
        campaignId: campaign.id,
        winnersToSelect: campaign.winners_to_select,
        initialWinnersDrawn: winnersDrawn,
        initialWinnersRemaining: winnersRemaining,
        canDraw,
        sample,
    });

    const stageRef = useRef<HTMLDivElement>(null);

    const toggleFullscreen = useCallback(() => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void stageRef.current?.requestFullscreen?.();
        }
    }, []);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.code === 'Space') {
                event.preventDefault();
                if (controller.phase === 'idle') controller.start();
                else if (controller.phase === 'revealed') controller.next();
            } else if (event.key.toLowerCase() === 'f') {
                toggleFullscreen();
            } else if (event.key.toLowerCase() === 'm') {
                controller.toggleMute();
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [controller, toggleFullscreen]);

    return (
        <div ref={stageRef}>
            <Head title={`Draw — ${campaign.name}`} />

            <StageLayout
                campaign={campaign}
                eligibleCount={eligibleCount}
                winnersDrawn={controller.winnersDrawn}
                isMuted={controller.isMuted}
                onToggleMute={controller.toggleMute}
                onToggleFullscreen={toggleFullscreen}
            >
                <AnimatePresence mode="wait">
                    {controller.phase === 'idle' && (
                        <motion.div key="idle" exit={{ opacity: 0 }} className="flex flex-col items-center gap-6">
                            {!canDraw ? (
                                <p className="text-lg text-white/50">You don't have permission to run this draw.</p>
                            ) : (
                                <button
                                    type="button"
                                    onClick={controller.start}
                                    className="gold-gradient-bg rounded-full px-16 py-8 text-3xl font-extrabold text-stage-bg shadow-[0_0_60px_rgba(242,179,61,0.4)] transition-transform hover:scale-105"
                                >
                                    Start Draw
                                </button>
                            )}
                            {controller.errorMessage && <p className="text-sm text-red-400">{controller.errorMessage}</p>}
                        </motion.div>
                    )}

                    {(controller.phase === 'countdown' || controller.phase === 'reconnecting') && (
                        <motion.div key="countdown" exit={{ opacity: 0 }} className="flex h-72 w-full items-center justify-center">
                            {controller.phase === 'reconnecting' ? (
                                <p className="animate-pulse text-2xl text-white/60">Reconnecting…</p>
                            ) : (
                                <Countdown value={controller.countdownValue} />
                            )}
                        </motion.div>
                    )}

                    {controller.phase === 'reeling' && controller.result && (
                        <motion.div key="reeling" exit={{ opacity: 0 }} className="w-full">
                            <Reel sample={sample} winner={controller.result.winner} />
                        </motion.div>
                    )}

                    {controller.phase === 'revealed' && controller.result && (
                        <motion.div key="revealed" className="relative">
                            <WinnerReveal
                                result={controller.result}
                                prizeName={campaign.prize_name}
                                winnersToSelect={campaign.winners_to_select}
                                winnersRemaining={controller.winnersRemaining}
                                onNext={controller.next}
                            />
                        </motion.div>
                    )}

                    {controller.phase === 'complete' && (
                        <motion.div key="complete" exit={{ opacity: 0 }} className="flex flex-col items-center gap-3">
                            <h2 className="gold-gradient-text text-5xl font-extrabold">Draw complete</h2>
                            <p className="text-white/50">All {campaign.winners_to_select} winners have been drawn.</p>
                        </motion.div>
                    )}

                    {controller.phase === 'error' && (
                        <motion.div key="error" exit={{ opacity: 0 }} className="flex flex-col items-center gap-4">
                            <p className="text-xl text-red-400">{controller.errorMessage}</p>
                            <button type="button" onClick={controller.next} className="rounded-full border border-white/20 px-6 py-3 text-white/80 hover:border-white/40">
                                Back
                            </button>
                        </motion.div>
                    )}
                </AnimatePresence>
            </StageLayout>
        </div>
    );
}
