# Architecture

## Pattern: MVC + Services Layer

Thin controllers (validation, authorization, dispatch) delegate to Services (business logic). Async jobs for heavy tasks. Eloquent models with `#[Fillable]` attributes — no Repository pattern.

```
Request → Controller → Service / Job → Model (Eloquent) → Controller → Inertia::render()
```

## Backend (Laravel 13)

```
app/
  Models/                          # Eloquent models with #[Fillable] / #[Hidden]
    User.php                       # Auth + TwoFactorAuthenticatable + hasMany(categories, transactions, conversations, rawImports)
    Transaction.php                # user, rawImport, category; casts: date, decimal:2; embedding via raw SQL
    Category.php                   # user; casts: keywords → array; name, color, icon, keywords[]
    RawImport.php                  # user, transactions; status helpers (markProcessing/Done/Failed); cascade delete
    Conversation.php               # user, messages; auto-generated title
    Message.php                    # conversation; role (user/assistant), content

  Http/
    Controllers/
      DashboardController.php      # Invokable — delegates to DashboardService; month selector + date parsing
      TransactionController.php    # index (delegates to TransactionService), store (manual), destroy (ownership check)
      ImportController.php         # index, store (multi-file upload), destroy (policy + storage cleanup)
      ConversationController.php   # index, show, store (ChatService::forUser), destroy (ConversationPolicy)
      MessageController.php        # store → ChatService::forUser() → reply()
      Settings/
        ProfileController.php      # edit, update, destroy (account deletion)
        SecurityController.php     # edit, update (password change)
        ApiController.php          # edit, update, destroy (OpenAI key/models per user)

    Middleware/
      HandleInertiaRequests.php    # Shares: app name, auth user, sidebar state
      HandleAppearance.php         # Shares: appearance cookie (light/dark/system)

    Requests/Settings/
      ProfileUpdateRequest.php
      ProfileDeleteRequest.php
      PasswordUpdateRequest.php
      TwoFactorAuthenticationRequest.php

  Services/
    OpenAiService.php              # Wrapper openai-php/client; factories: forUser(), forUserId(); methods: chat(), categorizeTransactions(), embeddings()
    ChatService.php                # Orchestrates: persist user msg → RAG context → history (10 msgs) → GPT-4o → persist reply → auto-title; factory: forUser()
    DashboardService.php           # Summary, spending by category, trend, month transactions, recent transactions, available months
    TransactionService.php         # Paginated listing with category join, categories for user
    CategorizationService.php      # Two steps: keyword-match first → fallback AI in chunks of 50; creates categories dynamically
    EmbeddingService.php           # Batch embeddings (100/call); stores via DB::statement with ::vector cast
    RagService.php                 # Semantic search cosine distance (<=>); top-N transactions → formats context for prompt
    CsvParserService.php           # Detects bank (Nubank/Inter/C6/Caixa/generic); normalizes columns, dates, BR/EN values
    PdfParserService.php           # smalot/pdfparser; regex per bank + state machine for generic credit cards

  Jobs/
    ProcessRawImport.php           # Parse CSV/PDF → create transactions → dispatch CategorizeTransactions (3 tries, 120s timeout)
    CategorizeTransactions.php     # Batch categorize → dispatch GenerateEmbeddings (no retry, 300s timeout)
    GenerateEmbeddings.php         # Generate embeddings for import (3 tries, 600s timeout)

  Actions/Fortify/
    CreateNewUser.php              # Registration + seeds default categories
    ResetUserPassword.php          # Reset via token

  Policies/
    ConversationPolicy.php         # view, delete → user_id match
    RawImportPolicy.php            # delete → user_id match

  Concerns/
    PasswordValidationRules.php    # passwordRules() + currentPasswordRules()
    ProfileValidationRules.php     # profileRules(userId) — name + email unique

  Providers/
    AppServiceProvider.php         # CarbonImmutable, Password rules (min 12, mixed case, numbers, symbols, uncompromised)
    FortifyServiceProvider.php     # Inertia views, rate limiting (login 5/min, 2FA 5/min)
```

## Import Pipeline (Queue)

```
Upload (multi-file) → ProcessRawImport → CategorizeTransactions → GenerateEmbeddings
                       ├─ CSV → CsvParserService (detects bank, normalizes)
                       └─ PDF → PdfParserService (regex + state machine)
```

Each job dispatches the next upon completion. Errors are marked in `raw_imports.status` (pending → processing → done/failed).

## Frontend (React 19 + Inertia.js)

```
resources/js/
  pages/                           # Inertia pages (one file = one route)
    welcome.tsx                    # Public landing page (features, tech stack)
    dashboard.tsx                  # Financial summary + Recharts charts (PieChart donut + BarChart)
    transactions/index.tsx         # Transaction list + infinite scroll + create dialog
    imports/index.tsx              # Multi-file upload + history with status
    chat/index.tsx                 # Split pane (conversation sidebar + chat area), suggestions, loading bubble
    auth/
      login.tsx
      register.tsx
      forgot-password.tsx
      reset-password.tsx
      verify-email.tsx
      confirm-password.tsx
      two-factor-challenge.tsx
    settings/
      profile.tsx                  # Name, email, verification, account deletion
      security.tsx                 # Password + 2FA (TOTP setup modal)
      appearance.tsx               # Light/dark/system
      api.tsx                      # OpenAI key + models

  components/
    ui/                            # shadcn/ui (Radix) — do not edit directly
    summary-card.tsx               # Reusable card with title, value, subtitle
    transaction-list.tsx           # Compact transaction list with category badges
    empty-state.tsx                # Empty state placeholder
    new-transaction-dialog.tsx     # Dialog form for creating manual transactions
    import-card.tsx                # Import item with status badge + delete
    message-content.tsx            # Markdown-lite renderer (bold + line breaks)
    app-sidebar.tsx                # Collapsible sidebar menu
    app-logo-icon.tsx              # SVG logo (wallet icon)
    two-factor-setup-modal.tsx     # 2FA modal with QR code + recovery codes
    nav-main.tsx, nav-footer.tsx, nav-user.tsx
    breadcrumbs.tsx
    input-error.tsx, password-input.tsx
    heading.tsx, text-link.tsx, alert-error.tsx
    appearance-tabs.tsx
    delete-user.tsx

  layouts/
    app-layout.tsx                 # Authenticated layout (sidebar + header)
    auth-layout.tsx                # Auth layout (card/simple/split variants)
    settings/layout.tsx            # Tab navigation for settings

  hooks/
    use-infinite-scroll.ts         # Generic IntersectionObserver + Inertia pagination (accumulates items via onSuccess callback)
    use-appearance.tsx             # Theme (light/dark/system) with cookie
    use-two-factor-auth.ts         # 2FA state and setup
    use-clipboard.ts               # Copy to clipboard
    use-current-url.ts
    use-initials.tsx
    use-mobile.tsx
    use-mobile-navigation.ts

  lib/
    formatters.ts                  # formatBRL(), formatBRLCompact(), formatDateBR() — shared across all pages
    form.ts                        # Inertia form helpers
    utils.ts                       # General utilities

  actions/                         # Generated by Wayfinder (do not edit)
  routes/                          # Generated by Wayfinder (do not edit)
  types/                           # index.ts, auth.ts, models.ts, navigation.ts, ui.ts, global.d.ts
```

## Routes

### web.php (auth + verified)
- `GET /` — welcome (public)
- `GET /dashboard` — DashboardController (invokable)
- `GET|POST /transactions` — index, store
- `DELETE /transactions/{transaction}` — destroy
- `GET|POST /imports` — index, store
- `DELETE /imports/{rawImport}` — destroy
- `GET|POST /chat` — index (list), store (new conversation)
- `GET|DELETE /chat/{conversation}` — show, destroy
- `POST /chat/{conversation}/messages` — MessageController::store

### settings.php
- `GET|PATCH /settings/profile` — edit, update (auth)
- `DELETE /settings/profile` — destroy (auth + verified)
- `GET /settings/security` — edit (auth + verified)
- `PUT /settings/password` — update (throttle:6,1)
- `GET /settings/appearance` — Inertia page
- `GET|PATCH|DELETE /settings/api` — edit, update, destroy

## AI / RAG

- **OpenAiService** — wrapper for `openai-php/client`; factory pattern (forUser/forUserId); 3 methods: `chat()`, `categorizeTransactions()`, `embeddings()`
- **ChatService** — factory `forUser()` builds the full pipeline (OpenAiService → EmbeddingService → RagService → ChatService)
- **Embeddings** — `text-embedding-3-small`, 1536 dimensions, `transactions.embedding vector(1536)` via `DB::statement`
- **HNSW index** on `transactions.embedding` for efficient search (`<=>` cosine distance)
- **RAG** — `RagService::search()` finds N most similar transactions → `contextFor()` formats for prompt
- **Chat** — synchronous (POST waits for GPT response); history limited to 10 messages; auto-generates title on 1st message
- **Categorization** — keyword-match first (fast) → fallback GPT-4o in chunks of 50; creates new categories automatically; JSON mode

## Database

- PostgreSQL required (pgvector)
- `vector` column created via `DB::statement` (no native Blueprint support)
- Embeddings always queried via raw SQL (`<=>` operator)
- Cascade deletes: user → categories/transactions/conversations/rawImports; rawImport → transactions (via model event); conversation → messages
- No soft deletes

## Tests

```
tests/
  Feature/
    Auth/                          # Authentication, Registration, PasswordReset, 2FA, EmailVerification
    DashboardTest.php              # Guest redirect, authenticated
    DashboardControllerTest.php    # Dashboard data
    TransactionControllerTest.php  # CRUD + pagination
    ImportControllerTest.php       # Upload, type/size validation, delete with policy
    ConversationControllerTest.php # Chat CRUD
    ProcessRawImportJobTest.php    # Import pipeline
    Settings/                      # ProfileUpdate, Security
  Unit/
    CsvParserServiceTest.php       # Bank detection, columns, dates, values
    PdfParserServiceTest.php       # PDF extraction, regex, state machine
```

PHPUnit with PostgreSQL (`finance_assistant_test`), sync queue, array cache.

## Deploy

- **Infra:** Oracle Cloud Instance (Ubuntu 22.04/24.04)
- **Provisioning:** `deploy/cloud-init.sh` — installs PHP 8.4, Composer, Node.js 20, Nginx, Supervisor
- **Web:** Nginx → PHP-FPM 8.4 (fastcgi, 120s timeout)
- **Queue:** Supervisor → `php artisan queue:work` (1 process, 3 tries, 1h max)
- **DB:** PostgreSQL via Supabase pooler (pgvector enabled)
- **Deploy script:** `/usr/local/bin/deploy` generated by cloud-init
- **Domain:** assistentefinanceiro.online
