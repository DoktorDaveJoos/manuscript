<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('settings/about', [
            'version' => config('app.version', '1.0.0'),
            'book' => $this->defaultBook(),
        ]);
    }

    /**
     * @return array{id: int, title: string}|null
     */
    private function defaultBook(): ?array
    {
        $book = Book::query()->select('id', 'title')->first();

        return $book?->only('id', 'title');
    }
}
