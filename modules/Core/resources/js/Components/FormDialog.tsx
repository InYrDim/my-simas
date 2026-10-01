import { useState } from 'react';
import type { ReactNode } from 'react';

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

import MasterForm from './MasterForm';
import type { FormRoute } from './MasterForm';

/**
 * Create/edit dialog: submits to `route` and closes once the server
 * accepts it; validation errors stay inline beside their fields. Without
 * a `route` (pages still on mock data) "Simpan" only closes the dialog.
 */
export default function FormDialog({
    trigger,
    title,
    description,
    route,
    submitLabel = 'Simpan',
    children,
}: {
    trigger: ReactNode;
    title: string;
    description?: string;
    route?: FormRoute;
    submitLabel?: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    const body = (processing: boolean) => (
        <>
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
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </DialogFooter>
        </>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                {route === undefined ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            setOpen(false);
                        }}
                        className="flex flex-col gap-6"
                    >
                        {body(false)}
                    </form>
                ) : (
                    <MasterForm
                        route={route}
                        className="flex flex-col gap-6"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing }) => body(processing)}
                    </MasterForm>
                )}
            </DialogContent>
        </Dialog>
    );
}
