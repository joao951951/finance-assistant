# Project: Finance Assistant

## Goal

Personal finance assistant where users import bank statements (CSV/PDF) and receive insights about spending patterns and savings suggestions via AI integration.

Portfolio application — focus on clean code, well-defined architecture, and complete features.

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 + PHP 8.3+ |
| Frontend | React 19 + Inertia.js |
| UI | shadcn/ui + Tailwind CSS v4 + Radix UI |
| Auth | Laravel Fortify (login, register, 2FA, reset, email verification) |
| Database | PostgreSQL 14+ + pgvector |
| AI | OpenAI GPT-4o (chat, categorization) |
| Embeddings | text-embedding-3-small 1536d (pgvector) |
| Queue | Laravel Queue (database driver) |
| Types | TypeScript 5.7 strict |
| Typed routes | Laravel Wayfinder (generates TS types from PHP routes) |
| Build | Vite 7 + React Compiler |
| Charts | Recharts |
| Parsers | league/csv + smalot/pdfparser |
| Deploy | OCI (Oracle Cloud) + Nginx + Supervisor |

## Environment Variables

```env
APP_NAME="Assistente Financeiro"

DB_CONNECTION=pgsql   # required for pgvector

# Global fallback key (each user has their own)
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_CHAT_MODEL=gpt-4o
```

## Development Commands

```bash
# Start server + queue + vite (concurrent)
composer dev

# Tests (PHPUnit — Feature + Unit)
composer test

# Lint PHP (Pint — PSR-12)
composer lint

# Lint + format frontend
npm run lint
npm run format

# TypeScript strict check
npm run types:check

# Full CI
composer ci:check
```

## Supported Banks (Import)

CSV: Nubank, Inter, C6, Caixa, generic
PDF: Nubank, Inter, Bradesco, C6, Itau, Santander, generic (credit card state machine)
