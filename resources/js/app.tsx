import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';

import { TooltipProvider } from '@shared/components/ui/tooltip';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Page components live per module (modules/<Name>/resources/js/Pages) and in
// the root resources/js/pages. Module pages are referenced as
// "<Module>/<Page>" (e.g. "Identity/Dashboard"); root pages by their name.
const pages = import.meta.glob<{ default: ComponentType }>([
    './pages/**/*.tsx',
    '../../modules/*/resources/js/Pages/**/*.tsx',
]);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    // shadcn Tooltip (sidebar, icon buttons) needs one provider at the root.
    withApp: (app) => <TooltipProvider>{app}</TooltipProvider>,
    resolve: async (name) => {
        const page =
            pages[`./pages/${name}.tsx`] ??
            Object.entries(pages).find(([key]) =>
                key.endsWith(`/Pages/${name}.tsx`),
            )?.[1];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return (await page()).default;
    },
});
