import type { Extracurricular, Teacher } from '../types/master';
import { InputField, SelectField } from './FormField';

/** Fields of the create/edit dialog of an extracurricular activity. */
export default function ExtracurricularForm({
    item,
    teachers,
}: {
    item?: Extracurricular;
    teachers: Teacher[];
}) {
    return (
        <>
            <InputField label="Nama kegiatan" id="name" defaultValue={item?.name} />
            <SelectField
                label="Pembina"
                id="coach_teacher_id"
                optionalLabel="Belum ditentukan"
                options={teachers.map((teacher) => ({
                    value: String(teacher.id),
                    label: teacher.name,
                }))}
                defaultValue={item?.coachId == null ? undefined : String(item.coachId)}
            />
            <InputField
                label="Jadwal"
                id="schedule"
                placeholder="Jumat 14.30–16.00"
                defaultValue={item?.schedule}
            />
            <SelectField
                label="Jenis"
                id="kind"
                options={['Wajib', 'Pilihan']}
                defaultValue={item?.kind}
            />
        </>
    );
}
