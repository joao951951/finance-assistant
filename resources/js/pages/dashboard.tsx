import { Head, router } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Summary {
    total_spent: number;
    total_income: number;
    balance: number;
    transactions_count: number;
    month_label: string;
}

interface CategorySpending {
    name: string;
    color: string;
    total: number;
}

interface TrendPoint {
    period: string;
    label: string;
    spent: number;
    income: number;
}

type TrendPeriod = 'daily' | 'monthly' | 'annual';

interface Transaction {
    id: number;
    date: string;
    description: string;
    amount: number;
    type: 'credit' | 'debit';
    category_name: string;
    category_color: string;
}

interface AvailableMonth {
    value: string;
    label: string;
}

interface Props {
    summary: Summary;
    spendingByCategory: CategorySpending[];
    trend: TrendPoint[];
    trendPeriod: TrendPeriod;
    recentTransactions: Transaction[];
    selectedMonth: string;
    availableMonths: AvailableMonth[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatBRL(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

// ─── Sub-components ──────────────────────────────────────────────────────────

function SummaryCard({
    title,
    value,
    subtitle,
    valueClass = '',
}: {
    title: string;
    value: string;
    subtitle?: string;
    valueClass?: string;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className={`text-2xl font-bold ${valueClass}`}>{value}</p>
                {subtitle && <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>}
            </CardContent>
        </Card>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-40 items-center justify-center text-sm text-muted-foreground">
            {message}
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

const TREND_OPTIONS: { value: TrendPeriod; label: string }[] = [
    { value: 'daily',   label: 'Diário'  },
    { value: 'monthly', label: 'Mensal'  },
    { value: 'annual',  label: 'Anual'   },
];

export default function Dashboard({
    summary,
    spendingByCategory,
    trend,
    trendPeriod,
    recentTransactions,
    selectedMonth,
    availableMonths,
}: Props) {
    const hasCategories   = spendingByCategory.length > 0;
    const hasTrend        = trend.length > 0;
    const hasTransactions = recentTransactions.length > 0;

    function handleMonthChange(month: string) {
        router.visit(dashboard(), {
            data: { month, trend: trendPeriod },
            preserveScroll: true,
        });
    }

    function handleTrendChange(period: TrendPeriod) {
        router.visit(dashboard(), {
            data: { month: selectedMonth, trend: period },
            preserveScroll: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        title={`Gastos — ${summary.month_label}`}
                        value={formatBRL(summary.total_spent)}
                        valueClass="text-red-500"
                    />
                    <SummaryCard
                        title={`Receita — ${summary.month_label}`}
                        value={formatBRL(summary.total_income)}
                        valueClass="text-green-500"
                    />
                    <SummaryCard
                        title="Saldo do mês"
                        value={formatBRL(summary.balance)}
                        valueClass={summary.balance >= 0 ? 'text-green-500' : 'text-red-500'}
                    />
                    <SummaryCard
                        title="Transações"
                        value={summary.transactions_count.toString()}
                        subtitle={summary.month_label}
                    />
                </div>

                {/* Charts row */}
                <div className="grid gap-4 lg:grid-cols-2">

                    {/* Spending by category — donut */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="text-base">Gastos por categoria</CardTitle>
                            {availableMonths.length > 0 && (
                                <select
                                    value={selectedMonth}
                                    onChange={(e) => handleMonthChange(e.target.value)}
                                    className="rounded-md border bg-background px-2 py-1 text-xs text-muted-foreground outline-none focus:ring-1 focus:ring-ring"
                                >
                                    {availableMonths.map((m) => (
                                        <option key={m.value} value={m.value}>{m.label}</option>
                                    ))}
                                </select>
                            )}
                        </CardHeader>
                        <CardContent>
                            {hasCategories ? (
                                <div className="flex flex-col items-center gap-4 sm:flex-row">
                                    <ResponsiveContainer width={220} height={220}>
                                        <PieChart>
                                            <Pie
                                                data={spendingByCategory}
                                                dataKey="total"
                                                nameKey="name"
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={60}
                                                outerRadius={100}
                                                paddingAngle={2}
                                            >
                                                {spendingByCategory.map((entry) => (
                                                    <Cell key={entry.name} fill={entry.color} />
                                                ))}
                                            </Pie>
                                            <Tooltip formatter={(v) => formatBRL(Number(v))} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                    <ul className="flex flex-1 flex-col gap-2 text-sm">
                                        {spendingByCategory.map((c) => (
                                            <li key={c.name} className="flex items-center justify-between gap-2">
                                                <span className="flex items-center gap-2">
                                                    <span
                                                        className="inline-block size-2.5 flex-shrink-0 rounded-full"
                                                        style={{ background: c.color }}
                                                    />
                                                    {c.name}
                                                </span>
                                                <span className="font-medium">{formatBRL(c.total)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : (
                                <EmptyState message="Nenhuma transação no mês atual" />
                            )}
                        </CardContent>
                    </Card>

                    {/* Trend — bar chart */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="text-base">Tendência</CardTitle>
                            <div className="flex gap-1">
                                {TREND_OPTIONS.map((opt) => (
                                    <button
                                        key={opt.value}
                                        onClick={() => handleTrendChange(opt.value)}
                                        className={`rounded-md px-2 py-1 text-xs transition-colors ${
                                            trendPeriod === opt.value
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:bg-muted'
                                        }`}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {hasTrend ? (
                                <ResponsiveContainer width="100%" height={220}>
                                    <BarChart data={trend} barGap={4}>
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-border" />
                                        <XAxis
                                            dataKey="label"
                                            tick={{ fontSize: 12 }}
                                            axisLine={false}
                                            tickLine={false}
                                        />
                                        <YAxis
                                            tick={{ fontSize: 11 }}
                                            axisLine={false}
                                            tickLine={false}
                                            tickFormatter={(v: number) =>
                                                new Intl.NumberFormat('pt-BR', {
                                                    notation: 'compact',
                                                    currency: 'BRL',
                                                    style: 'currency',
                                                }).format(v)
                                            }
                                        />
                                        <Tooltip formatter={(v) => formatBRL(Number(v))} />
                                        <Bar dataKey="income" name="Receita" fill="#22c55e" radius={[4, 4, 0, 0]} />
                                        <Bar dataKey="spent"  name="Gastos"  fill="#ef4444" radius={[4, 4, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            ) : (
                                <EmptyState message="Nenhum dado para exibir" />
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Recent transactions */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <CardTitle className="text-base">Transações recentes</CardTitle>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(TransactionController.index.url())}
                            className="text-xs"
                        >
                            Ver todas
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {hasTransactions ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="pb-2 pr-4 font-medium">Data</th>
                                            <th className="pb-2 pr-4 font-medium">Descrição</th>
                                            <th className="pb-2 pr-4 font-medium">Categoria</th>
                                            <th className="pb-2 text-right font-medium">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentTransactions.map((t) => (
                                            <tr key={t.id} className="border-b last:border-0">
                                                <td className="py-2 pr-4 text-muted-foreground">
                                                    {new Date(t.date).toLocaleDateString('pt-BR')}
                                                </td>
                                                <td className="max-w-[220px] truncate py-2 pr-4">{t.description}</td>
                                                <td className="py-2 pr-4">
                                                    <span
                                                        className="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                                        style={{
                                                            background: t.category_color + '22',
                                                            color: t.category_color,
                                                        }}
                                                    >
                                                        {t.category_name}
                                                    </span>
                                                </td>
                                                <td
                                                    className={`py-2 text-right font-medium ${
                                                        t.type === 'credit' ? 'text-green-500' : 'text-red-500'
                                                    }`}
                                                >
                                                    {t.type === 'credit' ? '+' : '-'}
                                                    {formatBRL(t.amount)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState message="Nenhuma transação ainda. Importe um extrato para começar." />
                        )}
                    </CardContent>
                </Card>

            </div>
        </AppLayout>
    );
}
