import type { ClassOption, Student } from '../types/master';
import { InputField, SelectField } from './FormField';

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'graduated', label: 'Lulus' },
    { value: 'transferred', label: 'Pindah' },
    { value: 'left', label: 'Keluar' },
];

/**
 * Fields of the create/edit dialog of a student. `classes` are the classes
 * of the active academic year; only an active student has a class.
 */
export default function StudentForm({
    student,
    classes,
}: {
    student?: Student;
    classes: ClassOption[];
}) {
    return (
        <>
            <InputField label="Nama lengkap" id="name" defaultValue={student?.name} />
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField label="NIS" id="nis" defaultValue={student?.nis} />
                <InputField label="NISN" id="nisn" defaultValue={student?.nisn ?? ''} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <SelectField
                    label="Jenis kelamin"
                    id="gender"
                    options={[
                        { value: 'L', label: 'Laki-laki' },
                        { value: 'P', label: 'Perempuan' },
                    ]}
                    defaultValue={student?.gender}
                />
                <InputField
                    label="Tanggal lahir"
                    id="birth_date"
                    type="date"
                    defaultValue={student?.birth ?? ''}
                />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                    label="Nama wali"
                    id="guardian_name"
                    defaultValue={student?.guardian ?? ''}
                />
                <InputField
                    label="Telepon wali"
                    id="guardian_phone"
                    defaultValue={student?.guardianPhone ?? ''}
                />
            </div>
            <SelectField
                label="Kelas (tahun ajaran aktif)"
                id="class_id"
                optionalLabel="Belum ditempatkan"
                options={classes.map((item) => ({
                    value: String(item.id),
                    label: item.name,
                }))}
                defaultValue={student?.classId == null ? undefined : String(student.classId)}
            />
            {student !== undefined && (
                <SelectField
                    label="Status"
                    id="status"
                    options={statusOptions}
                    defaultValue={student.status}
                />
            )}
        </>
    );
}
