import { update } from '@/actions/Modules/Core/App/Http/Controllers/SchoolProfileController';

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
 */
export default function SchoolShow({ school }: { school: SchoolSummary }) {
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
                            />
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
                            ['Kode sekolah', <code key="c">{school.code}</code>],
                            ['Zona waktu', school.timezone],
                        ]}
                    />
                </Panel>

                <div className="flex justify-end">
                    <Button type="submit">Simpan perubahan</Button>
                </div>
            </MasterForm>
        </MasterPage>
    );
}
