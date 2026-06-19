import Select from '@/components/ui/Select';
import { GENRE_GROUPS } from '@/lib/genres';

type GenreSelectProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    /** Genre values to omit (e.g. the primary genre and already-picked secondaries). */
    exclude?: string[];
    variant?: 'default' | 'compact' | 'dialog';
};

/**
 * Genre dropdown rendering the shared {@link GENRE_GROUPS} as <optgroup>s.
 * Used for both the primary genre and the secondary-genre picker.
 */
export default function GenreSelect({
    value,
    onChange,
    placeholder,
    exclude,
    variant,
}: GenreSelectProps) {
    const groups =
        exclude && exclude.length > 0
            ? GENRE_GROUPS.map((group) => ({
                  ...group,
                  options: group.options.filter(
                      (g) => !exclude.includes(g.value),
                  ),
              })).filter((group) => group.options.length > 0)
            : GENRE_GROUPS;

    return (
        <Select
            variant={variant}
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="">{placeholder}</option>
            {groups.map((group) => (
                <optgroup key={group.label} label={group.label}>
                    {group.options.map((g) => (
                        <option key={g.value} value={g.value}>
                            {g.label}
                        </option>
                    ))}
                </optgroup>
            ))}
        </Select>
    );
}
