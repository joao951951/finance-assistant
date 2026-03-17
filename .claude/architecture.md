# Arquitetura

## Backend (Laravel 13)

```
app/
  Models/                   # Eloquent models (Category, RawImport, Transaction, Conversation, Message)
  Http/
    Controllers/             # Inertia controllers (retornam Inertia::render)
    Requests/                # Form requests
  Services/                  # Integrações externas e lógica de negócio
    OpenAiService.php         # Cliente OpenAI (chat, categorization, embeddings)
    CsvParserService.php      # Parser CSV multi-banco
    PdfParserService.php      # Parser PDF multi-banco
    CategorizationService.php # Keyword-match → AI fallback
    EmbeddingService.php      # Gera e salva embeddings no pgvector
    RagService.php            # Busca semântica + formatação de contexto
    ChatService.php           # Orquestra RAG + GPT-4o + persistência
  Jobs/                      # Processamento assíncrono
    ProcessRawImport.php      # Parse CSV/PDF
    CategorizeTransactions.php
    GenerateEmbeddings.php
  Actions/                   # Actions de domínio (Fortify, etc.)
  Policies/                  # Autorização Eloquent

database/
  migrations/
  seeders/                   # CategorySeeder (9 categorias PT-BR por usuário)

lang/
  en/                        # Strings traduzidas para PT-BR (auth, passwords, validation)
```

## Pipeline de importação (fila)

```
Upload → ProcessRawImport → CategorizeTransactions → GenerateEmbeddings
```

Cada job dispara o próximo ao finalizar. Erros são marcados em `raw_imports.status`.

## Frontend (React 19 + Inertia.js)

```
resources/js/
  pages/                    # Páginas Inertia (um arquivo = uma rota)
    welcome.tsx             # Página inicial pública
    dashboard.tsx           # Resumo financeiro + gráficos
    imports/index.tsx       # Upload de extratos
    chat/index.tsx          # Chat IA (split pane)
    settings/
      profile.tsx
      security.tsx          # Senha + 2FA
      appearance.tsx
      api.tsx               # Chave OpenAI por usuário
  components/
    ui/                     # shadcn/ui (não editar diretamente)
    app-sidebar.tsx         # Menu lateral
    app-logo-icon.tsx       # Logo SVG (wallet icon)
    ...
  layouts/
    app-layout.tsx
    settings/layout.tsx
  hooks/
    use-appearance.ts
    use-two-factor-auth.ts
    use-clipboard.ts
  actions/                  # Gerado pelo Wayfinder (não editar)
  routes/                   # Gerado pelo Wayfinder (não editar)
  types/
```

## IA / RAG

- **OpenAiService** — wrapper em torno do `openai-php/client`; todas as operações passam por aqui
- **Embeddings** — `text-embedding-3-small`, 1536 dimensões, armazenadas em `transactions.embedding vector(1536)` via `DB::statement` (Eloquent não suporta tipo `vector`)
- **HNSW index** em `transactions.embedding` para busca eficiente (`<=>` cosine distance)
- **RAG** — `RagService::search()` busca as N transações mais similares → `contextFor()` formata para o prompt
- **Chat** — síncrono (POST aguarda resposta do GPT); auto-gera título da conversa na 1ª mensagem

## Banco de Dados

- PostgreSQL obrigatório (pgvector)
- `vector` column criada via `DB::statement` (sem suporte nativo no Blueprint)
- Soft deletes em dados do usuário
- Embeddings consultados sempre via SQL raw (`<=>` operator)
