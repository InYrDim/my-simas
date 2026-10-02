import { useState } from 'react';
import type { MouseEvent } from 'react';

import { update } from '@/actions/Modules/Core/App/Http/Controllers/SchoolProfileController';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@shared/components/ui/alert-dialog';
import { Button } from '@shared/components/ui/button';
import { FieldGroup } from '@shared/components/ui/field';
import { DefinitionList, Panel } from '@shared/components/page-parts';

import { InputField, SelectField } from '../../../../Components/FormField';
import MasterForm from '../../../../Components/MasterForm';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary } from '../../../../types/master';

/**
 * Profil Sekolah: identity, address/contact and headmaster in one editable
 * page. School code and timezone are Platform's — shown read-only.
 * The jenjang asks for confirmation when changed, and is locked once the
 * school has a class.
 */
export default function SchoolShow({
    school,
    levelLocked,
}: {
    school: SchoolSummary;
    levelLocked: boolean;
}) {
    const [levelChange, setLevelChange] = useState<{
        form: HTMLFormElement;
        label: string;
    } | null>(null);

    function confirmLevelChange(event: MouseEvent<HTMLButtonElement>) {
        const form = event.currentTarget.form;
        const level = form === null ? null : new FormData(form).get('level');

        if (
            form === null ||
            typeof level !== 'string' ||
            level === school.level
        ) {
            return;
        }

        event.preventDefault();
        setLevelChange({
            form,
            label:
                school.levelOptions.find((option) => option.value === level)
                    ?.label ?? level,
        });
    }

    return (
        <MasterPage
            school={school}
            title="Profil Sekolah"
            description="Identitas resmi sekolah yang dipakai di seluruh modul."
            width="max-w-4xl"
            mock={false}
        >
            <MasterForm route={update()} className="flex flex-col gap-6">
                <Panel title="Identitas">
                    <FieldGroup>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <InputField
                                label="NPSN"
                                id="npsn"
                                defaultValue={school.npsn}
                            />
                            <SelectField
                                label="Jenjang"
                                id="level"
                                options={school.levelOptions}
                                defaultValue={school.level}
                                disabled={levelLocked}
                                hint={
                                    levelLocked
                                        ? 'Jenjang terkunci karena sekolah sudah memiliki kelas.'
                                        : undefined
                                }
                            />
                            {levelLocked && (
                                <input
                                    type="hidden"
                                    name="level"
                                    value={school.level}
                                />
                            )}
                            <SelectField
                                label="Status"
                                id="ownership"
                                options={['Negeri', 'Swasta']}
                                defaultValue={school.ownership}
                            />
                            <SelectField
                                label="Akreditasi"
                                id="accreditation"
                                options={['A', 'B', 'C', 'Belum terakreditasi']}
                                defaultValue={school.accreditation}
                            />
                        </div>
                    </FieldGroup>
                </Panel>

                <Panel title="Alamat & kontak">
                    <FieldGroup>
                        <InputField
                            label="Alamat"
                            id="address"
                            defaultValue={school.address}
                        />
                        <div className="grid gap-6 sm:grid-cols-2">
                            <InputField
                                label="Telepon"
                                id="phone"
                                defaultValue={school.phone}
                            />
                            <InputField
                                label="Email"
                                id="email"
                                type="email"
                                defaultValue={school.email}
                            />
                        </div>
                    </FieldGroup>
                </Panel>

                <Panel title="Kepala sekolah">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <InputField
                            label="Nama"
                            id="headmaster"
                            defaultValue={school.headmaster}
                        />
                        <InputField
                            label="NIP"
                            id="headmaster_nip"
                            defaultValue={school.headmasterNip}
                        />
                    </div>
                </Panel>

                <Panel title="Dikelola platform">
                    <DefinitionList
                        rows={[
                            ['Nama sekolah', school.name],
                            [
                                'Kode sekolah',
                                <code key="c">{school.code}</code>,
                            ],
                            ['Zona waktu', school.timezone],
                        ]}
                    />
                </Panel>

                <div className="flex justify-end">
                    <Button type="submit" onClick={confirmLevelChange}>
                        Simpan perubahan
                    </Button>
                </div>
            </MasterForm>

            <AlertDialog
                open={levelChange !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setLevelChange(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Ubah jenjang sekolah?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Jenjang akan diubah dari {school.levelLabel} menjadi{' '}
                            {levelChange?.label}. Daftar tingkat disusun ulang
                            mengikuti jenjang baru, dan jenjang tidak bisa
                            diubah lagi setelah kelas pertama dibuat.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => levelChange?.form.requestSubmit()}
                        >
                            Ya, ubah jenjang
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MasterPage>
    );
}
