<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$languages = db()->query(
    'SELECT lg.id, lg.name, lg.paradigm, lg.difficulty, lg.description,
            COUNT(l.id) AS lesson_count
     FROM languages lg
     LEFT JOIN lessons l ON l.language_id = lg.id
     GROUP BY lg.id, lg.name, lg.paradigm, lg.difficulty, lg.description
     ORDER BY lg.name ASC'
)->fetchAll();

$filterId = filter_var($_GET['language'] ?? 0, FILTER_VALIDATE_INT);
$filterId = $filterId === false ? 0 : $filterId;

$errors = [];
$form = [
    'language_id'      => $filterId > 0 ? (string) $filterId : (string) ($languages[0]['id'] ?? ''),
    'title'            => '',
    'summary'          => '',
    'duration_minutes' => '30',
    'content'          => '',
    'code_example'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $form = [
        'language_id'      => post('language_id'),
        'title'            => post('title'),
        'summary'          => post('summary'),
        'duration_minutes' => post('duration_minutes'),
        'content'          => post('content'),
        'code_example'     => post('code_example'),
    ];

    $languageIds = array_map('intval', array_column($languages, 'id'));
    $languageId = filter_var($form['language_id'], FILTER_VALIDATE_INT);

    if ($languageId === false || !in_array($languageId, $languageIds, true)) {
        $errors['language_id'] = 'Choose a language from the library.';
    }

    $titleLength = mb_strlen($form['title']);
    if ($titleLength < 3) {
        $errors['title'] = 'Give the lesson a title with at least 3 characters.';
    } elseif ($titleLength > 150) {
        $errors['title'] = 'Keep the title under 150 characters.';
    }

    if (mb_strlen($form['summary']) > 255) {
        $errors['summary'] = 'Keep the summary under 255 characters.';
    }

    $duration = filter_var($form['duration_minutes'], FILTER_VALIDATE_INT);
    if ($duration === false || $duration < 5 || $duration > 600) {
        $errors['duration_minutes'] = 'Duration must be a whole number between 5 and 600 minutes.';
    }

    if (mb_strlen($form['content']) < 20) {
        $errors['content'] = 'Write at least 20 characters of lesson content.';
    }

    if (mb_strlen($form['code_example']) > 5000) {
        $errors['code_example'] = 'Keep the code example under 5000 characters.';
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $languageId,
            $form['title'],
            $form['summary'] !== '' ? $form['summary'] : null,
            $form['content'],
            $form['code_example'] !== '' ? $form['code_example'] : null,
            $duration,
        ]);

        flash('success', 'Lesson "' . $form['title'] . '" was added to the curriculum.');
        redirect('lessons.php?language=' . $languageId);
    }
}

$sql = 'SELECT l.*, lg.name AS language_name, lg.difficulty
        FROM lessons l
        JOIN languages lg ON lg.id = l.language_id';
$params = [];

if ($filterId > 0) {
    $sql .= ' WHERE l.language_id = ?';
    $params[] = $filterId;
}

$sql .= ' ORDER BY l.created_at DESC, l.id DESC';

$statement = db()->prepare($sql);
$statement->execute($params);
$lessonRows = $statement->fetchAll();

$activeLanguage = null;
$activeLanguageRow = null;
foreach ($languages as $language) {
    if ((int) $language['id'] === $filterId) {
        $activeLanguage = $language['name'];
        $activeLanguageRow = $language;
        break;
    }
}

$activeGuide = $activeLanguage !== null ? language_guide($activeLanguage) : null;

$pageTitle = 'Lessons';
$activePage = 'lessons';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Curriculum</p>
        <h1>Guided lessons</h1>
        <p class="lede">
            Each lesson explains one idea and shows a working example. Add a lesson with the form and the
            record is inserted into MySQL, then rendered in the table below.
        </p>
    </div>
    <div class="head-actions">
        <?php if (!$filterId): ?>
            <a class="btn btn-ghost" href="#language-handbook">Language handbook</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="languages.php">Language library</a>
    </div>
</section>

<?php $pageImageKey = 'lessons'; require __DIR__ . '/includes/page-image.php'; ?>

<?php if ($activeGuide): ?>
    <section class="card language-guide" id="language-guide" aria-labelledby="language-guide-heading">
        <div class="guide-head">
            <div>
                <p class="eyebrow">Language handbook</p>
                <h2 id="language-guide-heading">Learn <?= e($activeLanguage) ?></h2>
                <p class="guide-tagline"><?= e($activeGuide['tagline']) ?></p>
            </div>
            <div class="guide-meta">
                <span class="pill pill-<?= e(level_class($activeLanguageRow['difficulty'])) ?>"><?= e($activeLanguageRow['difficulty']) ?></span>
                <span class="meta-text">
                    <?= (int) $activeLanguageRow['lesson_count'] ?>
                    lesson<?= (int) $activeLanguageRow['lesson_count'] === 1 ? '' : 's' ?> in this track
                </span>
                <a class="btn btn-ghost btn-small" href="#lesson-list-heading">Jump to lessons</a>
            </div>
        </div>

        <div class="guide-grid">
            <div class="guide-panel guide-span-2">
                <h3>What it is</h3>
                <p><?= nl2br(e($activeGuide['overview'])) ?></p>
            </div>

            <div class="guide-panel">
                <h3>Core concepts</h3>
                <ul class="guide-list">
                    <?php foreach ($activeGuide['concepts'] as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="guide-panel">
                <h3>Syntax to remember</h3>
                <ul class="guide-list guide-syntax">
                    <?php foreach ($activeGuide['syntax'] as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="guide-panel guide-span-2">
                <h3><?= e($activeGuide['example_title']) ?></h3>
                <div class="code-window">
                    <div class="code-window-bar">
                        <span></span><span></span><span></span>
                        <em><?= e($activeGuide['example_file']) ?></em>
                    </div>
                    <pre><code><?= e($activeGuide['example']) ?></code></pre>
                </div>
            </div>

            <div class="guide-panel">
                <h3>Common pitfalls</h3>
                <ul class="guide-list guide-warn">
                    <?php foreach ($activeGuide['pitfalls'] as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="guide-panel">
                <h3>Where it is used</h3>
                <p><?= e($activeGuide['used_for']) ?></p>
            </div>

            <div class="guide-panel">
                <h3>Learning path</h3>
                <ol class="guide-list">
                    <?php foreach ($activeGuide['path'] as $step): ?>
                        <li><?= e($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="guide-panel">
                <h3>Keep learning</h3>
                <p>
                    Official documentation:
                    <a href="<?= e($activeGuide['docs']) ?>" target="_blank" rel="noopener"><?= e($activeGuide['docs_label']) ?></a>
                </p>
                <a class="btn btn-primary btn-block" href="#lesson-list-heading">Start the <?= e($activeLanguage) ?> lessons</a>
            </div>
        </div>
    </section>
<?php elseif ($activeLanguage && !$activeGuide): ?>
    <section class="card section-card">
        <div class="card-body">
            <p class="empty-state">
                <strong>No handbook entry for <?= e($activeLanguage) ?> yet</strong>
                The lessons in this track are still available below. Add a guide in
                <code>data/language-guides.php</code> to complete it.
            </p>
        </div>
    </section>
<?php endif; ?>

<div class="split">
    <section class="card form-card" aria-labelledby="add-lesson-heading">
        <div class="card-header">
            <div>
                <h2 id="add-lesson-heading">Add a lesson</h2>
                <p>Create a new lesson for any language in the library.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$languages): ?>
                <div class="empty-state">
                    <strong>No languages yet</strong>
                    Add a language before creating lessons.
                    <a class="btn btn-primary" href="languages.php">Go to languages</a>
                </div>
            <?php else: ?>
                <form method="post" action="lessons.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="field<?= isset($errors['language_id']) ? ' has-error' : '' ?>">
                        <label for="language_id">Language</label>
                        <select id="language_id" name="language_id" required>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?= (int) $language['id'] ?>"<?= selected($form['language_id'], $language['id']) ?>>
                                    <?= e($language['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['language_id'])): ?>
                            <p class="error-text"><?= e($errors['language_id']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['title']) ? ' has-error' : '' ?>">
                        <label for="title">Lesson title</label>
                        <input type="text" id="title" name="title" value="<?= e($form['title']) ?>"
                               maxlength="150" placeholder="e.g. Arrays and slices" required
                               <?= isset($errors['title']) ? 'aria-invalid="true" aria-describedby="title-error"' : '' ?>>
                        <?php if (isset($errors['title'])): ?>
                            <p class="error-text" id="title-error"><?= e($errors['title']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['summary']) ? ' has-error' : '' ?>">
                        <label for="summary">Short summary</label>
                        <input type="text" id="summary" name="summary" value="<?= e($form['summary']) ?>"
                               maxlength="255" placeholder="One sentence shown in the lesson table">
                        <?php if (isset($errors['summary'])): ?>
                            <p class="error-text"><?= e($errors['summary']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['duration_minutes']) ? ' has-error' : '' ?>">
                        <label for="duration_minutes">Duration (minutes)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes"
                               value="<?= e($form['duration_minutes']) ?>" min="5" max="600" step="5" required>
                        <?php if (isset($errors['duration_minutes'])): ?>
                            <p class="error-text"><?= e($errors['duration_minutes']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['content']) ? ' has-error' : '' ?>">
                        <label for="content">Lesson content</label>
                        <textarea id="content" name="content" rows="7"
                                  placeholder="Explain the concept in plain language." required><?= e($form['content']) ?></textarea>
                        <p class="hint">Plain text. Blank lines become separate paragraphs.</p>
                        <?php if (isset($errors['content'])): ?>
                            <p class="error-text"><?= e($errors['content']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['code_example']) ? ' has-error' : '' ?>">
                        <label for="code_example">Code example</label>
                        <textarea id="code_example" name="code_example" rows="7" class="code-input"
                                  placeholder="Paste a short, working example."><?= e($form['code_example']) ?></textarea>
                        <p class="hint">Optional. Rendered in a code window, escaped for safety.</p>
                        <?php if (isset($errors['code_example'])): ?>
                            <p class="error-text"><?= e($errors['code_example']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-primary btn-block" type="submit">Add lesson</button>
                    <p class="form-note">Saved to the <code>lessons</code> table with a prepared statement.</p>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="card" aria-labelledby="lesson-list-heading">
        <div class="card-header">
            <div>
                <h2 id="lesson-list-heading">Lesson list</h2>
                <p>
                    <?= count($lessonRows) ?> lesson<?= count($lessonRows) === 1 ? '' : 's' ?>
                    <?= $activeLanguage ? 'in ' . e($activeLanguage) : 'across all languages' ?>.
                </p>
            </div>
            <form class="filter-bar" method="get" action="lessons.php#language-guide">
                <label class="sr-only" for="language-filter">Filter lessons by language</label>
                <select id="language-filter" name="language">
                    <option value="0">All languages</option>
                    <?php foreach ($languages as $language): ?>
                        <option value="<?= (int) $language['id'] ?>"<?= selected($filterId, $language['id']) ?>>
                            <?= e($language['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-ghost btn-small" type="submit">Filter</button>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Lesson</th>
                        <th scope="col">Language</th>
                        <th scope="col">Length</th>
                        <th scope="col">Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessonRows as $lesson): ?>
                        <tr>
                            <td>
                                <a class="primary" href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= e($lesson['title']) ?></a>
                                <?php if ($lesson['summary']): ?>
                                    <span class="secondary"><?= e(excerpt($lesson['summary'], 92)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-nowrap"><?= e($lesson['language_name']) ?></td>
                            <td class="cell-nowrap"><?= (int) $lesson['duration_minutes'] ?> min</td>
                            <td class="cell-nowrap"><?= e(format_date($lesson['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$lessonRows): ?>
                        <tr class="empty-row">
                            <td colspan="4">
                                <?= $filterId > 0 ? 'No lessons for this language yet. Add one with the form.' : 'No lessons yet. Add the first one with the form.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if (!$filterId): ?>
    <section class="card handbook section-card" id="language-handbook" aria-labelledby="handbook-heading">
        <div class="card-header">
            <div>
                <h2 id="handbook-heading">Language handbook</h2>
                <p>Open any language for its concepts, syntax, pitfalls, worked example and learning path.</p>
            </div>
            <span class="meta-text"><?= count(language_guides()) ?> guides</span>
        </div>
        <div class="handbook-grid">
            <?php foreach ($languages as $language): ?>
                <?php $cardGuide = language_guide($language['name']); ?>
                <?php if (!$cardGuide) { continue; } ?>
                <a class="handbook-card" href="lessons.php?language=<?= (int) $language['id'] ?>#language-guide">
                    <span class="handbook-top">
                        <span class="lang-badge" aria-hidden="true"><?= e(language_badge($language['name'])) ?></span>
                        <span class="handbook-title"><?= e($language['name']) ?></span>
                    </span>
                    <span class="handbook-tagline"><?= e($cardGuide['tagline']) ?></span>
                    <span class="handbook-meta">
                        <?= e($language['paradigm']) ?> ·
                        <?= (int) $language['lesson_count'] ?>
                        lesson<?= (int) $language['lesson_count'] === 1 ? '' : 's' ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
