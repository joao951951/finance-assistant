import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bem-vindo" />

            <div className="min-h-screen bg-background text-foreground">
                {/* Nav */}
                <header className="fixed top-0 z-10 w-full border-b border-border/50 bg-background/80 backdrop-blur-sm">
                    <div className="mx-auto flex h-14 max-w-4xl items-center justify-between px-6">
                        <span className="text-sm font-semibold tracking-tight">Assistente Financeiro</span>
                        <nav className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-md bg-foreground px-4 py-1.5 text-sm font-medium text-background transition-opacity hover:opacity-80"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-md px-4 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        Entrar
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="rounded-md bg-foreground px-4 py-1.5 text-sm font-medium text-background transition-opacity hover:opacity-80"
                                        >
                                            Criar conta
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <main className="mx-auto flex min-h-screen max-w-4xl flex-col items-center justify-center px-6 pt-20 text-center sm:pt-14">
                    <p className="mb-4 text-xs font-medium uppercase tracking-widest text-muted-foreground sm:mb-5">
                        Projeto de portfólio
                    </p>
                    <h1 className="mb-4 max-w-2xl text-3xl font-bold tracking-tight sm:mb-5 sm:text-4xl lg:text-6xl">
                        Suas finanças com integração à inteligência artificial
                    </h1>
                    <p className="mb-8 max-w-md text-base text-muted-foreground sm:mb-10">
                        Importe extratos, visualize gastos e converse com um modelo de linguagem
                        que entende os seus próprios dados financeiros.
                    </p>

                    <div className="flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md bg-foreground px-6 py-2.5 text-sm font-medium text-background transition-opacity hover:opacity-80"
                            >
                                Acessar dashboard
                            </Link>
                        ) : (
                            <>
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="rounded-md bg-foreground px-6 py-2.5 text-sm font-medium text-background transition-opacity hover:opacity-80"
                                    >
                                        Começar agora
                                    </Link>
                                )}
                                <Link
                                    href={login()}
                                    className="rounded-md border px-6 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    Entrar
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Features */}
                    <div className="mt-14 grid w-full gap-px overflow-hidden rounded-xl border bg-border sm:mt-24 sm:grid-cols-3">
                        {[
                            {
                                title: 'Importação',
                                description: 'Extratos em CSV ou PDF de qualquer banco, com detecção automática de formato.',
                            },
                            {
                                title: 'Dashboard',
                                description: 'Gastos por categoria, tendências mensais e resumo do orçamento em gráficos.',
                            },
                            {
                                title: 'Chat com IA',
                                description: 'Converse com um modelo de linguagem usando seus dados como contexto via RAG.',
                            },
                        ].map(({ title, description }) => (
                            <div key={title} className="bg-background p-6 sm:p-8">
                                <h3 className="mb-2 text-sm font-semibold">{title}</h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>
                            </div>
                        ))}
                    </div>

                    {/* Stack */}
                    <div className="mt-8 flex flex-wrap justify-center gap-2">
                        {['Laravel 13', 'React 19', 'Inertia.js', 'Integração com IA', 'pgvector', 'Tailwind CSS v4'].map((tech) => (
                            <span key={tech} className="rounded-full border px-3 py-1 text-xs text-muted-foreground">
                                {tech}
                            </span>
                        ))}
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t">
                    <div className="mx-auto flex max-w-4xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-muted-foreground sm:flex-row">
                        <span>Desenvolvido por <strong className="font-medium text-foreground">João Carnellossi</strong></span>
                        <div className="flex items-center gap-5">
                            <a
                                href="https://github.com/joao951951"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="transition-colors hover:text-foreground"
                            >
                                GitHub
                            </a>
                            <a
                                href="https://carnellossi.com.br"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="transition-colors hover:text-foreground"
                            >
                                Portfólio
                            </a>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
