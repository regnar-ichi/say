<?php

namespace App\Controllers\Api;

use App\Core\Response;
use App\Models\Word;
use App\Services\WordService;
use App\Requests\WordStoreRequest;
use App\Core\Auth;
use App\Controllers\Controller;

class WordController extends Controller
{
    public function index(): void
    {
        $words = Word::getAll();

        Response::json([
            'status' => 'ok',
            'data' => $words
        ]);
    }

    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        if ($id <= 0) {
            Response::json([
                'status' => 'error',
                'message' => 'Missing word id'
            ]);
        }

        $word = Word::find($id);

        if (!$word) {
            Response::json([
                'status' => 'error',
                'message' => 'Word not found'
            ]);
        }

        Response::json([
            'status' => 'ok',
            'data' => $word
        ]);
    }  
    
    public function store(): void
    {
        $request = new WordStoreRequest();

        $validated = $request->validate();

        if ($validated['status'] === 'error') {
            // Check if AJAX request
            if ($this->isAjax()) {
                Response::json([
                    'status' => 'error',
                    'message' => $validated['message']
                ]);
            }
            redirect('/words');
        }

        $service = new WordService();

        $service->create(
            $validated['data']['text'],
            $validated['data']['translate'],
            Auth::id(),
            $validated['data']['type'],
            $validated['data']['example'],
            $validated['data']['example_ru']
        );

        // Check if AJAX request
        if ($this->isAjax()) {
            Response::json([
                'status' => 'ok',
                'message' => 'Word added successfully'
            ]);
        }

        redirect('/words');
    }

    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $translate = trim($_POST['translate'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $example = trim($_POST['example'] ?? '');
        $example_ru = trim($_POST['example_ru'] ?? '');

        if ($id <= 0 || $text === '' || $translate === '') {
            // Check if AJAX request
            if ($this->isAjax()) {
                Response::json([
                    'status' => 'error',
                    'message' => 'Missing or invalid fields'
                ]);
            }
            redirect('/words');
        }

        Word::update($id, $text, $translate, $type, $example, $example_ru);

        // Check if AJAX request
        if ($this->isAjax()) {
            Response::json([
                'status' => 'ok',
                'message' => 'Word updated successfully'
            ]);
        }

        redirect('/words');
    }

    public function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect('/words');
        }

        Word::delete($id);

        redirect('/words');
    }

    public function toggleVisibility(): void
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            Word::toggleVisibility($id);
        }

        $back = $_SERVER['HTTP_REFERER'] ?? '/words';
        header('Location: ' . $back);
        exit;
    }

    public function search(): void
    {
        $query = trim($_GET['q'] ?? $_POST['q'] ?? '');

        if (strlen($query) < 1) {
            Response::json([
                'status' => 'error',
                'message' => 'Query too short'
            ]);
        }

        $results = Word::search($query);

        Response::json([
            'status' => 'ok',
            'data' => $results
        ]);
    }
}
