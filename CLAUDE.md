# Finance Assistant — CLAUDE.md

## Project Overview

Personal finance assistant where users import bank statements (CSV/PDF) and get AI-powered insights on spending patterns and savings suggestions.

**Stack:** Laravel 13 + React 19 (Inertia.js) + OpenAI GPT-4o + pgvector (RAG) + Tailwind CSS v4 + shadcn/ui

## Architecture

### Backend (Laravel 13)
- **Framework:** Laravel 13 with PHP 8.3+
- **Auth:** Laravel Fortify (already configured)
- **Queue:** Laravel Queue for async AI processing
- **ORM:** Eloquent

### Frontend (React 19 + Inertia.js)
- **Routing:** Inertia.js (no separate API — server-side renders props)
- **UI:** shadcn/ui (Radix UI primitives) + Tailwind CSS v4
- **Type safety:** TypeScript strict mode
- **Wayfinder:** Laravel Wayfinder for type-safe route/action references

### AI / RAG
- **LLM:** OpenAI GPT-4o via API
- **Embeddings:** text-embedding-3-small stored in pgvector
- **RAG pattern:** transactions embedded → semantic search → context passed to GPT-4o

## Key Features (to be implemented)

1. **Statement Import** — Upload CSV or PDF bank statements
2. **Transaction Parsing** — Extract and normalize transactions
3. **Categorization** — AI auto-categorizes transactions
4. **Dashboard** — Charts and summaries of spending
5. **AI Chat** — RAG-powered chat over the user's financial data
6. **Insights** — Periodic AI-generated savings suggestions

## Directory Structure

```
app/
  Models/           # Eloquent models
  Http/
    Controllers/    # Inertia controllers
  Services/         # Business logic (AI, parsing, etc.)
  Jobs/             # Async queue jobs
  Actions/          # Single-purpose action classes (existing pattern)

resources/js/
  pages/            # Inertia page components
  components/       # Shared React components
  layouts/          # Layout wrappers
  hooks/            # Custom React hooks
  types/            # TypeScript types

database/
  migrations/       # DB schema
  seeders/          # Dev seed data
```

## Development Commands

```bash
# Start all services (server + queue + vite)
composer dev

# Run tests
composer test

# PHP lint (Pint)
composer lint

# Frontend lint + format
npm run lint
npm run format

# Type check
npm run types:check
```

## Code Conventions

### PHP
- Follow PSR-12 via Laravel Pint (auto-formatted)
- Use Action classes for business operations (see `app/Actions/` and `app/actions/` — both exist)
- Jobs for all async processing (AI calls, PDF parsing)
- Services for external integrations (OpenAI, PDF parser)

### TypeScript / React
- Strict TypeScript — no `any`
- Functional components only
- Use Inertia `useForm` for forms
- Wayfinder for type-safe routes: `import { route } from '@/routes'`
- shadcn/ui components in `resources/js/components/ui/`

### Database
- Always use migrations — no manual DB changes
- Use pgvector for embeddings (vector column type)
- Soft deletes on user-owned data
- `vector` column type requires `DB::statement` — Blueprint has no native pgvector support

## Environment Variables (to add to .env)

```
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_CHAT_MODEL=gpt-4o

DB_CONNECTION=pgsql  # Required for pgvector
```

## Implementation Phases

- [x] **Phase 1:** Database schema (transactions, statements, categories)
  - Migrations: pgvector extension, categories, raw_imports, transactions, conversations, messages
  - `embedding vector(1536)` added via `DB::statement` (no native Blueprint support)
  - HNSW index on `transactions.embedding` via `DB::statement`
- [x] **Phase 2:** Eloquent models + CSV import + transaction parser
  - Models: Category, RawImport, Transaction, Conversation, Message (PHP8 attribute style)
  - `CsvParserService`: auto-detects bank (Nubank/Inter/C6/generic), handles BR/EN number formats, multiple delimiters
  - `ProcessRawImport` job: queued, 3 retries, calls `markProcessing/markDone/markFailed` on RawImport
  - `ImportController` + routes + `RawImportPolicy`
  - Wayfinder actions at `@/actions/App/Http/Controllers/ImportController` — use `.url()` when passing to Inertia's `post()`
  - Page: `resources/js/pages/imports/index.tsx`
- [ ] **Phase 3:** AI categorization pipeline (queued job)
- [ ] **Phase 4:** Dashboard with charts
- [ ] **Phase 5:** pgvector embeddings + RAG setup
- [ ] **Phase 6:** AI chat interface
- [ ] **Phase 7:** PDF import support

## Notes

- Project uses Laravel Wayfinder — always prefer Wayfinder-generated types over manual route strings
- shadcn/ui components are already initialized (`components.json` present)
- Wayfinder generates **actions** per controller (not routes) — import from `@/actions/App/Http/Controllers/XController`; use `.url()` when Inertia expects a string
- Tailwind CSS v4 (not v3) — use CSS variables config, not `tailwind.config.js`
- Auth is fully configured via Fortify — do not rebuild auth
