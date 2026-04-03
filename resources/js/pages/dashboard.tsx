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
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/empty-state';
import { SummaryCard } from '@/components/summary-card';
import { TransactionList } from '@/components/transaction-list';
import AppLayout from '@/layouts/app-layout';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { dashboard } from '@/routes';
import { formatBRL, formatBRLCompact, formatDateBR } from '@/lib/formatters';
import type { BreadcrumbItem, Summary, CategorySpending, TrendPoint, Transaction, AvailableMonth } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Props {
    summary: Summary;
    spendingByCategory: CategorySpending[];
    monthTransactions: Transaction[];
    trend: TrendPoint[];
    recentTransactions: Transaction[];
    selectedMonth: string;
    availableMonths: AvailableMonth[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Dashboard({
    summary,
    spendingByCategory,
    monthTransactions,
    trend,
    recentTransactions,
    selectedMonth,
    availableMonths,
}: Props) {
    const [txDialogOpen, setTxDialogOpen] = useState(false);

    const hasCategories = spendingByCategory.length > 0;
    const hasMonthTransactions = monthTransactions.length > 0;
    const hasTrend = trend.length > 0;
    const hasTransactions = recentTransactions.length > 0;

    function handleMonthChange(month: string) {
        router.visit(dashboard(), {
            data: { month },
            preserveScroll: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">

                {/* Summary — header with month selector */}
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold text-muted-foreground">{summary.month_label}</h2>
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
                </div>

                {/* Summary cards */}
                <div className="-mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        title="Gastos"
                        value={formatBRL(summary.total_spent)}
                        valueClass="text-red-500"
                    />
                    <SummaryCard
                        title="Receita"
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
                    />
                </div>

                {/* Category chart + month transactions side by side */}
                <div className="grid gap-4 lg:grid-cols-2">

                    {/* Spending by category — donut */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Gastos por categoria</CardTitle>
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
                                <EmptyState message="Nenhuma transação no período selecionado" />
                            )}
                        </CardContent>
                    </Card>

                    {/* Latest transactions of the selected month */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="text-base">Últimas transações do mês</CardTitle>
                            {hasMonthTransactions && (
                                <Button variant="outline" size="sm" className="text-xs" onClick={() => setTxDialogOpen(true)}>
                                    Ver mais
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent>
                            {hasMonthTransactions ? (
                                <TransactionList transactions={monthTransactions.slice(0, 4)} />
                            ) : (
                                <EmptyState message="Nenhuma transação no período selecionado" />
                            )}
                        </CardContent>
                    </Card>

                    {/* Dialog — all transactions of the selected month */}
                    <Dialog open={txDialogOpen} onOpenChange={setTxDialogOpen}>
                        <DialogContent className="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Transações de {summary.month_label}</DialogTitle>
                            </DialogHeader>
                            <TransactionList transactions={monthTransactions} />
                        </DialogContent>
                    </Dialog>

                </div>

                {/* Trend — daily breakdown of the selected month */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Tendência do mês</CardTitle>
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
                                        tickFormatter={(v: number) => formatBRLCompact(v)}
                                    />
                                    <Tooltip formatter={(v) => formatBRL(Number(v))} />
                                    <Bar dataKey="income" name="Receita" fill="#22c55e" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="spent" name="Gastos" fill="#ef4444" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <EmptyState message="Nenhum dado para exibir" />
                        )}
                    </CardContent>
                </Card>

                {/* Recent transactions */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <CardTitle className="text-base">Todas as transações</CardTitle>
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
                                                    {formatDateBR(t.date)}
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
                                                    className={`py-2 text-right font-medium ${t.type === 'credit' ? 'text-green-500' : 'text-red-500'
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
