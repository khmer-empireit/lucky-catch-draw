// Lightweight procedural sound effects (no binary assets to ship/load).
// Respects autoplay rules by only ever starting audio from within a user
// gesture (the Start Draw click) or from mid-sequence timers chained off it.

let sharedContext: AudioContext | null = null;

function getContext(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const AudioContextClass = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;

    if (!AudioContextClass) {
        return null;
    }

    sharedContext ??= new AudioContextClass();

    return sharedContext;
}

function beep(frequency: number, durationMs: number, volume: number, type: OscillatorType = 'sine'): void {
    const ctx = getContext();

    if (!ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        void ctx.resume();
    }

    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.value = frequency;
    gain.gain.value = volume;

    oscillator.connect(gain);
    gain.connect(ctx.destination);

    const now = ctx.currentTime;
    gain.gain.setValueAtTime(volume, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + durationMs / 1000);

    oscillator.start(now);
    oscillator.stop(now + durationMs / 1000);
}

export const drawSounds = {
    tick: (muted: boolean) => !muted && beep(880, 90, 0.08),
    spin: (muted: boolean) => !muted && beep(220, 160, 0.05, 'sawtooth'),
    win: (muted: boolean) => {
        if (muted) return;
        beep(523.25, 150, 0.1);
        setTimeout(() => beep(659.25, 150, 0.1), 140);
        setTimeout(() => beep(783.99, 300, 0.12), 280);
    },
};
