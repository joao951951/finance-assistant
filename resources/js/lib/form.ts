import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

type Method = 'get' | 'post' | 'put' | 'delete' | 'patch' | 'head' | 'options';

/**
 * Converts a Wayfinder route definition to props for Inertia's <Form> component.
 * Usage: <Form {...asForm(store())} ...>
 */
export const asForm = <TMethod extends Method>(
    route: RouteDefinition<TMethod>,
): RouteFormDefinition<TMethod> => ({
    action: route.url,
    method: route.method as TMethod,
});
