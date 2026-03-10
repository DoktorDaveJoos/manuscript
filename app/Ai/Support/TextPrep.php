<?php

namespace App\Ai\Support;

class TextPrep
{
    /**
     * Strip HTML tags and cap at a maximum word count.
     */
    public static function plainTextCapped(string $html, int $maxWords = 3000): string
    {
        $plainText = strip_tags($html);
        $words = preg_split('/\s+/', $plainText);

        return count($words) > $maxWords ? implode(' ', array_slice($words, 0, $maxWords)) : $plainText;
    }
}
