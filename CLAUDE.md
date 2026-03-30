# Finance Assistant — CLAUDE.md

Assistente financeiro pessoal com importação de extratos bancários (CSV/PDF), categorização por IA, busca semântica (RAG) e chat inteligente sobre os dados financeiros do usuário.

**Stack:** Laravel 13 · PHP 8.3 · React 19 · Inertia.js · TypeScript strict · OpenAI GPT-4o · pgvector · Tailwind v4 · shadcn/ui · Wayfinder

**Arquitetura:** MVC + Services layer — controllers finos delegam para services, jobs assíncronos para tarefas pesadas, models Eloquent com `#[Fillable]` attributes.

---

## Documentacao

| Arquivo | Conteudo |
|---|---|
| [`.claude/project.md`](.claude/project.md) | Objetivos, stack, variaveis de ambiente, comandos |
| [`.claude/architecture.md`](.claude/architecture.md) | Estrutura de pastas, pipeline de importacao, banco, IA, deploy |
| [`.claude/patterns.md`](.claude/patterns.md) | Convencoes de codigo, gotchas criticos |
| [`.claude/features.md`](.claude/features.md) | Log de funcionalidades implementadas (fases 1-8 + UI/UX) |

---

## Referencia Rapida

### Comandos essenciais
```bash
composer dev          # servidor + fila + vite (concorrente)
composer test         # PHPUnit (Feature + Unit)
composer lint         # PHP Pint (PSR-12)
npm run lint          # ESLint
npm run format        # Prettier
npm run types:check   # TypeScript strict
composer ci:check     # lint + types + test
```

### Wayfinder — regras criticas
- Importar de `@/actions/App/Http/Controllers/XController`
- Usar `.url()` quando Inertia ou `<a>` esperam uma string
- **Nunca** escrever rotas manualmente (ex: `'/imports'`)

### OpenAI — instanciar corretamente
```php
// Em controllers:
OpenAiService::forUser($request->user())

// Em jobs (sem DI de usuario):
OpenAiService::forUserId($this->userId)
```

### pgvector — sempre SQL raw
```php
// Salvar embedding
DB::statement('UPDATE transactions SET embedding = ?::vector WHERE id = ?', [$json, $id]);

// Buscar similares
DB::select('SELECT id FROM transactions ORDER BY embedding <=> ?::vector LIMIT 5', [$json]);
```

### Models — PHP 8 attributes
```php
#[Fillable(['campo1', 'campo2'])]   // mass assignment
#[Hidden(['password', 'api_key'])]   // nao expor em JSON/Inertia
```

### Autenticacao
- Fortify ja configurado — nao recriar auth
- 2FA implementado — `use-two-factor-auth.ts` + `TwoFactorSetupModal`
- Rate limiting: login 5/min, 2FA 5/min, password update 6/min

### Idioma
- UI inteiramente em PT-BR
- Erros do Laravel: `lang/en/auth.php`, `lang/en/passwords.php`, `lang/en/validation.php`

### Deploy
- Oracle Cloud (Ubuntu) via `deploy/cloud-init.sh`
- Nginx + PHP-FPM 8.4 + Supervisor (queue worker)
- PostgreSQL via Supabase (pgvector habilitado)
- Dominio: assistentefinanceiro.online
