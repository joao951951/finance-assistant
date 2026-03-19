import { Head, useForm, router } from '@inertiajs/react';
import { Upload, Trash2, FileText, AlertCircle, CheckCircle, Clock, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import type { BreadcrumbItem } from '@/types';

interface RawImport {
    id: number;
    filename: string;
    type: 'csv' | 'pdf';
    bank: string | null;
    status: 'pending' | 'processing' | 'done' | 'failed';
    transactions_count: number;
    error_message: string | null;
    created_at: string;
}

interface Props {
    imports: RawImport[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Importações', href: ImportController.index() },
];

const STATUS_CONFIG = {
    pending: { label: 'Aguardando', icon: Clock, className: 'text-yellow-500' },
    processing: { label: 'Processando', icon: Loader2, className: 'text-blue-500 animate-spin' },
    done: { label: 'Concluído', icon: CheckCircle, className: 'text-green-500' },
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

function ImportCard({ item }: { item: RawImport }) {
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
                        {item.transactions_count > 0 ? `${item.transactions_count} transações · ` : ''}
                        {new Date(item.created_at).toLocaleDateString('pt-BR')}
                    </p>
                    {item.error_message && (
                        <p className="mt-1 text-xs text-red-500">{item.error_message}</p>
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

export default function ImportsIndex({ imports: importList }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{ files: File[] }>({
        files: [],
    });

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const selected = Array.from(e.target.files ?? []);
        setData('files', selected);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(ImportController.store.url(), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                // clear the native file input
                const input = document.getElementById('file-upload') as HTMLInputElement | null;
                if (input) input.value = '';
            },
        });
    }

    const fileLabel = data.files.length === 0
        ? 'Clique ou arraste os arquivos aqui'
        : data.files.length === 1
            ? data.files[0].name
            : `${data.files.length} arquivos selecionados`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Importar Extrato" />

            <div className="flex flex-col gap-6 p-4">
                {/* Upload card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Importar Extrato</CardTitle>
                        <CardDescription>
                            Envie extratos CSV ou PDF do seu banco. Suporte a Nubank, Inter, Bradesco, C6 e formatos genéricos. Você pode selecionar vários arquivos de uma vez.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="flex items-end gap-4">
                            <div className="flex-1">
                                <label
                                    htmlFor="file-upload"
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/30 px-6 py-8 text-center transition hover:border-primary/50 hover:bg-accent"
                                >
                                    <Upload className="mb-2 size-8 text-muted-foreground" />
                                    <span className="text-sm font-medium">{fileLabel}</span>
                                    {data.files.length > 1 && (
                                        <ul className="mt-2 flex flex-col gap-0.5">
                                            {data.files.map((f, i) => (
                                                <li key={i} className="text-xs text-muted-foreground">{f.name}</li>
                                            ))}
                                        </ul>
                                    )}
                                    <span className="mt-2 text-xs text-muted-foreground">CSV ou PDF · até 20 MB por arquivo</span>
                                    <input
                                        id="file-upload"
                                        type="file"
                                        accept=".csv,.txt,.pdf"
                                        multiple
                                        className="sr-only"
                                        onChange={handleFileChange}
                                    />
                                </label>
                                {errors.files && (
                                    <p className="mt-1 text-sm text-red-500">{errors.files}</p>
                                )}
                            </div>
                            <Button type="submit" disabled={data.files.length === 0 || processing}>
                                {processing ? (
                                    <><Loader2 className="mr-2 size-4 animate-spin" /> Enviando...</>
                                ) : (
                                    <><Upload className="mr-2 size-4" /> Enviar</>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* History */}
                {importList.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Histórico de Importações</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {importList.map((item) => (
                                <ImportCard key={item.id} item={item} />
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
