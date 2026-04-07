import { formatBRL, formatDateBR } from '@/lib/formatters';
import type { Transaction } from '@/types';

export function TransactionList({
    transactions,
}: {
    transactions: Transaction[];
}) {
    return (
        <ul className="flex flex-col divide-y text-sm">
            {transactions.map((t) => (
                <li
                    key={t.id}
                    className="flex items-center justify-between gap-3 py-2"
                >
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-medium">{t.description}</p>
                        <div className="mt-0.5 flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">
                                {formatDateBR(t.date)}
                            </span>
                            <span
                                className="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium"
                                style={{
                                    background: t.category_color + '22',
                                    color: t.category_color,
                                }}
                            >
                                {t.category_name}
                            </span>
                        </div>
                    </div>
                    <span
                        className={`shrink-0 font-semibold ${t.type === 'credit' ? 'text-green-500' : 'text-red-500'}`}
                    >
                        {t.type === 'credit' ? '+' : '-'}
                        {formatBRL(t.amount)}
                    </span>
                </li>
            ))}
        </ul>
    );
}
