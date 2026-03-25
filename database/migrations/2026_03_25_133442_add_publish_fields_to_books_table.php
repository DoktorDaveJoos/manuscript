<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->text('copyright_text')->nullable()->after('secondary_genres');
            $table->text('dedication_text')->nullable()->after('copyright_text');
            $table->text('epigraph_text')->nullable()->after('dedication_text');
            $table->string('epigraph_attribution')->nullable()->after('epigraph_text');
            $table->text('acknowledgment_text')->nullable()->after('epigraph_attribution');
            $table->text('about_author_text')->nullable()->after('acknowledgment_text');
            $table->text('also_by_text')->nullable()->after('about_author_text');
            $table->string('publisher_name')->nullable()->after('also_by_text');
            $table->string('isbn')->nullable()->after('publisher_name');
            $table->string('cover_image_path')->nullable()->after('isbn');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->boolean('is_epilogue')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'copyright_text', 'dedication_text', 'epigraph_text',
                'epigraph_attribution', 'acknowledgment_text', 'about_author_text',
                'also_by_text', 'publisher_name', 'isbn', 'cover_image_path',
            ]);
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn('is_epilogue');
        });
    }
};
