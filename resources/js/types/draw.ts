export interface SampleParticipant {
    id: number;
    name: string;
    profile_url: string;
}

export interface DrawCampaign {
    id: number;
    name: string;
    prize_name: string | null;
    prize_image: string | null;
    winners_to_select: number;
    status: string;
}

export interface WinnerPayload {
    id: number;
    name: string;
    profile_url: string;
}

export interface DrawResult {
    draw_id: number;
    sequence: number;
    winner: WinnerPayload;
    pool_size: number;
    pool_hash: string;
    winners_remaining: number;
}

export interface DrawShowProps {
    campaign: DrawCampaign;
    eligibleCount: number;
    winnersDrawn: number;
    winnersRemaining: number;
    canDraw: boolean;
    sample: SampleParticipant[];
}

export type DrawPhase = 'idle' | 'countdown' | 'reconnecting' | 'reeling' | 'revealed' | 'complete' | 'error';
