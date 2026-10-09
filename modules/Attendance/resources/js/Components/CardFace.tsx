import type { KeyboardEvent, PointerEvent, ReactNode, Ref } from 'react';

export interface CardTemplate {
    imageUrl: string;
    imageName: string;
    /** Width over height of the picture. */
    aspect: number;
    /** Left edge of the QR box, percent of the picture's width. */
    x: number;
    /** Top edge of the QR box, percent of the picture's height. */
    y: number;
    /** Side of the QR box, percent of the picture's width. */
    size: number;
    /** Printed width of the card. */
    widthMm: number;
}

/**
 * The school's card picture with a square box where the QR goes. The
 * editor and the printed page both draw it, so the place is the same in
 * both. The picture is an <img>, not a background, so it prints without
 * the browser's "Background graphics" option.
 */
export default function CardFace({
    imageUrl,
    aspect,
    x,
    y,
    size,
    widthMm,
    containerRef,
    boxProps,
    children,
}: {
    imageUrl: string;
    aspect: number;
    x: number;
    y: number;
    size: number;
    /** Fixed width in millimetres; without it the card fills its parent. */
    widthMm?: number;
    containerRef?: Ref<HTMLDivElement>;
    boxProps?: {
        className?: string;
        tabIndex?: number;
        'aria-label'?: string;
        onPointerDown?: (event: PointerEvent<HTMLDivElement>) => void;
        onPointerMove?: (event: PointerEvent<HTMLDivElement>) => void;
        onPointerUp?: (event: PointerEvent<HTMLDivElement>) => void;
        onKeyDown?: (event: KeyboardEvent<HTMLDivElement>) => void;
    };
    children: ReactNode;
}) {
    return (
        <div
            ref={containerRef}
            className="relative max-w-full select-none"
            style={{
                width: widthMm === undefined ? '100%' : `${widthMm}mm`,
                aspectRatio: String(aspect),
            }}
        >
            <img
                src={imageUrl}
                alt=""
                draggable={false}
                className="block size-full"
            />
            <div
                {...boxProps}
                className={boxProps?.className}
                style={{
                    position: 'absolute',
                    left: `${x}%`,
                    top: `${y}%`,
                    width: `${size}%`,
                    aspectRatio: '1',
                }}
            >
                {children}
            </div>
        </div>
    );
}
