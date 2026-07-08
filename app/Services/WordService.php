<?php

namespace App\Services;

use App\Models\Word;

class WordService
{
    public function create(string $text, string $translate, int $userId, string $type = '', string $example = '', string $example_ru = ''): array
    {
        if (Word::existsByText($text, $userId)) {
            return [
                'status' => 'exists'
            ];
        }

        $id = Word::create($text, $translate, $userId, $type, $example, $example_ru);

        return [
            'status' => 'ok',
            'id' => $id
        ];
    }
}