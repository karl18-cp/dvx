import { Head } from '@inertiajs/react';
import { BellRing, Gamepad2, ShieldCheck, Zap } from 'lucide-react';

export default function MiniGames() {
    return (
        <>
            <Head title="Mini-Games" />
            <main className="min-h-full bg-[radial-gradient(circle_at_top_left,#fff1f2,#f8fafc_45%,#f4f0f3)] p-5 lg:p-8">
                <div className="mx-auto max-w-6xl space-y-6">
                    <header>
                        <p className="text-xs font-bold tracking-[.22em] text-red-700 uppercase">
                            Available-time lounge
                        </p>
                        <h1 className="mt-2 flex items-center gap-3 text-3xl font-black text-slate-950">
                            <Gamepad2 className="text-red-700" />
                            Multiplayer Mini-Games
                        </h1>
                        <p className="mt-2 max-w-3xl text-slate-600">
                            Challenge coworkers during available time. The
                            floating Mini-Games button stays with you across the
                            portal, preserves your room, and minimizes gameplay
                            when an incoming-call event is received.
                        </p>
                    </header>
                    <section className="grid gap-4 md:grid-cols-3">
                        {[
                            [
                                Zap,
                                'Fast matchmaking',
                                'Start a quick match or invite an available coworker.',
                            ],
                            [
                                BellRing,
                                'Calls always win',
                                'Incoming-call signals pause the room and minimize gameplay immediately.',
                            ],
                            [
                                ShieldCheck,
                                'Work-safe sessions',
                                'Exit instantly, resume paused games, and avoid forced animations or waiting.',
                            ],
                        ].map(([Icon, title, text]) => {
                            const I = Icon as typeof Zap;

                            return (
                                <article
                                    key={String(title)}
                                    className="rounded-3xl border border-white bg-white/80 p-5 shadow-sm backdrop-blur"
                                >
                                    <span className="grid size-11 place-items-center rounded-2xl bg-red-50 text-red-700">
                                        <I />
                                    </span>
                                    <h2 className="mt-4 font-black">
                                        {String(title)}
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-slate-600">
                                        {String(text)}
                                    </p>
                                </article>
                            );
                        })}
                    </section>
                    <section className="rounded-3xl border border-red-100 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black">
                            Open the games lounge
                        </h2>
                        <p className="mt-2 text-slate-600">
                            Use the floating <b>Mini-Games</b> button at the
                            bottom-right. It shows online agents, current
                            availability, invitations, open rooms, quick match
                            options, and the friendly leaderboard.
                        </p>
                    </section>
                </div>
            </main>
        </>
    );
}

MiniGames.layout = {
    breadcrumbs: [{ title: 'Mini-Games', href: '/mini-games' }],
};
