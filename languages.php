<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$difficulties = ['Beginner', 'Intermediate', 'Advanced'];

$errors = [];
$form = [
    'name'        => '',
    'paradigm'    => '',
    'difficulty'  => 'Beginner',
    'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $form = [
        'name'        => post('name'),
        'paradigm'    => post('paradigm'),
        'difficulty'  => post('difficulty'),
        'description' => post('description'),
    ];

    $nameLength = mb_strlen($form['name']);

    if ($nameLength < 2) {
        $errors['name'] = 'Enter a language name with at least 2 characters.';
    } elseif ($nameLength > 60) {
        $errors['name'] = 'Keep the language name under 60 characters.';
    }

    if (mb_strlen($form['paradigm']) > 80) {
        $errors['paradigm'] = 'Keep the paradigm under 80 characters.';
    }

    if (!in_array($form['difficulty'], $difficulties, true)) {
        $errors['difficulty'] = 'Choose a valid difficulty level.';
    }

    if (mb_strlen($form['description']) > 600) {
        $errors['description'] = 'Keep the description under 600 characters.';
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO languages (name, paradigm, difficulty, description)
             VALUES (?, ?, ?, ?)'
        );

        try {
            $statement->execute([
                $form['name'],
                $form['paradigm'] !== '' ? $form['paradigm'] : null,
                $form['difficulty'],
                $form['description'] !== '' ? $form['description'] : null,
            ]);

            flash('success', $form['name'] . ' was added to the language library.');
            redirect('languages.php');
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $errors['name'] = 'That language is already in the library.';
            } else {
                throw $exception;
            }
        }
    }
}

$languages = db()->query(
    'SELECT lg.*,
            (SELECT COUNT(*) FROM lessons l WHERE l.language_id = lg.id) AS lesson_count,
            (SELECT COUNT(DISTINCT p.learner_id)
             FROM progress p
             JOIN lessons l ON l.id = p.lesson_id
             WHERE l.language_id = lg.id AND p.status = \'Completed\') AS learner_count
     FROM languages lg
     ORDER BY lg.name ASC'
)->fetchAll();

$pageTitle = 'Languages';
$activePage = 'languages';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Language library</p>
        <h1>Every language in one place</h1>
        <p class="lede">
            Browse <?= count($languages) ?> languages available on CodeLearn, see how many lessons each one has,
            and add anything missing. Every submission is inserted into the MySQL <code>languages</code> table
            and appears below immediately.
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-ghost" href="lessons.php">Browse lessons</a>
    </div>
</section>

<?php $pageImageKey = 'languages'; require __DIR__ . '/includes/page-image.php'; ?>

<div class="split">
    <section class="card form-card" aria-labelledby="add-language-heading">
        <div class="card-header">
            <div>
                <h2 id="add-language-heading">Add a language</h2>
                <p>Extend the library with another language or technology.</p>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="languages.php" novalidate>
                <?= csrf_field() ?>

                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Language name</label>
                    <input type="text" id="name" name="name" value="<?= e($form['name']) ?>"
                           maxlength="60" placeholder="e.g. Zig" required
                           <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php if (isset($errors['name'])): ?>
                        <p class="error-text" id="name-error"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['paradigm']) ? ' has-error' : '' ?>">
                    <label for="paradigm">Paradigm</label>
                    <input type="text" id="paradigm" name="paradigm" value="<?= e($form['paradigm']) ?>"
                           maxlength="80" placeholder="e.g. Systems / concurrency">
                    <p class="hint">Optional — the style the language is best known for.</p>
                    <?php if (isset($errors['paradigm'])): ?>
                        <p class="error-text"><?= e($errors['paradigm']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['difficulty']) ? ' has-error' : '' ?>">
                    <label for="difficulty">Difficulty</label>
                    <select id="difficulty" name="difficulty">
                        <?php foreach ($difficulties as $level): ?>
                            <option value="<?= e($level) ?>"<?= selected($form['difficulty'], $level) ?>><?= e($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['difficulty'])): ?>
                        <p class="error-text"><?= e($errors['difficulty']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
                    <label for="description">Short description</label>
                    <textarea id="description" name="description" maxlength="600"
                              placeholder="What is this language used for?"><?= e($form['description']) ?></textarea>
                    <p class="hint">Optional — shown in the library table.</p>
                    <?php if (isset($errors['description'])): ?>
                        <p class="error-text"><?= e($errors['description']) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Add language</button>
                <p class="form-note">Submitted values are validated on the server and saved with a prepared statement.</p>
            </form>
        </div>
    </section>

    <section class="card" aria-labelledby="library-heading">
        <div class="card-header">
            <div>
                <h2 id="library-heading">Language library</h2>
                <p><?= count($languages) ?> languages · click a lesson count to filter the lesson list.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Language</th>
                        <th scope="col">Paradigm</th>
                        <th scope="col">Level</th>
                        <th scope="col">Lessons</th>
                        <th scope="col">Learners</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($languages as $language): ?>
                        <tr>
                            <td>
                                <span class="lang-id">
                                    <span class="lang-badge" aria-hidden="true"><?= e(language_badge($language['name'])) ?></span>
                                    <span>
                                        <span class="primary"><?= e($language['name']) ?></span>
                                        <?php if ($language['description']): ?>
                                            <span class="secondary"><?= e(excerpt($language['description'], 74)) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td><?= e($language['paradigm'] ?? '—') ?></td>
                            <td>
                                <span class="pill pill-<?= e(level_class($language['difficulty'])) ?>">
                                    <?= e($language['difficulty']) ?>
                                </span>
                            </td>
                            <td class="cell-nowrap">
                                <a href="lessons.php?language=<?= (int) $language['id'] ?>">
                                    <?= (int) $language['lesson_count'] ?> →
                                </a>
                            </td>
                            <td><?= (int) $language['learner_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$languages): ?>
                        <tr class="empty-row">
                            <td colspan="5">No languages yet. Add the first one with the form.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
