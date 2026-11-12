<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/admin.php';

$isAdmin = ($_SESSION['is_admin'] ?? false) === true;
$messagesReady = table_exists('contact_messages');
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = post('action');

    if ($action === 'login') {
        $password = post('password');

        if ($password !== '' && password_verify($password, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $isAdmin = true;
            flash('success', 'Welcome back. You are signed in to the admin area.');
            redirect('admin.php');
        }

        $loginError = 'That password is not correct. Try again.';
    } elseif ($action === 'logout') {
        if ($isAdmin) {
            unset($_SESSION['is_admin']);
            session_regenerate_id(true);
            flash('success', 'You have been signed out of the admin area.');
        }
        redirect('admin.php');
    } elseif ($action === 'delete_message') {
        if (!$isAdmin) {
            flash('error', 'Sign in before deleting messages.');
            redirect('admin.php');
        }

        $messageId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($messageId === false || $messageId < 1) {
            flash('error', 'That message could not be found.');
            redirect('admin.php');
        }

        if (!$messagesReady) {
            flash('error', 'The contact_messages table is missing, so there was nothing to delete.');
            redirect('admin.php');
        }

        $statement = db()->prepare('DELETE FROM contact_messages WHERE id = ?');
        $statement->execute([$messageId]);

        flash(
            $statement->rowCount() > 0 ? 'success' : 'error',
            $statement->rowCount() > 0 ? 'Message deleted from the inbox.' : 'That message was already removed.'
        );
        redirect('admin.php');
    } elseif ($action === 'delete_lesson' || $action === 'delete_learner') {
        if (!$isAdmin) {
            flash('error', 'Sign in before deleting records.');
            redirect('admin.php');
        }

        $recordId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($recordId === false || $recordId < 1) {
            flash('error', 'That record could not be found.');
            redirect('admin.php');
        }

        $isLesson = $action === 'delete_lesson';
        $statement = db()->prepare(
            $isLesson ? 'DELETE FROM lessons WHERE id = ?' : 'DELETE FROM learners WHERE id = ?'
        );
        $statement->execute([$recordId]);

        if ($statement->rowCount() > 0) {
            flash(
                'success',
                $isLesson
                    ? 'Lesson deleted. Its progress records were removed with it.'
                    : 'Learner deleted. All of their progress records were removed too.'
            );
        } else {
            flash('error', $isLesson ? 'That lesson was already removed.' : 'That learner was already removed.');
        }
        redirect('admin.php');
    }
}

$pageTitle = $isAdmin ? 'Admin' : 'Admin sign in';
$activePage = 'admin';

if (!$isAdmin) {
    require __DIR__ . '/includes/header.php';
    ?>

    <div class="auth-wrap">
        <section class="card auth-card" aria-labelledby="admin-login-heading">
            <div class="auth-mark" aria-hidden="true">AD</div>
            <p class="eyebrow">Staff only</p>
            <h1 id="admin-login-heading">Admin sign in</h1>
            <p class="lede">
                The inbox contains messages submitted through the contact form. Sign in to read,
                review and delete them.
            </p>

            <?php if ($loginError !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($loginError) ?></div>
            <?php endif; ?>

            <form method="post" action="admin.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">

                <div class="field<?= $loginError !== '' ? ' has-error' : '' ?>">
                    <label for="password">Admin password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password"
                           required autocomplete="current-password" autofocus
                           <?= $loginError !== '' ? 'aria-invalid="true"' : '' ?>>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Sign in</button>
            </form>

            <p class="auth-hint">
                Demo password: <code>CodeLearn@2026</code> — change the hash in
                <code>config/admin.php</code> before deploying.
            </p>
        </section>
    </div>

    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$languageCount = (int) db()->query('SELECT COUNT(*) FROM languages')->fetchColumn();
$lessonCount = (int) db()->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
$learnerCount = (int) db()->query('SELECT COUNT(*) FROM learners')->fetchColumn();
$messageCount = $messagesReady
    ? (int) db()->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()
    : 0;

$messages = $messagesReady
    ? db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC, id DESC')->fetchAll()
    : [];

$learners = db()->query(
    'SELECT u.id, u.full_name, u.email, u.experience_level, u.created_at,
            (SELECT COUNT(*) FROM progress p
             WHERE p.learner_id = u.id AND p.status = \'Completed\') AS completed_count
     FROM learners u
     ORDER BY u.created_at DESC, u.id DESC'
)->fetchAll();

$lessons = db()->query(
    'SELECT l.id, l.title, l.duration_minutes, l.created_at, lg.name AS language
     FROM lessons l
     JOIN languages lg ON lg.id = l.language_id
     ORDER BY l.created_at DESC, l.id DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Dashboard</h1>
        <p class="lede">
            You are signed in. Review contact messages, add or delete lessons, manage learner
            profiles and their progress, and curate the image library.
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-ghost" href="contact.php">Open contact form</a>
        <form method="post" action="admin.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-ghost" type="submit">Sign out</button>
        </form>
    </div>
</section>

<?php $pageImageKey = 'admin'; require __DIR__ . '/includes/page-image.php'; ?>

<div class="stats">
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">&lt;&gt;</span>
        <div>
            <strong><?= number_format($languageCount) ?></strong>
            <span>Languages</span>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">//</span>
        <div>
            <strong><?= number_format($lessonCount) ?></strong>
            <span>Lessons</span>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">@</span>
        <div>
            <strong><?= number_format($learnerCount) ?></strong>
            <span>Learners</span>
        </div>
    </article>
    <article class="stat">
        <span class="stat-icon" aria-hidden="true">&#9993;</span>
        <div>
            <strong><?= number_format($messageCount) ?></strong>
            <span>Messages</span>
        </div>
    </article>
</div>

<?php if (!$messagesReady): ?>
    <div class="alert alert-error" role="alert">
        The <code>contact_messages</code> table is missing. Re-import <code>database.sql</code> to enable the inbox.
    </div>
<?php endif; ?>

<section class="card section-card" aria-labelledby="inbox-heading">
    <div class="card-header">
        <div>
            <h2 id="inbox-heading">Contact inbox</h2>
            <p>
                <?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?>
                submitted through the contact form.
            </p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">From</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Message</th>
                    <th scope="col">Received</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $message): ?>
                    <tr>
                        <td>
                            <span class="primary"><?= e($message['name']) ?></span>
                            <span class="secondary"><?= e($message['email']) ?></span>
                        </td>
                        <td class="cell-nowrap"><?= e($message['subject']) ?></td>
                        <td>
                            <p class="message-body"><?= nl2br(e($message['message'])) ?></p>
                        </td>
                        <td class="cell-nowrap"><?= e(format_datetime($message['created_at'])) ?></td>
                        <td>
                            <form method="post" action="admin.php" class="inline-form"
                                  onsubmit="return confirm('Delete this message permanently?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_message">
                                <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit"
                                        aria-label="Delete message from <?= e($message['name']) ?>">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$messages): ?>
                    <tr class="empty-row">
                        <td colspan="5">The inbox is empty. New contact messages will appear here.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-actions" aria-labelledby="actions-heading">
    <div class="section-head">
        <div>
            <h2 id="actions-heading">Quick actions</h2>
            <p>Manage the content that powers the learning pages.</p>
        </div>
    </div>
    <div class="action-grid">
        <a class="action-card" href="languages.php">
            <span class="action-mark" aria-hidden="true">&lt;&gt;</span>
            <strong>Add a language</strong>
            <span>Grow the catalogue beyond the seeded 25 languages.</span>
        </a>
        <a class="action-card" href="lessons.php">
            <span class="action-mark" aria-hidden="true">//</span>
            <strong>Add a lesson</strong>
            <span>Attach a new lesson, code example and duration to a language.</span>
        </a>
        <a class="action-card" href="learners.php">
            <span class="action-mark" aria-hidden="true">@</span>
            <strong>Register a learner</strong>
            <span>Create a profile so progress can be tracked.</span>
        </a>
        <a class="action-card" href="progress.php">
            <span class="action-mark" aria-hidden="true">%</span>
            <strong>Log progress</strong>
            <span>Record a score or completion for a learner and lesson.</span>
        </a>
        <a class="action-card" href="admin-media.php">
            <span class="action-mark" aria-hidden="true">IMG</span>
            <strong>Media library</strong>
            <span>Upload photos or download images from the internet, then manage them.</span>
        </a>
    </div>
</section>

<section class="card section-card" aria-labelledby="manage-lessons-heading">
    <div class="card-header">
        <div>
            <h2 id="manage-lessons-heading">Manage lessons</h2>
            <p>
                <?= $lessonCount ?> lesson<?= $lessonCount === 1 ? '' : 's' ?> in the catalogue.
                Deleting a lesson also removes its progress records.
            </p>
        </div>
        <a class="btn btn-ghost btn-small" href="lessons.php">Add lesson</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Lesson</th>
                    <th scope="col">Language</th>
                    <th scope="col">Minutes</th>
                    <th scope="col">Added</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lessons as $lesson): ?>
                    <tr>
                        <td><span class="primary"><?= e($lesson['title']) ?></span></td>
                        <td class="cell-nowrap"><?= e($lesson['language']) ?></td>
                        <td class="cell-nowrap"><?= (int) $lesson['duration_minutes'] ?> min</td>
                        <td class="cell-nowrap"><?= e(format_date($lesson['created_at'])) ?></td>
                        <td>
                            <form method="post" action="admin.php" class="inline-form"
                                  onsubmit="return confirm('Delete this lesson and its progress records permanently?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_lesson">
                                <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit"
                                        aria-label="Delete lesson <?= e($lesson['title']) ?>">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$lessons): ?>
                    <tr class="empty-row">
                        <td colspan="5">No lessons yet. Add the first one from the Lessons page.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card section-card" aria-labelledby="manage-learners-heading">
    <div class="card-header">
        <div>
            <h2 id="manage-learners-heading">Manage learners</h2>
            <p>
                <?= $learnerCount ?> learner<?= $learnerCount === 1 ? '' : 's' ?> registered.
                Deleting a learner also removes their progress records.
            </p>
        </div>
        <a class="btn btn-ghost btn-small" href="learners.php">Add learner</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Learner</th>
                    <th scope="col">Experience</th>
                    <th scope="col">Completed</th>
                    <th scope="col">Joined</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($learners as $learner): ?>
                    <tr>
                        <td>
                            <span class="primary"><?= e($learner['full_name']) ?></span>
                            <span class="secondary"><?= e($learner['email']) ?></span>
                        </td>
                        <td>
                            <span class="pill pill-<?= e(level_class($learner['experience_level'])) ?>">
                                <?= e($learner['experience_level']) ?>
                            </span>
                        </td>
                        <td class="cell-nowrap"><?= (int) $learner['completed_count'] ?> lesson<?= (int) $learner['completed_count'] === 1 ? '' : 's' ?></td>
                        <td class="cell-nowrap"><?= e(format_date($learner['created_at'])) ?></td>
                        <td>
                            <form method="post" action="admin.php" class="inline-form"
                                  onsubmit="return confirm('Delete this learner and all of their progress permanently?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_learner">
                                <input type="hidden" name="id" value="<?= (int) $learner['id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit"
                                        aria-label="Delete learner <?= e($learner['full_name']) ?>">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$learners): ?>
                    <tr class="empty-row">
                        <td colspan="5">No learners yet. Add the first profile from the Learners page.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
