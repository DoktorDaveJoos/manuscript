<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupPlotStructureRequest;
use App\Models\Book;
use App\Services\PlotTemplateService;
use Illuminate\Http\RedirectResponse;

class PlotSetupController extends Controller
{
    public function __construct(private PlotTemplateService $templateService) {}

    public function store(SetupPlotStructureRequest $request, Book $book): RedirectResponse
    {
        $this->templateService->createStructure(
            $book,
            $request->validated('acts'),
            $request->validated('chapter_assignments'),
        );

        return redirect()->route('books.plot', $book);
    }
}
