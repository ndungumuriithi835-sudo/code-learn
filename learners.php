<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$experienceLevels = ['Beginner', 'Some experience', 'Professional'];

$errors = [];
$form = [
    'full_name'        => '',
    'email'            => '',
    'country'          => '',
    'experience_level' => 'Beginner',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $form = [
        'full_name'        => post('full_name'),
        'email'            => post('email'),
        'country'          => post('country'),
        'experience_level' => post('experience_level'),
    ];

    $nameLength = mb_strlen($form['full_name']);
    if ($nameLength < 2) {
        $errors['full_name'] = 'Enter a name with at least 2 characters.';
    } elseif ($nameLength > 100) {
        $errors['full_name'] = 'Keep the name under 100 characters.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (mb_strlen($form['email']) > 150) {
        $errors['email'] = 'Keep the email under 150 characters.';
    }

    if (mb_strlen($form['country']) > 60) {
        $errors['country'] = 'Keep the country under 60 characters.';
    }

    if (!in_array($form['experience_level'], $experienceLevels, true)) {
        $errors['experience_level'] = 'Choose a valid experience level.';
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO learners (full_name, email, country, experience_level)
             VALUES (?, ?, ?, ?)'
        );

        try {
            $statement->execute([
                $form['full_name'],
                $form['email'],
                $form['country'] !== '' ? $form['country'] : null,
                $form['experience_level'],
            ]);

            flash('success', 'Profile created for ' . $form['full_name'] . '. You can log progress now.');
            redirect('learners.php');
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $errors['email'] = 'That email address already has a profile.';
            } else {
                throw $exception;
            }
        }
    }
}

$learners = db()->query(
    'SELECT u.*,
            (SELECT COUNT(*) FROM progress p
             WHERE p.learner_id = u.id AND p.status = \'Completed\') AS completed_count,
            (SELECT MAX(p.updated_at) FROM progress p
             WHERE p.learner_id = u.id) AS last_active
     FROM learners u
     ORDER BY u.created_at DESC, u.id DESC'
)->fetchAll();

$pageTitle = 'Learners';
$activePage = 'learners';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Community</p>
        <h1>Learner profiles</h1>
        <p class="lede">
            Create a profile to start tracking lessons. The registration form inserts a row into MySQL,
            and the table below shows every learner with the number of lessons they have completed.
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-ghost" href="progress.php">Log progress</a>
    </div>
</section>

<?php $pageImageKey = 'learners'; require __DIR__ . '/includes/page-image.php'; ?>

<div class="split">
    <section class="card form-card" aria-labelledby="register-heading">
        <div class="card-header">
            <div>
                <h2 id="register-heading">Create a profile</h2>
                <p>Takes less than a minute. No password required for this demo.</p>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="learners.php" novalidate>
                <?= csrf_field() ?>

                <div class="field<?= isset($errors['full_name']) ? ' has-error' : '' ?>">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>"
                           maxlength="100" placeholder="e.g. Amara Okafor" required autocomplete="name"
                           <?= isset($errors['full_name']) ? 'aria-invalid="true" aria-describedby="full-name-error"' : '' ?>>
                    <?php if (isset($errors['full_name'])): ?>
                        <p class="error-text" id="full-name-error"><?= e($errors['full_name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e($form['email']) ?>"
                           maxlength="150" placeholder="you@example.com" required autocomplete="email"
                           <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <p class="hint">Used as the unique identifier for each learner.</p>
                    <?php if (isset($errors['email'])): ?>
                        <p class="error-text" id="email-error"><?= e($errors['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['country']) ? ' has-error' : '' ?>">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" value="<?= e($form['country']) ?>"
                           maxlength="60" placeholder="Optional">
                    <?php if (isset($errors['country'])): ?>
                        <p class="error-text"><?= e($errors['country']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['experience_level']) ? ' has-error' : '' ?>">
                    <label for="experience_level">Programming experience</label>
                    <select id="experience_level" name="experience_level">
                        <?php foreach ($experienceLevels as $level): ?>
                            <option value="<?= e($level) ?>"<?= selected($form['experience_level'], $level) ?>><?= e($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['experience_level'])): ?>
                        <p class="error-text"><?= e($errors['experience_level']) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Create profile</button>
                <p class="form-note">Emails are unique, and every value is stored with a prepared statement.</p>
            </form>
        </div>
    </section>

    <section class="card" aria-labelledby="learner-list-heading">
        <div class="card-header">
            <div>
                <h2 id="learner-list-heading">Registered learners</h2>
                <p><?= count($learners) ?> profile<?= count($learners) === 1 ? '' : 's' ?> on CodeLearn.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Learner</th>
                        <th scope="col">Country</th>
                        <th scope="col">Experience</th>
                        <th scope="col">Completed</th>
                        <th scope="col">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($learners as $learner): ?>
                        <tr>
                            <td>
                                <span class="primary"><?= e($learner['full_name']) ?></span>
                                <span class="secondary"><?= e($learner['email']) ?></span>
                            </td>
                            <td><?= e($learner['country'] ?? '—') ?></td>
                            <td>
                                <span class="pill pill-<?= e(level_class($learner['experience_level'])) ?>">
                                    <?= e($learner['experience_level']) ?>
                                </span>
                            </td>
                            <td class="cell-nowrap"><?= (int) $learner['completed_count'] ?> lesson<?= (int) $learner['completed_count'] === 1 ? '' : 's' ?></td>
                            <td class="cell-nowrap"><?= e(format_date($learner['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$learners): ?>
                        <tr class="empty-row">
                            <td colspan="5">No profiles yet. Create the first one with the form.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
