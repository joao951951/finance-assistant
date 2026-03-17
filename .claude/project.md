# Projeto: Assistente Financeiro

## Objetivo

Assistente financeiro pessoal onde usuários importam extratos bancários (CSV/PDF) e recebem insights sobre padrões de gasto e sugestões de economia via integração com IA.

Aplicação de portfólio — foco em código limpo, arquitetura bem definida e funcionalidades completas.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13 + PHP 8.3+ |
| Frontend | React 19 + Inertia.js |
| UI | shadcn/ui + Tailwind CSS v4 |
| Auth | Laravel Fortify |
| Banco | PostgreSQL + pgvector |
| IA | Integração com OpenAI GPT-4o |
| Embeddings | text-embedding-3-small (pgvector) |
| Queue | Laravel Queue (sync/database) |
| Tipos | TypeScript strict |
| Rotas | Laravel Wayfinder |

## Variáveis de Ambiente

```env
APP_NAME="Assistente Financeiro"

DB_CONNECTION=pgsql   # obrigatório para pgvector

# Chave global de fallback (cada usuário tem a sua própria)
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_CHAT_MODEL=gpt-4o
```

## Comandos de Desenvolvimento

```bash
# Inicia servidor + fila + vite
composer dev

# Testes
composer test

# Lint PHP (Pint)
composer lint

# Lint + format frontend
npm run lint
npm run format

# Type check TypeScript
npm run types:check
```
