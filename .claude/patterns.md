# Padrões e Convenções

## PHP / Laravel

- **PSR-12** via Laravel Pint (rode `composer lint` antes de commitar)
- **Action classes** para operações de domínio — `app/Actions/` e `app/actions/` (ambos existem)
- **Jobs** para todo processamento assíncrono (chamadas à IA, parsing de arquivos)
- **Services** para integrações externas (OpenAI, parsers)
- **Policies** para autorização por recurso (`RawImportPolicy`, `ConversationPolicy`)
- PHP 8 attribute style nos models (`#[Attribute]`)

## TypeScript / React

- TypeScript strict — sem `any`
- Functional components apenas
- Usar `Form` do Inertia para formulários (não `useForm` manual)
- **Wayfinder** para rotas e actions — importar de `@/actions/App/Http/Controllers/XController`
  - Usar `.url()` quando Inertia espera uma string: `ImportController.store.url()`
  - Nunca escrever rotas manualmente como `/imports` ou `/api/...`
- shadcn/ui components ficam em `resources/js/components/ui/` — não editar diretamente
- Tailwind CSS v4 — usar variáveis CSS, **não** `tailwind.config.js`

## Idioma

- Toda a UI em **português brasileiro** (PT-BR)
- Mensagens de erro do Laravel em PT-BR (arquivos em `lang/en/`)
- Comentários de código podem ser em inglês ou português

## OpenAI / IA

- **Nunca** instanciar `OpenAiService` diretamente — usar os factories estáticos:
  - `OpenAiService::forUser(User $user)` — nos controllers
  - `OpenAiService::forUserId(int $id)` — nos jobs (DI não conhece o usuário)
- A chave de API é por usuário (`users.openai_api_key`, encriptada)
- Fallback para a chave global em `config/services.php` se o usuário não configurou
- `User::hasOpenAiKey(): bool` — verificar disponibilidade antes de chamar a IA
- `openai_api_key` está em `#[Hidden]` no model — nunca exposta em props Inertia
- A classe `OpenAI` (openai-php/client) está no namespace raiz — `use OpenAI;` está correto; aviso "unknown class" no IDE é falso positivo

## pgvector / Embeddings

- Colunas `vector` sempre via `DB::statement` — Blueprint não tem suporte nativo
- Embeddings armazenados/consultados sempre via SQL raw
- Operador de distância cosine: `<=>` (quanto menor, mais similar)
- HNSW index em `transactions.embedding` para performance

## Auth

- Auth configurado via **Laravel Fortify** — não recriar nem sobrescrever
- 2FA com TOTP já implementado em `settings/security.tsx`

## SSL no Windows (desenvolvimento)

- cURL no Windows pode falhar com `SSL certificate problem: unable to get local issuer certificate`
- Solução: `OpenAiService` usa cliente Guzzle customizado com `verify => storage_path('cacert.pem')`
- O arquivo `storage/cacert.pem` foi baixado do curl.se/ca/cacert.pem

## Gotchas Críticos

| Problema | Solução |
|---|---|
| `vector` type no migration | Usar `DB::statement()` em vez de Blueprint |
| Embeddings no Eloquent | Sempre SQL raw (`DB::statement` / `DB::select`) |
| Wayfinder retorna objeto | Usar `.url()` quando string é necessária |
| `OpenAI` class não reconhecida no IDE | Falso positivo — `use OpenAI;` está correto |
| SSL cURL no Windows | `cacert.pem` em `storage/` + Guzzle customizado no `OpenAiService` |
| Recharts ValueType no Tooltip | Usar `formatter={(v) => formatBRL(Number(v))}` |
