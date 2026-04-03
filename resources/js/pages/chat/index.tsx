import { Head, useForm, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { MessageSquare, Plus, Send, Trash2, Bot, User, Loader2, Menu, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import MessageController from '@/actions/App/Http/Controllers/MessageController';
import type { BreadcrumbItem, Conversation, Message } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Props {
    conversations: Conversation[];
    activeConversation: { id: number; title: string | null } | null;
    messages: Message[];
}

// ─── Breadcrumbs ─────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Chat IA', href: ConversationController.index.url() },
];

// ─── Markdown-lite renderer (bold + line breaks only) ────────────────────────

function MessageContent({ content }: { content: string }) {
    const parts = content.split(/(\*\*[^*]+\*\*)/g);
    return (
        <p className="text-sm leading-relaxed whitespace-pre-wrap">
            {parts.map((part, i) =>
                part.startsWith('**') && part.endsWith('**')
                    ? <strong key={i}>{part.slice(2, -2)}</strong>
                    : part
            )}
        </p>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function ChatIndex({ conversations, activeConversation, messages }: Props) {
    const bottomRef = useRef<HTMLDivElement>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Form for the first message (creates a new conversation)
    const newForm = useForm({ message: '' });

    // Form for replies inside an active conversation
    const replyForm = useForm({ message: '' });

    // Scroll to bottom whenever messages change
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    function handleNewConversation(e: React.FormEvent) {
        e.preventDefault();
        setIsSubmitting(true);
        newForm.post(ConversationController.store.url(), {
            onFinish: () => setIsSubmitting(false),
        });
    }

    function handleReply(e: React.FormEvent) {
        e.preventDefault();
        if (!activeConversation) return;
        setIsSubmitting(true);
        replyForm.post(MessageController.store.url({ conversation: activeConversation.id }), {
            onSuccess: () => replyForm.reset(),
            onFinish: () => setIsSubmitting(false),
        });
    }

    function handleDelete(conversation: Conversation) {
        router.delete(ConversationController.destroy.url({ conversation: conversation.id }), {
            preserveScroll: true,
        });
    }

    function navigateToConversation(id: number) {
        setSidebarOpen(false);
        router.visit(ConversationController.show.url({ conversation: id }));
    }

    const conversationTitle = activeConversation?.title ?? 'Nova conversa';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chat IA" />

            <div className="flex h-[calc(100vh-4rem)] overflow-hidden">

                {/* ── Mobile sidebar backdrop ── */}
                {sidebarOpen && (
                    <div
                        className="fixed inset-0 z-10 bg-black/40 md:hidden"
                        onClick={() => setSidebarOpen(false)}
                    />
                )}

                {/* ── Sidebar ── */}
                <aside className={`
                    fixed inset-y-0 left-0 z-20 flex w-72 flex-col border-r bg-sidebar transition-transform duration-200
                    md:static md:w-64 md:translate-x-0
                    ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
                `}>
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <span className="text-sm font-semibold">Conversas</span>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={() => { setSidebarOpen(false); router.visit(ConversationController.index.url()); }}
                                title="Nova conversa"
                            >
                                <Plus className="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 md:hidden"
                                onClick={() => setSidebarOpen(false)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    </div>

                    <nav className="flex-1 overflow-y-auto py-2">
                        {conversations.length === 0 ? (
                            <p className="px-4 py-6 text-center text-xs text-muted-foreground">
                                Nenhuma conversa ainda
                            </p>
                        ) : (
                            conversations.map((c) => (
                                <div
                                    key={c.id}
                                    className={`group flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-accent ${
                                        activeConversation?.id === c.id ? 'bg-accent font-medium' : ''
                                    }`}
                                >
                                    <button
                                        className="flex flex-1 items-center gap-2 overflow-hidden text-left"
                                        onClick={() => navigateToConversation(c.id)}
                                    >
                                        <MessageSquare className="size-3.5 flex-shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {c.title ?? 'Sem título'}
                                        </span>
                                    </button>
                                    <button
                                        onClick={() => handleDelete(c)}
                                        className="hidden text-muted-foreground hover:text-destructive group-hover:flex md:group-hover:flex"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                            ))
                        )}
                    </nav>
                </aside>

                {/* ── Chat area ── */}
                <div className="flex flex-1 flex-col overflow-hidden">

                    {/* Header */}
                    <div className="flex items-center gap-3 border-b px-4 py-3 md:px-6">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 flex-shrink-0 md:hidden"
                            onClick={() => setSidebarOpen(true)}
                        >
                            <Menu className="size-4" />
                        </Button>
                        <div className="min-w-0">
                            <h1 className="truncate text-sm font-semibold">{conversationTitle}</h1>
                            <p className="truncate text-xs text-muted-foreground">
                                Pergunte sobre seus gastos, padrões de consumo ou peça sugestões de economia
                            </p>
                        </div>
                    </div>

                    {/* Messages */}
                    <div className="flex-1 overflow-y-auto px-4 py-4 md:px-6">
                        {!activeConversation ? (
                            /* Empty state — new conversation */
                            <div className="flex h-full flex-col items-center justify-center gap-6">
                                <div className="flex flex-col items-center gap-2 text-center">
                                    <Bot className="size-12 text-muted-foreground/50" />
                                    <h2 className="text-lg font-semibold">Assistente Financeiro</h2>
                                    <p className="max-w-sm text-sm text-muted-foreground">
                                        Faça uma pergunta sobre seus gastos e eu vou analisar suas transações para responder.
                                    </p>
                                </div>

                                {/* Suggestions */}
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {[
                                        'Quanto gastei com alimentação este mês?',
                                        'Quais são meus maiores gastos?',
                                        'Como posso economizar mais?',
                                        'Mostre meus gastos por categoria',
                                    ].map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            onClick={() => newForm.setData('message', suggestion)}
                                            className="rounded-lg border px-4 py-2 text-left text-sm transition hover:bg-accent"
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>

                                {/* New conversation form */}
                                <form onSubmit={handleNewConversation} className="flex w-full max-w-xl gap-2 px-1">
                                    <input
                                        value={newForm.data.message}
                                        onChange={(e) => newForm.setData('message', e.target.value)}
                                        placeholder="Faça uma pergunta..."
                                        className="flex-1 rounded-lg border bg-background px-4 py-2 text-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring"
                                        disabled={isSubmitting}
                                    />
                                    <Button type="submit" disabled={!newForm.data.message || isSubmitting}>
                                        {isSubmitting
                                            ? <Loader2 className="size-4 animate-spin" />
                                            : <Send className="size-4" />
                                        }
                                    </Button>
                                </form>
                            </div>
                        ) : (
                            /* Active conversation */
                            <div className="flex flex-col gap-4">
                                {messages.map((msg) => (
                                    <div
                                        key={msg.id}
                                        className={`flex gap-3 ${msg.role === 'user' ? 'flex-row-reverse' : ''}`}
                                    >
                                        {/* Avatar */}
                                        <div className={`flex size-8 flex-shrink-0 items-center justify-center rounded-full text-white ${
                                            msg.role === 'user' ? 'bg-primary' : 'bg-violet-600'
                                        }`}>
                                            {msg.role === 'user'
                                                ? <User className="size-4" />
                                                : <Bot className="size-4" />
                                            }
                                        </div>

                                        {/* Bubble */}
                                        <Card className={`max-w-[85%] px-3 py-2.5 md:max-w-[75%] md:px-4 md:py-3 ${
                                            msg.role === 'user'
                                                ? 'rounded-tr-none bg-primary text-primary-foreground'
                                                : 'rounded-tl-none'
                                        }`}>
                                            <MessageContent content={msg.content} />
                                        </Card>
                                    </div>
                                ))}

                                {/* Loading bubble while waiting for AI */}
                                {isSubmitting && (
                                    <div className="flex gap-3">
                                        <div className="flex size-8 flex-shrink-0 items-center justify-center rounded-full bg-violet-600 text-white">
                                            <Bot className="size-4" />
                                        </div>
                                        <Card className="rounded-tl-none px-4 py-3">
                                            <Loader2 className="size-4 animate-spin text-muted-foreground" />
                                        </Card>
                                    </div>
                                )}

                                <div ref={bottomRef} />
                            </div>
                        )}
                    </div>

                    {/* Input — only shown in active conversation */}
                    {activeConversation && (
                        <div className="border-t px-4 py-3 md:px-6 md:py-4">
                            <form onSubmit={handleReply} className="flex gap-2">
                                <input
                                    value={replyForm.data.message}
                                    onChange={(e) => replyForm.setData('message', e.target.value)}
                                    placeholder="Faça uma pergunta sobre seus gastos..."
                                    className="flex-1 rounded-lg border bg-background px-4 py-2 text-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring"
                                    disabled={isSubmitting}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            handleReply(e as unknown as React.FormEvent);
                                        }
                                    }}
                                />
                                <Button type="submit" disabled={!replyForm.data.message || isSubmitting}>
                                    {isSubmitting
                                        ? <Loader2 className="size-4 animate-spin" />
                                        : <Send className="size-4" />
                                    }
                                </Button>
                            </form>
                        </div>
                    )}

                </div>
            </div>
        </AppLayout>
    );
}
