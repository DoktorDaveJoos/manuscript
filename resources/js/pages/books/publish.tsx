import { Head } from '@inertiajs/react';

interface Props {
    book: {
        id: number;
        title: string;
        author: string;
    };
    chapters: Array<{
        id: number;
        title: string;
        is_epilogue: boolean;
    }>;
}

export default function PublishPage({ book, chapters }: Props) {
    return (
        <>
            <Head title={`Publish — ${book.title}`} />
            <div>Publish page placeholder</div>
        </>
    );
}
