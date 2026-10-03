import type { Teacher } from '../types/master';
import { InputField, SelectField } from './FormField';

/** Fields of the create/edit dialog of a teacher or staff member. */
export default function TeacherForm({ teacher }: { teacher?: Teacher }) {
    return (
        <>
            <InputField
                label="Nama lengkap"
                id="name"
                defaultValue={teacher?.name}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                    label="NIP"
                    id="nip"
                    defaultValue={teacher?.nip ?? ''}
                />
                <InputField
                    label="NUPTK"
                    id="nuptk"
                    defaultValue={teacher?.nuptk ?? ''}
                />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <SelectField
                    label="Status kepegawaian"
                    id="employment"
                    options={['PNS', 'GTY', 'GTT', 'Honorer']}
                    defaultValue={teacher?.employment}
                />
                <SelectField
                    label="Tugas"
                    id="duty"
                    options={[
                        'Guru Mapel',
                        'Tenaga Kependidikan',
                        'Kepala Sekolah',
                    ]}
                    defaultValue={teacher?.duty}
                />
            </div>
            <InputField
                label="Email"
                id="email"
                type="email"
                defaultValue={teacher?.email ?? ''}
            />
        </>
    );
}
