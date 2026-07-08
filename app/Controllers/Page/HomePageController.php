<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Models\HomeDashboard;

class HomePageController extends Controller
{
    public function index(): void
    {
        Auth::require();

        $this->view('pages/home/index', [
            'title' => 'Home',
            'users' => HomeDashboard::getUserProgressRows(),
            'quickLinks' => [
                [
                    'label' => 'Words',
                    'href' => '/words',
                    'description' => 'Словарь: добавляй, редактируй и фильтруй слова по уровню, типу и теме.',
                ],
                [
                    'label' => 'Tests',
                    'href' => '/tests',
                    'description' => 'Тесты: проверь знание слов в направлениях English → Russian и Russian → English.',
                ],
                [
                    'label' => 'Reader',
                    'href' => '/reader',
                    'description' => 'Чтение: вставь английский текст и читай с подсказками по словам.',
                ],
                [
                    'label' => 'Library',
                    'href' => '/library',
                    'description' => 'Библиотека: сохраняй тексты и книги, открывай их позже и продолжай чтение.',
                ],
                [
                    'label' => 'Memory Cards',
                    'href' => '/words',
                    'description' => 'Карточки памяти: повторяй слова и отмечай знакомые, чтобы они больше не мешали в тренировках.',
                ],
            ],
        ]);
    }
}
