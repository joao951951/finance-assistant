import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface UseInfiniteScrollOptions {
    hasMore: boolean;
    nextPage: number | null;
    only: string[];
}

export function useInfiniteScroll({ hasMore, nextPage, only }: UseInfiniteScrollOptions) {
    const [isLoading, setIsLoading] = useState(false);
    const loaderRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setIsLoading(false);
    }, [nextPage]);

    useEffect(() => {
        if (!loaderRef.current) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore && !isLoading) {
                    setIsLoading(true);
                    router.reload({
                        data: { page: nextPage },
                        only,
                        preserveUrl: true,
                    });
                }
            },
            { threshold: 0.1 },
        );
        observer.observe(loaderRef.current);
        return () => observer.disconnect();
    }, [hasMore, isLoading, nextPage, only]);

    return { loaderRef, isLoading };
}
