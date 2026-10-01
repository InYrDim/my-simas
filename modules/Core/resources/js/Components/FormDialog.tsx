import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@shared/components/ui/dialog';
import { FieldGroup } from '@shared/components/ui/field';

/**
 * Create/edit dialog for the mockup pages: renders the real fields, but
 * "Simpan" only closes the dialog — nothing is stored until the DB phase.
 */
export default function FormDialog({
    trigger,
    title,
    description,
    submitLabel = 'Simpan',
    children,
}: {
    trigger: ReactNode;
    title: string;
    description?: string;
    submitLabel?: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();
        setOpen(false);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-6">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description !== undefined && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>

                    <FieldGroup>{children}</FieldGroup>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit">{submitLabel}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
