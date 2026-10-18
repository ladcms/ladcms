import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';

/** Временная главная (в Task 3 заменяется на `core::home`). */
export default function Welcome({ version }: { version: string }) {
    return (
        <>
            <Head title="LADCMS" />
            <Card>
                <CardHeader>
                    <CardTitle className="text-2xl">LADCMS</CardTitle>
                </CardHeader>
                <CardContent className="text-muted-foreground">
                    core {version}
                </CardContent>
            </Card>
        </>
    );
}
