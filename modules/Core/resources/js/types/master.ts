/** Page prop shapes for the master-data mockup pages. */

export type LevelKey = 'sd' | 'smp' | 'sma' | 'smk';

export interface SchoolSummary {
    name: string;
    code: string;
    timezone: string;
    npsn: string;
    level: LevelKey;
    levelLabel: string;
    levelOptions: { value: string; label: string }[];
    ownership: string;
    accreditation: string;
    address: string;
    phone: string;
    email: string;
    headmaster: string;
    headmasterNip: string;
    hasMajors: boolean;
    homeroomLabel: string;
}

export interface Semester {
    name: string;
    start: string;
    end: string;
}

/** One semester across all years, as listed on the Semester page. */
export interface SemesterRow {
    id: number;
    year: string;
    name: string;
    start: string;
    end: string;
    weeks: number;
    status: 'current' | 'upcoming' | 'finished';
    /** Week number of the running semester; null for the others. */
    week: number | null;
}

export interface AcademicYear {
    id: number;
    name: string;
    curriculum: string;
    start: string;
    end: string;
    status: 'active' | 'draft' | 'archived';
    semesters: Semester[];
}

/** Pre-filled values for the "Tambah tahun ajaran" form. */
export interface AcademicYearSuggestion {
    name: string;
    curriculum: string;
    start_date: string;
    end_date: string;
}

export interface Grade {
    id: number;
    name: string;
    order: number;
    classes: number;
}

export interface Major {
    id: number;
    code: string;
    name: string;
    kind: string;
    concentrations: string[];
}

export interface ClassGroup {
    id: number;
    name: string;
    gradeId: number;
    grade: string;
    majorId: number | null;
    major: string | null;
    homeroom: string | null;
    homeroomId: number | null;
    roomId: number | null;
    room: string | null;
    students: number;
    yearId: number;
    year: string;
}

export interface Subject {
    id: number;
    name: string;
    code: string;
    group: string;
    kkm: number;
    grades: string;
}

export interface Assignment {
    id: number;
    classId: number;
    class: string;
    subjectId: number;
    subject: string;
    teacherId: number;
    teacher: string;
    hours: number;
}

/** Paging state of a server-side list. */
export interface Pagination {
    page: number;
    lastPage: number;
    total: number;
    from: number;
    to: number;
}

/** The login account linked to a student or a teacher. */
export interface LinkedAccount {
    username: string | null;
    email: string | null;
    active: boolean;
    mustChangePassword: boolean;
}

/** What the signed-in user may do with linked accounts. */
export interface AccountAbilities {
    create: boolean;
    reset: boolean;
}

/** A class as offered in a select. */
export interface ClassOption {
    id: number;
    name: string;
}

export interface Teacher {
    id: number;
    name: string;
    nip: string | null;
    nuptk: string | null;
    employment: string;
    duty: string;
    hasAccount: boolean;
    email: string | null;
}

export interface Student {
    id: number;
    name: string;
    nis: string;
    nisn: string | null;
    gender: 'L' | 'P';
    birth: string | null;
    class: string | null;
    classId: number | null;
    status: 'active' | 'graduated' | 'transferred' | 'left';
    guardian: string | null;
    guardianPhone: string | null;
    hasAccount: boolean;
}

export interface Room {
    id: number;
    code: string;
    name: string;
    type: string;
    capacity: number;
    status: 'active' | 'maintenance';
}

export interface PeriodSlot {
    id: number;
    order: number | null;
    start: string;
    end: string;
    type: string;
}

export interface PeriodDay {
    day: string;
    dayNumber: number;
    slots: PeriodSlot[];
}

export type EventCategory = 'holiday' | 'exam' | 'activity';

export interface CalendarEvent {
    id: number;
    date: string;
    endDate: string | null;
    title: string;
    category: EventCategory;
}

export interface Extracurricular {
    id: number;
    name: string;
    schedule: string;
    kind: string;
    coach: string | null;
    coachId: number | null;
    members: number;
    memberList?: Student[];
}
