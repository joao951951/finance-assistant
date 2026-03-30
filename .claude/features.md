# Funcionalidades Implementadas

## Fase 1 — Schema do banco

- Extensao pgvector habilitada
- Migrations: `categories`, `raw_imports`, `transactions`, `conversations`, `messages`
- `embedding vector(1536)` adicionado via `DB::statement`
- HNSW index em `transactions.embedding` via `DB::statement`
- Migration para `openai_api_key` (encrypted), `openai_chat_model`, `openai_embedding_model` no users
- Migration para `raw_import_id` nullable (transacoes manuais)
- Migration para colunas 2FA no users

## Fase 2 — Models + importacao CSV

- Models: `Category`, `RawImport`, `Transaction`, `Conversation`, `Message` — todos com `#[Fillable]` attributes
- `User` model: `#[Hidden]` para campos sensiveis, `encrypted` cast na API key, `TwoFactorAuthenticatable`
- `RawImport` com status helpers (`markProcessing`, `markDone`, `markFailed`) e cascade delete de transactions
- `CsvParserService` — detecta banco automaticamente (Nubank/Inter/C6/Caixa/generico), suporta formatos BR/EN de numero e multiplos delimitadores
- Job `ProcessRawImport` — queued, 3 retries, 120s timeout, atualiza `status` no `RawImport`
- `ImportController` + `RawImportPolicy`
- Upload multi-arquivo (CSV/TXT/PDF, max 20MB por arquivo)
- Pagina: `resources/js/pages/imports/index.tsx`

## Fase 3 — Categorizacao por IA

- `OpenAiService` — categorizacao em lote via GPT-4o com `response_format: json_object`, temperature 0
- `CategorizationService` — keyword-match primeiro → fallback para IA em chunks de 50; cria categorias novas automaticamente
- Job `CategorizeTransactions` — despachado ao fim do `ProcessRawImport` (sem retry, 300s timeout)
- `CategorySeeder` — 9 categorias PT-BR com keywords, cores e icones; chamado no registro via `CategorySeeder::seedForUser($user)`
- Config em `config/services.php` → `services.openai.*`

## Fase 4 — Dashboard com graficos

- `DashboardController` (invokable) — summary (total gasto/receita/saldo/count), gasto por categoria, tendencia diaria (generate_series), transacoes recentes, seletor de mes (24 meses)
- Queries PostgreSQL otimizadas (`TO_CHAR`, `generate_series`, `COALESCE`, `SUM CASE WHEN`)
- Graficos: Recharts `PieChart` (donut) + `BarChart` (tendencia diaria receita vs despesa)
- Pagina: `resources/js/pages/dashboard.tsx`

## Fase 5 — Embeddings + RAG

- `OpenAiService::embeddings(string[])` — batch via `text-embedding-3-small`
- `EmbeddingService::generateForImport()` — chunked (100/call), armazena via `DB::statement('UPDATE ... SET embedding = ?::vector')`
- Job `GenerateEmbeddings` — despachado ao fim do `CategorizeTransactions` (3 tries, 600s timeout)
- `RagService::search()` — cosine distance (`<=>`); `contextFor()` formata contexto para prompts

## Fase 6 — Chat com IA

- `ChatService::reply()` — persiste mensagens, busca RAG, chama GPT-4o (temp 0.7), auto-gera titulo na 1a mensagem (temp 0)
- Historico limitado a 10 mensagens por request
- System prompt financeiro em PT-BR (formato R$ X.XXX,XX, nao inventa dados)
- Controllers: `ConversationController` (index/show/store/destroy) + `MessageController` (store)
- `ConversationPolicy` — acesso protegido por `user_id`
- Chat sincrono (POST aguarda resposta do GPT)
- Pagina: `resources/js/pages/chat/index.tsx` — split pane (sidebar + area de chat), sugestoes de perguntas, Enter para enviar, loading bubble

## Fase 7 — Importacao PDF

- `PdfParserService` — usa `smalot/pdfparser`; estrategia multi-camada:
  - Regex por banco (Nubank, Inter, Bradesco, C6)
  - State machine generica para faturas de cartao de credito (Bradesco/Nubank/Inter/Itau/Santander/C6)
  - Fallback generico para extratos de conta
- Detecta banco pelo texto do PDF (palavras-chave no cabecalho)
- `ProcessRawImport` roteia: `type === 'pdf'` → `PdfParserService`, senao → `CsvParserService`
- `ImportController` aceita `mimes:csv,txt,pdf` (ate 20 MB)

## Fase 8 — Chave OpenAI por usuario

- Colunas no `users`: `openai_api_key` (encrypted cast), `openai_chat_model`, `openai_embedding_model`
- `OpenAiService::forUser()` / `forUserId()` — factories com fallback para config global
- Pagina de configuracao: `settings/api.tsx` (`GET/PATCH/DELETE /settings/api`)
- `User::hasOpenAiKey(): bool`
- Chave nunca exposta em props — pagina recebe apenas `hasApiKey: bool`

## UI / UX

- Pagina inicial (`/`) — design minimalista, cards de features, tech stack pills
- UI completamente em PT-BR (paginas auth, settings, componentes)
- Mensagens de erro do Laravel em PT-BR (`lang/en/*.php`)
- Logo: icone de carteira SVG moderno (`app-logo-icon.tsx`)
- 2FA com TOTP (`settings/security.tsx`) — QR code + manual key + recovery codes
- Tema light/dark/system (`use-appearance.tsx` + cookie)
- Sidebar colapsavel com estado persistido
- Breadcrumbs de navegacao
- Password input com toggle show/hide
- Transacoes manuais com formulario + validacao de categoria por usuario
- Exclusao de conta com confirmacao de senha

## Seguranca

- Autenticacao Fortify com rate limiting (login 5/min, 2FA 5/min)
- Password rules rigorosos (min 12, mixed case, numeros, simbolos, uncompromised em producao)
- Session cookie: HttpOnly, Secure, SameSite=Lax
- API keys encriptadas no banco (Laravel encrypted cast)
- Ownership checks em todos os controllers (abort_unless + Policies)
- CSRF via Inertia (automatico)
- Upload restrito: mimes:csv,txt,pdf, max 20MB
- Dotfiles bloqueados no nginx (`location ~ /\.(?!well-known).*`)

## Testes

- Feature tests: Auth (login, registro, 2FA, password reset, email verification), Dashboard, Transactions, Imports, Conversations, ProcessRawImport job
- Unit tests: CsvParserService (deteccao banco, parsing), PdfParserService (extracao, regex)
- PHPUnit com PostgreSQL dedicado (`finance_assistant_test`)
