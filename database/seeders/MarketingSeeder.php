<?php

namespace Database\Seeders;

use App\Enums\AnalysisType;
use App\Enums\BeatStatus;
use App\Enums\ChapterStatus;
use App\Enums\CharacterPlotPointRole;
use App\Enums\CharacterRole;
use App\Enums\ConnectionType;
use App\Enums\EditorialSectionType;
use App\Enums\Genre;
use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Enums\StorylineType;
use App\Enums\VersionSource;
use App\Enums\WikiEntryKind;
use App\Models\Act;
use App\Models\Analysis;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Character;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\EditorialReviewSection;
use App\Models\HealthSnapshot;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\User;
use App\Models\WikiEntry;
use App\Models\WritingSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@manuscript.app'],
            ['name' => 'Sarah Mitchell', 'password' => bcrypt('password')],
        );

        $book = $this->createBook();
        [$mainStoryline, $parallelStoryline] = $this->createStorylines($book);
        [$actOne, $actTwo, $actThree] = $this->createActs($book);
        $characters = $this->createCharacters($book);
        $chapters = $this->createChapters($book, $mainStoryline, $parallelStoryline, $actOne, $actTwo, $actThree, $characters);
        $this->createWikiEntries($book, $chapters);
        $plotPoints = $this->createPlotStructure($book, $actOne, $actTwo, $actThree, $characters, $chapters);
        $this->createAnalyses($book, $chapters);
        $this->createEditorialReview($book, $chapters);
        $this->createHealthSnapshots($book);
        $this->createWritingSessions($book);
    }

    private function createBook(): Book
    {
        return Book::create([
            'title' => 'The Vanishing Hour',
            'author' => 'Sarah Mitchell',
            'language' => 'en',
            'genre' => Genre::Thriller,
            'secondary_genres' => [Genre::Mystery->value],
            'writing_style' => [
                'tone' => 'suspenseful',
                'perspective' => 'third-person limited',
                'tense' => 'past',
            ],
            'daily_word_count_goal' => 1000,
            'target_word_count' => 80000,
            'ai_input_tokens' => 284500,
            'ai_output_tokens' => 42300,
            'ai_cost_microdollars' => 1850000,
            'ai_request_count' => 47,
            'ai_usage_reset_at' => Carbon::now()->startOfMonth(),
            'copyright_text' => '© 2026 Sarah Mitchell. All rights reserved.',
            'dedication_text' => 'For those who refuse to let the truth stay buried.',
        ]);
    }

    /**
     * @return array{Storyline, Storyline}
     */
    private function createStorylines(Book $book): array
    {
        $main = Storyline::create([
            'book_id' => $book->id,
            'name' => 'Present Day',
            'type' => StorylineType::Main,
            'color' => '#3B82F6',
            'sort_order' => 0,
        ]);

        $parallel = Storyline::create([
            'book_id' => $book->id,
            'name' => 'The Cold Case — 1987',
            'type' => StorylineType::Parallel,
            'color' => '#8B5CF6',
            'sort_order' => 1,
        ]);

        return [$main, $parallel];
    }

    /**
     * @return array{Act, Act, Act}
     */
    private function createActs(Book $book): array
    {
        return [
            Act::create(['book_id' => $book->id, 'number' => 1, 'title' => 'The Discovery', 'description' => 'A cold case resurfaces, pulling Detective Harlow into a web of old secrets.', 'color' => '#10B981', 'sort_order' => 0]),
            Act::create(['book_id' => $book->id, 'number' => 2, 'title' => 'The Unraveling', 'description' => 'Each lead raises more questions. Trust erodes as the investigation closes in.', 'color' => '#F59E0B', 'sort_order' => 1]),
            Act::create(['book_id' => $book->id, 'number' => 3, 'title' => 'The Reckoning', 'description' => 'The truth demands a price. Harlow must decide what justice really means.', 'color' => '#EF4444', 'sort_order' => 2]),
        ];
    }

    /**
     * @return array<string, Character>
     */
    private function createCharacters(Book $book): array
    {
        return [
            'harlow' => Character::create([
                'book_id' => $book->id,
                'name' => 'Detective Claire Harlow',
                'description' => 'A sharp, methodical detective in her late thirties. Transferred to the cold case unit after a high-profile investigation went sideways. She compensates with obsessive attention to detail and a refusal to leave any thread unpulled.',
                'aliases' => ['Harlow', 'Claire'],
            ]),
            'reed' => Character::create([
                'book_id' => $book->id,
                'name' => 'Marcus Reed',
                'description' => 'A retired journalist who covered the original 1987 disappearance. Now in his seventies, he still keeps a file cabinet full of clippings and notes. Knows more than he lets on.',
                'aliases' => ['Reed', 'Marcus'],
            ]),
            'elena' => Character::create([
                'book_id' => $book->id,
                'name' => 'Elena Voss',
                'description' => 'The missing woman from the 1987 case. A university researcher whose disappearance was ruled voluntary — but the evidence never quite supported that conclusion.',
                'aliases' => ['Voss', 'Elena'],
            ]),
            'kieran' => Character::create([
                'book_id' => $book->id,
                'name' => 'Kieran Aldridge',
                'description' => 'A smooth-talking real estate developer with deep ties to the town. His family name appears in the 1987 case files more often than coincidence would allow.',
                'aliases' => ['Aldridge', 'Kieran'],
            ]),
        ];
    }

    /**
     * @param  array<string, Character>  $characters
     * @return array<Chapter>
     */
    private function createChapters(
        Book $book,
        Storyline $main,
        Storyline $parallel,
        Act $actOne,
        Act $actTwo,
        Act $actThree,
        array $characters,
    ): array {
        $chaptersData = [
            [
                'storyline' => $main,
                'act' => $actOne,
                'title' => 'The Box in the Basement',
                'status' => ChapterStatus::Final,
                'reader_order' => 1,
                'pov' => 'harlow',
                'notes' => 'Opening chapter — establish Harlow\'s character through action, not exposition. The box discovery should feel accidental but inevitable.',
                'analysis' => [
                    'tension_score' => 72,
                    'hook_score' => 85,
                    'hook_type' => 'soft_hook',
                    'scene_purpose' => 'setup',
                    'value_shift' => 'routine → curiosity',
                    'emotional_state_open' => 'restless',
                    'emotional_state_close' => 'compelled',
                    'emotional_shift_magnitude' => 6,
                    'micro_tension_score' => 68,
                    'pacing_feel' => 'measured',
                    'entry_hook_score' => 82,
                    'exit_hook_score' => 91,
                    'sensory_grounding' => 7,
                    'information_delivery' => 'organic',
                ],
                'scenes' => [
                    ['title' => 'Cold Case Storage', 'words' => 480, 'content' => '<p>The basement of the precinct smelled of damp concrete and old paper. Detective Claire Harlow descended the stairs with a cup of coffee in one hand and a requisition form in the other, neither of which she particularly wanted.</p><p>She had been reassigned to Cold Cases three months ago — a lateral move that everyone understood was a demotion. The Whitfield investigation had ended careers. Hers had merely been redirected.</p><p>The storage room was a maze of filing cabinets and evidence boxes stacked on industrial shelving. Harlow located the section she needed and pulled a box from the middle shelf. A cascade of dust motes swirled in the fluorescent light.</p><p>The label read: VOSS, E. — MISSING PERSON — 1987. The tape sealing the box had yellowed but never been broken.</p>'],
                    ['title' => 'Something Doesn\'t Add Up', 'words' => 410, 'content' => '<p>Back at her desk, Harlow spread the contents of the Voss file across every available surface. The original detective\'s notes were meticulous in their brevity — short declarative sentences that described a young researcher who had simply walked away from her life.</p><p>Elena Voss, age twenty-nine. Last seen leaving the university library at 10:47 PM on a Tuesday in October. Her car was found at the train station the following morning. No ticket purchased. No security footage. No witnesses after the library.</p><p>The case had been classified as a voluntary disappearance within seventy-two hours. Harlow frowned at the speed of that determination. She turned the page and found a photograph — a woman with sharp, attentive eyes staring directly into the camera.</p><p>"You didn\'t just leave," Harlow murmured. She pinned the photograph to her board.</p>'],
                    ['title' => 'The First Thread', 'words' => 350, 'content' => '<p>The property records were the first anomaly. Three weeks before her disappearance, Elena Voss had filed a public records request for land surveys dating back to the 1940s. The request had been logged but the records themselves were marked as "unavailable — see archives."</p><p>Harlow pulled up the county assessor\'s database on her terminal. The parcels Voss had been researching now belonged to Aldridge Development Group. She wrote the name on a sticky note and pressed it to her monitor.</p><p>Her phone buzzed. A text from her captain: "Quarterly reviews next week. Make sure your caseload numbers look presentable."</p><p>Harlow silenced the phone and opened a fresh notebook. At the top of the first page, she wrote a single question: Why was a university researcher interested in forty-year-old land surveys?</p>'],
                ],
            ],
            [
                'storyline' => $main,
                'act' => $actTwo,
                'title' => 'The Journalist\'s Archive',
                'status' => ChapterStatus::Revised,
                'reader_order' => 2,
                'pov' => 'harlow',
                'notes' => 'Harlow meets Reed. Their dynamic is wary mutual respect. Reed should feel like he\'s been waiting for someone to finally ask the right questions.',
                'analysis' => [
                    'tension_score' => 78,
                    'hook_score' => 74,
                    'hook_type' => 'soft_hook',
                    'scene_purpose' => 'deepening',
                    'value_shift' => 'skepticism → alliance',
                    'emotional_state_open' => 'guarded',
                    'emotional_state_close' => 'unsettled',
                    'emotional_shift_magnitude' => 5,
                    'micro_tension_score' => 71,
                    'pacing_feel' => 'measured',
                    'entry_hook_score' => 70,
                    'exit_hook_score' => 83,
                    'sensory_grounding' => 8,
                    'information_delivery' => 'mostly_organic',
                ],
                'scenes' => [
                    ['title' => 'The House on Birch Lane', 'words' => 520, 'content' => '<p>Marcus Reed\'s house sat at the end of a cul-de-sac that time had forgotten. The siding needed paint, the garden had gone feral, but the porch light was on and the door opened before Harlow could knock.</p><p>"You found the Voss file," Reed said. It was not a question. He was tall and lean, with the watchful posture of someone who had spent decades listening for the thing people weren\'t saying. "Took them long enough to send someone."</p><p>The interior was tidy in the way of a person who lived alone and had made peace with it. Books lined every wall. A desk near the window held a typewriter and a stack of manila folders.</p><p>"I covered the Voss disappearance for the Herald," Reed said, lowering himself into an armchair. "Wrote four pieces before my editor killed the story. Said there wasn\'t enough reader interest." He smiled without warmth. "Funny how that works."</p>'],
                    ['title' => 'What He Kept', 'words' => 440, 'content' => '<p>Reed\'s archive filled two filing cabinet drawers. He had kept everything — interview transcripts, photographs, phone records he had obtained through means Harlow decided not to ask about.</p><p>"Elena was researching contamination," Reed said, handing her a folder. "Industrial waste. The old chemical plant north of town closed in \'82, but the groundwater was still poisoned. She was documenting the health effects on nearby residents."</p><p>Harlow paged through photocopied medical records and hand-drawn maps marking well water testing sites. "This was her university research?"</p><p>"Her dissertation. Except the land she was studying had been quietly purchased by a development company six months before she disappeared. The same company that\'s now building luxury condominiums on the site." Reed let the implication settle. "Aldridge Development."</p><p>Harlow felt the familiar click of a pattern forming. "You think she found something they didn\'t want found."</p><p>"I think," Reed said carefully, "that the question answers itself."</p>'],
                ],
            ],
            [
                'storyline' => $parallel,
                'act' => $actOne,
                'title' => 'The Last Tuesday',
                'status' => ChapterStatus::Revised,
                'reader_order' => 3,
                'pov' => 'elena',
                'notes' => 'This is the 1987 timeline. Elena\'s POV. Show her intelligence and determination. The reader should understand why she became a target.',
                'analysis' => [
                    'tension_score' => 81,
                    'hook_score' => 77,
                    'hook_type' => 'cliffhanger',
                    'scene_purpose' => 'setup',
                    'value_shift' => 'determination → dread',
                    'emotional_state_open' => 'focused',
                    'emotional_state_close' => 'afraid',
                    'emotional_shift_magnitude' => 8,
                    'micro_tension_score' => 76,
                    'pacing_feel' => 'brisk',
                    'entry_hook_score' => 75,
                    'exit_hook_score' => 88,
                    'sensory_grounding' => 6,
                    'information_delivery' => 'organic',
                ],
                'scenes' => [
                    ['title' => 'The Library at Night', 'words' => 390, 'content' => '<p>The university library emptied at ten o\'clock. Elena Voss barely noticed. She was cross-referencing soil composition data from the county agricultural extension with hospital admission records from 1979 to 1985, and the numbers were telling a story that made her hands shake.</p><p>Seven cases of acute liver failure within a two-mile radius of the old Meridian Chemical plant. Three childhood leukemia clusters. A pattern of miscarriages that the county health department had attributed to "statistical variance."</p><p>Elena saved her work to two floppy disks. One went into her bag. The other she taped to the underside of her desk drawer — a habit her advisor would have called paranoid.</p><p>Her advisor hadn\'t received a phone call at two in the morning suggesting she find a new research topic.</p>'],
                    ['title' => 'The Warning', 'words' => 430, 'content' => '<p>The parking lot was empty except for her Civic and a campus security vehicle making its rounds. Elena walked quickly, keys already in her hand. The October air smelled of wood smoke and dead leaves.</p><p>She noticed the envelope on her windshield from ten feet away. Plain white, no name. Inside was a single sheet of paper with a typed message: "Some ground is better left undisturbed."</p><p>Elena stood under the parking lot light and read it twice. Her first instinct was to call campus security. Her second, stronger instinct was to call Marcus Reed at the Herald. He had been the only journalist willing to listen when she first approached the press three weeks ago.</p><p>She looked across the dark campus. Somewhere in this town, someone was watching her research closely enough to leave messages on her car. The rational response was fear. But Elena Voss had grown up in a family where silence was the weapon of choice, and she had spent her entire adult life learning to refuse it.</p><p>She got in her car and drove to the Herald\'s night desk.</p>'],
                ],
            ],
            [
                'storyline' => $parallel,
                'act' => $actTwo,
                'title' => 'Echoes in the Soil',
                'status' => ChapterStatus::Draft,
                'reader_order' => 4,
                'pov' => 'elena',
                'notes' => 'Elena goes deeper. Introduce Aldridge Sr. as the antagonist in 1987. Mirror the present-day Aldridge connection. Draft — needs more sensory detail in the confrontation scene.',
                'analysis' => [
                    'tension_score' => 69,
                    'hook_score' => 65,
                    'hook_type' => 'soft_hook',
                    'scene_purpose' => 'turning_point',
                    'value_shift' => 'resolve → cornered',
                    'emotional_state_open' => 'determined',
                    'emotional_state_close' => 'isolated',
                    'emotional_shift_magnitude' => 7,
                    'micro_tension_score' => 62,
                    'pacing_feel' => 'languid',
                    'entry_hook_score' => 63,
                    'exit_hook_score' => 72,
                    'sensory_grounding' => 4,
                    'information_delivery' => 'exposition_heavy',
                ],
                'scenes' => [
                    ['title' => 'The County Records Office', 'words' => 360, 'content' => '<p>The records clerk was a woman named Doris who had been filing land deeds since before Elena was born. She pulled the survey maps without comment and spread them on the counter.</p><p>"These parcels changed hands in \'83," Doris said, tracing the boundary lines with a pencil. "Private sale. No auction, no public listing. The Aldridge family bought them from the state for back taxes." She glanced up. "Pennies on the dollar."</p><p>Elena photographed each page. The parcels corresponded exactly to the contamination zones she had mapped. Someone had bought poisoned land for almost nothing — and now, four years later, was sitting on it while property values in the surrounding area climbed.</p><p>"Who authorized the sale?" Elena asked.</p><p>Doris\'s expression shifted. "That would be in the county commissioner\'s records. But those files are sealed."</p>'],
                    ['title' => 'An Unwelcome Visitor', 'words' => 280, 'content' => '<p>She found him waiting by her car again — not an envelope this time, but a man. Gray suit, polished shoes, the kind of confident stillness that came from never having been told no.</p><p>"Miss Voss. I\'m Gerald Aldridge." He extended a hand she did not take. "I understand you\'ve been looking into some property records connected to my family\'s business."</p><p>"Public records," Elena said.</p><p>"Of course. But research can be misinterpreted. Data without context is just numbers, and numbers can say anything you want them to." He smiled. "I\'d hate to see a promising academic career derailed by a misunderstanding."</p><p>Elena held his gaze. "Is that a warning?"</p><p>"It\'s advice." He buttoned his jacket. "Good evening, Miss Voss."</p>'],
                ],
            ],
        ];

        $chapters = [];

        foreach ($chaptersData as $data) {
            $totalWords = array_sum(array_column($data['scenes'], 'words'));

            $chapter = Chapter::create([
                'book_id' => $book->id,
                'storyline_id' => $data['storyline']->id,
                'act_id' => $data['act']->id,
                'pov_character_id' => $characters[$data['pov']]->id,
                'title' => $data['title'],
                'status' => $data['status'],
                'reader_order' => $data['reader_order'],
                'word_count' => $totalWords,
                'notes' => $data['notes'],
                'analyzed_at' => Carbon::now()->subDays(2),
                ...$data['analysis'],
            ]);

            foreach ($data['scenes'] as $i => $sceneData) {
                Scene::create([
                    'chapter_id' => $chapter->id,
                    'title' => $sceneData['title'],
                    'content' => $sceneData['content'],
                    'sort_order' => $i,
                    'word_count' => $sceneData['words'],
                ]);
            }

            ChapterVersion::create([
                'chapter_id' => $chapter->id,
                'version_number' => 1,
                'content' => collect($data['scenes'])->pluck('content')->implode("\n"),
                'source' => VersionSource::Original,
                'is_current' => true,
            ]);

            $chapters[] = $chapter;
        }

        // Attach characters to chapters with roles
        $chapters[0]->characters()->attach($characters['harlow']->id, ['role' => CharacterRole::Protagonist->value]);
        $chapters[0]->characters()->attach($characters['kieran']->id, ['role' => CharacterRole::Mentioned->value]);

        $chapters[1]->characters()->attach($characters['harlow']->id, ['role' => CharacterRole::Protagonist->value]);
        $chapters[1]->characters()->attach($characters['reed']->id, ['role' => CharacterRole::Supporting->value]);

        $chapters[2]->characters()->attach($characters['elena']->id, ['role' => CharacterRole::Protagonist->value]);
        $chapters[2]->characters()->attach($characters['reed']->id, ['role' => CharacterRole::Mentioned->value]);

        $chapters[3]->characters()->attach($characters['elena']->id, ['role' => CharacterRole::Protagonist->value]);
        $chapters[3]->characters()->attach($characters['kieran']->id, ['role' => CharacterRole::Supporting->value]);

        // Set first appearances
        $characters['harlow']->update(['first_appearance' => $chapters[0]->id]);
        $characters['reed']->update(['first_appearance' => $chapters[1]->id]);
        $characters['elena']->update(['first_appearance' => $chapters[2]->id]);
        $characters['kieran']->update(['first_appearance' => $chapters[3]->id]);

        return $chapters;
    }

    /**
     * @param  array<Chapter>  $chapters
     */
    private function createWikiEntries(Book $book, array $chapters): void
    {
        $entries = [
            ['kind' => WikiEntryKind::Location, 'type' => 'City', 'name' => 'Carver Falls', 'description' => 'A mid-sized town in upstate New York, built around the lumber and chemical industries. The downtown has gentrified, but the north side still bears the scars of industrial decline.', 'chapter' => 0],
            ['kind' => WikiEntryKind::Location, 'type' => 'Building', 'name' => 'Meridian Chemical Plant', 'description' => 'Decommissioned industrial facility north of town. Operated from 1952 to 1982. Known for improper waste disposal that contaminated the local groundwater. Now slated for redevelopment as luxury condominiums.', 'chapter' => 2],
            ['kind' => WikiEntryKind::Organization, 'type' => 'Corporation', 'name' => 'Aldridge Development Group', 'description' => 'A real estate development company controlled by the Aldridge family for three generations. Specializes in acquiring undervalued land and redeveloping it. Currently building on the former Meridian Chemical site.', 'chapter' => 0],
            ['kind' => WikiEntryKind::Item, 'type' => 'Document', 'name' => 'The Voss Research Files', 'description' => 'Elena Voss\'s dissertation research documenting groundwater contamination and associated health effects near the Meridian Chemical plant. Includes soil samples, medical records, and land survey data.', 'chapter' => 2],
            ['kind' => WikiEntryKind::Lore, 'type' => 'History', 'name' => 'The 1987 Disappearance', 'description' => 'Elena Voss was last seen leaving the university library on October 14, 1987. Her car was found at the train station the next morning. The case was classified as a voluntary disappearance within 72 hours despite inconclusive evidence.', 'chapter' => 0],
            ['kind' => WikiEntryKind::Lore, 'type' => 'Legend', 'name' => 'The Aldridge Land Deal', 'description' => 'In 1983, the Aldridge family purchased contaminated parcels from the state for back taxes. Local rumor holds that the county commissioner was involved, but the relevant records were sealed.', 'chapter' => 3],
        ];

        foreach ($entries as $data) {
            $entry = WikiEntry::create([
                'book_id' => $book->id,
                'kind' => $data['kind'],
                'type' => $data['type'],
                'name' => $data['name'],
                'description' => $data['description'],
                'first_appearance' => $chapters[$data['chapter']]->id,
            ]);

            $entry->chapters()->attach($chapters[$data['chapter']]->id);
        }
    }

    /**
     * @param  array<string, Character>  $characters
     * @param  array<Chapter>  $chapters
     * @return array<PlotPoint>
     */
    private function createPlotStructure(Book $book, Act $actOne, Act $actTwo, Act $actThree, array $characters, array $chapters): array
    {
        $ppDiscovery = PlotPoint::create([
            'book_id' => $book->id,
            'act_id' => $actOne->id,
            'title' => 'Cold Case Reopened',
            'description' => 'Harlow discovers the Voss file and recognizes inconsistencies in the original investigation.',
            'type' => PlotPointType::Setup,
            'status' => PlotPointStatus::Fulfilled,
            'sort_order' => 0,
        ]);

        $ppContamination = PlotPoint::create([
            'book_id' => $book->id,
            'act_id' => $actTwo->id,
            'title' => 'The Contamination Cover-Up',
            'description' => 'Evidence emerges that Elena\'s research threatened a multi-million dollar land deal.',
            'type' => PlotPointType::Conflict,
            'status' => PlotPointStatus::Fulfilled,
            'sort_order' => 0,
        ]);

        $ppBetrayal = PlotPoint::create([
            'book_id' => $book->id,
            'act_id' => $actTwo->id,
            'title' => 'Institutional Betrayal',
            'description' => 'Harlow discovers that the original detective was pressured to close the case quickly.',
            'type' => PlotPointType::TurningPoint,
            'status' => PlotPointStatus::Planned,
            'sort_order' => 1,
        ]);

        $ppReckoning = PlotPoint::create([
            'book_id' => $book->id,
            'act_id' => $actThree->id,
            'title' => 'The Reckoning',
            'description' => 'Harlow confronts Kieran Aldridge with the full weight of evidence spanning four decades.',
            'type' => PlotPointType::Resolution,
            'status' => PlotPointStatus::Planned,
            'sort_order' => 0,
        ]);

        // Beats
        $this->createBeats($ppDiscovery, [
            ['title' => 'Harlow finds the Voss evidence box', 'status' => BeatStatus::Fulfilled, 'chapters' => [$chapters[0]]],
            ['title' => 'File inconsistencies noted', 'status' => BeatStatus::Fulfilled, 'chapters' => [$chapters[0]]],
        ]);

        $this->createBeats($ppContamination, [
            ['title' => 'Reed reveals contamination research', 'status' => BeatStatus::Fulfilled, 'chapters' => [$chapters[1]]],
            ['title' => 'Aldridge connection established', 'status' => BeatStatus::Fulfilled, 'chapters' => [$chapters[1], $chapters[3]]],
            ['title' => 'Elena confronted by Gerald Aldridge', 'status' => BeatStatus::Fulfilled, 'chapters' => [$chapters[3]]],
        ]);

        $this->createBeats($ppBetrayal, [
            ['title' => 'Original case files show external pressure', 'status' => BeatStatus::Planned, 'chapters' => []],
            ['title' => 'Harlow\'s own department pushes back', 'status' => BeatStatus::Planned, 'chapters' => []],
        ]);

        $this->createBeats($ppReckoning, [
            ['title' => 'Final confrontation', 'status' => BeatStatus::Planned, 'chapters' => []],
            ['title' => 'Resolution and aftermath', 'status' => BeatStatus::Planned, 'chapters' => []],
        ]);

        // Connections
        PlotPointConnection::create([
            'book_id' => $book->id,
            'source_plot_point_id' => $ppDiscovery->id,
            'target_plot_point_id' => $ppContamination->id,
            'type' => ConnectionType::SetsUp,
            'description' => 'The discovery of the cold case file leads directly to uncovering the contamination cover-up.',
        ]);

        PlotPointConnection::create([
            'book_id' => $book->id,
            'source_plot_point_id' => $ppContamination->id,
            'target_plot_point_id' => $ppBetrayal->id,
            'type' => ConnectionType::Causes,
            'description' => 'The contamination evidence reveals that the original investigation was deliberately suppressed.',
        ]);

        PlotPointConnection::create([
            'book_id' => $book->id,
            'source_plot_point_id' => $ppBetrayal->id,
            'target_plot_point_id' => $ppReckoning->id,
            'type' => ConnectionType::SetsUp,
            'description' => 'Institutional betrayal forces Harlow to pursue justice outside official channels.',
        ]);

        // Character-PlotPoint associations
        $ppDiscovery->characters()->attach($characters['harlow']->id, ['role' => CharacterPlotPointRole::Key->value]);
        $ppContamination->characters()->attach($characters['elena']->id, ['role' => CharacterPlotPointRole::Key->value]);
        $ppContamination->characters()->attach($characters['reed']->id, ['role' => CharacterPlotPointRole::Supporting->value]);
        $ppBetrayal->characters()->attach($characters['harlow']->id, ['role' => CharacterPlotPointRole::Key->value]);
        $ppReckoning->characters()->attach($characters['harlow']->id, ['role' => CharacterPlotPointRole::Key->value]);
        $ppReckoning->characters()->attach($characters['kieran']->id, ['role' => CharacterPlotPointRole::Key->value]);

        return [$ppDiscovery, $ppContamination, $ppBetrayal, $ppReckoning];
    }

    /**
     * @param  array{title: string, status: BeatStatus, chapters: array<Chapter>}[]  $beatsData
     */
    private function createBeats(PlotPoint $plotPoint, array $beatsData): void
    {
        foreach ($beatsData as $i => $data) {
            $beat = Beat::create([
                'plot_point_id' => $plotPoint->id,
                'title' => $data['title'],
                'status' => $data['status'],
                'sort_order' => $i,
            ]);

            foreach ($data['chapters'] as $j => $chapter) {
                $beat->chapters()->attach($chapter->id, ['sort_order' => $j]);
            }
        }
    }

    /**
     * @param  array<Chapter>  $chapters
     */
    private function createAnalyses(Book $book, array $chapters): void
    {
        $analysisData = [
            [AnalysisType::Pacing, [
                ['score' => 8, 'notes' => 'Well-paced opening that balances exposition with forward momentum. Scene transitions are clean.'],
                ['score' => 7, 'notes' => 'Good conversational rhythm in the Reed interview. Could tighten the transition between scenes.'],
                ['score' => 8, 'notes' => 'Effective escalation from routine research to genuine threat. The parking lot scene is taut.'],
                ['score' => 6, 'notes' => 'Pacing feels uneven — the records office scene is slower than the confrontation warrants. Consider intercutting.'],
            ]],
            [AnalysisType::ChapterHook, [
                ['score' => 9, 'notes' => 'Strong exit hook with the pinned photograph. Reader compelled to continue.'],
                ['score' => 7, 'notes' => 'Reed\'s reveal about contamination provides good chapter-end tension.'],
                ['score' => 8, 'notes' => 'Dramatic irony of knowing Elena\'s fate creates inherent tension throughout.'],
                ['score' => 6, 'notes' => 'The confrontation with Aldridge Sr. is effective but the exit could be sharper.'],
            ]],
            [AnalysisType::SceneAudit, [
                ['score' => 8, 'notes' => 'Three scenes, each with distinct purpose. Scene economy is strong.'],
                ['score' => 7, 'notes' => 'Two scenes that complement each other well. The archive reveal is the centerpiece.'],
                ['score' => 7, 'notes' => 'Both scenes serve the narrative. The warning note is a strong catalytic moment.'],
                ['score' => 5, 'notes' => 'The records office scene duplicates information the reader already has. Consider consolidating.'],
            ]],
        ];

        foreach ($analysisData as [$type, $chapterResults]) {
            foreach ($chapterResults as $i => $result) {
                Analysis::create([
                    'book_id' => $book->id,
                    'chapter_id' => $chapters[$i]->id,
                    'type' => $type,
                    'result' => $result,
                    'ai_generated' => true,
                ]);
            }
        }
    }

    /**
     * @param  array<Chapter>  $chapters
     */
    private function createEditorialReview(Book $book, array $chapters): void
    {
        $review = EditorialReview::create([
            'book_id' => $book->id,
            'status' => 'completed',
            'overall_score' => 74,
            'executive_summary' => 'The Vanishing Hour demonstrates strong structural instincts and a compelling dual-timeline premise. The present-day investigation is well-paced with natural revelations, and the 1987 timeline effectively builds dramatic irony. Key areas for improvement include deepening sensory detail in the parallel storyline and tightening the pacing in Chapter 4. The character work is solid — Harlow and Reed have an authentic dynamic, and Elena\'s perspective chapters avoid the trap of making the victim purely sympathetic without agency.',
            'top_strengths' => [
                'Dual timeline structure creates natural tension and dramatic irony',
                'Strong opening chapter with effective inciting incident',
                'Character voices are distinct and consistent across POV shifts',
            ],
            'top_improvements' => [
                'Chapter 4 pacing needs tightening — records office scene duplicates known information',
                'Sensory grounding in 1987 timeline could be stronger to differentiate the time periods',
                'The Aldridge antagonist needs more nuance to avoid feeling like a stock villain',
            ],
            'started_at' => Carbon::now()->subHours(2),
            'completed_at' => Carbon::now()->subHour(),
        ]);

        $sections = [
            [EditorialSectionType::Plot, 78, 'The central mystery is well-constructed with layered revelations.', [
                ['severity' => 'suggestion', 'description' => 'Consider planting a subtle red herring in Chapter 1 to complicate the investigation path.', 'chapter_references' => [$chapters[0]->id], 'recommendation' => 'Add a detail in the evidence box that initially points away from Aldridge.'],
                ['severity' => 'warning', 'description' => 'The connection between timelines risks becoming too obvious too quickly.', 'chapter_references' => [$chapters[1]->id, $chapters[3]->id], 'recommendation' => 'Delay the explicit Aldridge connection until later in Act 2.'],
            ]],
            [EditorialSectionType::Characters, 81, 'Character work is a strength. Harlow and Elena are distinct, layered protagonists.', [
                ['severity' => 'suggestion', 'description' => 'Kieran Aldridge needs a humanizing detail to avoid feeling one-dimensional.', 'chapter_references' => [$chapters[3]->id], 'recommendation' => 'Give Aldridge a moment of genuine conflict about his family\'s legacy.'],
                ['severity' => 'suggestion', 'description' => 'Reed\'s backstory as a journalist could be deepened to explain why he kept the files.', 'chapter_references' => [$chapters[1]->id], 'recommendation' => 'Add a line about personal guilt or a promise made to someone.'],
            ]],
            [EditorialSectionType::Pacing, 68, 'Generally well-paced with some unevenness in the parallel timeline chapters.', [
                ['severity' => 'warning', 'description' => 'Chapter 4 opens slowly with the records office scene repeating established facts.', 'chapter_references' => [$chapters[3]->id], 'recommendation' => 'Start the chapter closer to the confrontation or give the records scene new information.'],
                ['severity' => 'suggestion', 'description' => 'The transition from Chapter 2 to Chapter 3 could be smoother.', 'chapter_references' => [$chapters[1]->id, $chapters[2]->id], 'recommendation' => 'End Chapter 2 with a line that echoes forward into the 1987 timeline.'],
            ]],
            [EditorialSectionType::NarrativeVoice, 76, 'Third-person limited is handled well with clear POV discipline.', [
                ['severity' => 'suggestion', 'description' => 'Elena\'s internal voice could be more distinct from Harlow\'s.', 'chapter_references' => [$chapters[2]->id, $chapters[3]->id], 'recommendation' => 'Give Elena more academic diction and longer, more analytical observations.'],
            ]],
            [EditorialSectionType::Themes, 82, 'Strong thematic core around institutional accountability and the cost of silence.', [
                ['severity' => 'suggestion', 'description' => 'The environmental justice angle could resonate more if connected to present-day stakes.', 'chapter_references' => [], 'recommendation' => 'Consider showing current health effects in the community Harlow investigates.'],
            ]],
            [EditorialSectionType::SceneCraft, 72, 'Scene construction is solid but sensory grounding varies.', [
                ['severity' => 'warning', 'description' => 'The 1987 scenes lack the sensory specificity of the present-day chapters.', 'chapter_references' => [$chapters[2]->id, $chapters[3]->id], 'recommendation' => 'Add period-specific details — technology, clothing, cultural references — to ground the reader.'],
                ['severity' => 'suggestion', 'description' => 'The precinct basement in Chapter 1 is well-drawn. Apply this level of detail elsewhere.', 'chapter_references' => [$chapters[0]->id], 'recommendation' => 'Use the basement scene as a model for environmental storytelling.'],
            ]],
            [EditorialSectionType::ProseStyle, 75, 'Clean, functional prose with occasional moments of real craft.', [
                ['severity' => 'suggestion', 'description' => 'Some dialogue tags could be cut in favor of action beats.', 'chapter_references' => [$chapters[1]->id], 'recommendation' => 'Replace "Reed said" with character-specific gestures where possible.'],
            ]],
        ];

        foreach ($sections as [$type, $score, $summary, $findings]) {
            EditorialReviewSection::create([
                'editorial_review_id' => $review->id,
                'type' => $type,
                'score' => $score,
                'summary' => $summary,
                'findings' => $findings,
                'recommendations' => array_column($findings, 'recommendation'),
            ]);
        }

        // Chapter-specific notes
        $chapterNotes = [
            [$chapters[0], ['Strong opening. The box discovery feels organic. Exit hook is excellent.', 'Consider varying sentence length in the records-reading scene to avoid monotony.']],
            [$chapters[1], ['The Reed-Harlow dynamic is the chapter\'s greatest strength.', 'The archive reveal is well-timed but could use one more surprising detail.']],
            [$chapters[2], ['Elena\'s voice is compelling. The floppy disk detail is perfect period specificity.', 'The parking lot scene needs more physical tension — heartbeat, shallow breathing, etc.']],
            [$chapters[3], ['The Aldridge confrontation is the weakest scene structurally.', 'The records office scene works better as setup than the confrontation does as payoff. Reverse the energy.']],
        ];

        foreach ($chapterNotes as [$chapter, $notes]) {
            EditorialReviewChapterNote::create([
                'editorial_review_id' => $review->id,
                'chapter_id' => $chapter->id,
                'notes' => $notes,
            ]);
        }
    }

    private function createHealthSnapshots(Book $book): void
    {
        $snapshots = [
            ['composite' => 62, 'hooks' => 58, 'pacing' => 60, 'tension' => 65, 'weave' => 55, 'scene_purpose' => 64, 'tension_dynamics' => 61, 'emotional_arc' => 63, 'craft' => 60, 'days_ago' => 14],
            ['composite' => 68, 'hooks' => 65, 'pacing' => 66, 'tension' => 70, 'weave' => 62, 'scene_purpose' => 71, 'tension_dynamics' => 67, 'emotional_arc' => 69, 'craft' => 65, 'days_ago' => 10],
            ['composite' => 71, 'hooks' => 72, 'pacing' => 68, 'tension' => 74, 'weave' => 66, 'scene_purpose' => 73, 'tension_dynamics' => 70, 'emotional_arc' => 72, 'craft' => 69, 'days_ago' => 7],
            ['composite' => 74, 'hooks' => 78, 'pacing' => 70, 'tension' => 76, 'weave' => 68, 'scene_purpose' => 75, 'tension_dynamics' => 73, 'emotional_arc' => 74, 'craft' => 72, 'days_ago' => 3],
            ['composite' => 76, 'hooks' => 80, 'pacing' => 72, 'tension' => 78, 'weave' => 70, 'scene_purpose' => 77, 'tension_dynamics' => 75, 'emotional_arc' => 76, 'craft' => 74, 'days_ago' => 0],
        ];

        foreach ($snapshots as $data) {
            HealthSnapshot::create([
                'book_id' => $book->id,
                'composite_score' => $data['composite'],
                'hooks_score' => $data['hooks'],
                'pacing_score' => $data['pacing'],
                'tension_score' => $data['tension'],
                'weave_score' => $data['weave'],
                'scene_purpose_score' => $data['scene_purpose'],
                'tension_dynamics_score' => $data['tension_dynamics'],
                'emotional_arc_score' => $data['emotional_arc'],
                'craft_score' => $data['craft'],
                'recorded_at' => Carbon::today()->subDays($data['days_ago']),
            ]);
        }
    }

    private function createWritingSessions(Book $book): void
    {
        // 30 days of writing sessions with realistic patterns
        // (weekdays more active, some rest days, varied word counts)
        $wordCounts = [
            820, 0, 1150, 940, 1280, 450, 0,       // week 1
            1050, 1320, 780, 0, 1100, 600, 0,       // week 2
            0, 1400, 950, 1180, 1050, 0, 300,        // week 3
            1250, 880, 1340, 1100, 0, 750, 0,        // week 4
            1020, 1180,                               // current week
        ];

        $dailyGoal = 1000;

        foreach ($wordCounts as $i => $words) {
            if ($words === 0) {
                continue;
            }

            WritingSession::create([
                'book_id' => $book->id,
                'date' => Carbon::today()->subDays(count($wordCounts) - 1 - $i),
                'words_written' => $words,
                'goal_met' => $words >= $dailyGoal,
            ]);
        }
    }
}
