import type { CSSProperties, ReactNode } from 'react';
import { useState } from 'react';

import { Bubble } from '@shared/components/lembar/parts';

import { modules } from './content';

/**
 * The landing hero: one school building in isometric line drawing. Every
 * room is a module — switching it on lights its windows and its name
 * sign. Lines carry example school codes to the door (each school its own
 * workspace), the default roles stand as tiles in the yard, and the flag
 * is up. Pure geometry computed here, no picture.
 */

type Point = [number, number];
type Tone = { top: string; right: string; left: string };

const COS = Math.cos(Math.PI / 6);

function project(x: number, y: number, z: number): Point {
    return [(x - y) * COS, (x + y) * 0.5 - z];
}

function pts(points: Point[]): string {
    return points.map(([x, y]) => `${x},${y}`).join(' ');
}

/** Text on a facade (plane x = const), reading along −y, baseline at z. */
function onFacade(x: number, y: number, z: number): string {
    const [sx, sy] = project(x, y, z);

    return `matrix(${COS} -0.5 0 1 ${sx} ${sy})`;
}

/** Text or shapes lying flat at height z, running along −y. */
function onFloor(x: number, y: number, z = 0): string {
    const [sx, sy] = project(x, y, z);

    return `matrix(${COS} -0.5 ${COS} 0.5 ${sx} ${sy})`;
}

const EDGE = {
    stroke: '#27313c',
    strokeOpacity: 0.5,
    strokeWidth: 1,
    strokeLinejoin: 'round' as const,
    vectorEffect: 'non-scaling-stroke' as const,
};

const WALL: Tone = { top: '#f3f8fb', right: '#ffffff', left: '#e6f1f8' };
const PLINTH: Tone = { top: '#5b6674', right: '#3a4552', left: '#2c3540' };
const FASCIA: Tone = { top: '#7fd0f4', right: '#09a8ed', left: '#0791cc' };
const TILE: Tone = { top: '#ffffff', right: '#e2f0f9', left: '#c9e2f2' };
const POLE: Tone = { top: '#5b6674', right: '#4a5664', left: '#3a4552' };
const ROOF = { front: '#7d98ab', back: '#a3b8c7', gable: '#e6f1f8' };
const LIT = '#bfe6fb';
const DARK = '#dde6ed';

/** The three faces of a box from (x, y, z), size w × d × h. */
function Block({
    x,
    y,
    z,
    w,
    d,
    h,
    tone,
    children,
}: {
    x: number;
    y: number;
    z: number;
    w: number;
    d: number;
    h: number;
    tone: Tone;
    children?: ReactNode;
}) {
    const top = z + h;

    return (
        <g>
            <polygon
                {...EDGE}
                fill={tone.left}
                points={pts([
                    project(x, y + d, z),
                    project(x + w, y + d, z),
                    project(x + w, y + d, top),
                    project(x, y + d, top),
                ])}
            />
            <polygon
                {...EDGE}
                fill={tone.right}
                points={pts([
                    project(x + w, y, z),
                    project(x + w, y + d, z),
                    project(x + w, y + d, top),
                    project(x + w, y, top),
                ])}
            />
            <polygon
                {...EDGE}
                fill={tone.top}
                points={pts([
                    project(x, y, top),
                    project(x + w, y, top),
                    project(x + w, y + d, top),
                    project(x, y + d, top),
                ])}
            />
            {children}
        </g>
    );
}

/** A rectangle drawn on the facade plane x = `x`. */
function FacadeRect({
    x,
    y0,
    y1,
    z0,
    z1,
    fill,
    className,
    style,
}: {
    x: number;
    y0: number;
    y1: number;
    z0: number;
    z1: number;
    fill: string;
    className?: string;
    style?: CSSProperties;
}) {
    return (
        <polygon
            {...EDGE}
            fill={fill}
            className={className}
            style={style}
            points={pts([
                project(x, y0, z0),
                project(x, y1, z0),
                project(x, y1, z1),
                project(x, y0, z1),
            ])}
        />
    );
}

/** A rectangle drawn on the side wall plane y = `y`. */
function SideRect({
    y,
    x0,
    x1,
    z0,
    z1,
    fill,
}: {
    y: number;
    x0: number;
    x1: number;
    z0: number;
    z1: number;
    fill: string;
}) {
    return (
        <polygon
            {...EDGE}
            fill={fill}
            points={pts([
                project(x0, y, z0),
                project(x1, y, z0),
                project(x1, y, z1),
                project(x0, y, z1),
            ])}
        />
    );
}

/** Building: footprint x 0..W (depth), y 0..D (the long facade faces x = W). */
const W = 28;
const D = 66;
const PLINTH_TOP = 2;
const FLOOR_H = 12;
const SLAB = 1.5;
const FLOOR_1 = PLINTH_TOP;
const FLOOR_2 = FLOOR_1 + FLOOR_H + SLAB;
const FASCIA_Z = FLOOR_2 + FLOOR_H;
const ROOF_Z = FASCIA_Z + 5.5;
const RIDGE = 11;

/** Rooms on the facade: y range and floor. The left of the screen is high y. */
const ROOMS: Record<
    string,
    { y0: number; y1: number; z: number; door?: boolean }
> = {
    absensi: { y0: 44, y1: 66, z: FLOOR_1, door: true },
    ppdb: { y0: 22, y1: 44, z: FLOOR_1, door: true },
    induk: { y0: 0, y1: 22, z: FLOOR_1 },
    akademik: { y0: 44, y1: 66, z: FLOOR_2 },
    laporan: { y0: 22, y1: 44, z: FLOOR_2 },
    whatsapp: { y0: 0, y1: 22, z: FLOOR_2 },
};

const SCHOOLS = [
    { code: '123456', x: 6, color: '#27313c' },
    { code: 'sekolah-a', x: 14, color: '#09a8ed' },
    { code: 'sekolah-b', x: 22, color: '#0b6f9e' },
];

const ROLES = ['Guru', 'Staf/TU', 'Admin', 'Siswa'];

function SchoolLine({
    code,
    x,
    color,
}: {
    code: string;
    x: number;
    color: string;
}) {
    const start = project(x, 112, 0);
    const end = project(x, D + 1, 0);

    return (
        <g>
            <path
                d={`M ${start.join(',')} L ${end.join(',')}`}
                fill="none"
                stroke={color}
                strokeWidth={1.5}
                strokeLinecap="round"
                vectorEffect="non-scaling-stroke"
            />
            <g transform={onFloor(x, 100)}>
                <rect
                    x={0}
                    y={-2.6}
                    width={code.length * 2.05 + 6}
                    height={5.2}
                    rx={2.6}
                    fill="#ffffff"
                    stroke={color}
                    strokeWidth={1}
                    vectorEffect="non-scaling-stroke"
                />
                <circle cx={2.7} cy={0} r={1} fill={color} />
                <text
                    x={4.6}
                    y={1.05}
                    fontSize={3}
                    fill="#27313c"
                    className="font-code"
                >
                    {code}
                </text>
            </g>
            <g transform={onFloor(x, 106)}>
                <circle
                    r={2.4}
                    fill="#ffffff"
                    stroke={color}
                    strokeWidth={1.25}
                    vectorEffect="non-scaling-stroke"
                />
                <circle r={1.1} fill={color} />
            </g>
        </g>
    );
}

/** One room on the facade: windows (or a door), and its name sign. */
function Room({
    room,
    on,
    delay,
}: {
    room: { y0: number; y1: number; z: number; door?: boolean };
    on: boolean;
    delay?: number;
}) {
    const { y0, y1, z, door } = room;
    const glass = on ? LIT : DARK;
    const lamp = on && delay !== undefined ? 'stack-in' : undefined;
    const style = { '--delay': `${delay ?? 0}ms` } as CSSProperties;
    const openings: Array<{ a: number; b: number; low: number; high: number }> =
        door
            ? [
                  { a: y1 - 4, b: y1 - 10, low: z + 2.6, high: z + 7.4 },
                  { a: y0 + 9, b: y0 + 4, low: z, high: z + 7.6 },
              ]
            : [
                  { a: y1 - 4, b: y1 - 10, low: z + 2.6, high: z + 7.4 },
                  { a: y0 + 10, b: y0 + 4, low: z + 2.6, high: z + 7.4 },
              ];

    return (
        <g>
            {openings.map((opening, index) => (
                <FacadeRect
                    key={index}
                    x={W}
                    y0={opening.b}
                    y1={opening.a}
                    z0={opening.low}
                    z1={opening.high}
                    fill={glass}
                    className={`transition-[fill] duration-300 ${lamp ?? ''}`}
                    style={style}
                />
            ))}
        </g>
    );
}

export function SchoolBuilding() {
    const [enabled, setEnabled] = useState<Set<string>>(
        () => new Set(['absensi', 'ppdb', 'induk']),
    );
    // Once the visitor switches a module it changes at once: the staggered
    // lights belong to the page-load moment only.
    const [touched, setTouched] = useState(false);

    function toggle(key: string) {
        setTouched(true);
        setEnabled((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    }

    const enabledNames = modules
        .filter((module) => enabled.has(module.key))
        .map((module) => module.name);

    const view = { x: -100, y: -48, width: 172, height: 116 };
    const roofFront = [
        project(W + 0.8, -2, ROOF_Z),
        project(W + 0.8, D + 2, ROOF_Z),
        project(W / 2, D + 2, ROOF_Z + RIDGE),
        project(W / 2, -2, ROOF_Z + RIDGE),
    ];
    const roofBack = [
        project(-2, -2, ROOF_Z),
        project(-2, D + 2, ROOF_Z),
        project(W / 2, D + 2, ROOF_Z + RIDGE),
        project(W / 2, -2, ROOF_Z + RIDGE),
    ];
    const gable = [
        project(-2, D + 2, ROOF_Z),
        project(W + 0.8, D + 2, ROOF_Z),
        project(W / 2, D + 2, ROOF_Z + RIDGE),
    ];

    return (
        <figure className="flex flex-col gap-5">
            <div className="relative">
                <svg
                    aria-hidden
                    viewBox={`${view.x} ${view.y} ${view.width} ${view.height}`}
                    className="block h-auto w-full overflow-visible"
                >
                    <defs>
                        <radialGradient id="school-grid-fade" cx="50%" cy="58%">
                            <stop
                                offset="35%"
                                stopColor="#fff"
                                stopOpacity={1}
                            />
                            <stop
                                offset="100%"
                                stopColor="#fff"
                                stopOpacity={0}
                            />
                        </radialGradient>
                        <mask id="school-grid-mask">
                            <rect
                                x={view.x}
                                y={view.y}
                                width={view.width}
                                height={view.height}
                                fill="url(#school-grid-fade)"
                            />
                        </mask>
                    </defs>

                    {/* The drawing board: a faint isometric grid. */}
                    <g mask="url(#school-grid-mask)" stroke="#d6e4ee">
                        {Array.from({ length: 24 }, (_, index) => {
                            const at = -40 + index * 8;
                            const [ax1, ay1] = project(at, -40, 0);
                            const [ax2, ay2] = project(at, 150, 0);
                            const [bx1, by1] = project(-40, at, 0);
                            const [bx2, by2] = project(140, at, 0);

                            return (
                                <g key={index}>
                                    <line
                                        vectorEffect="non-scaling-stroke"
                                        x1={ax1}
                                        y1={ay1}
                                        x2={ax2}
                                        y2={ay2}
                                    />
                                    <line
                                        vectorEffect="non-scaling-stroke"
                                        x1={bx1}
                                        y1={by1}
                                        x2={bx2}
                                        y2={by2}
                                    />
                                </g>
                            );
                        })}
                    </g>

                    {/* A soft footprint under the building. */}
                    <polygon
                        fill="#27313c"
                        fillOpacity={0.06}
                        points={pts([
                            project(4, 4, 0),
                            project(W + 10, 4, 0),
                            project(W + 10, D + 4, 0),
                            project(4, D + 4, 0),
                        ])}
                    />

                    {SCHOOLS.map((school) => (
                        <SchoolLine key={school.code} {...school} />
                    ))}

                    {/* Plinth: each school its own workspace. */}
                    <Block
                        x={0}
                        y={0}
                        z={0}
                        w={W + 4}
                        d={D}
                        h={PLINTH_TOP}
                        tone={PLINTH}
                    />
                    <text
                        transform={onFacade(W + 4, D - 3, 0.55)}
                        fontSize={1.5}
                        fontWeight={700}
                        letterSpacing={0.3}
                        fill="#ffffff"
                    >
                        RUANG KERJA PER SEKOLAH
                    </text>

                    {/* Ground floor walls, rooms, columns. */}
                    <Block
                        x={0}
                        y={0}
                        z={FLOOR_1}
                        w={W}
                        d={D}
                        h={FLOOR_H}
                        tone={WALL}
                    />
                    <SideRect
                        y={D}
                        x0={6}
                        x1={12}
                        z0={FLOOR_1 + 3.5}
                        z1={FLOOR_1 + 8.5}
                        fill={DARK}
                    />
                    <SideRect
                        y={D}
                        x0={16}
                        x1={22}
                        z0={FLOOR_1 + 3.5}
                        z1={FLOOR_1 + 8.5}
                        fill={DARK}
                    />

                    {/* Upper floor. */}
                    <Block
                        x={0}
                        y={0}
                        z={FLOOR_1 + FLOOR_H}
                        w={W + 2.6}
                        d={D}
                        h={SLAB}
                        tone={WALL}
                    />
                    <Block
                        x={0}
                        y={0}
                        z={FLOOR_2}
                        w={W}
                        d={D}
                        h={FLOOR_H}
                        tone={WALL}
                    />
                    <SideRect
                        y={D}
                        x0={6}
                        x1={12}
                        z0={FLOOR_2 + 3.5}
                        z1={FLOOR_2 + 8.5}
                        fill={DARK}
                    />
                    <SideRect
                        y={D}
                        x0={16}
                        x1={22}
                        z0={FLOOR_2 + 3.5}
                        z1={FLOOR_2 + 8.5}
                        fill={DARK}
                    />

                    {modules.map((module, index) => {
                        const room = ROOMS[module.key];

                        return room === undefined ? null : (
                            <Room
                                key={module.key}
                                room={room}
                                on={enabled.has(module.key)}
                                delay={touched ? undefined : 250 + index * 140}
                            />
                        );
                    })}

                    {/* Columns at the room boundaries, both floors. */}
                    {[0, 22, 44, 66].map((y) => (
                        <g key={y}>
                            <Block
                                x={W}
                                y={Math.min(Math.max(y - 0.8, 0), D - 1.6)}
                                z={FLOOR_1}
                                w={1.6}
                                d={1.6}
                                h={FLOOR_H}
                                tone={WALL}
                            />
                        </g>
                    ))}

                    {/* Upper corridor railing. */}
                    <Block
                        x={W + 1.6}
                        y={0}
                        z={FLOOR_2}
                        w={1}
                        d={D}
                        h={2.2}
                        tone={WALL}
                    />

                    {/* Fascia: the school's name band. */}
                    <Block
                        x={0}
                        y={0}
                        z={FASCIA_Z}
                        w={W}
                        d={D}
                        h={ROOF_Z - FASCIA_Z}
                        tone={FASCIA}
                    />
                    <text
                        transform={onFacade(W, D - 4, FASCIA_Z + 1.2)}
                        fontSize={4.4}
                        fontWeight={800}
                        fill="#ffffff"
                        style={{ fontStretch: '118%' }}
                    >
                        SIMAS
                    </text>

                    {/* Pitched roof, ridge along the building. */}
                    <polygon
                        {...EDGE}
                        fill={ROOF.back}
                        points={pts(roofBack)}
                    />
                    <polygon {...EDGE} fill={ROOF.gable} points={pts(gable)} />
                    <polygon
                        {...EDGE}
                        fill={ROOF.front}
                        points={pts(roofFront)}
                    />

                    {/* The default roles, standing in the yard. */}
                    {ROLES.map((role, index) => {
                        const y = 2 + index * 15.5;

                        return (
                            <Block
                                key={role}
                                x={40}
                                y={y}
                                z={0}
                                w={9}
                                d={13}
                                h={1.5}
                                tone={TILE}
                            >
                                <text
                                    transform={onFloor(45.4, y + 13 - 1.6, 1.5)}
                                    fontSize={2.7}
                                    fontWeight={600}
                                    fill="#27313c"
                                >
                                    {role}
                                </text>
                            </Block>
                        );
                    })}

                    {/* The flag in the yard: merah putih. */}
                    <Block
                        x={56}
                        y={-10}
                        z={0}
                        w={1}
                        d={1}
                        h={40}
                        tone={POLE}
                    />
                    <SideRect
                        y={-9.5}
                        x0={57}
                        x1={68}
                        z0={35}
                        z1={39.5}
                        fill="#d9534a"
                    />
                    <SideRect
                        y={-9.5}
                        x0={57}
                        x1={68}
                        z0={30.5}
                        z1={35}
                        fill="#ffffff"
                    />
                </svg>

                {modules.map((module, index) => {
                    const room = ROOMS[module.key];

                    if (room === undefined) {
                        return null;
                    }

                    const on = enabled.has(module.key);
                    const [px, py] = project(
                        W,
                        (room.y0 + room.y1) / 2,
                        room.z + 5,
                    );

                    return (
                        <span
                            key={module.key}
                            aria-hidden
                            style={{
                                left: `${((px - view.x) / view.width) * 100}%`,
                                top: `${((py - view.y) / view.height) * 100}%`,
                            }}
                            className="absolute flex -translate-x-1/2 -translate-y-1/2 rounded-full bg-white p-0.5"
                        >
                            <Bubble
                                filled={on}
                                delay={touched ? 0 : 250 + index * 140}
                            />
                        </span>
                    );
                })}
            </div>

            <figcaption className="flex flex-col gap-2">
                <span className="text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase">
                    Contoh sekolah — coba nyalakan modul
                </span>
                <span className="sr-only">
                    Modul yang menyala: {enabledNames.join(', ') || 'tidak ada'}
                    .
                </span>
                <span className="grid grid-cols-2 gap-x-6 sm:grid-cols-3">
                    {modules.map((module) => {
                        const on = enabled.has(module.key);

                        return (
                            <button
                                key={module.key}
                                type="button"
                                aria-pressed={on}
                                onClick={() => toggle(module.key)}
                                className="flex min-h-11 items-center gap-2.5 text-left text-sm font-medium transition-colors hover:text-(--ink-deep)"
                            >
                                <Bubble filled={on} delay={0} />
                                {module.name}
                            </button>
                        );
                    })}
                </span>
                <span className="text-xs leading-relaxed text-(--pencil)">
                    {enabled.size} dari {modules.length} modul dinyalakan. Modul
                    dinyalakan per sekolah, bukan serentak untuk semua sekolah.
                </span>
            </figcaption>
        </figure>
    );
}
