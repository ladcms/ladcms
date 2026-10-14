type PageLoader = () => Promise<unknown>;

export function resolveModulePage(
    name: string,
    appPages: Record<string, PageLoader>,
    modulePages: Record<string, PageLoader>,
): Promise<unknown> {
    if (name.includes('::')) {
        const [module, page] = name.split('::', 2);
        const key = `/vendor/ladcms/${module}/resources/js/pages/${page}.tsx`;
        const loader = modulePages[key];
        if (!loader) {
            throw new Error(
                `Inertia page "${name}" not found (expected ${key})`,
            );
        }
        return loader();
    }

    const loader = appPages[`./pages/${name}.tsx`];
    if (!loader) {
        throw new Error(
            `Inertia page "${name}" not found in resources/js/pages`,
        );
    }
    return loader();
}
