import { useTranslation } from 'react-i18next';
import SearchInput from '@/components/ui/SearchInput';

export default function WikiSearchInput({
    query,
    onChange,
}: {
    query: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation('wiki');

    return (
        <SearchInput
            value={query}
            onChange={onChange}
            placeholder={t('search.placeholder')}
        />
    );
}
