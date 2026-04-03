export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-40 items-center justify-center text-sm text-muted-foreground">
            {message}
        </div>
    );
}
