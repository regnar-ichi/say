<?php

use App\Controllers\Api\PingController;
use App\Controllers\Api\WordController;
use App\Controllers\Api\TestController;
use App\Controllers\Api\ClickUpController;
use App\Controllers\Api\WordCardController;

$router->add('ping', [PingController::class, 'index']);

$router->add('words', [WordController::class, 'index']);

$router->add('word', [WordController::class, 'show']);

$router->add('word_create', [WordController::class, 'store'], 'POST');

$router->add('word_delete', [WordController::class, 'delete'], 'POST');

$router->add('word_update', [WordController::class, 'update'], 'POST');

$router->add('word_toggle_visibility', [WordController::class, 'toggleVisibility'], 'POST');

$router->add('find_words', [WordController::class, 'search']);

$router->add('get_test_words', [TestController::class, 'getTestWords']);

$router->add('check_test_words', [TestController::class, 'checkTestWords'], 'POST');

$router->add('get_word_cards', [WordCardController::class, 'getCards']);

$router->add('mark_word_card', [WordCardController::class, 'mark'], 'POST');

//$router->add('clickup', [ClickUpController::class, 'handle'], 'POST');
