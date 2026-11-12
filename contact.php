<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$contactReady = table_exists('contact_messages');

$errors = [];
$form = [
    'name'    => '',
    'email'   => '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    if (!$contactReady) {
        flash('error', 'The message inbox is not set up yet. Import database.sql, then try again.');
        redirect('contact.php');
    }

    $form = [
        'name'    => post('name'),
        'email'   => post('email'),
        'subject' => post('subject'),
        'message' => post('message'),
    ];

    $nameLength = mb_strlen($form['name']);
    if ($nameLength < 2) {
        $errors['name'] = 'Enter your name with at least 2 characters.';
    } elseif ($nameLength > 100) {
        $errors['name'] = 'Keep your name under 100 characters.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (mb_strlen($form['email']) > 150) {
        $errors['email'] = 'Keep the email under 150 characters.';
    }

    $subjectLength = mb_strlen($form['subject']);
    if ($subjectLength < 3) {
        $errors['subject'] = 'Add a subject with at least 3 characters.';
    } elseif ($subjectLength > 150) {
        $errors['subject'] = 'Keep the subject under 150 characters.';
    }

    $messageLength = mb_strlen($form['message']);
    if ($messageLength < 20) {
        $errors['message'] = 'Tell us a little more — at least 20 characters.';
    } elseif ($messageLength > 2000) {
        $errors['message'] = 'Keep the message under 2000 characters.';
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message)
             VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            $form['name'],
            $form['email'],
            $form['subject'],
            $form['message'],
        ]);

        flash('success', 'Thanks, ' . $form['name'] . '. Your message is in the inbox — we reply within two working days.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact';
$activePage = 'contact';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Support</p>
        <h1>Get in touch</h1>
        <p class="lede">
            Questions about a lesson, a broken code example or a language we should add? Send a message —
            it is stored in MySQL and read in the admin inbox.
        </p>
    </div>
</section>

<?php if (!$contactReady): ?>
    <div class="alert alert-error" role="alert">
        The <code>contact_messages</code> table is missing. Re-import <code>database.sql</code> (or run the
        contact block from it in phpMyAdmin) to enable this form and the admin inbox.
    </div>
<?php endif; ?>

<?php $pageImageKey = 'contact'; require __DIR__ . '/includes/page-image.php'; ?>

<div class="split">
    <?php if ($contactReady): ?>
        <section class="card form-card" aria-labelledby="contact-heading">
            <div class="card-header">
                <div>
                    <h2 id="contact-heading">Send a message</h2>
                    <p>All fields are required. Your email stays private.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="contact.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                        <label for="name">Your name</label>
                        <input type="text" id="name" name="name" value="<?= e($form['name']) ?>"
                               maxlength="100" placeholder="e.g. Priya Nair" required autocomplete="name"
                               <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="contact-name-error"' : '' ?>>
                        <?php if (isset($errors['name'])): ?>
                            <p class="error-text" id="contact-name-error"><?= e($errors['name']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                        <label for="contact_email">Email address</label>
                        <input type="email" id="contact_email" name="email" value="<?= e($form['email']) ?>"
                               maxlength="150" placeholder="you@example.com" required autocomplete="email"
                               <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="contact-email-error"' : '' ?>>
                        <?php if (isset($errors['email'])): ?>
                            <p class="error-text" id="contact-email-error"><?= e($errors['email']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['subject']) ? ' has-error' : '' ?>">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" value="<?= e($form['subject']) ?>"
                               maxlength="150" placeholder="What is this about?" required
                               <?= isset($errors['subject']) ? 'aria-invalid="true" aria-describedby="contact-subject-error"' : '' ?>>
                        <?php if (isset($errors['subject'])): ?>
                            <p class="error-text" id="contact-subject-error"><?= e($errors['subject']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['message']) ? ' has-error' : '' ?>">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" maxlength="2000" rows="6"
                                  placeholder="Share the lesson, error message and what you expected to happen."
                                  required
                                  <?= isset($errors['message']) ? 'aria-invalid="true" aria-describedby="contact-message-error"' : '' ?>><?= e($form['message']) ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <p class="error-text" id="contact-message-error"><?= e($errors['message']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-primary btn-block" type="submit">Send message</button>
                    <p class="form-note">Your message is inserted with a prepared statement and visible only in the admin area.</p>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="card" aria-labelledby="contact-info-heading">
        <div class="card-header">
            <div>
                <h2 id="contact-info-heading">Contact details</h2>
                <p>Email the team or follow CodeLearn on social media.</p>
            </div>
        </div>
        <div class="card-body">
            <dl class="contact-list">
                <div>
                    <dt class="label">Email</dt>
                    <dd class="value"><a href="g6823324@gmail.com">g6823324@gmail.com</a></dd>
                    <dd class="note">The fastest way to reach us about lessons, bugs or new languages.</dd>
                </div>
                <div>
                    <dt class="label">Support hours</dt>
                    <dd class="value">Mon–Fri, 09:00–17:00</dd>
                    <dd class="note">We reply within two working days; weekend messages are answered on Monday.</dd>
                </div>
            </dl>

            <div class="social-block">
                <p class="label">Social accounts</p>
                <ul class="social-list">
                    <li>
                        <a href="https://github.com/" target="_blank" rel="noopener noreferrer">
                            <span class="social-mark" aria-hidden="true">GH</span>
                            <span>
                                <strong>GitHub</strong>
                                <span class="social-handle">@codelearn</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="https://x.com/" target="_blank" rel="noopener noreferrer">
                            <span class="social-mark" aria-hidden="true">X</span>
                            <span>
                                <strong>X / Twitter</strong>
                                <span class="social-handle">@codelearn</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer">
                            <span class="social-mark" aria-hidden="true">IN</span>
                            <span>
                                <strong>LinkedIn</strong>
                                <span class="social-handle">/company/codelearn</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer">
                            <span class="social-mark" aria-hidden="true">YT</span>
                            <span>
                                <strong>YouTube</strong>
                                <span class="social-handle">@codelearn</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="https://discord.com/" target="_blank" rel="noopener noreferrer">
                            <span class="social-mark" aria-hidden="true">DC</span>
                            <span>
                                <strong>Discord</strong>
                                <span class="social-handle">/codelearn</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer">
                            <span class="social-mark" aria-hidden="true">IG</span>
                            <span>
                                <strong>Instagram</strong>
                                <span class="social-handle">@codelearn</span>
                            </span>
                        </a>
                    </li>
                </ul>
                <p class="social-note">Sample handles for this demo — replace the links and names with your real accounts.</p>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
