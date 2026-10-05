import { EmptyState } from '@shared/components/page-parts';

import AttendancePage from '../../Components/AttendancePage';

/**
 * "Saya" › Absensi Saya for a teacher: the entry stays on the sidebar
 * while its pages are rebuilt. Attendance per lesson lives under Kelas
 * Saya.
 */
export default function Soon() {
    return (
        <AttendancePage title="Absensi Saya" width="max-w-xl">
            <EmptyState>
                Segera hadir. Absensi jam pelajaran Anda ada di menu Kelas Saya.
            </EmptyState>
        </AttendancePage>
    );
}
