import { Head } from '@inertiajs/react';

type Props = {
    stats: Record<string, unknown>;
};

export default function Dashboard(_props: Props) {
    return (
        <>
            <Head title="Панель управления" />
            <h1 className="text-2xl font-semibold">Панель управления</h1>
        </>
    );
}
