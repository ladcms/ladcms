import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@ladcms/core/components/ui/card';

const t = {
    title: 'Панель управления',
    welcome: 'Добро пожаловать',
    stub: 'Виджеты панели появятся позже.',
};

type Props = {
    stats: Record<string, unknown>;
};

export default function Dashboard(_props: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{t.welcome}</CardTitle>
                <CardDescription>{t.stub}</CardDescription>
            </CardHeader>
            <CardContent />
        </Card>
    );
}

Dashboard.layout = {
    title: t.title,
};
