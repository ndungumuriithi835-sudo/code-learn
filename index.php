<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();

$stats = [
    'languages'   => (int) $pdo->query('SELECT COUNT(*) FROM languages')->fetchColumn(),
    'lessons'     => (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn(),
    'learners'    => (int) $pdo->query('SELECT COUNT(*) FROM learners')->fetchColumn(),
    'completions' => (int) $pdo->query("SELECT COUNT(*) FROM progress WHERE status = 'Completed'")->fetchColumn(),
];

$recent = $pdo->query(
    "SELECT p.status, p.score, p.updated_at,
            u.full_name,
            l.title AS lesson_title,
            lg.name AS language_name
     FROM progress p
     JOIN learners u  ON u.id = p.learner_id
     JOIN lessons l   ON l.id = p.lesson_id
     JOIN languages lg ON lg.id = l.language_id
     ORDER BY p.updated_at DESC
     LIMIT 8"
)->fetchAll();

$topLearners = $pdo->query(
    "SELECT u.full_name, u.email, COUNT(p.id) AS completed
     FROM learners u
     LEFT JOIN progress p ON p.learner_id = u.id AND p.status = 'Completed'
     GROUP BY u.id, u.full_name, u.email
     ORDER BY completed DESC, u.full_name ASC
     LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div>
        <p class="eyebrow">Learn by building</p>
        <h1>Every language.<br><span class="grad">One clear path.</span></h1>
        <p>
            CodeLearn brings <?= number_format($stats['languages']) ?> programming languages and
            <?= number_format($stats['lessons']) ?> guided lessons into one place. Pick a lesson,
            follow the examples, then save your progress to a real MySQL database.
        </p>
        <div class="hero-actions">
            <a class="btn btn-light" href="lessons.php">Browse lessons</a>
            <a class="btn btn-ghost-light" href="learners.php">Create a learner profile</a>
        </div>
        <div class="hero-meta">
            <span>HTML · CSS · PHP · MySQL</span>
            <span>Apache / XAMPP</span>
        </div>
    </div>

    <div class="code-window hero-code" aria-hidden="true">
        <div class="code-window-bar">
            <span></span><span></span><span></span>
            <em>learn.py</em>
        </div>
        <pre><code><span class="tok-comment"># start with one language</span>
<span class="tok-key">languages</span> = [<span class="tok-str">"Python"</span>, <span class="tok-str">"JavaScript"</span>, <span class="tok-str">"Rust"</span>]

<span class="tok-key">for</span> language <span class="tok-key">in</span> languages:
    <span class="tok-fn">print</span>(<span class="tok-str">f"Hello, {language}!"</span>)

<span class="tok-comment"># → Hello, Python!</span>
<span class="tok-comment"># → Hello, JavaScript!</span>
<span class="tok-comment"># → Hello, Rust!</span></code></pre>
    </div>
</section>

<?php $pageImageKey = 'dashboard'; require __DIR__ . '/includes/page-image.php'; ?>

<section class="stats" aria-label="Platform statistics">
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">{ }</span>
        <div>
            <strong><?= number_format($stats['languages']) ?></strong>
            <span>Languages</span>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">&lt;/&gt;</span>
        <div>
            <strong><?= number_format($stats['lessons']) ?></strong>
            <span>Lessons</span>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">@</span>
        <div>
            <strong><?= number_format($stats['learners']) ?></strong>
            <span>Learners</span>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">✓</span>
        <div>
            <strong><?= number_format($stats['completions']) ?></strong>
            <span>Completions</span>
        </div>
    </article>
</section>

<div class="section-head">
    <div>
        <h2>Recent activity</h2>
        <p>The latest progress records stored in MySQL.</p>
    </div>
    <a href="progress.php">Log progress →</a>
</div>

<div class="grid-2">
    <section class="card" aria-labelledby="recent-heading">
        <div class="card-header">
            <h2 id="recent-heading">Latest updates</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Learner</th>
                        <th scope="col">Lesson</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $entry): ?>
                        <tr>
                            <td><span class="primary"><?= e($entry['full_name']) ?></span></td>
                            <td>
                                <span class="primary"><?= e($entry['lesson_title']) ?></span>
                                <span class="secondary"><?= e($entry['language_name']) ?></span>
                            </td>
                            <td><span class="pill pill-<?= e(status_class($entry['status'])) ?>"><?= e($entry['status']) ?></span></td>
                            <td class="cell-nowrap"><?= e(format_datetime($entry['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr class="empty-row">
                            <td colspan="4">No progress yet. <a href="progress.php">Log the first lesson</a>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" aria-labelledby="top-heading">
        <div class="card-header">
            <h2 id="top-heading">Top learners</h2>
            <a href="learners.php">View all</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Learner</th>
                        <th scope="col">Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topLearners as $index => $learner): ?>
                        <tr>
                            <td class="rank"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <span class="primary"><?= e($learner['full_name']) ?></span>
                                <span class="secondary"><?= e($learner['email']) ?></span>
                            </td>
                            <td><span class="cell-nowrap"><?= (int) $learner['completed'] ?> lesson<?= (int) $learner['completed'] === 1 ? '' : 's' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$topLearners): ?>
                        <tr class="empty-row">
                            <td colspan="3">No learners yet. <a href="learners.php">Create a profile</a>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="steps" aria-label="How CodeLearn works">
    <article class="step">
        <span>01</span>
        <h3>Pick a language</h3>
        <p>Browse the library, from Python and JavaScript to Rust, Haskell and Assembly.</p>
    </article>
    <article class="step">
        <span>02</span>
        <h3>Follow a lesson</h3>
        <p>Read the explanation, study the code example and try it in your own editor.</p>
    </article>
    <article class="step">
        <span>03</span>
        <h3>Log your progress</h3>
        <p>Submit the progress form. PHP stores it in MySQL and the tables update instantly.</p>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
