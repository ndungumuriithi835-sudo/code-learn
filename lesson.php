<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$lessonId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$lessonId = $lessonId === false ? 0 : $lessonId;

$statement = db()->prepare(
    'SELECT l.*, lg.name AS language_name, lg.difficulty
     FROM lessons l
     JOIN languages lg ON lg.id = l.language_id
     WHERE l.id = ?'
);
$statement->execute([$lessonId]);
$lesson = $statement->fetch();

if (!$lesson) {
    http_response_code(404);
    $pageTitle = 'Lesson not found';
    $activePage = 'lessons';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="page-head">
        <div>
            <p class="eyebrow">404</p>
            <h1>Lesson not found</h1>
            <p class="lede">The lesson you are looking for does not exist or may have been removed.</p>
        </div>
    </section>
    <div class="card">
        <div class="card-body">
            <a class="btn btn-primary" href="lessons.php">Browse all lessons</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$relatedStatement = db()->prepare(
    'SELECT id, title, duration_minutes
     FROM lessons
     WHERE language_id = ? AND id <> ?
     ORDER BY title ASC
     LIMIT 5'
);
$relatedStatement->execute([$lesson['language_id'], $lesson['id']]);
$related = $relatedStatement->fetchAll();

$extensions = [
    'Assembly'      => 'asm',
    'C'             => 'c',
    'C#'            => 'cs',
    'C++'           => 'cpp',
    'Dart'          => 'dart',
    'Elixir'        => 'ex',
    'Go'            => 'go',
    'Haskell'       => 'hs',
    'HTML & CSS'    => 'html',
    'Java'          => 'java',
    'JavaScript'    => 'js',
    'Kotlin'        => 'kt',
    'Lua'           => 'lua',
    'MATLAB'        => 'm',
    'Perl'          => 'pl',
    'PHP'           => 'php',
    'Python'        => 'py',
    'R'             => 'r',
    'Ruby'          => 'rb',
    'Rust'          => 'rs',
    'Scala'         => 'scala',
    'Shell / Bash'  => 'sh',
    'SQL'           => 'sql',
    'Swift'         => 'swift',
    'TypeScript'    => 'ts',
];
$fileName = 'example.' . ($extensions[$lesson['language_name']] ?? 'txt');

$paragraphs = array_filter(
    array_map('trim', preg_split('/\R{2,}/', trim($lesson['content'])) ?: []),
    static fn (string $paragraph): bool => $paragraph !== ''
);

$pageTitle = $lesson['title'];
$activePage = 'lessons';
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="lessons.php">Lessons</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="lessons.php?language=<?= (int) $lesson['language_id'] ?>"><?= e($lesson['language_name']) ?></a>
    <span class="sep" aria-hidden="true">/</span>
    <span><?= e($lesson['title']) ?></span>
</nav>

<header class="lesson-head">
    <p class="eyebrow"><?= e($lesson['language_name']) ?></p>
    <h1><?= e($lesson['title']) ?></h1>
    <?php if ($lesson['summary']): ?>
        <p class="lede"><?= e($lesson['summary']) ?></p>
    <?php endif; ?>
    <div class="lesson-meta">
        <span class="pill pill-<?= e(level_class($lesson['difficulty'])) ?>"><?= e($lesson['difficulty']) ?></span>
        <span class="meta-text"><?= (int) $lesson['duration_minutes'] ?> min read</span>
        <span class="meta-text">Added <?= e(format_date($lesson['created_at'])) ?></span>
    </div>
</header>

<div class="lesson-layout">
    <article class="card lesson-content">
        <div class="prose">
            <?php foreach ($paragraphs as $paragraph): ?>
                <p><?= nl2br(e($paragraph)) ?></p>
            <?php endforeach; ?>
        </div>

        <?php if ($lesson['code_example']): ?>
            <div class="code-window">
                <div class="code-window-bar">
                    <span></span><span></span><span></span>
                    <em><?= e($fileName) ?></em>
                </div>
                <pre><code><?= e($lesson['code_example']) ?></code></pre>
            </div>
        <?php endif; ?>

        <div class="lesson-actions">
            <a class="btn btn-primary" href="progress.php?lesson=<?= (int) $lesson['id'] ?>">Log my progress</a>
            <a class="btn btn-ghost" href="lessons.php?language=<?= (int) $lesson['language_id'] ?>">
                More <?= e($lesson['language_name']) ?> lessons
            </a>
            <?php if (language_guide($lesson['language_name'])): ?>
                <a class="btn btn-ghost" href="lessons.php?language=<?= (int) $lesson['language_id'] ?>#language-guide">
                    Language handbook
                </a>
            <?php endif; ?>
        </div>
    </article>

    <aside class="card related-card" aria-labelledby="related-heading">
        <div class="card-header">
            <h2 id="related-heading">More in <?= e($lesson['language_name']) ?></h2>
        </div>
        <div class="card-body">
            <?php if ($related): ?>
                <ul class="related-list">
                    <?php foreach ($related as $item): ?>
                        <li>
                            <a href="lesson.php?id=<?= (int) $item['id'] ?>">
                                <?= e($item['title']) ?>
                                <span><?= (int) $item['duration_minutes'] ?> min</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-state">
                    <strong>No other lessons yet</strong>
                    This is the only lesson in <?= e($lesson['language_name']) ?> so far.
                </p>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
