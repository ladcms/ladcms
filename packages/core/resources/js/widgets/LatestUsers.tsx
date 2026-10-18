type LatestUser = {
    id: number;
    login: string;
    display_name: string;
    avatar_url: string | null;
    created_at: string;
};

type Props = { users: LatestUser[] };

// Placeholder: replaced by the real widget in Task 2.
export default function LatestUsers({ users }: Props) {
    return (
        <section>
            <ul>
                {users.map((user) => (
                    <li key={user.id}>{user.display_name}</li>
                ))}
            </ul>
        </section>
    );
}
