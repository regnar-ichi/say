<?php

namespace App\Core;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function id(): int
    {
        self::start();
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::id() > 0;
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }
}