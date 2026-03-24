<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\AiTaskCategory;
use App\Models\Book;
use App\Models\EditorialReview;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[Temperature(0.5)]
#[Timeout(120)]
class EditorialChatAgent implements Agent, BelongsToBook, Conversational, HasMiddleware, HasTools
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(
        private Book $book,
        private EditorialReview $review,
        /** @var array<int, array{role: string, content: string}> */
        private array $history = [],
        private ?string $sectionType = null,
        private ?int $findingIndex = null,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    /**
     * @return list<class-string>
     */
    public static function middleware(): array
    {
        return [InjectProviderCredentials::class];
    }

    /**
     * @return list<class-string>
     */
    public function tools(): array
    {
        return [];
    }

    public function instructions(): string
    {
        return 'You are an editorial review assistant. Discuss findings from the editorial review.';
    }

    /**
     * @return list<\Laravel\Ai\Messages\Message>
     */
    public function messages(): array
    {
        return [];
    }
}
