<?php

namespace Database\Seeders;

use App\Enums\ChapterStatus;
use App\Enums\CharacterRole;
use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Enums\StorylineType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $book = Book::factory()->create([
            'title' => 'Beispielroman',
            'author' => 'Max Mustermann',
            'language' => 'de',
        ]);

        $storyline = Storyline::factory()->create([
            'book_id' => $book->id,
            'name' => 'Haupthandlung',
            'type' => StorylineType::Main,
            'color' => '#3B82F6',
            'sort_order' => 0,
        ]);

        $acts = collect([
            ['number' => 1, 'title' => 'Einführung', 'sort_order' => 0, 'color' => '#10B981'],
            ['number' => 2, 'title' => 'Konfrontation', 'sort_order' => 1, 'color' => '#F59E0B'],
            ['number' => 3, 'title' => 'Auflösung', 'sort_order' => 2, 'color' => '#EF4444'],
        ])->map(fn (array $data) => Act::factory()->create([
            'book_id' => $book->id,
            ...$data,
        ]));

        $protagonist = Character::factory()->create([
            'book_id' => $book->id,
            'name' => 'Elena Fischer',
            'description' => 'Eine junge Journalistin, die einem Geheimnis auf der Spur ist.',
            'aliases' => ['Elena', 'E.F.'],
        ]);

        $chapters = collect([
            ['title' => 'Der Anfang', 'act_id' => $acts[0]->id, 'reader_order' => 1, 'status' => ChapterStatus::Revised, 'word_count' => 2500],
            ['title' => 'Die Entdeckung', 'act_id' => $acts[1]->id, 'reader_order' => 2, 'status' => ChapterStatus::Draft, 'word_count' => 3200],
            ['title' => 'Der Wendepunkt', 'act_id' => $acts[2]->id, 'reader_order' => 3, 'status' => ChapterStatus::Draft, 'word_count' => 1800],
        ])->map(fn (array $data) => Chapter::factory()->create([
            'book_id' => $book->id,
            'storyline_id' => $storyline->id,
            'pov_character_id' => $protagonist->id,
            ...$data,
        ]));

        $protagonist->update(['first_appearance' => $chapters[0]->id]);

        foreach ($chapters as $chapter) {
            ChapterVersion::factory()->create([
                'chapter_id' => $chapter->id,
            ]);

            $chapter->characters()->attach($protagonist->id, [
                'role' => CharacterRole::Protagonist->value,
            ]);
        }

        PlotPoint::factory()->fulfilled()->create([
            'book_id' => $book->id,
            'storyline_id' => $storyline->id,
            'act_id' => $acts[0]->id,
            'title' => 'Protagonistin wird vorgestellt',
            'type' => PlotPointType::Setup,
            'status' => PlotPointStatus::Fulfilled,
            'intended_chapter_id' => $chapters[0]->id,
            'actual_chapter_id' => $chapters[0]->id,
            'sort_order' => 0,
        ]);
    }
}
