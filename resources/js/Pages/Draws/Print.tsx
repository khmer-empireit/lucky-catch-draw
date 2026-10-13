import { Head } from '@inertiajs/react';

interface PrintDraw {
    id: number;
    sequence: number;
    status: string;
    drawn_at: string;
    pool_size: number;
    pool_hash: string;
    winner: { name: string | null; profile_url: string | null };
    drawn_by: string | null;
    campaign: { name: string; prize_name: string | null };
}

export default function Print({ draw }: { draw: PrintDraw }) {
    const drawnAt = new Date(draw.drawn_at);

    return (
        <div className="min-h-screen bg-white px-10 py-12 font-sans text-gray-900 print:px-0 print:py-0">
            <Head title={`Winner Report — ${draw.campaign.name}`} />

            <div className="mx-auto max-w-2xl">
                <div className="mb-8 flex items-center justify-between border-b-4 border-double pb-4" style={{ borderImage: 'linear-gradient(90deg, #f2b33d, #ff6b2c) 1' }}>
                    <div>
                        <p className="text-xs uppercase tracking-[0.3em] text-gray-400">Lucky Catch Draw</p>
                        <h1 className="text-2xl font-bold">Winner Report</h1>
                    </div>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="rounded-full bg-gray-900 px-5 py-2 text-sm font-semibold text-white print:hidden"
                    >
                        Print
                    </button>
                </div>

                <dl className="grid grid-cols-2 gap-x-8 gap-y-5 text-sm">
                    <div className="col-span-2">
                        <dt className="text-gray-400">Campaign</dt>
                        <dd className="text-lg font-semibold">{draw.campaign.name}</dd>
                    </div>

                    <div className="col-span-2 rounded-xl border border-gray-200 p-6 text-center">
                        <dt className="text-xs uppercase tracking-widest text-gray-400">Winner</dt>
                        <dd className="mt-1 text-3xl font-extrabold">{draw.winner.name ?? '—'}</dd>
                        {draw.winner.profile_url && (
                            <dd className="mt-1 text-sm text-gray-500 break-all">{draw.winner.profile_url}</dd>
                        )}
                    </div>

                    <div>
                        <dt className="text-gray-400">Prize</dt>
                        <dd className="font-medium">{draw.campaign.prize_name ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Sequence</dt>
                        <dd className="font-medium">#{draw.sequence}</dd>
                    </div>

                    <div>
                        <dt className="text-gray-400">Drawn at</dt>
                        <dd className="font-medium">{drawnAt.toLocaleString()}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Drawn by</dt>
                        <dd className="font-medium">{draw.drawn_by ?? '—'}</dd>
                    </div>

                    <div>
                        <dt className="text-gray-400">Status</dt>
                        <dd className="font-medium capitalize">{draw.status}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Pool size</dt>
                        <dd className="font-medium">{draw.pool_size}</dd>
                    </div>

                    <div className="col-span-2">
                        <dt className="text-gray-400">Pool hash</dt>
                        <dd className="break-all font-mono text-xs text-gray-500">{draw.pool_hash}</dd>
                    </div>
                </dl>

                <p className="mt-12 text-center text-xs text-gray-300">
                    This result was selected by the server using a cryptographically random draw and is verifiable via the pool hash above.
                </p>
            </div>
        </div>
    );
}
