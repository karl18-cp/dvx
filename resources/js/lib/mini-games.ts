export type GameType =
    'tic_tac_toe' | 'connect_four' | 'checkers' | 'chess' | 'poker';

export type GamePerson = {
    id: number;
    name: string;
    username: string;
    avatar: string;
    role: string;
    team?: string;
    status: 'available' | 'playing' | 'on_call' | 'offline';
};

export type GamePlayer = {
    id: number;
    name: string;
    username: string;
    avatar: string;
    seat: number;
    symbol?: string;
};

export type GameRoom = {
    id: string;
    game_type: GameType;
    status: 'waiting' | 'active' | 'paused' | 'completed' | 'cancelled';
    version: number;
    host_id: number;
    current_turn_id?: number;
    winner_id?: number;
    state?: {
        board?: (string | null)[];
        hands?: Record<string, string[]>;
        acted?: number[];
        labels?: Record<string, string>;
    };
    players: GamePlayer[];
    updated_at: string;
};

export type GameSnapshot = {
    me: number;
    people: GamePerson[];
    rooms: GameRoom[];
    invitations: {
        id: number;
        sender: { id: number; name: string; avatar: string };
        room: GameRoom;
        expires_at: string;
    }[];
    leaderboard: {
        user: { id: number; name: string; avatar: string };
        played: number;
        wins: number;
        losses: number;
        draws: number;
        rating: number;
    }[];
};

const token = () =>
    document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

export async function gameRequest<T>(
    url: string,
    method = 'GET',
    data?: unknown,
    signal?: AbortSignal,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        signal,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token()
                ? { 'X-XSRF-TOKEN': decodeURIComponent(token() ?? '') }
                : {}),
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = body.errors as Record<string, string[]> | undefined;

        throw new Error(
            errors
                ? Object.values(errors).flat()[0]
                : body.message || 'The game request failed.',
        );
    }

    return body as T;
}
