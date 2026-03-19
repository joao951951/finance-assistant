import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Transaction {
    id: number;
    date: string;
    description: string;
    amount: number;
    type: 'credit' | 'debit';
    category_name: string;
    category_color: string;
}

interface Props {
    transactions: Transaction[];
    current_page: number;
    has_more: boolean;
    next_page: number | null;
    total: number;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatBRL(value: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Transações', href: TransactionController.index.url() },
];

// ─── Main page ────────────────────────────────────────────────────────────────

export default function TransactionsIndex({
    transactions: initialTransactions,
    current_page,
    has_more,
    next_page,
    total,
}: Props) {
    const [allTransactions, setAllTransactions] = useState<Transaction[]>(initialTransactions);
    const [isLoading, setIsLoading] = useState(false);
    const loaderRef = useRef<HTMLDivElement>(null);

    // When server sends new page data, append to the list
    useEffect(() => {
        if (current_page === 1) {
            setAllTransactions(initialTransactions);
        } else {
            setAllTransactions((prev) => [...prev, ...initialTransactions]);
        }
        setIsLoading(false);
    }, [initialTransactions, current_page]);

    // Infinite scroll: observe the loader sentinel
    useEffect(() => {
        if (!loaderRef.current) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && has_more && !isLoading) {
                    setIsLoading(true);
                    router.reload({
                        data: { page: next_page },
                        only: ['transactions', 'current_page', 'has_more', 'next_page'],
                        preserveState: true,
                        preserveUrl: true,
                    });
                }
            },
            { threshold: 0.1 },
        );

        observer.observe(loaderRef.current);
        return () => observer.disconnect();
    }, [has_more, isLoading, next_page]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transações" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold">Todas as transações</h1>
                        <p className="text-sm text-muted-foreground">{total} transações no total</p>
                    </div>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Mostrando {allTransactions.length} de {total}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-4 pb-3 pt-2 font-medium">Data</th>
                                        <th className="px-4 pb-3 pt-2 font-medium">Descrição</th>
                                        <th className="hidden px-4 pb-3 pt-2 font-medium sm:table-cell">Categoria</th>
                                        <th className="px-4 pb-3 pt-2 text-right font-medium">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {allTransactions.map((t) => (
                                        <tr key={t.id} className="border-b last:border-0 hover:bg-muted/30">
                                            <td className="whitespace-nowrap px-4 py-3 text-muted-foreground">
                                                {new Date(t.date).toLocaleDateString('pt-BR')}
                                            </td>
                                            <td className="max-w-[200px] truncate px-4 py-3 sm:max-w-[320px]">
                                                {t.description}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
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
                                                className={`whitespace-nowrap px-4 py-3 text-right font-medium ${
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

                        {/* Sentinel + loader */}
                        <div ref={loaderRef} className="flex justify-center py-6">
                            {isLoading && <Loader2 className="size-5 animate-spin text-muted-foreground" />}
                            {!has_more && allTransactions.length > 0 && (
                                <p className="text-xs text-muted-foreground">Todas as transações carregadas</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
