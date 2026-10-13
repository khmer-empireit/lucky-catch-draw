import { useCallback, useEffect, useRef, useState } from 'react';
import { DrawRequestError, fetchLatestDraw, isAxiosNetworkOrServerError, postDraw } from '@/lib/drawApi';
import { drawSounds } from '@/lib/drawSounds';
import type { DrawPhase, DrawResult, SampleParticipant } from '@/types/draw';

const COUNTDOWN_START = 3;
const REEL_DURATION_MS = 5000;
const RETRY_DELAYS_MS = [1000, 2000, 4000, 8000, 15000];

interface UseDrawControllerArgs {
    campaignId: number;
    winnersToSelect: number;
    initialWinnersDrawn: number;
    initialWinnersRemaining: number;
    canDraw: boolean;
    sample: SampleParticipant[];
}

function storageKey(campaignId: number): string {
    return `lucky-catch-draw:pending:${campaignId}`;
}

function readPendingKey(campaignId: number): string | null {
    try {
        return sessionStorage.getItem(storageKey(campaignId));
    } catch {
        return null;
    }
}

function writePendingKey(campaignId: number, key: string | null): void {
    try {
        if (key === null) {
            sessionStorage.removeItem(storageKey(campaignId));
        } else {
            sessionStorage.setItem(storageKey(campaignId), key);
        }
    } catch {
        // sessionStorage unavailable (private mode, etc.) — recovery on reload
        // simply won't be available; the draw itself is unaffected.
    }
}

export function useDrawController({
    campaignId,
    winnersToSelect,
    initialWinnersDrawn,
    initialWinnersRemaining,
    canDraw,
    sample,
}: UseDrawControllerArgs) {
    const [phase, setPhase] = useState<DrawPhase>('idle');
    const [countdownValue, setCountdownValue] = useState(COUNTDOWN_START);
    const [winnersDrawn, setWinnersDrawn] = useState(initialWinnersDrawn);
    const [winnersRemaining, setWinnersRemaining] = useState(initialWinnersRemaining);
    const [result, setResult] = useState<DrawResult | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [isMuted, setIsMuted] = useState(false);
    const [isReconnecting, setIsReconnecting] = useState(false);

    const isMutedRef = useRef(isMuted);
    isMutedRef.current = isMuted;

    const requestInFlight = useRef(false);

    const applyResult = useCallback((drawResult: DrawResult) => {
        setResult(drawResult);
        setWinnersRemaining(drawResult.winners_remaining);
        setWinnersDrawn(winnersToSelect - drawResult.winners_remaining);
    }, [winnersToSelect]);

    const requestDrawWithRetry = useCallback(async (key: string): Promise<DrawResult> => {
        let attempt = 0;

        // eslint-disable-next-line no-constant-condition
        while (true) {
            try {
                return await postDraw(campaignId, key);
            } catch (error) {
                if (!isAxiosNetworkOrServerError(error)) {
                    const message =
                        (error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
                        'This draw could not be completed.';
                    throw new DrawRequestError(message, false);
                }

                setIsReconnecting(true);
                const delay = RETRY_DELAYS_MS[Math.min(attempt, RETRY_DELAYS_MS.length - 1)];
                await new Promise((resolve) => setTimeout(resolve, delay));
                attempt += 1;
            }
        }
    }, [campaignId]);

    // On mount: recover from a reload that happened mid-draw.
    useEffect(() => {
        const pendingKey = readPendingKey(campaignId);

        if (!pendingKey) {
            return;
        }

        let cancelled = false;
        setPhase('reconnecting');
        setIsReconnecting(true);

        requestDrawWithRetry(pendingKey)
            .then((drawResult) => {
                if (cancelled) return;
                writePendingKey(campaignId, null);
                setIsReconnecting(false);
                applyResult(drawResult);
                setPhase('revealed');
                drawSounds.win(isMutedRef.current);
            })
            .catch(async () => {
                if (cancelled) return;
                // The key was never accepted and isn't retryable — fall back
                // to whatever the server considers the latest valid draw.
                writePendingKey(campaignId, null);
                setIsReconnecting(false);
                const latest = await fetchLatestDraw(campaignId).catch(() => null);
                if (latest) {
                    applyResult(latest);
                    setPhase('revealed');
                } else {
                    setPhase('idle');
                }
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [campaignId]);

    const start = useCallback(() => {
        if (!canDraw || requestInFlight.current || phase !== 'idle') {
            return;
        }

        requestInFlight.current = true;
        setErrorMessage(null);

        const key = crypto.randomUUID();
        writePendingKey(campaignId, key);

        setPhase('countdown');
        setCountdownValue(COUNTDOWN_START);

        const drawPromise = requestDrawWithRetry(key);

        let tick = COUNTDOWN_START;
        const countdownInterval = setInterval(() => {
            tick -= 1;
            if (tick <= 0) {
                clearInterval(countdownInterval);
                setCountdownValue(0);
            } else {
                setCountdownValue(tick);
                drawSounds.tick(isMutedRef.current);
            }
        }, 1000);

        const countdownDone = new Promise<void>((resolve) => {
            setTimeout(resolve, COUNTDOWN_START * 1000);
        });

        Promise.all([drawPromise, countdownDone])
            .then(([drawResult]) => {
                writePendingKey(campaignId, null);
                setIsReconnecting(false);
                setResult(drawResult);
                setPhase('reeling');
                drawSounds.spin(isMutedRef.current);

                setTimeout(() => {
                    applyResult(drawResult);
                    setPhase('revealed');
                    drawSounds.win(isMutedRef.current);
                    requestInFlight.current = false;
                }, REEL_DURATION_MS);
            })
            .catch((error: unknown) => {
                clearInterval(countdownInterval);
                writePendingKey(campaignId, null);
                setIsReconnecting(false);
                requestInFlight.current = false;
                setPhase('error');
                setErrorMessage(error instanceof DrawRequestError ? error.message : 'Something went wrong starting the draw.');
            });
    }, [campaignId, canDraw, phase, requestDrawWithRetry, applyResult]);

    const next = useCallback(() => {
        setResult(null);
        setErrorMessage(null);
        setPhase(winnersRemaining > 0 ? 'idle' : 'complete');
    }, [winnersRemaining]);

    const toggleMute = useCallback(() => setIsMuted((m) => !m), []);

    useEffect(() => {
        if (winnersRemaining <= 0 && phase === 'idle') {
            setPhase('complete');
        }
    }, [winnersRemaining, phase]);

    return {
        phase,
        countdownValue,
        winnersDrawn,
        winnersRemaining,
        result,
        errorMessage,
        isMuted,
        isReconnecting,
        sample,
        start,
        next,
        toggleMute,
    };
}
