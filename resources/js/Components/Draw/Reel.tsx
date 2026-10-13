import { useMemo } from 'react';
import { motion } from 'motion/react';
import ParticipantCard from '@/Components/Draw/ParticipantCard';
import type { SampleParticipant, WinnerPayload } from '@/types/draw';

const CARD_HEIGHT = 112; // px, must match the card's h-24 (96px) + gap (16px)
const FILLER_COUNT = 36;
const TRAILING_COUNT = 4;
const REEL_DURATION_SECONDS = 5;

interface ReelProps {
    sample: SampleParticipant[];
    winner: WinnerPayload;
    durationMs?: number;
}

function buildReel(sample: SampleParticipant[], winner: WinnerPayload): { id: string; name: string; profile_url: string }[] {
    const pool = sample.length > 0 ? sample : [{ id: winner.id, name: winner.name, profile_url: winner.profile_url }];

    const pick = (count: number) =>
        Array.from({ length: count }, (_, i) => {
            const participant = pool[Math.floor(Math.random() * pool.length)];
            return { id: `filler-${i}-${participant.id}`, name: participant.name, profile_url: participant.profile_url };
        });

    return [
        ...pick(FILLER_COUNT),
        { id: `winner-${winner.id}`, name: winner.name, profile_url: winner.profile_url },
        ...pick(TRAILING_COUNT),
    ];
}

export default function Reel({ sample, winner, durationMs = REEL_DURATION_SECONDS * 1000 }: ReelProps) {
    const items = useMemo(() => buildReel(sample, winner), [sample, winner]);
    const landingIndex = FILLER_COUNT;
    const targetOffset = landingIndex * CARD_HEIGHT;

    return (
        <div className="relative mx-auto h-[340px] w-full max-w-2xl overflow-hidden">
            <div className="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-28 -translate-y-1/2 rounded-2xl border-2 border-gold-400/60" />
            <div className="pointer-events-none absolute inset-x-0 top-0 z-[5] h-24 bg-gradient-to-b from-stage-bg to-transparent" />
            <div className="pointer-events-none absolute inset-x-0 bottom-0 z-[5] h-24 bg-gradient-to-t from-stage-bg to-transparent" />

            <motion.div
                className="flex flex-col gap-4"
                initial={{ y: 0 }}
                animate={{ y: -(targetOffset - CARD_HEIGHT) }}
                transition={{ duration: durationMs / 1000, ease: [0.1, 0.4, 0.1, 1] }}
                style={{ paddingTop: 340 / 2 - CARD_HEIGHT / 2 }}
            >
                {items.map((item, index) => (
                    <ParticipantCard key={item.id} name={item.name} profileUrl={item.profile_url} highlighted={index === landingIndex} />
                ))}
            </motion.div>
        </div>
    );
}
