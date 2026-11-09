import { Link } from '@inertiajs/react';
import {
    BellRing,
    ChevronDown,
    CircleDot,
    Club,
    Crown,
    Gamepad2,
    Grid3X3,
    LoaderCircle,
    Maximize2,
    Minimize2,
    Play,
    RotateCcw,
    Send,
    Swords,
    Trophy,
    Users,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { gameRequest } from '@/lib/mini-games';
import type {
    GamePerson,
    GameRoom,
    GameSnapshot,
    GameType,
} from '@/lib/mini-games';

const games: {
    type: GameType;
    name: string;
    note: string;
    icon: typeof Gamepad2;
    accent: string;
}[] = [
    {
        type: 'tic_tac_toe',
        name: 'Tic-Tac-Toe',
        note: '1–3 min',
        icon: Grid3X3,
        accent: 'from-rose-500 to-red-700',
    },
    {
        type: 'connect_four',
        name: 'Connect Four',
        note: '3–6 min',
        icon: CircleDot,
        accent: 'from-amber-400 to-orange-600',
    },
    {
        type: 'checkers',
        name: 'Checkers',
        note: 'Pause anytime',
        icon: Crown,
        accent: 'from-violet-500 to-purple-800',
    },
    {
        type: 'chess',
        name: 'Casual Chess',
        note: 'Pause anytime',
        icon: Swords,
        accent: 'from-slate-600 to-slate-900',
    },
    {
        type: 'poker',
        name: 'Five-Card Draw',
        note: 'One quick draw',
        icon: Club,
        accent: 'from-emerald-500 to-green-800',
    },
];

const gameName = (type: GameType) =>
    games.find((game) => game.type === type)?.name ?? type;

export function MiniGamesPanel() {
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const [snapshot, setSnapshot] = useState<GameSnapshot>();
    const [room, setRoom] = useState<GameRoom>();
    const [busy, setBusy] = useState('');
    const [error, setError] = useState('');
    const [incomingCall, setIncomingCall] = useState(false);
    const pending = useRef(false);

    const refresh = useCallback(async () => {
        if (pending.current || document.visibilityState !== 'visible') {
            return;
        }

        pending.current = true;

        try {
            const next = await gameRequest<GameSnapshot>(
                '/mini-games/snapshot',
            );
            setSnapshot(next);

            if (room) {
                const latest = next.rooms.find((item) => item.id === room.id);

                if (latest) {
                    setRoom(latest);
                }
            }

            setError('');
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Games are temporarily unavailable.',
            );
        } finally {
            pending.current = false;
        }
    }, [room]);

    useEffect(() => {
        const initial = window.setTimeout(() => void refresh(), 0);
        const timer = window.setInterval(
            () => void refresh(),
            room ? 1200 : 4000,
        );
        const focus = () => void refresh();
        window.addEventListener('focus', focus);
        document.addEventListener('visibilitychange', focus);

        return () => {
            window.clearTimeout(initial);
            window.clearInterval(timer);
            window.removeEventListener('focus', focus);
            document.removeEventListener('visibilitychange', focus);
        };
    }, [refresh, room]);

    useEffect(() => {
        const callStarted = () => {
            setIncomingCall(true);
            setMinimized(true);
            setOpen(false);
            void gameRequest('/mini-games/call-state', 'PATCH', {
                status: 'on_call',
            });
        };
        const callEnded = () => {
            setIncomingCall(false);
            void gameRequest('/mini-games/call-state', 'PATCH', {
                status: 'available',
            }).then(refresh);
        };
        window.addEventListener('divertex:incoming-call', callStarted);
        window.addEventListener('divertex:call-ended', callEnded);

        return () => {
            window.removeEventListener('divertex:incoming-call', callStarted);
            window.removeEventListener('divertex:call-ended', callEnded);
        };
    }, [refresh]);

    const act = async (key: string, action: () => Promise<void>) => {
        setBusy(key);
        setError('');

        try {
            await action();
            await refresh();
        } catch (reason) {
            setError(
                reason instanceof Error ? reason.message : 'Please try again.',
            );
        } finally {
            setBusy('');
        }
    };

    const quickMatch = (type: GameType) =>
        act(`quick-${type}`, async () => {
            const result = await gameRequest<{ room: GameRoom }>(
                '/mini-games/quick-match',
                'POST',
                { game_type: type },
            );
            setRoom(result.room);
            setOpen(true);
            setMinimized(false);
        });

    const chooseRoom = (selected: GameRoom) => {
        setRoom(selected);
        setOpen(true);
        setMinimized(false);
    };

    return (
        <>
            {incomingCall && (
                <div
                    className="fixed inset-x-0 top-0 z-[90] flex items-center justify-center gap-3 bg-red-700 px-4 py-3 font-bold text-white shadow-xl"
                    role="alert"
                >
                    <BellRing className="animate-pulse" size={20} />
                    Incoming call has priority. Your game was minimized and
                    paused.
                    <button
                        onClick={() => setIncomingCall(false)}
                        className="rounded-lg bg-white/15 px-3 py-1 text-xs"
                    >
                        Dismiss
                    </button>
                </div>
            )}
            <button
                type="button"
                onClick={() => {
                    setOpen((value) => !value);
                    setMinimized(false);
                }}
                className={`fixed right-20 bottom-4 z-40 flex h-14 items-center gap-2 rounded-full bg-[#211727] px-4 font-bold text-white shadow-xl transition hover:-translate-y-0.5 hover:bg-[#351b29] sm:right-24 sm:bottom-6 ${snapshot?.invitations.length ? 'shadow-[0_0_28px_rgba(245,158,11,.45)] ring-4 ring-amber-300/70' : ''}`}
                aria-label="Open Mini-Games"
            >
                <Gamepad2 size={23} />
                <span className="hidden lg:inline">Mini-Games</span>
                {!!snapshot?.invitations.length && (
                    <span className="grid size-6 place-items-center rounded-full bg-amber-400 text-xs text-slate-950">
                        {snapshot.invitations.length}
                    </span>
                )}
            </button>

            {open && !minimized && (
                <section
                    className="fixed right-4 bottom-20 z-50 flex h-[min(720px,calc(100dvh-105px))] w-[min(440px,calc(100vw-24px))] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl sm:right-6 sm:bottom-24"
                    aria-label="Mini-Games panel"
                >
                    <header className="flex items-center justify-between bg-[linear-gradient(120deg,#171326,#4b1b28,#a51f29)] px-5 py-4 text-white">
                        <div className="flex items-center gap-3">
                            <span className="grid size-11 place-items-center rounded-2xl bg-white/15">
                                <Gamepad2 />
                            </span>
                            <div>
                                <h2 className="font-black">
                                    Mini-Games Lounge
                                </h2>
                                <p className="text-xs text-white/70">
                                    Call-ready casual play
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-1">
                            <Link
                                href="/mini-games"
                                className="rounded-xl p-2 hover:bg-white/10"
                                aria-label="Open games page"
                            >
                                <Maximize2 size={18} />
                            </Link>
                            <button
                                onClick={() => setOpen(false)}
                                className="rounded-xl p-2 hover:bg-white/10"
                                aria-label="Close Mini-Games"
                            >
                                <X size={20} />
                            </button>
                        </div>
                    </header>
                    <div className="flex-1 overflow-y-auto bg-slate-50 p-4">
                        {error && (
                            <p className="mb-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                {error}
                            </p>
                        )}
                        {room ? (
                            <GameRoomView
                                room={room}
                                me={snapshot?.me ?? 0}
                                busy={busy}
                                act={act}
                                onClose={() => setRoom(undefined)}
                                onRoom={setRoom}
                            />
                        ) : (
                            <Lobby
                                snapshot={snapshot}
                                busy={busy}
                                act={act}
                                quickMatch={quickMatch}
                                chooseRoom={chooseRoom}
                            />
                        )}
                    </div>
                </section>
            )}
        </>
    );
}

function Lobby({
    snapshot,
    busy,
    act,
    quickMatch,
    chooseRoom,
}: {
    snapshot?: GameSnapshot;
    busy: string;
    act: (key: string, action: () => Promise<void>) => Promise<void>;
    quickMatch: (type: GameType) => Promise<void>;
    chooseRoom: (room: GameRoom) => void;
}) {
    const [opponent, setOpponent] = useState<number>();
    const [inviteGame, setInviteGame] = useState<GameType>('tic_tac_toe');
    const people =
        snapshot?.people.filter((person) => person.id !== snapshot.me) ?? [];
    const invite = () =>
        act('invite', async () => {
            const created = await gameRequest<{ room: GameRoom }>(
                '/mini-games/rooms',
                'POST',
                { game_type: inviteGame },
            );
            await gameRequest(
                `/mini-games/rooms/${created.room.id}/invite`,
                'POST',
                { user_id: opponent },
            );
            chooseRoom(created.room);
        });

    return (
        <div className="space-y-5">
            {!!snapshot?.invitations.length && (
                <section className="space-y-2">
                    <h3 className="flex items-center gap-2 text-sm font-black text-slate-900">
                        <Send size={16} className="text-amber-600" />
                        Challenges
                    </h3>
                    {snapshot.invitations.map((item) => (
                        <div
                            key={item.id}
                            className="rounded-2xl border border-amber-200 bg-amber-50 p-3"
                        >
                            <p className="font-bold text-slate-900">
                                {item.sender.name}
                            </p>
                            <p className="text-xs text-slate-600">
                                invited you to {gameName(item.room.game_type)}
                            </p>
                            <div className="mt-3 flex gap-2">
                                <button
                                    disabled={!!busy}
                                    onClick={() =>
                                        void act(
                                            `accept-${item.id}`,
                                            async () => {
                                                const result =
                                                    await gameRequest<{
                                                        room: GameRoom;
                                                    }>(
                                                        `/mini-games/invitations/${item.id}`,
                                                        'PATCH',
                                                        { decision: 'accept' },
                                                    );
                                                chooseRoom(result.room);
                                            },
                                        )
                                    }
                                    className="flex-1 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-bold text-white"
                                >
                                    Accept
                                </button>
                                <button
                                    disabled={!!busy}
                                    onClick={() =>
                                        void act(
                                            `decline-${item.id}`,
                                            async () => {
                                                await gameRequest(
                                                    `/mini-games/invitations/${item.id}`,
                                                    'PATCH',
                                                    { decision: 'decline' },
                                                );
                                            },
                                        )
                                    }
                                    className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold"
                                >
                                    Decline
                                </button>
                            </div>
                        </div>
                    ))}
                </section>
            )}
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="font-black text-slate-900">Quick match</h3>
                    <span className="text-xs text-slate-500">No setup</span>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    {games.map((game) => {
                        const Icon = game.icon;

                        return (
                            <button
                                key={game.type}
                                disabled={!!busy}
                                onClick={() => void quickMatch(game.type)}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md disabled:opacity-60"
                            >
                                <span
                                    className={`flex h-16 items-center justify-between bg-gradient-to-br ${game.accent} px-4 text-white`}
                                >
                                    <Icon size={25} />
                                    {busy === `quick-${game.type}` && (
                                        <LoaderCircle
                                            className="animate-spin"
                                            size={18}
                                        />
                                    )}
                                </span>
                                <span className="block p-3">
                                    <b className="block text-sm">{game.name}</b>
                                    <small className="text-slate-500">
                                        {game.note}
                                    </small>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </section>
            <section className="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 className="flex items-center gap-2 font-black">
                    <Users size={18} className="text-red-700" />
                    Challenge a coworker
                </h3>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    <select
                        value={opponent ?? ''}
                        onChange={(event) =>
                            setOpponent(Number(event.target.value) || undefined)
                        }
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="">Choose agent</option>
                        {people
                            .filter((person) => person.status === 'available')
                            .map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                    </select>
                    <select
                        value={inviteGame}
                        onChange={(event) =>
                            setInviteGame(event.target.value as GameType)
                        }
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        {games.map((game) => (
                            <option key={game.type} value={game.type}>
                                {game.name}
                            </option>
                        ))}
                    </select>
                </div>
                <button
                    disabled={!opponent || !!busy}
                    onClick={() => void invite()}
                    className="mt-3 w-full rounded-xl bg-red-700 px-4 py-2.5 font-bold text-white disabled:opacity-50"
                >
                    Send challenge
                </button>
            </section>
            <Presence people={snapshot?.people ?? []} me={snapshot?.me ?? 0} />
            {!!snapshot?.rooms.filter((item) => item.status === 'waiting')
                .length && (
                <section>
                    <h3 className="mb-2 font-black">Open rooms</h3>
                    <div className="space-y-2">
                        {snapshot?.rooms
                            .filter((item) => item.status === 'waiting')
                            .map((openRoom) => (
                                <button
                                    key={openRoom.id}
                                    onClick={() => chooseRoom(openRoom)}
                                    className="flex w-full items-center justify-between rounded-xl border bg-white p-3 text-left"
                                >
                                    <span>
                                        <b className="block">
                                            {gameName(openRoom.game_type)}
                                        </b>
                                        <small className="text-slate-500">
                                            Hosted by{' '}
                                            {openRoom.players[0]?.name}
                                        </small>
                                    </span>
                                    <Play size={17} />
                                </button>
                            ))}
                    </div>
                </section>
            )}
            <Leaderboard rows={snapshot?.leaderboard ?? []} />
        </div>
    );
}

function Presence({ people, me }: { people: GamePerson[]; me: number }) {
    const colors = {
        available: 'bg-emerald-500',
        playing: 'bg-violet-500',
        on_call: 'bg-red-500',
        offline: 'bg-slate-300',
    };

    return (
        <section>
            <div className="mb-2 flex items-center justify-between">
                <h3 className="font-black">Agent availability</h3>
                <span className="text-xs text-slate-500">
                    {people.length} online
                </span>
            </div>
            <div className="space-y-2">
                {people.slice(0, 8).map((person) => (
                    <div
                        key={person.id}
                        className="flex items-center gap-3 rounded-xl border bg-white p-2.5"
                    >
                        <Avatar person={person} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold">
                                {person.name}
                                {person.id === me ? ' (You)' : ''}
                            </p>
                            <p className="truncate text-xs text-slate-500">
                                {person.team ||
                                    (person.role === 'team_leader'
                                        ? 'Team Leader'
                                        : 'Agent')}
                            </p>
                        </div>
                        <span className="flex items-center gap-1.5 text-xs text-slate-600 capitalize">
                            <i
                                className={`size-2 rounded-full ${colors[person.status]}`}
                            />
                            {person.status.replace('_', ' ')}
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}

function Avatar({ person }: { person: Pick<GamePerson, 'name' | 'avatar'> }) {
    return person.avatar ? (
        <img
            src={person.avatar}
            alt=""
            className="size-9 rounded-full object-cover"
        />
    ) : (
        <span className="grid size-9 place-items-center rounded-full bg-red-100 text-xs font-black text-red-800">
            {person.name
                .split(' ')
                .map((part) => part[0])
                .slice(0, 2)
                .join('')}
        </span>
    );
}

function Leaderboard({ rows }: { rows: GameSnapshot['leaderboard'] }) {
    return (
        <section>
            <h3 className="mb-2 flex items-center gap-2 font-black">
                <Trophy size={18} className="text-amber-500" />
                Friendly leaderboard
            </h3>
            <div className="overflow-hidden rounded-2xl border bg-white">
                {rows.length ? (
                    rows.slice(0, 5).map((row, index) => (
                        <div
                            key={row.user.id}
                            className="flex items-center gap-3 border-b p-3 last:border-0"
                        >
                            <span className="w-5 text-center text-sm font-black text-slate-400">
                                {index + 1}
                            </span>
                            <Avatar person={row.user} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-bold">
                                    {row.user.name}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {row.wins} wins · {row.played} played
                                </p>
                            </div>
                            <b className="text-sm text-red-700">{row.rating}</b>
                        </div>
                    ))
                ) : (
                    <p className="p-5 text-center text-sm text-slate-500">
                        Complete a game to start the leaderboard.
                    </p>
                )}
            </div>
        </section>
    );
}

function GameRoomView({
    room,
    me,
    busy,
    act,
    onClose,
    onRoom,
}: {
    room: GameRoom;
    me: number;
    busy: string;
    act: (key: string, action: () => Promise<void>) => Promise<void>;
    onClose: () => void;
    onRoom: (room: GameRoom) => void;
}) {
    const mine = room.players.find((player) => player.id === me);
    const opponent = room.players.find((player) => player.id !== me);
    const myTurn = room.current_turn_id === me || room.game_type === 'poker';
    const move = (action: Record<string, unknown>) =>
        act('move', async () => {
            const result = await gameRequest<{ room: GameRoom }>(
                `/mini-games/rooms/${room.id}/move`,
                'POST',
                { version: room.version, action },
            );
            onRoom(result.room);
        });
    const leave = () =>
        act('leave', async () => {
            await gameRequest(`/mini-games/rooms/${room.id}/leave`, 'POST');
            onClose();
        });

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <button
                    onClick={onClose}
                    className="flex items-center gap-1 text-sm font-bold text-slate-600"
                >
                    <ChevronDown className="rotate-90" size={17} />
                    Lounge
                </button>
                <span
                    className={`rounded-full px-3 py-1 text-xs font-bold capitalize ${room.status === 'active' ? 'bg-emerald-100 text-emerald-800' : room.status === 'paused' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'}`}
                >
                    {room.status}
                </span>
            </div>
            <div className="rounded-2xl bg-[linear-gradient(120deg,#211727,#701d27)] p-4 text-white">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-xs text-white/60">NOW PLAYING</p>
                        <h3 className="text-xl font-black">
                            {gameName(room.game_type)}
                        </h3>
                    </div>
                    <Gamepad2 size={30} className="text-red-200" />
                </div>
                <div className="mt-4 flex items-center justify-between text-sm">
                    <b>{mine?.name || 'You'}</b>
                    <span className="text-white/50">VS</span>
                    <b>{opponent?.name || 'Waiting…'}</b>
                </div>
            </div>
            {room.status === 'waiting' ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                    <LoaderCircle className="mx-auto animate-spin text-red-700" />
                    <h4 className="mt-3 font-black">
                        Waiting for another player
                    </h4>
                    <p className="mt-1 text-sm text-slate-500">
                        You can minimize this panel while matchmaking continues.
                    </p>
                </div>
            ) : room.status === 'paused' ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-center">
                    <Minimize2 className="mx-auto text-amber-700" />
                    <h4 className="mt-2 font-black">
                        Paused for call priority
                    </h4>
                    <p className="mt-1 text-sm text-amber-800">
                        Resume only when both players are available.
                    </p>
                    <button
                        disabled={!!busy}
                        onClick={() =>
                            void act('resume', async () => {
                                const result = await gameRequest<{
                                    room: GameRoom;
                                }>(
                                    `/mini-games/rooms/${room.id}/resume`,
                                    'POST',
                                );
                                onRoom(result.room);
                            })
                        }
                        className="mt-4 rounded-xl bg-amber-700 px-4 py-2 font-bold text-white"
                    >
                        <RotateCcw className="mr-2 inline" size={16} />
                        Resume
                    </button>
                </div>
            ) : room.status === 'completed' ? (
                <Result room={room} me={me} />
            ) : (
                <>
                    <p className="text-center text-sm font-bold text-slate-600">
                        {myTurn
                            ? 'Your move'
                            : `Waiting for ${opponent?.name ?? 'opponent'}`}
                    </p>
                    <GameBoard
                        room={room}
                        me={me}
                        disabled={!myTurn || !!busy}
                        move={move}
                    />
                </>
            )}
            <div className="flex items-center justify-between">
                <p className="max-w-[240px] text-xs leading-5 text-slate-500">
                    Games pause immediately when the call integration dispatches
                    an incoming-call event.
                </p>
                <button
                    disabled={!!busy}
                    onClick={() => void leave()}
                    className="rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700"
                >
                    {room.status === 'completed'
                        ? 'Close room'
                        : 'Exit instantly'}
                </button>
            </div>
        </div>
    );
}

function Result({ room, me }: { room: GameRoom; me: number }) {
    const text =
        room.winner_id === me
            ? 'You won!'
            : room.winner_id
              ? `${room.players.find((p) => p.id === room.winner_id)?.name} won`
              : 'Draw game';

    return (
        <div className="rounded-2xl border bg-white p-8 text-center">
            <Trophy className="mx-auto text-amber-500" size={42} />
            <h4 className="mt-3 text-xl font-black">{text}</h4>
            {room.game_type === 'poker' && (
                <div className="mt-3 space-y-1 text-sm text-slate-600">
                    {room.players.map((player) => (
                        <p key={player.id}>
                            <b>{player.name}:</b>{' '}
                            {room.state?.labels?.[String(player.id)]}
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}

function GameBoard({
    room,
    me,
    disabled,
    move,
}: {
    room: GameRoom;
    me: number;
    disabled: boolean;
    move: (action: Record<string, unknown>) => Promise<void>;
}) {
    const [selected, setSelected] = useState<number>();
    const [discard, setDiscard] = useState<number[]>([]);
    const board = room.state?.board ?? [];

    if (room.game_type === 'tic_tac_toe') {
        return (
            <div className="mx-auto grid max-w-[300px] grid-cols-3 gap-2">
                {board.map((cell, index) => (
                    <button
                        key={index}
                        disabled={disabled || !!cell}
                        onClick={() => void move({ index })}
                        className="aspect-square rounded-2xl border-2 border-slate-200 bg-white text-4xl font-black text-red-700 shadow-sm disabled:cursor-default"
                    >
                        {cell}
                    </button>
                ))}
            </div>
        );
    }

    if (room.game_type === 'connect_four') {
        return (
            <div className="mx-auto grid max-w-[350px] grid-cols-7 gap-1 rounded-2xl bg-blue-700 p-3">
                {board.map((cell, index) => (
                    <button
                        key={index}
                        disabled={disabled}
                        onClick={() => void move({ column: index % 7 })}
                        className={`aspect-square rounded-full border-2 border-blue-900/30 ${cell === 'R' ? 'bg-red-500' : cell === 'Y' ? 'bg-yellow-400' : 'bg-white'}`}
                        aria-label={`Column ${(index % 7) + 1}`}
                    />
                ))}
            </div>
        );
    }

    if (room.game_type === 'poker') {
        const hand = room.state?.hands?.[String(me)] ?? [];
        const acted = room.state?.acted?.includes(me);
        const suits: Record<string, string> = {
            S: '♠',
            H: '♥',
            D: '♦',
            C: '♣',
        };

        return (
            <div className="rounded-2xl bg-emerald-800 p-5">
                <div className="flex justify-center gap-2">
                    {hand.map((card, index) => (
                        <button
                            key={index}
                            disabled={disabled || acted}
                            onClick={() =>
                                setDiscard((current) =>
                                    current.includes(index)
                                        ? current.filter(
                                              (item) => item !== index,
                                          )
                                        : current.length < 3
                                          ? [...current, index]
                                          : current,
                                )
                            }
                            className={`grid h-24 w-16 place-items-center rounded-xl border-2 bg-white text-lg font-black shadow-lg ${discard.includes(index) ? '-translate-y-3 border-amber-400' : 'border-white'}`}
                        >
                            {card === '??' ? (
                                '🂠'
                            ) : (
                                <>
                                    <span>{card[0]}</span>
                                    <span
                                        className={
                                            ['H', 'D'].includes(card[1])
                                                ? 'text-red-600'
                                                : 'text-slate-900'
                                        }
                                    >
                                        {suits[card[1]]}
                                    </span>
                                </>
                            )}
                        </button>
                    ))}
                </div>
                <button
                    disabled={disabled || acted}
                    onClick={() => void move({ discard })}
                    className="mt-5 w-full rounded-xl bg-white px-4 py-2.5 font-black text-emerald-900 disabled:opacity-50"
                >
                    {acted ? 'Draw locked — waiting' : 'Draw selected cards'}
                </button>
            </div>
        );
    }

    const chessPieces: Record<string, string> = {
        K: '♔',
        Q: '♕',
        R: '♖',
        B: '♗',
        N: '♘',
        P: '♙',
        k: '♚',
        q: '♛',
        r: '♜',
        b: '♝',
        n: '♞',
        p: '♟',
    };
    const mySymbol = room.players.find((player) => player.id === me)?.symbol;

    return (
        <div className="mx-auto grid max-w-[390px] grid-cols-8 overflow-hidden rounded-xl border-4 border-[#3f2a27]">
            {board.map((cell, index) => {
                const row = Math.floor(index / 8);
                const dark = (row + (index % 8)) % 2 === 1;
                const isMine =
                    room.game_type === 'checkers'
                        ? cell?.toLowerCase() === mySymbol
                        : Boolean(cell) &&
                          /[A-Z]/.test(cell ?? '') === (mySymbol === 'white');

                return (
                    <button
                        key={index}
                        disabled={disabled}
                        onClick={() => {
                            if (selected === undefined) {
                                if (isMine) {
                                    setSelected(index);
                                }
                            } else {
                                void move({
                                    from: selected,
                                    to: index,
                                }).finally(() => setSelected(undefined));
                            }
                        }}
                        className={`relative aspect-square text-2xl sm:text-3xl ${dark ? 'bg-[#9b6655]' : 'bg-[#f4dfc2]'} ${selected === index ? 'ring-4 ring-amber-400 ring-inset' : ''}`}
                    >
                        {room.game_type === 'checkers'
                            ? cell && (
                                  <span
                                      className={`mx-auto grid size-[70%] place-items-center rounded-full shadow-md ${cell.toLowerCase() === 'r' ? 'bg-red-700 text-white' : 'bg-slate-900 text-white'}`}
                                  >
                                      {cell === cell.toUpperCase() ? '★' : ''}
                                  </span>
                              )
                            : cell
                              ? chessPieces[cell]
                              : ''}
                    </button>
                );
            })}
        </div>
    );
}
