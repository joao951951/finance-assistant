# 💰 Assistente Financeiro

Assistente financeiro pessoal onde você importa extratos bancários (CSV/PDF) e conversa com uma IA para entender seus gastos, identificar padrões e receber sugestões de economia.

> Projeto de portfólio desenvolvido com Laravel 13 + React 19 + integração com OpenAI GPT-4o + RAG com pgvector.

---

## Funcionalidades

- **Importação de extratos** — CSV e PDF (Nubank, Inter, Bradesco, C6 e formato genérico)
- **Categorização automática** — keyword-match + fallback para IA (GPT-4o)
- **Dashboard financeiro** — resumo de gastos, gráfico por categoria e tendência mensal
- **Chat com IA** — converse com seus dados financeiros via RAG (busca semântica em pgvector)
- **Chave OpenAI por usuário** — cada usuário configura sua própria integração com IA
- **Autenticação completa** — login, registro, 2FA com TOTP, reset de senha
- **Interface em português** — UI e mensagens de erro inteiramente em PT-BR

---

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13 + PHP 8.3+ |
| Frontend | React 19 + Inertia.js + TypeScript |
| UI | shadcn/ui + Tailwind CSS v4 |
| Auth | Laravel Fortify (2FA incluso) |
| Banco | PostgreSQL + pgvector |
| IA | OpenAI GPT-4o + text-embedding-3-small |
| Gráficos | Recharts |
| Rotas type-safe | Laravel Wayfinder |

---

## Pré-requisitos

- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL com extensão pgvector

### Subir o banco com Docker

```bash
docker run --name finance-postgres \
  -e POSTGRES_PASSWORD=sua_senha \
  -p 5432:5432 \
  -d pgvector/pgvector:pg17
```

---

## Instalação

```bash
# 1. Clonar o repositório
git clone https://github.com/seu-usuario/finance-assistant.git
cd finance-assistant

# 2. Instalar dependências PHP
composer install

# 3. Instalar dependências JavaScript
npm install

# 4. Configurar o ambiente
cp .env.example .env
php artisan key:generate
```

### Configurar o `.env`

```env
APP_NAME="Assistente Financeiro"

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=finance_assistant
DB_USERNAME=postgres
DB_PASSWORD=sua_senha

# Opcional: chave global de fallback (cada usuário pode configurar a sua)
OPENAI_API_KEY=sk-...
OPENAI_CHAT_MODEL=gpt-4o
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

```bash
# 5. Rodar migrations e seeders
php artisan migrate --seed

# 6. Iniciar o projeto (servidor + fila + vite)
composer dev
```

Acesse em [http://localhost:8000](http://localhost:8000).

---

## Configurando a integração com IA

Após criar sua conta, acesse **Configurações → API / IA** e insira sua chave da OpenAI. A chave é armazenada de forma criptografada e nunca é exposta.

> Sem a chave configurada, a importação de extratos funciona normalmente, mas categorização automática e o chat com IA ficam indisponíveis.

---

## Arquitetura

### Pipeline de importação (assíncrono)

```
Upload do arquivo
      ↓
ProcessRawImport (Job) — parse CSV ou PDF
      ↓
CategorizeTransactions (Job) — keyword-match → GPT-4o
      ↓
GenerateEmbeddings (Job) — text-embedding-3-small → pgvector
```

### Chat com IA (RAG)

```
Mensagem do usuário
      ↓
Busca semântica no pgvector (cosine distance)
      ↓
Contexto financeiro formatado + histórico da conversa
      ↓
GPT-4o gera resposta
```

---

## Comandos úteis

```bash
# Desenvolvimento (servidor + fila + vite)
composer dev

# Testes
composer test

# Lint PHP
composer lint

# Lint + format frontend
npm run lint
npm run format

# Type check TypeScript
npm run types:check
```

---

## Estrutura do projeto

```
app/
  Models/           # Category, RawImport, Transaction, Conversation, Message
  Http/Controllers/ # Controllers Inertia
  Services/         # OpenAiService, CsvParser, PdfParser, ChatService, RagService
  Jobs/             # ProcessRawImport, CategorizeTransactions, GenerateEmbeddings

resources/js/
  pages/            # Páginas Inertia (dashboard, imports, chat, settings)
  components/       # Componentes React compartilhados
  hooks/            # Custom hooks
```

---

## Licença

MIT
