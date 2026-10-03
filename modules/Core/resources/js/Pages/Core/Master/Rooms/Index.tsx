import { PlusIcon } from 'lucide-react';

import {
    destroy,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/RoomController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
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
                <InputField
                    label="Kapasitas"
                    id="capacity"
                    type="number"
                    defaultValue={room?.capacity}
                />
            </div>
            <InputField
                label="Nama ruangan"
                id="name"
                defaultValue={room?.name}
            />
            <SelectField
                label="Jenis"
                id="type"
                options={[
                    'Kelas',
                    'Laboratorium',
                    'Aula',
                    'Perpustakaan',
                    'Lainnya',
                ]}
                defaultValue={room?.type}
            />
            <SelectField
                label="Status"
                id="status"
                options={[
                    { value: 'active', label: 'Aktif' },
                    { value: 'maintenance', label: 'Perbaikan' },
                ]}
                defaultValue={room?.status}
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
            mock={false}
            actions={
                <FormDialog
                    route={store()}
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
            {rooms.length === 0 ? (
                <EmptyState>Belum ada ruangan.</EmptyState>
            ) : (
                <DataTable
                    head={['Kode', 'Nama', 'Jenis', 'Kapasitas', 'Status', '']}
                >
                    {rooms.map((room) => (
                        <TableRow key={room.id}>
                            <TableCell className="font-mono text-xs">
                                {room.code}
                            </TableCell>
                            <TableCell className="font-medium">
                                {room.name}
                            </TableCell>
                            <TableCell>{room.type}</TableCell>
                            <TableCell>{room.capacity}</TableCell>
                            <TableCell>
                                <StatusBadge status={room.status} />
                            </TableCell>
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    <FormDialog
                                        route={update(room.id)}
                                        title={`Ubah ${room.name}`}
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                Ubah
                                            </Button>
                                        }
                                    >
                                        <RoomForm room={room} />
                                    </FormDialog>
                                    <ConfirmAction
                                        route={destroy(room.id)}
                                        title={`Hapus ${room.name}?`}
                                        description="Ruangan yang masih dipakai kelas tidak bisa dihapus."
                                        confirmLabel="Hapus"
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                Hapus
                                            </Button>
                                        }
                                    />
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
