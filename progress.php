<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$learners = db()->query(
    'SELECT id, full_name FROM learners ORDER BY full_name ASC'
)->fetchAll();

$lessonOptions = db()->query(
    'SELECT l.id, l.title, lg.name AS language_name
     FROM lessons l
     JOIN languages lg ON lg.id = l.language_id
     ORDER BY lg.name ASC, l.title ASC'
)->fetchAll();

$learnerNames = array_column($learners, 'full_name', 'id');
$lessonTitles = array_column($lessonOptions, 'title', 'id');

$groupedLessons = [];
foreach ($lessonOptions as $option) {
    $groupedLessons[$option['language_name']][] = $option;
}

$errors = [];
$form = [
    'learner_id' => '',
    'lesson_id'  => '',
    'status'     => 'In progress',
    'score'      => '',
    'notes'      => '',
];

$preselected = filter_var($_GET['lesson'] ?? 0, FILTER_VALIDATE_INT);
if ($preselected !== false && isset($lessonTitles[$preselected])) {
    $form['lesson_id'] = (string) $preselected;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $form = [
        'learner_id' => post('learner_id'),
        'lesson_id'  => post('lesson_id'),
        'status'     => post('status'),
        'score'      => post('score'),
        'notes'      => post('notes'),
    ];

    $learnerId = filter_var($form['learner_id'], FILTER_VALIDATE_INT);
    if ($learnerId === false || !isset($learnerNames[$learnerId])) {
        $errors['learner_id'] = 'Choose a learner.';
    }

    $lessonId = filter_var($form['lesson_id'], FILTER_VALIDATE_INT);
    if ($lessonId === false || !isset($lessonTitles[$lessonId])) {
        $errors['lesson_id'] = 'Choose a lesson.';
    }

    if (!in_array($form['status'], ['In progress', 'Completed'], true)) {
        $errors['status'] = 'Choose a valid status.';
    }

    $score = null;
    if ($form['score'] !== '') {
        $validatedScore = filter_var(
            $form['score'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 100]]
        );

        if ($validatedScore === false) {
            $errors['score'] = 'Score must be a whole number between 0 and 100.';
        } else {
            $score = $validatedScore;
        }
    }

    if (mb_strlen($form['notes']) > 255) {
        $errors['notes'] = 'Keep the notes under 255 characters.';
    }

    if (!$errors) {
        $completedAt = $form['status'] === 'Completed' ? date('Y-m-d H:i:s') : null;
        $notes = $form['notes'] !== '' ? $form['notes'] : null;

        $statement = db()->prepare(
            'INSERT INTO progress (learner_id, lesson_id, status, score, notes, completed_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status = ?,
                score = ?,
                notes = ?,
                completed_at = ?'
        );
        $statement->execute([
            $learnerId,
            $lessonId,
            $form['status'],
            $score,
            $notes,
            $completedAt,
            $form['status'],
            $score,
            $notes,
            $completedAt,
        ]);

        flash(
            'success',
            'Progress saved for ' . $learnerNames[$learnerId] . ' on "' . $lessonTitles[$lessonId] . '".'
        );
        redirect('progress.php');
    }
}

$entries = db()->query(
    'SELECT p.status, p.score, p.notes, p.updated_at,
            u.full_name, u.email,
            l.title AS lesson_title,
            lg.name AS language_name
     FROM progress p
     JOIN learners u    ON u.id = p.learner_id
     JOIN lessons l     ON l.id = p.lesson_id
     JOIN languages lg  ON lg.id = l.language_id
     ORDER BY p.updated_at DESC
     LIMIT 50'
)->fetchAll();

$pageTitle = 'Progress';
$activePage = 'progress';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Tracking</p>
        <h1>Log your progress</h1>
        <p class="lede">
            Record what you have finished or what you are working on. The form writes to MySQL, and saving the
            same learner and lesson again updates the existing record instead of duplicating it.
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-ghost" href="learners.php">View learners</a>
    </div>
</section>

<?php $pageImageKey = 'progress'; require __DIR__ . '/includes/page-image.php'; ?>

<div class="split">
    <section class="card form-card" aria-labelledby="log-progress-heading">
        <div class="card-header">
            <div>
                <h2 id="log-progress-heading">Save a progress entry</h2>
                <p>One record per learner and lesson pair.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$learners || !$lessonOptions): ?>
                <div class="empty-state">
                    <strong>Setup needed</strong>
                    <?php if (!$learners): ?>
                        Create a learner profile before logging progress.
                    <?php else: ?>
                        Add at least one lesson before logging progress.
                    <?php endif; ?>
                    <a class="btn btn-primary" href="<?= $learners ? 'lessons.php' : 'learners.php' ?>">
                        <?= $learners ? 'Add a lesson' : 'Create a profile' ?>
                    </a>
                </div>
            <?php else: ?>
                <form method="post" action="progress.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="field<?= isset($errors['learner_id']) ? ' has-error' : '' ?>">
                        <label for="learner_id">Learner</label>
                        <select id="learner_id" name="learner_id" required>
                            <option value="">Select a learner</option>
                            <?php foreach ($learners as $learner): ?>
                                <option value="<?= (int) $learner['id'] ?>"<?= selected($form['learner_id'], $learner['id']) ?>>
                                    <?= e($learner['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['learner_id'])): ?>
                            <p class="error-text"><?= e($errors['learner_id']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['lesson_id']) ? ' has-error' : '' ?>">
                        <label for="lesson_id">Lesson</label>
                        <select id="lesson_id" name="lesson_id" required>
                            <option value="">Select a lesson</option>
                            <?php foreach ($groupedLessons as $languageName => $items): ?>
                                <optgroup label="<?= e($languageName) ?>">
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?= (int) $item['id'] ?>"<?= selected($form['lesson_id'], $item['id']) ?>>
                                            <?= e($item['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['lesson_id'])): ?>
                            <p class="error-text"><?= e($errors['lesson_id']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field-row">
                        <div class="field<?= isset($errors['status']) ? ' has-error' : '' ?>">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="In progress"<?= selected($form['status'], 'In progress') ?>>In progress</option>
                                <option value="Completed"<?= selected($form['status'], 'Completed') ?>>Completed</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <p class="error-text"><?= e($errors['status']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field<?= isset($errors['score']) ? ' has-error' : '' ?>">
                            <label for="score">Score (optional)</label>
                            <input type="number" id="score" name="score" value="<?= e($form['score']) ?>"
                                   min="0" max="100" step="1" placeholder="0 – 100">
                            <?php if (isset($errors['score'])): ?>
                                <p class="error-text"><?= e($errors['score']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="field<?= isset($errors['notes']) ? ' has-error' : '' ?>">
                        <label for="notes">Notes</label>
                        <input type="text" id="notes" name="notes" value="<?= e($form['notes']) ?>"
                               maxlength="255" placeholder="Optional — what clicked, what needs review">
                        <?php if (isset($errors['notes'])): ?>
                            <p class="error-text"><?= e($errors['notes']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-primary btn-block" type="submit">Save progress</button>
                    <p class="form-note">
                        Uses <code>INSERT … ON DUPLICATE KEY UPDATE</code> so each learner and lesson pair stays unique.
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="card" aria-labelledby="progress-list-heading">
        <div class="card-header">
            <div>
                <h2 id="progress-list-heading">Progress log</h2>
                <p>The 50 most recently updated records.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Learner</th>
                        <th scope="col">Lesson</th>
                        <th scope="col">Status</th>
                        <th scope="col">Score</th>
                        <th scope="col">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td>
                                <span class="primary"><?= e($entry['full_name']) ?></span>
                                <span class="secondary"><?= e($entry['email']) ?></span>
                            </td>
                            <td>
                                <span class="primary"><?= e($entry['lesson_title']) ?></span>
                                <span class="secondary">
                                    <?= e($entry['language_name']) ?><?= $entry['notes'] ? ' · ' . e(excerpt($entry['notes'], 52)) : '' ?>
                                </span>
                            </td>
                            <td>
                                <span class="pill pill-<?= e(status_class($entry['status'])) ?>"><?= e($entry['status']) ?></span>
                            </td>
                            <td>
                                <?php if ($entry['score'] !== null): ?>
                                    <span class="score"><?= (int) $entry['score'] ?></span>
                                <?php else: ?>
                                    <span class="score-empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-nowrap"><?= e(format_datetime($entry['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$entries): ?>
                        <tr class="empty-row">
                            <td colspan="5">Nothing logged yet. Use the form to save your first lesson.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
