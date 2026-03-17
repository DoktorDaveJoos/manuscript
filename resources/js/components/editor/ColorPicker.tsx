import { Check } from 'lucide-react';

const PRESET_COLORS = [
    '#B8433A',
    '#C97030',
    '#B87333',
    '#4D8B52',
    '#3A7F7F',
    '#4270A8',
    '#6F5298',
    '#B05472',
    '#7D6A4E',
    '#5F7080',
];

export default function ColorPicker({
    value,
    onChange,
}: {
    value: string | null;
    onChange: (color: string) => void;
}) {
    return (
        <div className="grid grid-cols-5 gap-1.5 px-3 py-2">
            {PRESET_COLORS.map((color) => (
                <button
                    key={color}
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        onChange(color);
                    }}
                    className="flex h-6 w-6 items-center justify-center rounded-full transition-transform hover:scale-110"
                    style={{ backgroundColor: color }}
                >
                    {value === color && (
                        <Check
                            size={12}
                            strokeWidth={2.5}
                            className="text-white"
                        />
                    )}
                </button>
            ))}
        </div>
    );
}
