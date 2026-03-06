const PRESET_COLORS = [
    '#C44D3C',
    '#D97A35',
    '#CBA135',
    '#5A9E5F',
    '#3D8B8B',
    '#4A7AB5',
    '#7B5EA7',
    '#C25D7E',
    '#8B7355',
    '#6B7B8D',
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
                        <svg className="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    )}
                </button>
            ))}
        </div>
    );
}
