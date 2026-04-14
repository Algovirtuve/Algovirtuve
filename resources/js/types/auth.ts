export type User = {
    id: number;
    name: string;
    first_name?: string | null;
    last_name?: string | null;
    nickname?: string | null;
    email: string;
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
