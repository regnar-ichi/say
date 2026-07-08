<?php

namespace App\Requests;

class WordStoreRequest
{
    public function validate(): array
    {
        $text = trim($_POST['text'] ?? $_GET['text'] ?? '');
        $translate = trim($_POST['translate'] ?? $_GET['translate'] ?? '');
        $type = trim($_POST['type'] ?? $_GET['type'] ?? '');
        $example = trim($_POST['example'] ?? $_GET['example'] ?? '');
        $example_ru = trim($_POST['example_ru'] ?? $_GET['example_ru'] ?? '');

        if ($text === '' || $translate === '') {
            return [
                'status' => 'error',
                'message' => 'Missing fields'
            ];
        }

        return [
            'status' => 'ok',
            'data' => [
                'text' => $text,
                'translate' => $translate,
                'type' => $type,
                'example' => $example,
                'example_ru' => $example_ru
            ]
        ];
    }
}