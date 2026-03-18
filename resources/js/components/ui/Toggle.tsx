export default function Toggle({ checked, onChange }: { checked: boolean; onChange: () => void }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            onClick={onChange}
            className={`relative inline-flex h-[20px] w-[34px] shrink-0 items-center rounded-full transition-colors ${
                checked ? 'bg-ink' : 'bg-[#E8E8E8] dark:bg-[#3d3a35]'
            }`}
        >
            <span
                className={`inline-block h-[14px] w-[14px] rounded-full bg-white shadow-sm transition-transform ${
                    checked ? 'translate-x-[17px]' : 'translate-x-[3px]'
                }`}
            />
        </button>
    );
}
