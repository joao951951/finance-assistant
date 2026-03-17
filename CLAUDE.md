# Finance Assistant — CLAUDE.md

## Project Overview

Personal finance assistant where users import bank statements (CSV) and get AI-powered insights on spending patterns and savings suggestions.

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

1. **Statement Import** — Upload CSV bank statements
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
- Jobs for all async processing (AI calls, CSV parsing)
- Services for external integrations (OpenAI, CSV parser)

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
- [x] **Phase 3:** AI categorization pipeline (queued job)
  - `OpenAiService`: batch categorization via `gpt-4o` with `response_format: json_object`
  - `CategorizationService`: keyword match first (free/fast) → AI fallback for remainder in chunks of 50
  - `CategorizeTransactions` job dispatched automatically after `ProcessRawImport` completes
  - `CategorySeeder` with 9 default PT-BR categories; call `CategorySeeder::seedForUser($user)` on registration
  - Config em `config/services.php` → `services.openai.*`
- [x] **Phase 4:** Dashboard with charts
  - `DashboardController` (invokable): summary cards, spending by category, monthly trend (6 months), recent transactions
  - All queries in PostgreSQL (TO_CHAR, COALESCE, SUM CASE WHEN)
  - Charts: Recharts `PieChart` (donut) + `BarChart` — use `formatter={(v) => formatBRL(Number(v))}` no Tooltip (ValueType issue)
  - Page: `resources/js/pages/dashboard.tsx`
- [x] **Phase 5:** pgvector embeddings + RAG setup
  - `OpenAiService::embeddings(string[])` — batch embeddings via `text-embedding-3-small`
  - `EmbeddingService` — `generateForImport()` chunks transactions, calls API, stores via `DB::statement('UPDATE ... SET embedding = ?::vector')`
  - `GenerateEmbeddings` job — dispatched by `CategorizeTransactions` on completion (pipeline: Parse → Categorize → Embed)
  - `RagService::search()` — cosine distance via pgvector `<=>` operator; `contextFor()` returns formatted string for prompts
  - Embeddings stored/queried always via raw SQL — Eloquent não suporta o tipo `vector`
- [x] **Phase 6:** AI chat interface
  - `ChatService::reply()` — persiste mensagens, busca RAG, chama GPT-4o, auto-gera título na 1ª mensagem
  - `ConversationController` (index/show/store/destroy) + `MessageController` (store)
  - `ConversationPolicy` — protege acesso por `user_id`
  - Resposta síncrona (POST espera o GPT) — simples e funcional para portfólio
  - Page: `resources/js/pages/chat/index.tsx` — split pane (sidebar conversas + área de chat)
  - Sugestões de perguntas na tela vazia; Enter envia mensagem; loading bubble durante espera
- [x] **Phase 7:** CSV import support
  - `CsvParserService` — usa `smalot/pdfparser` para extrair texto; regex por banco (Nubank, Inter, Bradesco, C6, genérico)
  - Detecta banco pelo texto do PDF (palavras-chave no cabeçalho)
  - `ProcessRawImport` roteado: `type === 'pdf'` → `PdfParserService`, caso contrário → `CsvParserService`
  - `ImportController` aceita `mimes:csv,txt,pdf` (até 20 MB)
  - Frontend atualizado: `accept=".csv,.txt,.pdf"`

## Per-User OpenAI API Key

Each user configures their own OpenAI credentials in **Settings → API / IA** (`/settings/api`):

| Column | Type | Description |
|---|---|---|
| `openai_api_key` | `text` encrypted | Armazenada com cast `encrypted` do Laravel; nunca exposta no JSON |
| `openai_chat_model` | `string(100)` nullable | Override do modelo GPT (padrão: `gpt-4o`) |
| `openai_embedding_model` | `string(100)` nullable | Override do modelo de embedding (padrão: `text-embedding-3-small`) |

### Como flui a chave pelo código

- `OpenAiService::forUser(User $user)` e `forUserId(int $id)` — factories estáticas que criam instância com a chave do usuário (fallback para config global)
- **Jobs** (`CategorizeTransactions`, `GenerateEmbeddings`) — constroem `OpenAiService::forUserId($userId)` + serviços dependentes manualmente (DI não conhece o usuário em tempo de fila)
- **Controllers** (`ConversationController`, `MessageController`) — constroem `ChatService` manualmente via helper `chatService(Request)` usando `OpenAiService::forUser($request->user())`
- `User::hasOpenAiKey(): bool` — helper para verificar se a chave está configurada
- `openai_api_key` está em `#[Hidden]` — nunca aparece em props Inertia; a página recebe apenas `hasApiKey: bool`

### Rotas de configuração
```
GET    /settings/api  → ApiController::edit    (name: api.edit)
PATCH  /settings/api  → ApiController::update  (name: api.update)
DELETE /settings/api  → ApiController::destroy (name: api.destroy)
```

## Notes

- Project uses Laravel Wayfinder — always prefer Wayfinder-generated types over manual route strings
- shadcn/ui components are already initialized (`components.json` present)
- Wayfinder generates **actions** per controller (not routes) — import from `@/actions/App/Http/Controllers/XController`; use `.url()` when Inertia expects a string
- Tailwind CSS v4 (not v3) — use CSS variables config, not `tailwind.config.js`
- Auth is fully configured via Fortify — do not rebuild auth
- `OpenAI` class (openai-php/client) está no namespace raiz — `use OpenAI;` é correto; aviso "unknown class" no IDE é falso positivo
