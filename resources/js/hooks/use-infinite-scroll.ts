import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface UseInfiniteScrollOptions<T> {
    initialItems: T[];
    hasMore: boolean;
    nextPage: number | null;
    only: string[];
    getItems: (props: Record<string, unknown>) => T[];
}

export function useInfiniteScroll<T>({
    initialItems,
    hasMore,
    nextPage,
    only,
    getItems,
}: UseInfiniteScrollOptions<T>) {
    const [allItems, setAllItems] = useState<T[]>(initialItems);
    const [isLoading, setIsLoading] = useState(false);
    const loaderRef = useRef<HTMLDivElement>(null);

    const loadNext = useCallback(() => {
        setIsLoading(true);
        router.reload({
            data: { page: nextPage },
            only,
            preserveUrl: true,
            onSuccess: (page) => {
                const newItems = getItems(
                    page.props as Record<string, unknown>,
                );
                setAllItems((prev) => [...prev, ...newItems]);
            },
            onFinish: () => setIsLoading(false),
        });
    }, [nextPage, only, getItems]);

    useEffect(() => {
        if (!loaderRef.current) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore && !isLoading) {
                    loadNext();
                }
            },
            { threshold: 0.1 },
        );
        observer.observe(loaderRef.current);

        return () => observer.disconnect();
    }, [hasMore, isLoading, loadNext]);

    // Reset when page 1 data changes (e.g., after creating/deleting a transaction)
    const resetItems = useCallback((items: T[]) => {
        setAllItems(items);
    }, []);

    return { allItems, loaderRef, isLoading, resetItems };
}
