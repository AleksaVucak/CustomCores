<?php
/**
 * CustomCore — PC Consultation Request (Commit 7.3).
 *
 * File responsibility:
 *   Lets a logged-in customer request a personalised PC consultation. Captures
 *   budget, games, software, performance goals, optional notes, and optional
 *   secure file attachments (Commit 7.4), then stores the request in
 *   consultation_requests (status = open) with any files in
 *   consultation_attachments. Customer history arrives in Commit 7.6.
 *
 * Flow:
 *   GET  — show the form (pre-filled on validation errors).
 *   POST — validate CSRF + fields + files, insert request + attachments in a
 *          single transaction, flash success, redirect (PRG).
 *
 * Authentication requirements:
 *   Logged-in customer (require_login). Each request is tied to the session
 *   user_id; customers never see or create requests for another account.
 *
 * Security:
 *   - CSRF token required on POST.
 *   - Server-side validation of every field (budget whitelisted).
 *   - Uploads validated by real MIME type (finfo), size, and count; on-disk
 *     names are generated (never derived from user input); files land in a
 *     directory guarded against direct browsing.
 *   - Request + attachments are written atomically; moved files are cleaned up
 *     if the transaction fails.
 *   - Ownership: user_id comes from the session, never the form.
 *   - All output escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/consultations.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'consultation';

/** @var array{budget: string, games: string, software: string, performance_goals: string, notes: string} $values */
$values = [
    'budget' => '',
    'games' => '',
    'software' => '',
    'performance_goals' => '',
    'notes' => '',
];

/** @var array<string, string> $errors */
$errors = [];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    $validated = customcore_consultation_validate([
        'budget' => $_POST['budget'] ?? '',
        'games' => $_POST['games'] ?? '',
        'software' => $_POST['software'] ?? '',
        'performance_goals' => $_POST['performance_goals'] ?? '',
        'notes' => $_POST['notes'] ?? '',
    ]);

    $values = $validated['values'];
    $errors = $validated['errors'];

    // Validate any uploaded attachments up front (real MIME type, size, count).
    $normalizedFiles = customcore_consultation_normalize_files($_FILES['attachments'] ?? null);
    $fileCheck = customcore_consultation_validate_files($normalizedFiles);
    if (!$fileCheck['ok']) {
        $errors['attachments'] = implode(' ', $fileCheck['errors']);
    }

    if (!$csrfOk) {
        $formError = 'Your session expired. Please review the form and submit again.';
    } elseif ($validated['ok'] && $fileCheck['ok']) {
        $movedPaths = [];
        $pdo = null;
        try {
            $pdo = customcore_pdo();
            $pdo->beginTransaction();

            $requestId = customcore_consultation_create($pdo, $userId, $values);
            $storedCount = customcore_consultation_store_files(
                $pdo,
                $requestId,
                $fileCheck['valid'],
                $movedPaths
            );

            $pdo->commit();

            $attachNote = $storedCount > 0
                ? ' ' . $storedCount . ' file' . ($storedCount === 1 ? '' : 's') . ' attached.'
                : '';
            customcore_flash_success(
                'Thank you! Your consultation request (#' . $requestId
                . ') has been submitted.' . $attachNote
                . ' Our team will review it and respond soon.'
            );
            customcore_redirect('consultation.php');
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Remove any files that were moved before the failure.
            customcore_consultation_cleanup_files($movedPaths);

            $formError = customcore_is_debug()
                ? $exception->getMessage()
                : 'We could not submit your request right now. Please try again later.';
        }
    } else {
        $formError = 'Please correct the highlighted fields and try again.';
    }
}

$budgetOptions = customcore_consultation_budget_options();

$pageTitle = 'Request a PC consultation — CustomCore';
$pageDescription = 'Tell CustomCore about your budget, games, software, and goals and get a personalised custom PC recommendation.';
$pageKeywords = 'CustomCore, PC consultation, custom build advice, gaming PC help';
$currentPage = 'consultation';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Consultation request page: guided PC advice form -->
<section class="content-section profile-page consultation-page" aria-labelledby="consultation-heading">
    <header class="profile-page__header">
        <h1 id="consultation-heading">Request a PC consultation</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/support.html#consultation')); ?>">Consultation &amp; support guide</a>
        </p>
    </header>

    <!-- Account layout: sidebar navigation and main content -->
    <div class="layout-split layout-split--account">
        <!-- Account section navigation -->
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <!-- Main column: intro, error banner, and request form -->
        <div class="profile-page__main">
            <p class="consultation-page__intro">
                Not sure which components you need? Tell us how you plan to use your PC and we'll
                put together a tailored recommendation. Fields marked
                <span class="form-required" aria-hidden="true">*</span> are required.
            </p>

            <!-- Submission error banner -->
            <?php if ($formError !== null) : ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($formError); ?>
                </div>
            <?php endif; ?>

            <!-- Consultation form: budget, needs, goals, and attachments -->
            <form
                class="form-stack consultation-form"
                method="post"
                action="<?php echo customcore_e(customcore_url('consultation.php')); ?>"
                enctype="multipart/form-data"
                novalidate
            >
                <?php echo customcore_csrf_field(); ?>

                <div class="form-row<?php echo isset($errors['budget']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="consult-budget">
                        Approximate budget <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="consult-budget"
                        name="budget"
                        required
                        <?php echo isset($errors['budget']) ? 'aria-invalid="true" aria-describedby="err-budget"' : ''; ?>
                    >
                        <option value="">Select a budget range…</option>
                        <?php foreach ($budgetOptions as $option) : ?>
                            <option
                                value="<?php echo customcore_e($option); ?>"
                                <?php echo $values['budget'] === $option ? 'selected' : ''; ?>
                            ><?php echo customcore_e($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['budget'])) : ?>
                        <p class="form-error" id="err-budget"><?php echo customcore_e($errors['budget']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row<?php echo isset($errors['games']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="consult-games">
                        Games you play <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="consult-games"
                        name="games"
                        class="form-textarea"
                        rows="3"
                        maxlength="2000"
                        required
                        <?php echo isset($errors['games']) ? 'aria-invalid="true" aria-describedby="err-games"' : ''; ?>
                    ><?php echo customcore_e($values['games']); ?></textarea>
                    <p class="form-help">e.g. Cyberpunk 2077 at 1440p, competitive Valorant at high FPS.</p>
                    <?php if (isset($errors['games'])) : ?>
                        <p class="form-error" id="err-games"><?php echo customcore_e($errors['games']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row<?php echo isset($errors['software']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="consult-software">
                        Other software you use <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="consult-software"
                        name="software"
                        class="form-textarea"
                        rows="3"
                        maxlength="2000"
                        required
                        <?php echo isset($errors['software']) ? 'aria-invalid="true" aria-describedby="err-software"' : ''; ?>
                    ><?php echo customcore_e($values['software']); ?></textarea>
                    <p class="form-help">e.g. Blender, Premiere Pro, streaming with OBS, or "none — gaming only".</p>
                    <?php if (isset($errors['software'])) : ?>
                        <p class="form-error" id="err-software"><?php echo customcore_e($errors['software']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row<?php echo isset($errors['performance_goals']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="consult-goals">
                        Performance goals <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="consult-goals"
                        name="performance_goals"
                        class="form-textarea"
                        rows="3"
                        maxlength="2000"
                        required
                        <?php echo isset($errors['performance_goals']) ? 'aria-invalid="true" aria-describedby="err-goals"' : ''; ?>
                    ><?php echo customcore_e($values['performance_goals']); ?></textarea>
                    <p class="form-help">e.g. 144+ FPS at 1440p, quiet operation, 4K video editing, VR-ready.</p>
                    <?php if (isset($errors['performance_goals'])) : ?>
                        <p class="form-error" id="err-goals"><?php echo customcore_e($errors['performance_goals']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row<?php echo isset($errors['notes']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="consult-notes">
                        Additional notes <span class="form-optional">(optional)</span>
                    </label>
                    <textarea
                        id="consult-notes"
                        name="notes"
                        class="form-textarea"
                        rows="3"
                        maxlength="2000"
                        <?php echo isset($errors['notes']) ? 'aria-invalid="true" aria-describedby="err-notes"' : ''; ?>
                    ><?php echo customcore_e($values['notes']); ?></textarea>
                    <p class="form-help">Anything else we should know — preferred brands, aesthetics, deadlines, etc.</p>
                    <?php if (isset($errors['notes'])) : ?>
                        <p class="form-error" id="err-notes"><?php echo customcore_e($errors['notes']); ?></p>
                    <?php endif; ?>
                </div>

                <?php
                $maxMb = number_format(customcore_consultation_upload_max_bytes() / (1024 * 1024), 1);
                ?>
                <!-- Optional secure file attachments -->
                <div class="form-row<?php echo isset($errors['attachments']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="consult-attachments">
                        Attachments <span class="form-optional">(optional)</span>
                    </label>
                    <input
                        type="file"
                        id="consult-attachments"
                        name="attachments[]"
                        class="consultation-form__file"
                        multiple
                        accept=".pdf,.txt,.png,.jpg,.jpeg,.webp,application/pdf,text/plain,image/png,image/jpeg,image/webp"
                        <?php echo isset($errors['attachments']) ? 'aria-invalid="true" aria-describedby="err-attachments"' : ''; ?>
                    >
                    <p class="form-help">
                        Optional reference files — screenshots, part lists, or quotes.
                        Accepted: PDF, TXT, PNG, JPG, WEBP.
                        Up to <?php echo customcore_e((string) CUSTOMCORE_CONSULTATION_MAX_FILES); ?> files,
                        max <?php echo customcore_e($maxMb); ?> MB each.
                    </p>
                    <?php if (isset($errors['attachments'])) : ?>
                        <p class="form-error" id="err-attachments"><?php echo customcore_e($errors['attachments']); ?></p>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['attachments'])) : ?>
                    <p class="consultation-form__reselect">
                        For security, please re-select your files after fixing the error above.
                    </p>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="button button--primary">Submit request</button>
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                        Or try the PC builder
                    </a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
