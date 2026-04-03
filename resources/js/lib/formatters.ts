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
