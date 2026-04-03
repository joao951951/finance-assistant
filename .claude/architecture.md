# Arquitetura

## Padrao: MVC + Services Layer

Controllers finos (validacao, autorizacao, despacho) delegam para Services (logica de negocio). Jobs assincrono para tarefas pesadas. Models Eloquent com `#[Fillable]` attributes — sem Repository pattern.

```
Request → Controller → Service / Job → Model (Eloquent) → Controller → Inertia::render()
```

### Technical debt: modularity violations

> Full details and refactoring plan in [`.claude/refactoring.md`](refactoring.md)

**Backend:**
- `DashboardController` (191 lines) — contains 6 methods with inline SQL queries; should delegate to `DashboardService`
- `TransactionController::index()` — join query + pagination inline; should delegate to `TransactionService`
- `ConversationController` and `MessageController` — duplicate manual `ChatService` construction (missing factory)

**Frontend:**
- Monolithic pages (dashboard 407 lines, transactions 328, chat 326) — sub-components, hooks and types inline
- Domain types (`Transaction`, `Category`, `Conversation`, etc.) duplicated in each page instead of centralized in `types/`
- `formatBRL()` duplicated in dashboard.tsx and transactions/index.tsx
- Missing domain hooks (`useInfiniteScroll`, `useChatMessaging`)

## Backend (Laravel 13)

```
app/
  Models/                          # Eloquent models com #[Fillable] / #[Hidden]
    User.php                       # Auth + TwoFactorAuthenticatable + hasMany(categories, transactions, conversations, rawImports)
    Transaction.php                # user, rawImport, category; casts: date, decimal:2; embedding via SQL raw
    Category.php                   # user; casts: keywords → array; name, color, icon, keywords[]
    RawImport.php                  # user, transactions; status helpers (markProcessing/Done/Failed); cascade delete
    Conversation.php               # user, messages; titulo auto-gerado
    Message.php                    # conversation; role (user/assistant), content

  Http/
    Controllers/
      DashboardController.php      # Invokable — summary, spendingByCategory, trend (daily), recentTransactions, monthSelector
      TransactionController.php    # index (paginado 25/page), store (manual), destroy (ownership check)
      ImportController.php         # index, store (multi-file upload), destroy (policy + storage cleanup)
      ConversationController.php   # index, show, store, destroy (ConversationPolicy)
      MessageController.php        # store → ChatService::reply()
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
    OpenAiService.php              # Wrapper openai-php/client; factories: forUser(), forUserId(); metodos: chat(), categorizeTransactions(), embeddings()
    ChatService.php                # Orquestra: persist user msg → RAG context → history (10 msgs) → GPT-4o → persist reply → auto-title
    CategorizationService.php      # Dois passos: keyword-match primeiro → fallback IA em chunks de 50; cria categorias dinamicamente
    EmbeddingService.php           # Batch embeddings (100/call); armazena via DB::statement com ::vector cast
    RagService.php                 # Busca semantica cosine distance (<=>); top-N transacoes → formata contexto para prompt
    CsvParserService.php           # Detecta banco (Nubank/Inter/C6/Caixa/generico); normaliza colunas, datas, valores BR/EN
    PdfParserService.php           # smalot/pdfparser; regex por banco + state machine para credit cards genericos
    # ⚠ MISSING (see refactoring.md):
    # DashboardService.php         # Summary, spending, trend, transaction queries — currently inline in controller
    # TransactionService.php       # Paginated listing, date range, recent — currently inline in controllers

  Jobs/
    ProcessRawImport.php           # Parse CSV/PDF → cria transactions → dispatch CategorizeTransactions (3 tries, 120s timeout)
    CategorizeTransactions.php     # Batch categorize → dispatch GenerateEmbeddings (no retry, 300s timeout)
    GenerateEmbeddings.php         # Gera embeddings para import (3 tries, 600s timeout)

  Actions/Fortify/
    CreateNewUser.php              # Registro + seeds categorias default
    ResetUserPassword.php          # Reset via token

  Policies/
    ConversationPolicy.php         # view, delete → user_id match
    RawImportPolicy.php            # delete → user_id match

  Concerns/
    PasswordValidationRules.php    # passwordRules() + currentPasswordRules()
    ProfileValidationRules.php     # profileRules(userId) — name + email unique

  Providers/
    AppServiceProvider.php         # CarbonImmutable, Password rules (min 12, mixed case, numbers, symbols, uncompromised)
    FortifyServiceProvider.php     # Views Inertia, rate limiting (login 5/min, 2FA 5/min)
```

## Pipeline de importacao (fila)

```
Upload (multi-file) → ProcessRawImport → CategorizeTransactions → GenerateEmbeddings
                       ├─ CSV → CsvParserService (detecta banco, normaliza)
                       └─ PDF → PdfParserService (regex + state machine)
```

Cada job dispara o proximo ao finalizar. Erros sao marcados em `raw_imports.status` (pending → processing → done/failed).

## Frontend (React 19 + Inertia.js)

```
resources/js/
  pages/                           # Paginas Inertia (um arquivo = uma rota)
    welcome.tsx                    # Pagina inicial publica (features, tech stack)
    dashboard.tsx                  # Resumo financeiro + graficos Recharts (PieChart donut + BarChart)
    transactions/index.tsx         # CRUD transacoes + paginacao + filtro categoria
    imports/index.tsx              # Upload multi-arquivo + historico com status
    chat/index.tsx                 # Split pane (sidebar conversas + area de chat), sugestoes, loading bubble
    auth/
      login.tsx
      register.tsx
      forgot-password.tsx
      reset-password.tsx
      verify-email.tsx
      confirm-password.tsx
      two-factor-challenge.tsx
    settings/
      profile.tsx                  # Nome, email, verificacao, exclusao de conta
      security.tsx                 # Senha + 2FA (TOTP setup modal)
      appearance.tsx               # Light/dark/system
      api.tsx                      # Chave OpenAI + modelos

  components/
    ui/                            # shadcn/ui (Radix) — nao editar diretamente
    app-sidebar.tsx                # Menu lateral colapsavel
    app-logo-icon.tsx              # Logo SVG (wallet icon)
    two-factor-setup-modal.tsx     # Modal 2FA com QR code + recovery codes
    nav-main.tsx, nav-footer.tsx, nav-user.tsx
    breadcrumbs.tsx
    input-error.tsx, password-input.tsx
    heading.tsx, text-link.tsx, alert-error.tsx
    appearance-tabs.tsx
    delete-user.tsx

  layouts/
    app-layout.tsx                 # Layout autenticado (sidebar + header)
    auth-layout.tsx                # Layout auth (card/simple/split variants)
    settings/layout.tsx            # Tab navigation para settings

  hooks/
    use-appearance.tsx             # Tema (light/dark/system) com cookie
    use-two-factor-auth.ts         # Estado e setup do 2FA
    use-clipboard.ts               # Copy to clipboard
    use-current-url.ts
    use-initials.tsx
    use-mobile.tsx
    use-mobile-navigation.ts

  actions/                         # Gerado pelo Wayfinder (nao editar)
  routes/                          # Gerado pelo Wayfinder (nao editar)
  types/                           # index.ts, auth.ts, navigation.ts, ui.ts, global.d.ts
```

## Rotas

### web.php (auth + verified)
- `GET /` — welcome (publica)
- `GET /dashboard` — DashboardController (invokable)
- `GET|POST /transactions` — index, store
- `DELETE /transactions/{transaction}` — destroy
- `GET|POST /imports` — index, store
- `DELETE /imports/{rawImport}` — destroy
- `GET|POST /chat` — index (lista), store (nova conversa)
- `GET|DELETE /chat/{conversation}` — show, destroy
- `POST /chat/{conversation}/messages` — MessageController::store

### settings.php
- `GET|PATCH /settings/profile` — edit, update (auth)
- `DELETE /settings/profile` — destroy (auth + verified)
- `GET /settings/security` — edit (auth + verified)
- `PUT /settings/password` — update (throttle:6,1)
- `GET /settings/appearance` — Inertia page
- `GET|PATCH|DELETE /settings/api` — edit, update, destroy

## IA / RAG

- **OpenAiService** — wrapper `openai-php/client`; factory pattern (forUser/forUserId); 3 metodos: `chat()`, `categorizeTransactions()`, `embeddings()`
- **Embeddings** — `text-embedding-3-small`, 1536 dimensoes, `transactions.embedding vector(1536)` via `DB::statement`
- **HNSW index** em `transactions.embedding` para busca eficiente (`<=>` cosine distance)
- **RAG** — `RagService::search()` busca N transacoes mais similares → `contextFor()` formata para o prompt
- **Chat** — sincrono (POST aguarda resposta GPT); historico limitado a 10 mensagens; auto-gera titulo na 1a mensagem
- **Categorizacao** — keyword-match primeiro (rapido) → fallback GPT-4o em chunks de 50; cria categorias novas automaticamente; JSON mode

## Banco de Dados

- PostgreSQL obrigatorio (pgvector)
- `vector` column criada via `DB::statement` (sem suporte nativo no Blueprint)
- Embeddings consultados sempre via SQL raw (`<=>` operator)
- Cascade deletes: user → categories/transactions/conversations/rawImports; rawImport → transactions (via model event); conversation → messages
- Nao usa soft deletes

## Testes

```
tests/
  Feature/
    Auth/                          # Authentication, Registration, PasswordReset, 2FA, EmailVerification
    DashboardTest.php              # Guest redirect, autenticado
    DashboardControllerTest.php    # Dados do dashboard
    TransactionControllerTest.php  # CRUD + paginacao
    ImportControllerTest.php       # Upload, validacao tipo/tamanho, delete com policy
    ConversationControllerTest.php # Chat CRUD
    ProcessRawImportJobTest.php    # Pipeline de importacao
    Settings/                      # ProfileUpdate, Security
  Unit/
    CsvParserServiceTest.php       # Deteccao banco, colunas, datas, valores
    PdfParserServiceTest.php       # Extracao PDF, regex, state machine
```

PHPUnit com PostgreSQL (`finance_assistant_test`), sync queue, array cache.

## Deploy

- **Infra:** Oracle Cloud Instance (Ubuntu 22.04/24.04)
- **Provisioning:** `deploy/cloud-init.sh` — instala PHP 8.4, Composer, Node.js 20, Nginx, Supervisor
- **Web:** Nginx → PHP-FPM 8.4 (fastcgi, 120s timeout)
- **Queue:** Supervisor → `php artisan queue:work` (1 processo, 3 tries, 1h max)
- **DB:** PostgreSQL via Supabase pooler (pgvector habilitado)
- **Deploy script:** `/usr/local/bin/deploy` gerado pelo cloud-init
- **Dominio:** assistentefinanceiro.online
