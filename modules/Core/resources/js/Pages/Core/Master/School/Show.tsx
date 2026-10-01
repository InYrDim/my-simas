import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@shared/components/ui/field';
import { DefinitionList, Panel } from '@shared/components/page-parts';

import { InputField, SelectField } from '../../../../Components/FormField';
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
        >
            <form
                key={school.level}
                onSubmit={(event) => event.preventDefault()}
                className="flex flex-col gap-6"
            >
                <Panel title="Identitas">
                    <FieldGroup>
                        <InputField
                            label="Nama sekolah"
                            id="name"
                            defaultValue={school.name}
                        />
                        <div className="grid gap-6 sm:grid-cols-2">
                            <InputField
                                label="NPSN"
                                id="npsn"
                                defaultValue={school.npsn}
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

                        <FieldSet>
                            <FieldLegend variant="label">Jenjang</FieldLegend>
                            <div className="flex flex-wrap gap-x-6 gap-y-3">
                                {school.levelOptions.map((option) => (
                                    <Field
                                        key={option.value}
                                        orientation="horizontal"
                                        className="w-auto"
                                    >
                                        <Checkbox
                                            id={`level-${option.value}`}
                                            defaultChecked={
                                                option.value === school.level
                                            }
                                        />
                                        <FieldLabel
                                            htmlFor={`level-${option.value}`}
                                            className="font-normal"
                                        >
                                            {option.label}
                                        </FieldLabel>
                                    </Field>
                                ))}
                            </div>
                        </FieldSet>
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
                            id="headmasterNip"
                            defaultValue={school.headmasterNip}
                        />
                    </div>
                </Panel>

                <Panel title="Dikelola platform">
                    <DefinitionList
                        rows={[
                            ['Kode sekolah', <code key="c">{school.code}</code>],
                            ['Zona waktu', school.timezone],
                        ]}
                    />
                </Panel>

                <div className="flex justify-end">
                    <Button type="submit">Simpan perubahan</Button>
                </div>
            </form>
        </MasterPage>
    );
}
