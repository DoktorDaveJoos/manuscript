<?php

use App\Models\AppSetting;
use App\Models\Book;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Copy the first book's writing style and prose pass rules to global AppSettings.
     */
    public function up(): void
    {
        $book = Book::query()->first();

        if (! $book) {
            return;
        }

        $styleText = $book->writing_style_text;
        if (! $styleText && $book->writing_style) {
            $styleText = Book::formatWritingStyle($book->writing_style);
        }
        if ($styleText) {
            AppSetting::set('writing_style_text', $styleText);
        }

        if ($book->prose_pass_rules) {
            AppSetting::set('prose_pass_rules', json_encode($book->prose_pass_rules));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        AppSetting::query()->whereIn('key', ['writing_style_text', 'prose_pass_rules'])->delete();
    }
};
