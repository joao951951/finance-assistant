# Padroes e Convencoes

## PHP / Laravel

- **PSR-12** via Laravel Pint (rode `composer lint` antes de commitar)
- **PHP 8 attributes** nos models: `#[Fillable]`, `#[Hidden]` — sem propriedades `$fillable`/`$guarded`
- **Controllers finos** — validam, autorizam, delegam para Services/Jobs
- **Services** para logica de negocio e integracoes externas (OpenAI, parsers)
- **Jobs** para processamento assincrono (parsing, categorizacao, embeddings)
- **Policies** para autorizacao por recurso (`ConversationPolicy`, `RawImportPolicy`)
- **Actions** (Fortify) para registro e reset de senha
- **Concerns (Traits)** para regras de validacao compartilhadas (`PasswordValidationRules`, `ProfileValidationRules`)
- Ownership check manual em controllers sem Policy: `abort_unless($model->user_id === $request->user()->id, 403)`

## TypeScript / React

- TypeScript strict — sem `any`
- Functional components apenas
- Usar `Form` do Inertia para formularios (nao `useForm` manual)
- **Wayfinder** para rotas e actions — importar de `@/actions/App/Http/Controllers/XController`
  - Usar `.url()` quando Inertia espera uma string: `ImportController.store.url()`
  - Nunca escrever rotas manualmente como `/imports` ou `/api/...`
- shadcn/ui components em `resources/js/components/ui/` — nao editar diretamente
- Tailwind CSS v4 — usar variaveis CSS, **nao** `tailwind.config.js`
- Graficos com Recharts (`PieChart` donut, `BarChart`)
- Path alias: `@/*` → `resources/js/*`

## Idioma

- Toda a UI em **portugues brasileiro** (PT-BR)
- Mensagens de erro do Laravel em PT-BR (arquivos em `lang/en/`)
- Comentarios de codigo podem ser em ingles ou portugues

## OpenAI / IA

- **Nunca** instanciar `OpenAiService` diretamente — usar factories estaticos:
  - `OpenAiService::forUser(User $user)` — nos controllers
  - `OpenAiService::forUserId(int $id)` — nos jobs (DI nao conhece o usuario)
- A chave de API e por usuario (`users.openai_api_key`, encrypted via cast)
- Fallback para a chave global em `config/services.php` se o usuario nao configurou
- `User::hasOpenAiKey(): bool` — verificar disponibilidade antes de chamar a IA
- `openai_api_key` esta em `#[Hidden]` no model — nunca exposta em props Inertia
- A classe `OpenAI` (openai-php/client) esta no namespace raiz — `use OpenAI;` correto; aviso do IDE e falso positivo
- Categorizacao usa JSON mode (`response_format: json_object`)
- Chat usa `temperature: 0.7`; categorizacao usa `temperature: 0`

## pgvector / Embeddings

- Colunas `vector` sempre via `DB::statement` — Blueprint nao tem suporte nativo
- Embeddings armazenados/consultados sempre via SQL raw
- Operador de distancia cosine: `<=>` (quanto menor, mais similar)
- HNSW index em `transactions.embedding` para performance
- Embedding model: `text-embedding-3-small` (1536 dimensoes)
- Batch de 100 transacoes por chamada de embedding

## Auth

- Auth configurado via **Laravel Fortify** — nao recriar nem sobrescrever
- 2FA com TOTP ja implementado em `settings/security.tsx`
- Password rules: min 12 chars, mixed case, numbers, symbols, uncompromised (producao)
- Rate limiting: login 5/min (por IP+username), 2FA 5/min, password update 6/min
- Session cookie: HttpOnly, Secure, SameSite=Lax

## SSL no Windows (desenvolvimento)

- cURL no Windows pode falhar com `SSL certificate problem: unable to get local issuer certificate`
- Solucao: `OpenAiService` usa cliente Guzzle customizado com `verify => storage_path('cacert.pem')`
- O arquivo `storage/cacert.pem` foi baixado do curl.se/ca/cacert.pem

## Gotchas Criticos

| Problema | Solucao |
|---|---|
| `vector` type no migration | Usar `DB::statement()` em vez de Blueprint |
| Embeddings no Eloquent | Sempre SQL raw (`DB::statement` / `DB::select`) |
| Wayfinder retorna objeto | Usar `.url()` quando string e necessaria |
| `OpenAI` class nao reconhecida no IDE | Falso positivo — `use OpenAI;` esta correto |
| SSL cURL no Windows | `cacert.pem` em `storage/` + Guzzle customizado no `OpenAiService` |
| Recharts ValueType no Tooltip | Usar `formatter={(v) => formatBRL(Number(v))}` |
| Mass assignment | Usar `#[Fillable([...])]` attribute, nao `$fillable` property |
| Import cascade delete | `RawImport::booted()` deleta transactions no evento `deleting` |
| Category keywords | JSON array, matched case-insensitively contra descricao da transacao |
