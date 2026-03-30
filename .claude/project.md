# Projeto: Assistente Financeiro

## Objetivo

Assistente financeiro pessoal onde usuarios importam extratos bancarios (CSV/PDF) e recebem insights sobre padroes de gasto e sugestoes de economia via integracao com IA.

Aplicacao de portfolio — foco em codigo limpo, arquitetura bem definida e funcionalidades completas.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13 + PHP 8.3+ |
| Frontend | React 19 + Inertia.js |
| UI | shadcn/ui + Tailwind CSS v4 + Radix UI |
| Auth | Laravel Fortify (login, registro, 2FA, reset, verificacao email) |
| Banco | PostgreSQL 14+ + pgvector |
| IA | OpenAI GPT-4o (chat, categorizacao) |
| Embeddings | text-embedding-3-small 1536d (pgvector) |
| Queue | Laravel Queue (database driver) |
| Tipos | TypeScript 5.7 strict |
| Rotas tipadas | Laravel Wayfinder (gera tipos TS das rotas PHP) |
| Build | Vite 7 + React Compiler |
| Graficos | Recharts |
| Parsers | league/csv + smalot/pdfparser |
| Deploy | OCI (Oracle Cloud) + Nginx + Supervisor |

## Variaveis de Ambiente

```env
APP_NAME="Assistente Financeiro"

DB_CONNECTION=pgsql   # obrigatorio para pgvector

# Chave global de fallback (cada usuario tem a sua propria)
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_CHAT_MODEL=gpt-4o
```

## Comandos de Desenvolvimento

```bash
# Inicia servidor + fila + vite (concorrente)
composer dev

# Testes (PHPUnit — Feature + Unit)
composer test

# Lint PHP (Pint — PSR-12)
composer lint

# Lint + format frontend
npm run lint
npm run format

# Type check TypeScript strict
npm run types:check

# CI completo
composer ci:check
```

## Bancos Suportados (Import)

CSV: Nubank, Inter, C6, Caixa, generico
PDF: Nubank, Inter, Bradesco, C6, Itau, Santander, generico (credit card state machine)
