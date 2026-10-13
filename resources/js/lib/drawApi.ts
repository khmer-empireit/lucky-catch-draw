import axios from '@/bootstrap';
import type { DrawResult } from '@/types/draw';

export async function postDraw(campaignId: number, idempotencyKey: string): Promise<DrawResult> {
    const { data } = await axios.post<DrawResult>(`/draw/${campaignId}`, {
        idempotency_key: idempotencyKey,
    });

    return data;
}

export async function fetchLatestDraw(campaignId: number): Promise<DrawResult | null> {
    const { data } = await axios.get<{ draw: DrawResult | null }>(`/draw/${campaignId}/latest`);

    return data.draw;
}

export class DrawRequestError extends Error {
    constructor(
        message: string,
        public readonly isRetryable: boolean,
    ) {
        super(message);
    }
}

export function isAxiosNetworkOrServerError(error: unknown): boolean {
    if (typeof error !== 'object' || error === null || !('isAxiosError' in error)) {
        return false;
    }

    const axiosError = error as { response?: { status: number } };

    // No response at all = network/connection failure. 5xx = transient server error.
    return !axiosError.response || axiosError.response.status >= 500;
}
