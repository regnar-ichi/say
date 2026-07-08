<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Models\ReaderItem;
use App\Services\Fb2Parser;
use Throwable;

class LibraryPageController extends Controller
{
    private const MAX_FB2_SIZE = 10485760;
    private const MAX_COVER_SIZE = 5242880;
    private const COVER_UPLOAD_DIR = '/uploads/reader-covers/';

    public function index(): void
    {
        Auth::require();

        $this->view('pages/library/index', [
            'title' => 'Library',
            'items' => ReaderItem::getAllForUser(Auth::id()),
            'error' => ''
        ]);
    }

    public function uploadFb2(): void
    {
        Auth::require();

        $userId = Auth::id();
        $file = $_FILES['fb2_file'] ?? null;

        if ($file === null || !is_array($file)) {
            $this->renderWithError('Choose an FB2 file to upload.');
            return;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->renderWithError($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
            return;
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_FB2_SIZE) {
            $this->renderWithError('FB2 file is too large. Maximum size is 10 MB.');
            return;
        }

        $originalName = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'fb2') {
            $this->renderWithError('Only plain .fb2 files are supported.');
            return;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $content = is_uploaded_file($tmpName) ? file_get_contents($tmpName) : false;

        if ($content === false || trim($content) === '') {
            $this->renderWithError('Could not read uploaded FB2 file.');
            return;
        }

        $fallbackTitle = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Untitled book';
        $coverPath = null;

        try {
            $parsed = (new Fb2Parser())->parse($content, $fallbackTitle);
            $coverPath = $this->saveCover($parsed['cover'] ?? null, $userId);
            $itemId = ReaderItem::createFb2Book(
                $userId,
                $parsed['title'],
                $originalName,
                $parsed['pages'],
                $coverPath
            );
        } catch (Throwable $exception) {
            $this->deleteSavedCover($coverPath);
            $this->renderWithError('Could not import FB2 file. Please check that the file is a valid FB2 book.');
            return;
        }

        redirect('/reader?item_id=' . $itemId . '&page=1');
    }

    public function saveText(): void
    {
        Auth::require();

        $text = trim($_POST['reader_text'] ?? '');
        $title = trim($_POST['reader_title'] ?? '');

        if ($text === '') {
            $this->renderWithError('Paste text before saving.', $title, '');
            return;
        }

        if ($title === '') {
            $title = $this->makeTitle($text);
        }

        $itemId = ReaderItem::createManualText(Auth::id(), $title, $text);

        redirect('/reader?item_id=' . $itemId . '&page=1');
    }

    public function delete(): void
    {
        Auth::require();

        $itemId = (int) ($_POST['item_id'] ?? 0);

        if ($itemId > 0) {
            ReaderItem::deleteForUser(Auth::id(), $itemId);
        }

        redirect('/library');
    }

    private function renderWithError(string $message, string $manualTitle = '', string $manualText = ''): void
    {
        $this->view('pages/library/index', [
            'title' => 'Library',
            'items' => ReaderItem::getAllForUser(Auth::id()),
            'error' => $message,
            'manualTitle' => $manualTitle,
            'manualText' => $manualText
        ]);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'FB2 file is too large. Maximum size is 10 MB.',
            UPLOAD_ERR_NO_FILE => 'Choose an FB2 file to upload.',
            default => 'Could not upload FB2 file.',
        };
    }

    private function saveCover(?array $cover, int $userId): ?string
    {
        if (empty($cover['content']) || empty($cover['extension'])) {
            return null;
        }

        $content = (string) $cover['content'];
        $extension = strtolower((string) $cover['extension']);

        if (!in_array($extension, ['jpg', 'png', 'gif', 'webp'], true) || strlen($content) > self::MAX_COVER_SIZE) {
            return null;
        }

        $publicDir = dirname(__DIR__, 3) . '/public';
        $uploadDir = $publicDir . self::COVER_UPLOAD_DIR;

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return null;
        }

        try {
            $suffix = bin2hex(random_bytes(8));
        } catch (Throwable $exception) {
            $suffix = uniqid('', true);
        }

        $filename = 'fb2-cover-' . $userId . '-' . $suffix . '.' . $extension;
        $path = $uploadDir . $filename;

        if (file_put_contents($path, $content) === false) {
            return null;
        }

        return self::COVER_UPLOAD_DIR . $filename;
    }

    private function deleteSavedCover(?string $coverPath): void
    {
        if ($coverPath === null || !str_starts_with($coverPath, self::COVER_UPLOAD_DIR) || str_contains($coverPath, '..')) {
            return;
        }

        $path = dirname(__DIR__, 3) . '/public' . $coverPath;

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function makeTitle(string $text): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $text));

        if (strlen($title) > 80) {
            $title = substr($title, 0, 77) . '...';
        }

        return $title !== '' ? $title : 'Untitled text';
    }
}
