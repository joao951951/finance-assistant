import { router } from '@inertiajs/react';
import {
    FileText,
    Trash2,
    Clock,
    Loader2,
    CheckCircle,
    AlertCircle,
} from 'lucide-react';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import { Button } from '@/components/ui/button';
import type { RawImport } from '@/types';

const STATUS_CONFIG = {
    pending: { label: 'Aguardando', icon: Clock, className: 'text-yellow-500' },
    processing: {
        label: 'Processando',
        icon: Loader2,
        className: 'text-blue-500 animate-spin',
    },
    done: {
        label: 'Concluído',
        icon: CheckCircle,
        className: 'text-green-500',
    },
    failed: { label: 'Erro', icon: AlertCircle, className: 'text-red-500' },
} as const;

function StatusBadge({ status }: { status: RawImport['status'] }) {
    const config = STATUS_CONFIG[status];
    const Icon = config.icon;

    return (
        <span className="flex items-center gap-1 text-sm">
            <Icon className={`size-4 ${config.className}`} />
            {config.label}
        </span>
    );
}

export function ImportCard({ item }: { item: RawImport }) {
    function handleDelete() {
        router.delete(ImportController.destroy({ rawImport: item.id }), {
            preserveScroll: true,
        });
    }

    return (
        <div className="flex items-center justify-between rounded-lg border p-4">
            <div className="flex items-center gap-3">
                <FileText className="size-5 text-muted-foreground" />
                <div>
                    <p className="text-sm font-medium">{item.filename}</p>
                    <p className="text-xs text-muted-foreground">
                        {item.bank ? `${item.bank} · ` : ''}
                        {item.transactions_count > 0
                            ? `${item.transactions_count} transações · `
                            : ''}
                        {new Date(item.created_at).toLocaleDateString('pt-BR')}
                    </p>
                    {item.error_message && (
                        <p className="mt-1 text-xs text-red-500">
                            {item.error_message}
                        </p>
                    )}
                </div>
            </div>
            <div className="flex items-center gap-4">
                <StatusBadge status={item.status} />
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={handleDelete}
                    disabled={item.status === 'processing'}
                    className="text-muted-foreground hover:text-destructive"
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
        </div>
    );
}
