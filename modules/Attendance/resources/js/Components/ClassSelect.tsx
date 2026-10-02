import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@shared/components/ui/select';

import type { ClassOption } from './status';

/**
 * Class picker of the recording pages. A teacher's own classes come first
 * under "Kelas Anda"; every other class stays one step below.
 */
export default function ClassSelect({
    value,
    onChange,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    options: ClassOption[];
}) {
    const mine = options.filter((option) => option.mine);
    const others = options.filter((option) => !option.mine);

    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger aria-label="Kelas" className="w-full">
                <SelectValue placeholder="Pilih kelas" />
            </SelectTrigger>
            <SelectContent>
                {mine.length > 0 && (
                    <SelectGroup>
                        <SelectLabel>Kelas Anda</SelectLabel>
                        {mine.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                )}
                <SelectGroup>
                    {mine.length > 0 && <SelectLabel>Kelas lain</SelectLabel>}
                    {others.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}
