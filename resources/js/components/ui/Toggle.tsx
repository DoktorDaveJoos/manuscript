export default function Toggle({ checked, onChange }: { checked: boolean; onChange: () => void }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            onClick={onChange}
            className={`relative inline-flex h-[22px] w-[40px] shrink-0 items-center rounded-full transition-colors ${
                checked ? 'bg-accent' : 'bg-status-draft'
            }`}
        >
            <span
                className={`inline-block h-[18px] w-[18px] rounded-full bg-white shadow-sm transition-transform ${
                    checked ? 'translate-x-[20px]' : 'translate-x-[2px]'
                }`}
            />
        </button>
    );
}
