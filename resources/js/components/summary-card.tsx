import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface SummaryCardProps {
    title: string;
    value: string;
    subtitle?: string;
    valueClass?: string;
}

export function SummaryCard({
    title,
    value,
    subtitle,
    valueClass = '',
}: SummaryCardProps) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className={`text-2xl font-bold ${valueClass}`}>{value}</p>
                {subtitle && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {subtitle}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
