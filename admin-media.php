<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

const MEDIA_MAX_BYTES = 5 * 1024 * 1024;
const MEDIA_MAX_DIMENSION = 8000;
const MEDIA_MIME_EXT = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

function media_directory(): string
{
    $directory = __DIR__ . '/uploads/media';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('The uploads/media directory could not be created.');
    }

    return $directory;
}

function media_image_mime(string $path): ?string
{
    if (!is_file($path) || filesize($path) === 0) {
        return null;
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path) ?: '';
    } else {
        $detected = @getimagesize($path);
        $mime = is_array($detected) ? (string) ($detected['mime'] ?? '') : '';
    }

    if (!isset(MEDIA_MIME_EXT[$mime])) {
        return null;
    }

    $size = @getimagesize($path);
    if ($size === false) {
        return null;
    }

    [$width, $height] = $size;
    if ($width < 1 || $height < 1 || $width > MEDIA_MAX_DIMENSION || $height > MEDIA_MAX_DIMENSION) {
        return null;
    }

    return $mime;
}

function media_title(string $raw, string $fallback): string
{
    $title = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw) ?? '';
    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

    if ($title === '') {
        $title = $fallback;
    }

    if ($title === '') {
        $title = 'Untitled image';
    }

    return mb_substr($title, 0, 120);
}

function media_original_name(string $name): ?string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? '';
    $name = trim($name);

    return $name === '' ? null : mb_substr($name, 0, 200);
}

function media_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024) . ' KB';
    }

    return $bytes . ' B';
}

function media_store(
    string $sourcePath,
    string $title,
    ?string $originalName,
    string $source,
    ?string $sourceUrl,
    bool $isUpload
): array {
    $bytes = filesize($sourcePath);
    if ($bytes === false || $bytes < 1) {
        return [false, 'The image file is empty.'];
    }

    if ($bytes > MEDIA_MAX_BYTES) {
        return [false, 'Images must be 5 MB or smaller.'];
    }

    $mime = media_image_mime($sourcePath);
    if ($mime === null) {
        return [false, 'That file is not a JPEG, PNG, GIF or WebP image (maximum 8000 px per side).'];
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . MEDIA_MIME_EXT[$mime];
    $destination = media_directory() . DIRECTORY_SEPARATOR . $storedName;
    $saved = $isUpload ? move_uploaded_file($sourcePath, $destination) : rename($sourcePath, $destination);

    if (!$saved) {
        return [false, 'The image could not be saved to uploads/media.'];
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO media (title, stored_name, original_name, mime_type, size_bytes, source, source_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$title, $storedName, $originalName, $mime, $bytes, $source, $sourceUrl]);
    } catch (Throwable $exception) {
        @unlink($destination);

        return [false, 'The image was saved but its database record failed. Import database.sql and try again.'];
    }

    return [true, 'Image added to the media library.'];
}

function media_normalize_ip(string $ip): string
{
    $ip = trim($ip, '[]');

    if (str_starts_with($ip, '::ffff:')) {
        $mapped = substr($ip, 7);
        if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $mapped;
        }
    }

    return $ip;
}

function media_ip_is_public(string $ip): bool
{
    return filter_var(
        media_normalize_ip($ip),
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function media_public_ips(string $host): array
{
    $host = trim($host, '[]');

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return [media_normalize_ip($host)];
    }

    $ips = [];

    if (function_exists('dns_get_record')) {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
    }

    if (!$ips) {
        foreach ((array) @gethostbynamel($host) as $ip) {
            $ips[] = $ip;
        }
    }

    return array_values(array_unique($ips));
}

function media_url_title(string $url): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
    $name = urldecode(basename($path));
    $name = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $name) ?? '';

    return media_title($name, 'Imported image');
}

/**
 * Downloads an image from a public HTTP(S) URL into a temporary file.
 * Private, loopback and reserved addresses are refused to prevent SSRF.
 *
 * @return string|null Temporary file path, or null when $error is set.
 */
function media_fetch_url(string $url, ?string &$error): ?string
{
    $error = null;

    if (mb_strlen($url) > 500) {
        $error = 'Keep the image URL under 500 characters.';
        return null;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Enter a full image URL starting with http:// or https://.';
        return null;
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        $error = 'Enter a full image URL starting with http:// or https://.';
        return null;
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        $error = 'URLs with embedded usernames or passwords are not allowed.';
        return null;
    }

    if (isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true)) {
        $error = 'Only standard ports 80 and 443 are allowed.';
        return null;
    }

    $ips = media_public_ips($host);
    if (!$ips) {
        $error = 'That host could not be resolved.';
        return null;
    }

    foreach ($ips as $ip) {
        if (!media_ip_is_public($ip)) {
            $error = 'For safety, images can only be imported from public internet hosts.';
            return null;
        }
    }

    $temporary = tempnam(sys_get_temp_dir(), 'climg');
    if ($temporary === false) {
        $error = 'A temporary file could not be created.';
        return null;
    }

    $handle = fopen($temporary, 'wb');
    if ($handle === false) {
        @unlink($temporary);
        $error = 'A temporary file could not be opened for writing.';
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'follow_location' => 1,
            'max_redirects' => 3,
            'ignore_errors' => true,
            'user_agent' => 'CodeLearnMediaImporter/1.0',
            'header' => "Accept: image/*\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $remote = @fopen($url, 'rb', false, $context);
    if ($remote === false) {
        fclose($handle);
        @unlink($temporary);
        $error = 'The image could not be downloaded. Check the URL and try again.';
        return null;
    }

    $meta = stream_get_meta_data($remote);
    $status = 0;
    foreach ((array) ($meta['wrapper_data'] ?? []) as $headerLine) {
        if (is_string($headerLine) && preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $match)) {
            $status = (int) $match[1];
        }
    }

    if ($status !== 200) {
        fclose($remote);
        fclose($handle);
        @unlink($temporary);
        $error = 'The server returned HTTP ' . ($status > 0 ? (string) $status : 'an error') . ' instead of an image.';
        return null;
    }

    $finalHost = (string) (parse_url((string) ($meta['uri'] ?? $url), PHP_URL_HOST) ?: '');
    if ($finalHost !== '') {
        $finalIps = media_public_ips($finalHost);
        foreach ($finalIps as $ip) {
            if (!media_ip_is_public($ip)) {
                fclose($remote);
                fclose($handle);
                @unlink($temporary);
                $error = 'For safety, images can only be imported from public internet hosts.';
                return null;
            }
        }
    }

    $limit = MEDIA_MAX_BYTES;
    $received = 0;
    $tooLarge = false;

    while (!feof($remote)) {
        $chunk = fread($remote, 8192);
        if ($chunk === false) {
            break;
        }

        $received += strlen($chunk);
        if ($received > $limit) {
            $tooLarge = true;
            break;
        }

        if (fwrite($handle, $chunk) === false) {
            break;
        }
    }

    fclose($remote);
    fclose($handle);

    if ($tooLarge) {
        @unlink($temporary);
        $error = 'That image is larger than 5 MB.';
        return null;
    }

    $bytes = filesize($temporary);
    if ($bytes === false || $bytes < 1) {
        @unlink($temporary);
        $error = 'The downloaded file was empty.';
        return null;
    }

    if ($bytes > $limit) {
        @unlink($temporary);
        $error = 'That image is larger than 5 MB.';
        return null;
    }

    return $temporary;
}

$isAdmin = ($_SESSION['is_admin'] ?? false) === true;

if (!$isAdmin) {
    redirect('admin.php');
}

$mediaReady = table_exists('media');

$pageLabels = [
    'dashboard' => 'Dashboard',
    'admin' => 'Admin dashboard',
    'languages' => 'Languages',
    'lessons' => 'Lessons',
    'learners' => 'Learners',
    'progress' => 'Progress',
    'contact' => 'Contact',
];

$pageDescriptions = [
    'dashboard' => 'Banner on the public Dashboard, below the hero.',
    'admin' => 'Banner on the admin Dashboard, above the stats.',
    'languages' => 'Banner at the top of the Languages page.',
    'lessons' => 'Banner at the top of the Lessons page.',
    'learners' => 'Banner at the top of the Learners page.',
    'progress' => 'Banner at the top of the Progress page.',
    'contact' => 'Banner at the top of the Contact page.',
];

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE => 'The image is larger than the server upload limit.',
    UPLOAD_ERR_FORM_SIZE => 'The image is larger than the form upload limit.',
    UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.',
    UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
    UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = post('action');

    if (!$mediaReady) {
        flash('error', 'The media table is missing. Import database.sql, then try again.');
        redirect('admin-media.php');
    }

    if ($action === 'upload') {
        $file = $_FILES['photo'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Choose an image file before uploading.');
            redirect('admin-media.php');
        }

        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError !== UPLOAD_ERR_OK) {
            flash('error', $uploadErrors[$uploadError] ?? 'The upload failed. Try again.');
            redirect('admin-media.php');
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            flash('error', 'The upload could not be verified.');
            redirect('admin-media.php');
        }

        $title = media_title(post('title'), (string) $file['name']);
        $originalName = media_original_name((string) $file['name']);
        [$ok, $message] = media_store(
            (string) $file['tmp_name'],
            $title,
            $originalName,
            'upload',
            null,
            true
        );

        flash($ok ? 'success' : 'error', $message);
        redirect('admin-media.php');
    }

    if ($action === 'import') {
        $url = trim(post('image_url'));
        $fetchError = null;
        $temporary = media_fetch_url($url, $fetchError);

        if ($temporary === null) {
            flash('error', $fetchError ?? 'The image could not be downloaded.');
            redirect('admin-media.php');
        }

        $title = media_title(post('title'), media_url_title($url));
        [$ok, $message] = media_store($temporary, $title, null, 'url', $url, false);

        if (!$ok) {
            @unlink($temporary);
        }

        flash($ok ? 'success' : 'error', $message);
        redirect('admin-media.php');
    }

    if ($action === 'assign_image') {
        $pageKey = post('page_key');

        if (!isset($pageLabels[$pageKey])) {
            flash('error', 'That page could not be found.');
            redirect('admin-media.php');
        }

        $mediaId = filter_var($_POST['media_id'] ?? null, FILTER_VALIDATE_INT);
        if ($mediaId === false || $mediaId < 0) {
            flash('error', 'Choose an image from the list.');
            redirect('admin-media.php');
        }

        $pdo = db();

        try {
            $pdo->beginTransaction();

            if ($mediaId > 0) {
                $check = $pdo->prepare('SELECT id FROM media WHERE id = ?');
                $check->execute([$mediaId]);
                if ($check->fetchColumn() === false) {
                    $pdo->rollBack();
                    flash('error', 'That image was already removed.');
                    redirect('admin-media.php');
                }
            }

            $clear = $pdo->prepare(
                'UPDATE media SET page_key = NULL WHERE page_key = ?' . ($mediaId > 0 ? ' OR id = ?' : '')
            );
            $clear->execute($mediaId > 0 ? [$pageKey, $mediaId] : [$pageKey]);

            if ($mediaId > 0) {
                $pdo->prepare('UPDATE media SET page_key = ? WHERE id = ?')->execute([$pageKey, $mediaId]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash('error', 'The page image could not be saved. Try again.');
            redirect('admin-media.php');
        }

        flash(
            'success',
            $mediaId > 0
                ? $pageLabels[$pageKey] . ' now shows the selected image.'
                : 'Image removed from ' . $pageLabels[$pageKey] . '.'
        );
        redirect('admin-media.php');
    }

    if ($action === 'delete') {
        $mediaId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($mediaId === false || $mediaId < 1) {
            flash('error', 'That image could not be found.');
            redirect('admin-media.php');
        }

        $statement = db()->prepare('SELECT stored_name FROM media WHERE id = ?');
        $statement->execute([$mediaId]);
        $storedName = $statement->fetchColumn();

        if ($storedName === false) {
            flash('error', 'That image was already removed.');
            redirect('admin-media.php');
        }

        $path = media_directory() . DIRECTORY_SEPARATOR . basename((string) $storedName);
        if (is_file($path)) {
            @unlink($path);
        }

        db()->prepare('DELETE FROM media WHERE id = ?')->execute([$mediaId]);
        flash('success', 'Image deleted from the library and removed from disk.');
        redirect('admin-media.php');
    }
}

$media = $mediaReady
    ? db()->query('SELECT * FROM media ORDER BY uploaded_at DESC, id DESC')->fetchAll()
    : [];
$totalBytes = array_sum(array_map(static fn (array $item): int => (int) $item['size_bytes'], $media));

$pageTitle = 'Media library';
$activePage = 'admin';
require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Admin &middot; Media</p>
        <h1>Media library</h1>
        <p class="lede">
            Upload photos from your computer or download an image from a public URL. Every file is
            stored in <code>uploads/media</code> and listed here so you can preview, download or delete it.
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-ghost" href="admin.php">Back to dashboard</a>
    </div>
</section>

<?php if (!$mediaReady): ?>
    <div class="alert alert-error" role="alert">
        The <code>media</code> table is missing. Import <code>database.sql</code> (or run the media
        block from it in phpMyAdmin) to enable uploading and importing.
    </div>
<?php else: ?>
    <div class="grid-2">
        <section class="card" aria-labelledby="upload-heading">
            <div class="card-header">
                <div>
                    <h2 id="upload-heading">Upload from your computer</h2>
                    <p>JPEG, PNG, GIF or WebP up to 5 MB.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="admin-media.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">

                    <div class="field">
                        <label for="photo">Image file</label>
                        <input type="file" id="photo" name="photo"
                               accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <p class="hint">The original file name is kept as metadata; files are stored under random names.</p>
                    </div>

                    <div class="field">
                        <label for="upload_title">Title (optional)</label>
                        <input type="text" id="upload_title" name="title" maxlength="120"
                               placeholder="e.g. Python course cover">
                    </div>

                    <button class="btn btn-primary btn-block" type="submit">Upload image</button>
                </form>
            </div>
        </section>

        <section class="card" aria-labelledby="import-heading">
            <div class="card-header">
                <div>
                    <h2 id="import-heading">Download from the internet</h2>
                    <p>Paste a direct link to an image on a public site.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="admin-media.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">

                    <div class="field">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" maxlength="500"
                               placeholder="https://example.com/photo.jpg" required>
                        <p class="hint">The server downloads the file, checks that it really is an image, then stores it locally.</p>
                    </div>

                    <div class="field">
                        <label for="import_title">Title (optional)</label>
                        <input type="text" id="import_title" name="title" maxlength="120"
                               placeholder="e.g. Rust ownership diagram">
                    </div>

                    <button class="btn btn-ghost btn-block" type="submit">Download and add</button>
                    <p class="form-note">Private, loopback and reserved hosts are refused to keep the server safe.</p>
                </form>
            </div>
        </section>
    </div>

    <section class="card section-card" aria-labelledby="page-images-heading">
        <div class="card-header">
            <div>
                <h2 id="page-images-heading">Images by page</h2>
                <p>Pick one image per page and press Save. Choose “— No image —” to remove it.</p>
            </div>
        </div>
        <div class="page-assign-list">
            <?php foreach ($pageLabels as $pageKey => $pageLabel): ?>
                <?php
                $selectedId = 0;
                foreach ($media as $candidate) {
                    if (($candidate['page_key'] ?? null) === $pageKey) {
                        $selectedId = (int) $candidate['id'];
                        break;
                    }
                }
                ?>
                <form class="page-assign" method="post" action="admin-media.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_image">
                    <input type="hidden" name="page_key" value="<?= e($pageKey) ?>">

                    <div class="page-assign-text">
                        <strong><?= e($pageLabel) ?></strong>
                        <span><?= e($pageDescriptions[$pageKey]) ?></span>
                    </div>

                    <select name="media_id" aria-label="Image for <?= e($pageLabel) ?>">
                        <option value="0">— No image —</option>
                        <?php foreach ($media as $candidate): ?>
                            <option value="<?= (int) $candidate['id'] ?>"<?= (int) $candidate['id'] === $selectedId ? ' selected' : '' ?>>
                                <?= e($candidate['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="btn btn-ghost btn-small" type="submit">Save</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card section-card" aria-labelledby="gallery-heading">
        <div class="card-header">
            <div>
                <h2 id="gallery-heading">Gallery</h2>
                <p>
                    <?= count($media) ?> image<?= count($media) === 1 ? '' : 's' ?>
                    &middot; <?= e(media_size($totalBytes)) ?> stored
                </p>
            </div>
        </div>

        <?php if (!$media): ?>
            <div class="empty-state">
                <strong>No images yet.</strong>
                <p>Upload a photo or paste an image URL to build the library.</p>
            </div>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($media as $item): ?>
                    <?php
                    $fileUrl = 'uploads/media/' . rawurlencode((string) $item['stored_name']);
                    $downloadName = (string) ($item['original_name'] ?: $item['title'] . '.' . pathinfo((string) $item['stored_name'], PATHINFO_EXTENSION));
                    ?>
                    <article class="media-card">
                        <a class="media-thumb" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">
                            <img src="<?= e($fileUrl) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                            <?php if (!empty($item['page_key']) && isset($pageLabels[$item['page_key']])): ?>
                                <span class="media-badge"><?= e($pageLabels[$item['page_key']]) ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="media-body">
                            <strong class="media-title"><?= e($item['title']) ?></strong>
                            <p class="media-meta">
                                <?= $item['source'] === 'url' ? 'Imported' : 'Uploaded' ?>
                                &middot; <?= e(media_size((int) $item['size_bytes'])) ?>
                                &middot; <?= e(format_date($item['uploaded_at'])) ?>
                            </p>
                            <?php if ($item['source_url']): ?>
                                <a class="media-source" href="<?= e($item['source_url']) ?>" target="_blank"
                                   rel="noopener noreferrer">View original source</a>
                            <?php endif; ?>
                            <div class="media-actions">
                                <a class="btn btn-ghost btn-small" href="<?= e($fileUrl) ?>"
                                   download="<?= e($downloadName) ?>">Download</a>
                                <form method="post" action="admin-media.php" class="inline-form"
                                      onsubmit="return confirm('Delete this image and its file permanently?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-danger btn-small" type="submit"
                                            aria-label="Delete image <?= e($item['title']) ?>">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
