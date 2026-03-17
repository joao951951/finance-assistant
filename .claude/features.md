# Funcionalidades Implementadas

## ✅ Fase 1 — Schema do banco

- Extensão pgvector habilitada
- Migrations: `categories`, `raw_imports`, `transactions`, `conversations`, `messages`
- `embedding vector(1536)` adicionado via `DB::statement`
- HNSW index em `transactions.embedding` via `DB::statement`

## ✅ Fase 2 — Models + importação CSV

- Models: `Category`, `RawImport`, `Transaction`, `Conversation`, `Message`
- `CsvParserService` — detecta banco automaticamente (Nubank/Inter/C6/genérico), suporta formatos BR/EN de número e múltiplos delimitadores
- Job `ProcessRawImport` — queued, 3 retries, atualiza `status` no `RawImport`
- `ImportController` + `RawImportPolicy`
- Página: `resources/js/pages/imports/index.tsx`

## ✅ Fase 3 — Categorização por IA

- `OpenAiService` — categorização em lote via `gpt-4o` com `response_format: json_object`
- `CategorizationService` — keyword-match primeiro → fallback para IA em chunks de 50
- Job `CategorizeTransactions` — despachado ao fim do `ProcessRawImport`
- `CategorySeeder` — 9 categorias PT-BR; chamado no registro do usuário via `CategorySeeder::seedForUser($user)`
- Config em `config/services.php` → `services.openai.*`

## ✅ Fase 4 — Dashboard com gráficos

- `DashboardController` (invokable) — cards de resumo, gasto por categoria, tendência mensal (6 meses), transações recentes
- Queries em PostgreSQL puro (`TO_CHAR`, `COALESCE`, `SUM CASE WHEN`)
- Gráficos: Recharts `PieChart` (donut) + `BarChart`
- Página: `resources/js/pages/dashboard.tsx`

## ✅ Fase 5 — Embeddings + RAG

- `OpenAiService::embeddings(string[])` — batch via `text-embedding-3-small`
- `EmbeddingService::generateForImport()` — chunked, armazena via `DB::statement('UPDATE ... SET embedding = ?::vector')`
- Job `GenerateEmbeddings` — despachado ao fim do `CategorizeTransactions`
- `RagService::search()` — cosine distance (`<=>`); `contextFor()` formata contexto para prompts

## ✅ Fase 6 — Chat com IA

- `ChatService::reply()` — persiste mensagens, busca RAG, chama GPT-4o, auto-gera título na 1ª mensagem
- Controllers: `ConversationController` (index/show/store/destroy) + `MessageController` (store)
- `ConversationPolicy` — acesso protegido por `user_id`
- Chat síncrono (POST aguarda resposta do GPT)
- Página: `resources/js/pages/chat/index.tsx` — split pane (sidebar + área de chat), sugestões de perguntas, Enter para enviar, loading bubble

## ✅ Fase 7 — Importação PDF

- `PdfParserService` — usa `smalot/pdfparser`; regex por banco (Nubank, Inter, Bradesco, C6, genérico)
- Detecta banco pelo texto do PDF (palavras-chave no cabeçalho)
- `ProcessRawImport` roteado: `type === 'pdf'` → `PdfParserService`, senão → `CsvParserService`
- `ImportController` aceita `mimes:csv,txt,pdf` (até 20 MB)

## ✅ Fase 8 — Chave OpenAI por usuário

- Colunas no `users`: `openai_api_key` (encrypted), `openai_chat_model`, `openai_embedding_model`
- `OpenAiService::forUser()` / `forUserId()` — factories com fallback para config global
- Página de configuração: `settings/api.tsx` (`GET/PATCH/DELETE /settings/api`)
- `User::hasOpenAiKey(): bool`
- Chave nunca exposta em props — página recebe apenas `hasApiKey: bool`

## ✅ UI / UX

- Página inicial (`/`) — design minimalista, cards de features, tech stack pills
- UI completamente em PT-BR (páginas auth, settings, componentes)
- Mensagens de erro do Laravel em PT-BR (`lang/en/*.php`)
- Logo: ícone de carteira SVG moderno (`app-logo-icon.tsx`)
- 2FA com TOTP (`settings/security.tsx`)
- SSL no Windows resolvido via `cacert.pem` + Guzzle customizado
