// Domain model types — shared across all pages
// Do NOT define these inline in page components

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
