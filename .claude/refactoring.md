# Refactoring Plan — Modularity

Diagnosis: the project has solid infrastructure (Services, Jobs, Policies), but several layers violate the modularity principle described in the architecture spec. Controllers contain business logic, frontend pages are monolithic, types are duplicated, and utilities are scattered.

---

## Backend

### 1. Create `DashboardService` — CRITICAL

**Problem:** `DashboardController` (191 lines) has 6 private methods with complex SQL queries inline (`summary()`, `spendingByCategory()`, `trend()`, `monthTransactions()`, `recentTransactions()`, `availableMonths()`). This violates the "thin controllers delegate to services" principle.

**Solution:** Extract to `app/Services/DashboardService.php`:

```
DashboardService
  + summary(int $userId, Carbon $from, Carbon $to): array
  + spendingByCategory(int $userId, Carbon $from, Carbon $to): array
  + trend(int $userId, Carbon $from, Carbon $to): array
  + monthTransactions(int $userId, Carbon $from, Carbon $to): array
  + recentTransactions(int $userId): array
  + availableMonths(int $userId): array
```

The controller becomes:
```php
public function __invoke(Request $request): Response
{
    $service = new DashboardService();
    // parseMonthParam, delegate everything to $service, return Inertia::render
}
```

---

### 2. Create `TransactionService` — CRITICAL

**Problem:** `TransactionController::index()` has a join query, pagination logic, and data mapping inline. The transaction-with-category join query is duplicated between TransactionController and DashboardController.

**Solution:** Extract to `app/Services/TransactionService.php`:

```
TransactionService
  + list(int $userId, int $page, int $perPage = 25): array    // returns {items, total, has_more, next_page}
  + listByDateRange(int $userId, Carbon $from, Carbon $to): array
  + recent(int $userId, int $limit = 10): array
  + categoriesForUser(int $userId): array
```

**Bonus:** `DashboardService` can reuse `TransactionService::listByDateRange()` and `recent()`, eliminating duplicate queries.

---

### 3. Extract `ChatService` factory — MODERATE

**Problem:** `ConversationController` (lines 73-80) and `MessageController` (lines 22-26) duplicate the same service construction block:
```php
$openAi = OpenAiService::forUser($request->user());
$embedding = new EmbeddingService($openAi);
$rag = new RagService($embedding);
return new ChatService($openAi, $rag);
```

**Solution:** Add a static factory to `ChatService`:
```php
class ChatService
{
    public static function forUser(User $user): self
    {
        $openAi = OpenAiService::forUser($user);
        $embedding = new EmbeddingService($openAi);
        $rag = new RagService($embedding);
        return new self($openAi, $rag);
    }
}
```

Usage in controllers:
```php
ChatService::forUser($request->user())->reply($conversation, $message);
```

---

## Frontend

### 4. Centralize domain types — CRITICAL

**Problem:** `Transaction`, `Category`, `Conversation`, `Message`, `RawImport` are defined inline in each page (dashboard.tsx, transactions/index.tsx, chat/index.tsx, imports/index.tsx) instead of shared.

**Solution:** Create `resources/js/types/models.ts`:

```typescript
// resources/js/types/models.ts
export interface Transaction {
    id: number;
    date: string;
    description: string;
    amount: number;
    type: 'credit' | 'debit';
    category_name: string;
    category_color: string;
}

export interface Category {
    id: number;
    name: string;
    color: string;
}

export interface Conversation {
    id: number;
    title: string | null;
    created_at: string;
}

export interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
}

export interface RawImport {
    id: number;
    filename: string;
    type: 'csv' | 'pdf';
    bank: string | null;
    status: 'pending' | 'processing' | 'done' | 'failed';
    transactions_count: number;
    error_message: string | null;
    created_at: string;
}

// Dashboard-specific
export interface Summary {
    total_spent: number;
    total_income: number;
    balance: number;
    transactions_count: number;
    month_label: string;
}

export interface CategorySpending {
    name: string;
    color: string;
    total: number;
}

export interface TrendPoint {
    period: string;
    label: string;
    spent: number;
    income: number;
}

export interface AvailableMonth {
    value: string;
    label: string;
}
```

Update `resources/js/types/index.ts` to export from `models.ts`.

---

### 5. Create `resources/js/lib/formatters.ts` — MODERATE

**Problem:** `formatBRL()` is duplicated in dashboard.tsx and transactions/index.tsx. `formatDateBR()` logic (toLocaleDateString) is repeated across multiple pages.

**Solution:**
```typescript
// resources/js/lib/formatters.ts
export function formatBRL(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

export function formatBRLCompact(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        notation: 'compact',
        currency: 'BRL',
        style: 'currency',
    }).format(value);
}

export function formatDateBR(date: string): string {
    return new Date(date).toLocaleDateString('pt-BR');
}
```

---

### 6. Extract `useInfiniteScroll` hook — MODERATE

**Problem:** `transactions/index.tsx` has ~30 lines of IntersectionObserver + pagination state management inline.

**Solution:** Create `resources/js/hooks/use-infinite-scroll.ts`:
```typescript
export function useInfiniteScroll({
    hasMore, nextPage, isLoading, only
}: {
    hasMore: boolean;
    nextPage: number | null;
    isLoading: boolean;
    only: string[];
}) {
    // returns { loaderRef, setIsLoading }
}
```

---

### 7. Extract domain components — MODERATE

**Problem:** Sub-components are defined inline in page files instead of being standalone reusable components.

**Solution:** Move to `resources/js/components/`:

| From | To |
|---|---|
| `dashboard.tsx` → `SummaryCard` | `components/summary-card.tsx` |
| `dashboard.tsx` → `TransactionList` | `components/transaction-list.tsx` |
| `dashboard.tsx` → `EmptyState` | `components/empty-state.tsx` |
| `transactions/index.tsx` → `NewTransactionDialog` | `components/new-transaction-dialog.tsx` |
| `imports/index.tsx` → `ImportCard` + `StatusBadge` | `components/import-card.tsx` |
| `chat/index.tsx` → `MessageContent` | `components/message-content.tsx` |

---

### 8. Extract constants — LOW PRIORITY

**Problem:** `STATUS_CONFIG` hardcoded in imports/index.tsx, chat suggestion strings in chat/index.tsx.

**Solution:** Create `resources/js/lib/constants.ts` for reusable domain constants.

---

## Recommended Execution Order

| Phase | Task | Impact | Risk |
|---|---|---|---|
| 1 | Centralized types (item 4) | High | Low |
| 2 | `formatters.ts` (item 5) | Medium | Low |
| 3 | `DashboardService` (item 1) | High | Medium |
| 4 | `TransactionService` (item 2) | High | Medium |
| 5 | `ChatService` factory (item 3) | Medium | Low |
| 6 | Extract components (item 7) | Medium | Low |
| 7 | `useInfiniteScroll` hook (item 6) | Medium | Low |
| 8 | Constants (item 8) | Low | Low |

**Strategy:** Start with low-risk items (types, formatters) to validate the pattern, then tackle backend services that eliminate query duplication.

**Testing:** Run `composer test` and `npm run types:check` after each phase. Existing tests cover controllers and services, so refactors that only move logic should keep tests passing.
