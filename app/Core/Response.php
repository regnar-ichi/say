<?php

namespace App\Core;

class Response
{
    public static function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function text(string $message): void
    {
        echo $message;
        exit;
    }
}