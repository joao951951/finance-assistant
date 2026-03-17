# Finance Assistant — CLAUDE.md

Assistente financeiro pessoal com importação de extratos (CSV/PDF) e chat com IA sobre os dados financeiros do usuário.

**Stack:** Laravel 13 · React 19 · Inertia.js · OpenAI GPT-4o · pgvector · Tailwind v4 · shadcn/ui · TypeScript

---

## Documentação

| Arquivo | Conteúdo |
|---|---|
| [`.claude/project.md`](.claude/project.md) | Objetivos, stack, variáveis de ambiente, comandos |
| [`.claude/architecture.md`](.claude/architecture.md) | Estrutura de pastas, pipeline de importação, banco, IA |
| [`.claude/patterns.md`](.claude/patterns.md) | Convenções de código, gotchas críticos |
| [`.claude/features.md`](.claude/features.md) | Log de funcionalidades implementadas (fases 1–8) |

---

## Referência Rápida

### Comandos essenciais
```bash
composer dev        # servidor + fila + vite
composer test       # testes
composer lint       # PHP Pint
npm run types:check # TypeScript
```

### Wayfinder — regras críticas
- Importar de `@/actions/App/Http/Controllers/XController`
- Usar `.url()` quando Inertia ou `<a>` esperam uma string
- **Nunca** escrever rotas manualmente (ex: `'/imports'`)

### OpenAI — instanciar corretamente
```php
// Em controllers:
OpenAiService::forUser($request->user())

// Em jobs (sem DI de usuário):
OpenAiService::forUserId($this->userId)
```

### pgvector — sempre SQL raw
```php
// Salvar embedding
DB::statement('UPDATE transactions SET embedding = ?::vector WHERE id = ?', [$json, $id]);

// Buscar similares
DB::select('SELECT id FROM transactions ORDER BY embedding <=> ?::vector LIMIT 5', [$json]);
```

### Autenticação
- Fortify já configurado — não recriar auth
- 2FA implementado — `use-two-factor-auth.ts` + `TwoFactorSetupModal`

### Idioma
- UI inteiramente em PT-BR
- Erros do Laravel: `lang/en/auth.php`, `lang/en/passwords.php`, `lang/en/validation.php`
