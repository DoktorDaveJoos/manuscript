<?php

namespace App\Ai\Contracts;

use App\Models\Book;

interface BelongsToBook
{
    public function book(): Book;
}
