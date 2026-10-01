import { PlusIcon } from 'lucide-react';

import { DataTable } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type { Room, SchoolSummary } from '../../../../types/master';

function RoomForm({ room }: { room?: Room }) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField label="Kode" id="code" defaultValue={room?.code} />
                <InputField label="Kapasitas" id="capacity" type="number" defaultValue={room?.capacity} />
            </div>
            <InputField label="Nama ruangan" id="name" defaultValue={room?.name} />
            <SelectField
                label="Jenis"
                id="type"
                options={['Kelas', 'Laboratorium', 'Aula', 'Perpustakaan', 'Lainnya']}
                defaultValue={room?.type}
            />
        </>
    );
}

/** Ruangan: classrooms, labs and halls with capacity and condition. */
export default function RoomsIndex({
    school,
    rooms,
}: {
    school: SchoolSummary;
    rooms: Room[];
}) {
    return (
        <MasterPage
            school={school}
            title="Ruangan"
            description="Ruang kelas, laboratorium, dan fasilitas lain."
            width="max-w-5xl"
            actions={
                <FormDialog
                    title="Tambah ruangan"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah ruangan
                        </Button>
                    }
                >
                    <RoomForm />
                </FormDialog>
            }
        >
            <DataTable head={['Kode', 'Nama', 'Jenis', 'Kapasitas', 'Status', '']}>
                {rooms.map((room) => (
                    <TableRow key={room.id}>
                        <TableCell className="font-mono text-xs">{room.code}</TableCell>
                        <TableCell className="font-medium">{room.name}</TableCell>
                        <TableCell>{room.type}</TableCell>
                        <TableCell>{room.capacity}</TableCell>
                        <TableCell>
                            <StatusBadge status={room.status} />
                        </TableCell>
                        <TableCell className="text-right">
                            <FormDialog
                                title={`Ubah ${room.name}`}
                                trigger={
                                    <Button variant="ghost" size="sm">
                                        Ubah
                                    </Button>
                                }
                            >
                                <RoomForm room={room} />
                            </FormDialog>
                        </TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </MasterPage>
    );
}
