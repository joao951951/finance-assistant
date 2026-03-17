import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import ApiController from '@/actions/App/Http/Controllers/Settings/ApiController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'API / IA', href: ApiController.edit.url() },
];

const CHAT_MODELS = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];
const EMBEDDING_MODELS = ['text-embedding-3-small', 'text-embedding-3-large', 'text-embedding-ada-002'];

export default function ApiSettings({
    hasApiKey,
    chatModel,
    embeddingModel,
}: {
    hasApiKey: boolean;
    chatModel: string;
    embeddingModel: string;
}) {
    const form = useForm({
        api_key: '',
        chat_model: chatModel,
        embedding_model: embeddingModel,
    });

    const removeForm = useForm({});

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.patch(ApiController.update.url(), { preserveScroll: true });
    }

    function removeKey(e: React.FormEvent) {
        e.preventDefault();
        if (confirm('Remover a chave da API? O chat com IA ficará indisponível até você adicionar uma nova.')) {
            removeForm.delete(ApiController.destroy.url(), { preserveScroll: true });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Integração com OpenAI"
                        description="Configure sua chave de API para usar categorização automática, embeddings e o chat com IA"
                    />

                    {/* Status da chave */}
                    <div className={`rounded-lg border px-4 py-3 text-sm ${hasApiKey ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300' : 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-300'}`}>
                        {hasApiKey ? (
                            <span>✓ Chave de API configurada — os recursos de IA estão disponíveis</span>
                        ) : (
                            <span>⚠ Nenhuma chave configurada — adicione sua chave OpenAI para ativar os recursos de IA</span>
                        )}
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        {/* API Key */}
                        <div className="grid gap-2">
                            <Label htmlFor="api_key">
                                {hasApiKey ? 'Nova chave de API (deixe em branco para manter a atual)' : 'Chave de API OpenAI'}
                            </Label>
                            <Input
                                id="api_key"
                                type="password"
                                autoComplete="off"
                                placeholder={hasApiKey ? '••••••••••••••••••••••••' : 'sk-proj-...'}
                                value={form.data.api_key}
                                onChange={e => form.setData('api_key', e.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Obtenha sua chave em{' '}
                                <a
                                    href="https://platform.openai.com/api-keys"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline"
                                >
                                    platform.openai.com/api-keys
                                </a>
                            </p>
                        </div>

                        {/* Chat Model */}
                        <div className="grid gap-2">
                            <Label htmlFor="chat_model">Modelo de chat</Label>
                            <select
                                id="chat_model"
                                name="chat_model"
                                value={form.data.chat_model}
                                onChange={e => form.setData('chat_model', e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Padrão (gpt-4o)</option>
                                {CHAT_MODELS.map(m => (
                                    <option key={m} value={m}>{m}</option>
                                ))}
                            </select>
                        </div>

                        {/* Embedding Model */}
                        <div className="grid gap-2">
                            <Label htmlFor="embedding_model">Modelo de embeddings</Label>
                            <select
                                id="embedding_model"
                                name="embedding_model"
                                value={form.data.embedding_model}
                                onChange={e => form.setData('embedding_model', e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Padrão (text-embedding-3-small)</option>
                                {EMBEDDING_MODELS.map(m => (
                                    <option key={m} value={m}>{m}</option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                Atenção: trocar o modelo de embeddings invalida os embeddings existentes (precisam ser regerados).
                            </p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={form.processing}>
                                Salvar
                            </Button>

                            <Transition
                                show={form.recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Salvo</p>
                            </Transition>
                        </div>
                    </form>

                    {/* Remover chave */}
                    {hasApiKey && (
                        <div className="rounded-lg border border-destructive/30 p-4">
                            <h3 className="text-sm font-medium text-destructive">Remover chave de API</h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Remove permanentemente sua chave e desativa os recursos de IA.
                            </p>
                            <form onSubmit={removeKey} className="mt-3">
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    size="sm"
                                    disabled={removeForm.processing}
                                >
                                    Remover chave
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
