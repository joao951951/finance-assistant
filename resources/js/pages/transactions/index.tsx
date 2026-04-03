import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NewTransactionDialog } from '@/components/new-transaction-dialog';
import AppLayout from '@/layouts/app-layout';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { dashboard } from '@/routes';
import { formatBRL, formatDateBR } from '@/lib/formatters';
import { useInfiniteScroll } from '@/hooks/use-infinite-scroll';
import type { BreadcrumbItem, Transaction, Category } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Props {
    transactions: Transaction[];
    current_page: number;
    has_more: boolean;
    next_page: number | null;
    total: number;
    categories: Category[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Transações', href: TransactionController.index.url() },
];

const SCROLL_ONLY = ['transactions', 'current_page', 'has_more', 'next_page'];

// ─── Main page ────────────────────────────────────────────────────────────────

export default function TransactionsIndex({
    transactions: initialTransactions,
    current_page,
    has_more,
    next_page,
    total,
    categories,
}: Props) {
    const openNew = useMemo(
        () => new URLSearchParams(window.location.search).get('new') === '1',
        [],
    );

    const [allTransactions, setAllTransactions] = useState<Transaction[]>(initialTransactions);
    const [newOpen, setNewOpen] = useState(openNew);

    const { loaderRef, isLoading } = useInfiniteScroll({
        hasMore: has_more,
        nextPage: next_page,
        only: SCROLL_ONLY,
    });

    // Remove ?new=1 from URL without reloading so refresh doesn't reopen the dialog
    useEffect(() => {
        if (openNew) {
            window.history.replaceState({}, '', window.location.pathname);
        }
    }, []);

    useEffect(() => {
        if (current_page === 1) {
            setAllTransactions(initialTransactions);
        } else {
            setAllTransactions((prev) => [...prev, ...initialTransactions]);
        }
    }, [initialTransactions, current_page]);

    function deleteTransaction(id: number) {
        router.delete(TransactionController.destroy.url({ transaction: id }), {
            preserveState: true,
            preserveUrl: true,
            onSuccess: () => setAllTransactions((prev) => prev.filter((t) => t.id !== id)),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transações" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold">Todas as transações</h1>
                        <p className="text-sm text-muted-foreground">{total} transações no total</p>
                    </div>
                    <Button size="sm" onClick={() => setNewOpen(true)}>
                        <Plus className="mr-1.5 size-4" />
                        Nova transação
                    </Button>
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
                                        <th className="px-4 pb-3 pt-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {allTransactions.map((t) => (
                                        <tr key={t.id} className="group border-b last:border-0 hover:bg-muted/30">
                                            <td className="whitespace-nowrap px-4 py-3 text-muted-foreground">
                                                {formatDateBR(t.date)}
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
                                                {t.type === 'credit' ? '+' : '-'}{formatBRL(t.amount)}
                                            </td>
                                            <td className="px-2 py-3">
                                                <button
                                                    onClick={() => deleteTransaction(t.id)}
                                                    className="invisible rounded p-1 text-muted-foreground hover:text-red-500 group-hover:visible"
                                                    title="Excluir"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div ref={loaderRef} className="flex justify-center py-6">
                            {isLoading && <Loader2 className="size-5 animate-spin text-muted-foreground" />}
                            {!has_more && allTransactions.length > 0 && (
                                <p className="text-xs text-muted-foreground">Todas as transações carregadas</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <NewTransactionDialog
                open={newOpen}
                onClose={() => setNewOpen(false)}
                categories={categories}
            />
        </AppLayout>
    );
}
