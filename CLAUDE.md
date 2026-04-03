# Finance Assistant — CLAUDE.md

Personal finance assistant with bank statement import (CSV/PDF), AI-driven categorization, semantic search (RAG), and intelligent chat about the user's financial data.

**Stack:** Laravel 13 · PHP 8.3 · React 19 · Inertia.js · TypeScript strict · OpenAI GPT-4o · pgvector · Tailwind v4 · shadcn/ui · Wayfinder

**Architecture:** MVC + Services layer — thin controllers delegate to services, async jobs for heavy tasks, Eloquent models with `#[Fillable]` attributes.

---

## Documentation

| File | Contents |
|---|---|
| [`.claude/project.md`](.claude/project.md) | Goals, stack, environment variables, commands |
| [`.claude/architecture.md`](.claude/architecture.md) | Folder structure, import pipeline, database, AI, deploy |
| [`.claude/patterns.md`](.claude/patterns.md) | Code conventions, critical gotchas |
| [`.claude/features.md`](.claude/features.md) | Implemented features log (phases 1-8 + UI/UX) |
| [`.claude/refactoring.md`](.claude/refactoring.md) | Modularity refactoring log (completed) |

---

## Quick Reference

### Essential commands
```bash
composer dev          # server + queue + vite (concurrent)
composer test         # PHPUnit (Feature + Unit)
composer lint         # PHP Pint (PSR-12)
npm run lint          # ESLint
npm run format        # Prettier
npm run types:check   # TypeScript strict
composer ci:check     # lint + types + test
```

### Wayfinder — critical rules
- Import from `@/actions/App/Http/Controllers/XController`
- Use `.url()` when Inertia or `<a>` expects a string
- **Never** write routes manually (e.g., `'/imports'`)

### OpenAI — correct instantiation
```php
// In controllers:
OpenAiService::forUser($request->user())

// In jobs (no user DI):
OpenAiService::forUserId($this->userId)

// For chat (full pipeline):
ChatService::forUser($request->user())
```

### pgvector — always raw SQL
```php
// Save embedding
DB::statement('UPDATE transactions SET embedding = ?::vector WHERE id = ?', [$json, $id]);

// Search similar
DB::select('SELECT id FROM transactions ORDER BY embedding <=> ?::vector LIMIT 5', [$json]);
```

### Models — PHP 8 attributes
```php
#[Fillable(['field1', 'field2'])]   // mass assignment
#[Hidden(['password', 'api_key'])]   // never expose in JSON/Inertia
```

### Authentication
- Fortify already configured — do not recreate auth
- 2FA implemented — `use-two-factor-auth.ts` + `TwoFactorSetupModal`
- Rate limiting: login 5/min, 2FA 5/min, password update 6/min

### Language
- UI entirely in PT-BR
- Laravel errors: `lang/en/auth.php`, `lang/en/passwords.php`, `lang/en/validation.php`

### Deploy
- Oracle Cloud (Ubuntu) via `deploy/cloud-init.sh`
- Nginx + PHP-FPM 8.4 + Supervisor (queue worker)
- PostgreSQL via Supabase (pgvector enabled)
- Domain: assistentefinanceiro.online
