import { useState } from 'react';
import type { ReactNode } from 'react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@shared/components/ui/dialog';

/**
 * A form that opens over the page it extends, so the page itself stays a
 * quiet summary. The form closes itself through `close` once the server
 * accepts it; validation errors stay inside the dialog beside their fields.
 */
export default function FormModal({
    trigger,
    title,
    description,
    children,
}: {
    trigger: ReactNode;
    title: string;
    description?: string;
    children: (close: () => void) => ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description !== undefined && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                {children(() => setOpen(false))}
            </DialogContent>
        </Dialog>
    );
}
