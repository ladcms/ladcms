type OnlineUser = {
    id: number;
    login: string;
    display_name: string;
    avatar_url: string | null;
};

type Props = { count: number; users: OnlineUser[] };

// Placeholder: replaced by the real widget in Task 2.
export default function Online({ count, users }: Props) {
    return (
        <section>
            <h2>{count}</h2>
            <ul>
                {users.map((user) => (
                    <li key={user.id}>{user.display_name}</li>
                ))}
            </ul>
        </section>
    );
}
