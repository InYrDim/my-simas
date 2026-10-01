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

export interface AcademicYear {
    id: number;
    name: string;
    curriculum: string;
    start: string;
    end: string;
    status: 'active' | 'draft' | 'archived';
    semesters: Semester[];
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
    major: string | null;
    homeroom: string;
    homeroomId: number;
    room: string;
    students: number;
    yearId: number;
}

export interface Subject {
    id: number;
    name: string;
    code: string;
    group: string;
    kkm: number;
    grades: string;
    teacher: string;
}

export interface Assignment {
    teacher: string;
    subject: string;
    class: string;
    hours: number;
}

export interface Teacher {
    id: number;
    name: string;
    nip: string;
    nuptk: string;
    employment: string;
    duty: string;
    hasAccount: boolean;
    email: string | null;
}

export interface Student {
    id: number;
    name: string;
    nis: string;
    nisn: string;
    gender: 'L' | 'P';
    birth: string;
    class: string | null;
    classId: number | null;
    status: 'active' | 'graduated' | 'transferred' | 'left';
    guardian: string;
    guardianPhone: string;
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
    order: number | null;
    start: string;
    end: string;
    type: string;
}

export interface PeriodDay {
    day: string;
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
    coach: string;
    members: number;
    memberList?: Student[];
}
