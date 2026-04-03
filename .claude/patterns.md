# Patterns and Conventions

## PHP / Laravel

- **PSR-12** via Laravel Pint (run `composer lint` before committing)
- **PHP 8 attributes** on models: `#[Fillable]`, `#[Hidden]` — no `$fillable`/`$guarded` properties
- **Thin controllers** — validate, authorize, delegate to Services/Jobs
- **Services** for business logic and external integrations (OpenAI, parsers, dashboard queries, transaction queries)
- **Jobs** for async processing (parsing, categorization, embeddings)
- **Policies** for resource authorization (`ConversationPolicy`, `RawImportPolicy`)
- **Actions** (Fortify) for registration and password reset
- **Concerns (Traits)** for shared validation rules (`PasswordValidationRules`, `ProfileValidationRules`)
- Manual ownership check in controllers without Policy: `abort_unless($model->user_id === $request->user()->id, 403)`

## TypeScript / React

- TypeScript strict — no `any`
- Functional components only
- Use `Form` from Inertia for forms (not manual `useForm`)
- **Wayfinder** for routes and actions — import from `@/actions/App/Http/Controllers/XController`
  - Use `.url()` when Inertia expects a string: `ImportController.store.url()`
  - Never write routes manually like `/imports` or `/api/...`
- shadcn/ui components in `resources/js/components/ui/` — do not edit directly
- Tailwind CSS v4 — use CSS variables, **not** `tailwind.config.js`
- Charts with Recharts (`PieChart` donut, `BarChart`)
- Path alias: `@/*` → `resources/js/*`

## Language

- All UI in **Brazilian Portuguese** (PT-BR)
- Laravel error messages in PT-BR (files in `lang/en/`)
- Code comments can be in English or Portuguese

## OpenAI / AI

- **Never** instantiate `OpenAiService` directly — use static factories:
  - `OpenAiService::forUser(User $user)` — in controllers
  - `OpenAiService::forUserId(int $id)` — in jobs (DI doesn't know the user)
- **For chat pipelines**, use `ChatService::forUser(User $user)` — builds the full dependency chain (OpenAiService → EmbeddingService → RagService → ChatService)
- API key is per-user (`users.openai_api_key`, encrypted via cast)
- Fallback to global key in `config/services.php` if user hasn't configured one
- `User::hasOpenAiKey(): bool` — check availability before calling AI
- `openai_api_key` is in `#[Hidden]` on the model — never exposed in Inertia props
- The `OpenAI` class (openai-php/client) is in the root namespace — `use OpenAI;` is correct; IDE warning is a false positive
- Categorization uses JSON mode (`response_format: json_object`)
- Chat uses `temperature: 0.7`; categorization uses `temperature: 0`

## pgvector / Embeddings

- `vector` columns always via `DB::statement` — Blueprint has no native support
- Embeddings stored/queried always via raw SQL
- Cosine distance operator: `<=>` (lower = more similar)
- HNSW index on `transactions.embedding` for performance
- Embedding model: `text-embedding-3-small` (1536 dimensions)
- Batch of 100 transactions per embedding call

## Auth

- Auth configured via **Laravel Fortify** — do not recreate or override
- 2FA with TOTP already implemented in `settings/security.tsx`
- Password rules: min 12 chars, mixed case, numbers, symbols, uncompromised (production)
- Rate limiting: login 5/min (per IP+username), 2FA 5/min, password update 6/min
- Session cookie: HttpOnly, Secure, SameSite=Lax

## SSL on Windows (development)

- cURL on Windows may fail with `SSL certificate problem: unable to get local issuer certificate`
- Solution: `OpenAiService` uses a custom Guzzle client with `verify => storage_path('cacert.pem')`
- The `storage/cacert.pem` file was downloaded from curl.se/ca/cacert.pem

## Critical Gotchas

| Problem | Solution |
|---|---|
| `vector` type in migrations | Use `DB::statement()` instead of Blueprint |
| Embeddings in Eloquent | Always raw SQL (`DB::statement` / `DB::select`) |
| Wayfinder returns object | Use `.url()` when string is needed |
| `OpenAI` class not recognized by IDE | False positive — `use OpenAI;` is correct |
| SSL cURL on Windows | `cacert.pem` in `storage/` + custom Guzzle in `OpenAiService` |
| Recharts ValueType in Tooltip | Use `formatter={(v) => formatBRL(Number(v))}` |
| Mass assignment | Use `#[Fillable([...])]` attribute, not `$fillable` property |
| Import cascade delete | `RawImport::booted()` deletes transactions on `deleting` event |
| Category keywords | JSON array, matched case-insensitively against transaction description |

## Modularity Rules

### Backend
- **Controllers must be thin** — no SQL queries, no data transformation, no joins. Delegate to Services.
- **Service layer:** `DashboardService` for dashboard queries, `TransactionService` for transaction listing/pagination, `ChatService::forUser()` for chat pipeline.
- **Factory pattern:** use `ChatService::forUser($user)` — never construct the dependency chain manually in controllers.

### Frontend
- **Domain types in `types/models.ts`** — never define `Transaction`, `Category`, `Message`, etc. inline in pages.
- **Shared formatters in `lib/formatters.ts`** — never duplicate `formatBRL()` or date formatting in pages.
- **Extract sub-components** — if a component is >30 lines and reusable, it goes in `components/`, not inline in a page.
- **Custom hooks for stateful logic** — IntersectionObserver, scroll management, form patterns go in `hooks/`.
