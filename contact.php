<?php
/**
 * CustomCore — Contact / Support Form (Commit 7.5).
 *
 * File responsibility:
 *   Public contact form for general support messages. Guests and logged-in
 *   customers can submit. Valid messages are stored in contact_messages with
 *   is_read = 0. When the visitor is logged in, user_id is set from the
 *   session (never from the form) and name/email are pre-filled from the
 *   profile. Success uses Post/Redirect/Get with a flash confirmation.
 *
 * Authentication requirements:
 *   None (public). Login is optional.
 *
 * Security:
 *   - CSRF token required on POST.
 *   - Server-side validation of name, email, subject, and message.
 *   - user_id taken only from the authenticated session.
 *   - All output escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/contact.php';

customcore_session_start();

$isLoggedIn = customcore_is_logged_in();
$sessionUserId = $isLoggedIn ? customcore_current_user_id() : 0;
$userId = $sessionUserId > 0 ? $sessionUserId : null;

/** @var array{name: string, email: string, subject: string, message: string} $values */
$values = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => '',
];

/** @var array<string, string> $errors */
$errors = [];
$formError = null;

// Pre-fill name/email for logged-in customers (overwritten by POST sticky values).
if ($isLoggedIn && $userId !== null) {
    try {
        $pdo = customcore_pdo();
        $stmt = $pdo->prepare(
            'SELECT first_name, last_name, email
             FROM users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $userRow = $stmt->fetch();
        if ($userRow !== false) {
            $fullName = trim(
                (string) ($userRow['first_name'] ?? '') . ' ' . (string) ($userRow['last_name'] ?? '')
            );
            $values['name'] = $fullName;
            $values['email'] = (string) ($userRow['email'] ?? '');
        }
    } catch (Throwable $e) {
        // Pre-fill is best-effort; the form still works without it.
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    $subjectChoice = isset($_POST['subject']) && is_string($_POST['subject'])
        ? trim($_POST['subject'])
        : '';
    $subjectOther = isset($_POST['subject_other']) && is_string($_POST['subject_other'])
        ? trim($_POST['subject_other'])
        : '';

    // "Other" requires a custom subject; otherwise store the selected label.
    $subjectResolved = $subjectChoice;
    if ($subjectChoice === 'Other') {
        $subjectResolved = $subjectOther !== '' ? $subjectOther : '';
    }

    $validated = customcore_contact_validate([
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'subject' => $subjectResolved,
        'message' => $_POST['message'] ?? '',
    ]);

    $values = $validated['values'];
    $errors = $validated['errors'];

    // Keep the select sticky separately from the resolved subject text.
    $values['subject_choice'] = $subjectChoice;
    $values['subject_other'] = $subjectOther;

    if ($subjectChoice === 'Other' && $subjectOther === '') {
        $errors['subject_other'] = 'Please enter a subject for “Other”.';
        unset($errors['subject']); // Prefer the more specific field error.
    } elseif ($subjectChoice !== '' && $subjectChoice !== 'Other'
        && !in_array($subjectChoice, customcore_contact_subject_options(), true)) {
        $errors['subject'] = 'Please choose a subject from the list.';
        $values['subject'] = '';
        $values['subject_choice'] = '';
    }

    if (!$csrfOk) {
        $formError = 'Your session expired. Please review the form and submit again.';
    } elseif ($errors === []) {
        try {
            $pdo = customcore_pdo();
            $messageId = customcore_contact_create(
                $pdo,
                [
                    'name' => $values['name'],
                    'email' => $values['email'],
                    'subject' => $values['subject'],
                    'message' => $values['message'],
                ],
                $userId
            );

            customcore_flash_success(
                'Thank you! Your message (#' . $messageId
                . ') has been sent. We will get back to you at '
                . $values['email'] . ' as soon as we can.'
            );
            customcore_redirect('contact.php');
        } catch (Throwable $exception) {
            $formError = customcore_is_debug()
                ? $exception->getMessage()
                : 'We could not send your message right now. Please try again later.';
        }
    } else {
        $formError = 'Please correct the highlighted fields and try again.';
    }
}

// Ensure sticky keys exist for the template even on GET.
if (!isset($values['subject_choice'])) {
    $values['subject_choice'] = in_array($values['subject'], customcore_contact_subject_options(), true)
        ? $values['subject']
        : '';
    $values['subject_other'] = ($values['subject_choice'] === '' && $values['subject'] !== '')
        ? $values['subject']
        : '';
}

$subjectOptions = customcore_contact_subject_options();

$pageTitle = 'Contact us — CustomCore';
$pageDescription = 'Send a support message to the CustomCore team about orders, products, the PC builder, or general questions.';
$pageKeywords = 'CustomCore, contact, support, help, message';
$currentPage = 'contact';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Contact page: public support message form -->
<section class="content-section contact-page" aria-labelledby="contact-heading">
    <!-- Intro and form layout wrapper -->
    <div class="contact-layout">
        <header class="contact-page__header">
            <h1 id="contact-heading">Contact us</h1>
            <p class="context-help">
                Help:
                <a href="<?php echo customcore_e(customcore_url('help/support.html#contact')); ?>">Support guide</a>
            </p>
            <p class="contact-page__intro">
                Have a question about an order, a product, or a custom build?
                Send us a message and we will reply by email. Fields marked
                <span class="form-required" aria-hidden="true">*</span> are required.
            </p>
            <?php if ($isLoggedIn) : ?>
                <p class="contact-page__note">
                    You are signed in — your name and email are filled from your account.
                    Prefer a PC build recommendation?
                    <a href="<?php echo customcore_e(customcore_url('consultation.php')); ?>">Request a consultation</a>.
                </p>
            <?php else : ?>
                <p class="contact-page__note">
                    Guests are welcome to contact us.
                    <a href="<?php echo customcore_e(customcore_url('login.php')); ?>">Log in</a>
                    if you already have an account, or
                    <a href="<?php echo customcore_e(customcore_url('consultation.php')); ?>">request a PC consultation</a>
                    after signing in.
                </p>
            <?php endif; ?>
        </header>

        <!-- Main column: error banner and contact form -->
        <div class="contact-page__main">
            <!-- Submission error banner -->
            <?php if ($formError !== null) : ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($formError); ?>
                </div>
            <?php endif; ?>

            <!-- Contact form: name, email, subject, message -->
            <form
                class="form-stack contact-form"
                method="post"
                action="<?php echo customcore_e(customcore_url('contact.php')); ?>"
                novalidate
                id="contact-form"
            >
                <?php echo customcore_csrf_field(); ?>

                <!-- Name and email, side by side -->
                <div class="form-row--inline">
                    <div class="form-row<?php echo isset($errors['name']) ? ' has-error' : ''; ?>">
                        <label class="form-label" for="contact-name">
                            Your name <span class="form-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            id="contact-name"
                            name="name"
                            value="<?php echo customcore_e($values['name']); ?>"
                            maxlength="200"
                            autocomplete="name"
                            required
                            <?php echo isset($errors['name']) ? 'aria-invalid="true" aria-describedby="err-name"' : ''; ?>
                        >
                        <?php if (isset($errors['name'])) : ?>
                            <p class="form-error" id="err-name"><?php echo customcore_e($errors['name']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-row<?php echo isset($errors['email']) ? ' has-error' : ''; ?>">
                        <label class="form-label" for="contact-email">
                            Email address <span class="form-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="email"
                            id="contact-email"
                            name="email"
                            value="<?php echo customcore_e($values['email']); ?>"
                            maxlength="255"
                            autocomplete="email"
                            required
                            <?php echo isset($errors['email']) ? 'aria-invalid="true" aria-describedby="err-email"' : ''; ?>
                        >
                        <?php if (isset($errors['email'])) : ?>
                            <p class="form-error" id="err-email"><?php echo customcore_e($errors['email']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row<?php echo isset($errors['subject']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="contact-subject">
                        Subject <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="contact-subject"
                        name="subject"
                        required
                        <?php echo isset($errors['subject']) ? 'aria-invalid="true" aria-describedby="err-subject"' : ''; ?>
                    >
                        <option value="">Select a topic…</option>
                        <?php foreach ($subjectOptions as $option) : ?>
                            <option
                                value="<?php echo customcore_e($option); ?>"
                                <?php echo ($values['subject_choice'] ?? '') === $option ? ' selected' : ''; ?>
                            ><?php echo customcore_e($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['subject'])) : ?>
                        <p class="form-error" id="err-subject"><?php echo customcore_e($errors['subject']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Custom subject field, shown only for "Other" -->
                <div
                    class="form-row contact-form__other<?php echo isset($errors['subject_other']) ? ' has-error' : ''; ?>"
                    id="contact-subject-other-row"
                    <?php echo ($values['subject_choice'] ?? '') === 'Other' ? '' : ' hidden'; ?>
                >
                    <label class="form-label" for="contact-subject-other">
                        Custom subject <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="contact-subject-other"
                        name="subject_other"
                        value="<?php echo customcore_e($values['subject_other'] ?? ''); ?>"
                        maxlength="300"
                        <?php echo isset($errors['subject_other']) ? 'aria-invalid="true" aria-describedby="err-subject-other"' : ''; ?>
                    >
                    <?php if (isset($errors['subject_other'])) : ?>
                        <p class="form-error" id="err-subject-other"><?php echo customcore_e($errors['subject_other']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row<?php echo isset($errors['message']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="contact-message">
                        Message <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="contact-message"
                        name="message"
                        class="form-textarea"
                        rows="7"
                        maxlength="5000"
                        required
                        minlength="10"
                        <?php echo isset($errors['message']) ? 'aria-invalid="true" aria-describedby="err-message"' : ''; ?>
                    ><?php echo customcore_e($values['message']); ?></textarea>
                    <p class="form-help">Include order numbers, product names, or builder details when relevant.</p>
                    <?php if (isset($errors['message'])) : ?>
                        <p class="form-error" id="err-message"><?php echo customcore_e($errors['message']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button--primary">Send message</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
