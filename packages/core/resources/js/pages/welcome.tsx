import { Head } from '@inertiajs/react';

export default function Welcome({ version }: { version: string }) {
    return (
        <>
            <Head title="LADCMS" />
            <main className="flex min-h-screen items-center justify-center">
                <div className="text-center">
                    <h1 className="text-3xl font-bold">LADCMS</h1>
                    <p className="text-muted-foreground">core {version}</p>
                </div>
            </main>
        </>
    );
}
