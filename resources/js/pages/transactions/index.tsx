import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { dashboard } from '@/routes';
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

interface Category {
    id: number;
    name: string;
    color: string;
}

interface Props {
    transactions: Transaction[];
    current_page: number;
    has_more: boolean;
    next_page: number | null;
    total: number;
    categories: Category[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatBRL(value: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Transações', href: TransactionController.index.url() },
];

// ─── New Transaction Dialog ───────────────────────────────────────────────────

function NewTransactionDialog({ open, onClose, categories }: {
    open: boolean;
    onClose: () => void;
    categories: Category[];
}) {
    const today = new Date().toISOString().slice(0, 10);
    const { data, setData, post, processing, errors, reset } = useForm({
        date: today,
        description: '',
        amount: '',
        type: 'debit' as 'debit' | 'credit',
        category_id: '',
    });

    function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(TransactionController.store.url(), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    function handleClose() {
        reset();
        onClose();
    }

    return (
        <Dialog open={open} onOpenChange={(v) => !v && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nova transação</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="flex flex-col gap-4 pt-1">
                    {/* Date */}
                    <div className="flex flex-col gap-1">
                        <label className="text-sm font-medium">Data</label>
                        <input
                            type="date"
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            className="rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-ring"
                        />
                        {errors.date && <p className="text-xs text-red-500">{errors.date}</p>}
                    </div>

                    {/* Description */}
                    <div className="flex flex-col gap-1">
                        <label className="text-sm font-medium">Descrição</label>
                        <input
                            type="text"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Ex: Supermercado, Salário..."
                            className="rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-ring"
                        />
                        {errors.description && <p className="text-xs text-red-500">{errors.description}</p>}
                    </div>

                    {/* Amount + Type */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="flex flex-col gap-1">
                            <label className="text-sm font-medium">Valor (R$)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                placeholder="0,00"
                                className="rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-ring"
                            />
                            {errors.amount && <p className="text-xs text-red-500">{errors.amount}</p>}
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="text-sm font-medium">Tipo</label>
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value as 'debit' | 'credit')}
                                className="rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="debit">Gasto</option>
                                <option value="credit">Receita</option>
                            </select>
                        </div>
                    </div>

                    {/* Category */}
                    <div className="flex flex-col gap-1">
                        <label className="text-sm font-medium">Categoria</label>
                        <select
                            value={data.category_id}
                            onChange={(e) => setData('category_id', e.target.value)}
                            className="rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option value="">Sem categoria</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2 className="size-4 animate-spin" /> : 'Salvar'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function TransactionsIndex({
    transactions: initialTransactions,
    current_page,
    has_more,
    next_page,
    total,
    categories,
}: Props) {
    const openNew = new URLSearchParams(window.location.search).get('new') === '1';

    const [allTransactions, setAllTransactions] = useState<Transaction[]>(initialTransactions);
    const [isLoading, setIsLoading] = useState(false);
    const [newOpen, setNewOpen] = useState(openNew);
    const loaderRef = useRef<HTMLDivElement>(null);

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
        setIsLoading(false);
    }, [initialTransactions, current_page]);

    useEffect(() => {
        if (!loaderRef.current) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && has_more && !isLoading) {
                    setIsLoading(true);
                    router.reload({
                        data: { page: next_page },
                        only: ['transactions', 'current_page', 'has_more', 'next_page'],
                        preserveUrl: true,
                    });
                }
            },
            { threshold: 0.1 },
        );
        observer.observe(loaderRef.current);
        return () => observer.disconnect();
    }, [has_more, isLoading, next_page]);

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
