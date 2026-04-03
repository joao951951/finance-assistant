# Implemented Features

## Phase 1 — Database Schema

- pgvector extension enabled
- Migrations: `categories`, `raw_imports`, `transactions`, `conversations`, `messages`
- `embedding vector(1536)` added via `DB::statement`
- HNSW index on `transactions.embedding` via `DB::statement`
- Migration for `openai_api_key` (encrypted), `openai_chat_model`, `openai_embedding_model` on users
- Migration for nullable `raw_import_id` (manual transactions)
- Migration for 2FA columns on users

## Phase 2 — Models + CSV Import

- Models: `Category`, `RawImport`, `Transaction`, `Conversation`, `Message` — all with `#[Fillable]` attributes
- `User` model: `#[Hidden]` for sensitive fields, `encrypted` cast on API key, `TwoFactorAuthenticatable`
- `RawImport` with status helpers (`markProcessing`, `markDone`, `markFailed`) and cascade delete of transactions
- `CsvParserService` — auto-detects bank (Nubank/Inter/C6/Caixa/generic), supports BR/EN number formats and multiple delimiters
- Job `ProcessRawImport` — queued, 3 retries, 120s timeout, updates `status` on `RawImport`
- `ImportController` + `RawImportPolicy`
- Multi-file upload (CSV/TXT/PDF, max 20MB per file)
- Page: `resources/js/pages/imports/index.tsx`

## Phase 3 — AI-driven Categorization

- `OpenAiService` — batch categorization via GPT-4o with `response_format: json_object`, temperature 0
- `CategorizationService` — keyword-match first → fallback to AI in chunks of 50; creates new categories automatically
- Job `CategorizeTransactions` — dispatched at end of `ProcessRawImport` (no retry, 300s timeout)
- `CategorySeeder` — 9 PT-BR categories with keywords, colors, and icons; called on registration via `CategorySeeder::seedForUser($user)`
- Config in `config/services.php` → `services.openai.*`

## Phase 4 — Dashboard with Charts

- `DashboardController` (invokable) delegates to `DashboardService`
- `DashboardService` — summary (total spent/income/balance/count), spending by category, daily trend (generate_series), month transactions, recent transactions, month selector (24 months)
- Optimized PostgreSQL queries (`TO_CHAR`, `generate_series`, `COALESCE`, `SUM CASE WHEN`)
- Charts: Recharts `PieChart` (donut) + `BarChart` (daily income vs expenses trend)
- Reusable components: `SummaryCard`, `TransactionList`, `EmptyState`
- Page: `resources/js/pages/dashboard.tsx`

## Phase 5 — Embeddings + RAG

- `OpenAiService::embeddings(string[])` — batch via `text-embedding-3-small`
- `EmbeddingService::generateForImport()` — chunked (100/call), stores via `DB::statement('UPDATE ... SET embedding = ?::vector')`
- Job `GenerateEmbeddings` — dispatched at end of `CategorizeTransactions` (3 tries, 600s timeout)
- `RagService::search()` — cosine distance (`<=>`); `contextFor()` formats context for prompts

## Phase 6 — AI Chat

- `ChatService::reply()` — persists messages, RAG search, calls GPT-4o (temp 0.7), auto-generates title on 1st message (temp 0)
- `ChatService::forUser()` — static factory that builds the full dependency chain
- History limited to 10 messages per request
- Financial system prompt in PT-BR (R$ X.XXX,XX format, doesn't invent data)
- Controllers: `ConversationController` (index/show/store/destroy) + `MessageController` (store)
- `ConversationPolicy` — access protected by `user_id`
- Synchronous chat (POST waits for GPT response)
- Reusable component: `MessageContent` (markdown-lite renderer)
- Page: `resources/js/pages/chat/index.tsx` — split pane (sidebar + chat area), question suggestions, Enter to send, loading bubble

## Phase 7 — PDF Import

- `PdfParserService` — uses `smalot/pdfparser`; multi-layer strategy:
  - Regex per bank (Nubank, Inter, Bradesco, C6)
  - Generic state machine for credit card statements (Bradesco/Nubank/Inter/Itau/Santander/C6)
  - Generic fallback for bank account statements
- Detects bank by PDF text (keywords in header)
- `ProcessRawImport` routes: `type === 'pdf'` → `PdfParserService`, else → `CsvParserService`
- `ImportController` accepts `mimes:csv,txt,pdf` (up to 20 MB)

## Phase 8 — Per-user OpenAI Key

- Columns on `users`: `openai_api_key` (encrypted cast), `openai_chat_model`, `openai_embedding_model`
- `OpenAiService::forUser()` / `forUserId()` — factories with fallback to global config
- Settings page: `settings/api.tsx` (`GET/PATCH/DELETE /settings/api`)
- `User::hasOpenAiKey(): bool`
- Key never exposed in props — page receives only `hasApiKey: bool`

## UI / UX

- Landing page (`/`) — minimalist design, feature cards, tech stack pills
- UI entirely in PT-BR (auth pages, settings, components)
- Laravel error messages in PT-BR (`lang/en/*.php`)
- Logo: modern wallet SVG icon (`app-logo-icon.tsx`)
- 2FA with TOTP (`settings/security.tsx`) — QR code + manual key + recovery codes
- Light/dark/system theme (`use-appearance.tsx` + cookie)
- Collapsible sidebar with persisted state
- Navigation breadcrumbs
- Password input with show/hide toggle
- Manual transactions with form + user category validation
- Account deletion with password confirmation

## Security

- Fortify authentication with rate limiting (login 5/min, 2FA 5/min)
- Strict password rules (min 12, mixed case, numbers, symbols, uncompromised in production)
- Session cookie: HttpOnly, Secure, SameSite=Lax
- API keys encrypted in database (Laravel encrypted cast)
- Ownership checks in all controllers (abort_unless + Policies)
- CSRF via Inertia (automatic)
- Upload restricted: mimes:csv,txt,pdf, max 20MB
- Dotfiles blocked in nginx (`location ~ /\.(?!well-known).*`)

## Tests

- Feature tests: Auth (login, register, 2FA, password reset, email verification), Dashboard, Transactions, Imports, Conversations, ProcessRawImport job
- Unit tests: CsvParserService (bank detection, parsing), PdfParserService (extraction, regex)
- PHPUnit with dedicated PostgreSQL (`finance_assistant_test`)

## Modularity Refactoring (completed)

- **Backend:** Extracted `DashboardService` (6 query methods from controller), `TransactionService` (paginated listing + categories), `ChatService::forUser()` factory (eliminated duplicate construction in 2 controllers)
- **Frontend types:** Centralized all domain interfaces in `types/models.ts` (Transaction, Category, Conversation, Message, RawImport, Summary, etc.)
- **Frontend formatters:** Extracted `lib/formatters.ts` (formatBRL, formatBRLCompact, formatDateBR) — eliminated duplication across pages
- **Frontend components:** Extracted 6 domain components from page files (SummaryCard, TransactionList, EmptyState, NewTransactionDialog, ImportCard, MessageContent)
- **Frontend hooks:** Extracted generic `useInfiniteScroll` hook with item accumulation via onSuccess callback
- All static checks pass: `composer lint`, `npm run lint`, `npm run types:check`
