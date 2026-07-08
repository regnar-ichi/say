<?php

// Create controllers/page directory if needed
@mkdir(__DIR__ . '/../app/Controllers/Page', 0755, true);

// File content for DashboardController
$dashboard = <<<'PHP'
<?php

namespace App\Controllers\Page;

use App\Core\View;

class DashboardController
{
    public function index(): void
    {
        View::render('pages/dashboard', [
            'title' => 'Dashboard'
        ]);
    }
}
PHP;

// File content for WordsPageController
$words = <<<'PHP'
<?php

namespace App\Controllers\Page;

use App\Core\View;
use App\Models\Word;

class WordsPageController extends Controller
{
    public function index(): void
    {
        $words = Word::getAll();

        $this->view('pages/words/index', [
            'title' => 'Words',
            'words' => $words
        ]);
    }
}
PHP;

// File content for TestsPageController
$tests = <<<'PHP'
<?php

namespace App\Controllers\Page;

class TestsPageController extends Controller
{
    public function index(): void
    {
        $this->view('pages/tests/index', [
            'title' => 'Tests'
        ]);
    }
}
PHP;

// File content for WordEditPageController
$edit = <<<'PHP'
<?php

namespace App\Controllers\Page;

use App\Models\Word;

class WordEditPageController extends Controller
{
    public function index(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $word = $id > 0 ? Word::find($id) : null;

        $this->view('pages/words/edit', [
            'title' => 'Edit word',
            'word' => $word
        ]);
    }
}
PHP;

// Create the files
$baseDir = __DIR__ . '/../app/Controllers/Page';

file_put_contents($baseDir . '/DashboardController.php', $dashboard);
file_put_contents($baseDir . '/WordsPageController.php', $words);
file_put_contents($baseDir . '/TestsPageController.php', $tests);
file_put_contents($baseDir . '/WordEditPageController.php', $edit);

// Delete old files from Controllers directory
@unlink(__DIR__ . '/../app/Controllers/DashboardController.php');
@unlink(__DIR__ . '/../app/Controllers/WordsPageController.php');
@unlink(__DIR__ . '/../app/Controllers/TestsPageController.php');
@unlink(__DIR__ . '/../app/Controllers/WordEditPageController.php');

echo "Migration complete!";
