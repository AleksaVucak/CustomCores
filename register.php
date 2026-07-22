<?php
/**
 * CustomCore — Customer Registration (Commit 4.1).
 *
 * File responsibility:
 *   Renders the registration form and processes new customer sign-ups.
 *   Validates all input server-side, hashes the password with password_hash(),
 *   rejects duplicate emails, and creates an active customer account.
 *
 * Flow:
 *   GET  — show the form (pre-filled on validation errors, never with passwords).
 *   POST — validate CSRF + fields, insert user, flash success, redirect to login.
 *
 * Authentication requirements:
 *   Guests only. Logged-in users are redirected to their profile.
 *
 * Security:
 *   - CSRF token required on POST.
 *   - Passwords hashed with PASSWORD_DEFAULT (bcrypt); never stored/echoed raw.
 *   - Email uniqueness enforced by pre-check AND the users.email UNIQUE index.
 *   - All output escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';

customcore_session_start();

// Already authenticated users have no reason to register again.
if (customcore_is_logged_in()) {
    customcore_redirect('profile.php');
}

$pageTitle = 'Create account — CustomCore';
$pageDescription = 'Create a CustomCore account to save builds, track orders, and manage your profile.';
$pageKeywords = 'CustomCore, register, create account, sign up';
$currentPage = 'register';

/** @var array<string, string> $values Sticky field values (no passwords). */
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
];

/** @var array<string, string> $errors Field-level validation messages. */
$errors = [];
$formError = null;

$minPasswordLength = 8;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CSRF ------------------------------------------------------------
    if (!customcore_csrf_verify(isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null)) {
        $formError = 'Your session expired. Please review the form and submit again.';
    }

    // --- Collect + trim --------------------------------------------------
    $values['first_name'] = isset($_POST['first_name']) && is_string($_POST['first_name'])
        ? trim($_POST['first_name']) : '';
    $values['last_name'] = isset($_POST['last_name']) && is_string($_POST['last_name'])
        ? trim($_POST['last_name']) : '';
    $values['email'] = isset($_POST['email']) && is_string($_POST['email'])
        ? trim($_POST['email']) : '';
    $values['phone'] = isset($_POST['phone']) && is_string($_POST['phone'])
        ? trim($_POST['phone']) : '';

    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $passwordConfirm = isset($_POST['password_confirm']) && is_string($_POST['password_confirm'])
        ? $_POST['password_confirm'] : '';

    // --- Validate --------------------------------------------------------
    if ($values['first_name'] === '') {
        $errors['first_name'] = 'First name is required.';
    } elseif (mb_strlen($values['first_name']) > 100) {
        $errors['first_name'] = 'First name must be 100 characters or fewer.';
    }

    if ($values['last_name'] === '') {
        $errors['last_name'] = 'Last name is required.';
    } elseif (mb_strlen($values['last_name']) > 100) {
        $errors['last_name'] = 'Last name must be 100 characters or fewer.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (mb_strlen($values['email']) > 255) {
        $errors['email'] = 'Email address must be 255 characters or fewer.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($values['phone'] !== '') {
        if (mb_strlen($values['phone']) > 30) {
            $errors['phone'] = 'Phone number must be 30 characters or fewer.';
        } elseif (!preg_match('/^[0-9+()\-.\s]+$/', $values['phone'])) {
            $errors['phone'] = 'Phone number contains invalid characters.';
        }
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < $minPasswordLength) {
        $errors['password'] = 'Password must be at least ' . $minPasswordLength . ' characters.';
    } elseif (strlen($password) > 200) {
        $errors['password'] = 'Password must be 200 characters or fewer.';
    }

    if ($passwordConfirm === '') {
        $errors['password_confirm'] = 'Please confirm your password.';
    } elseif ($password !== '' && !hash_equals($password, $passwordConfirm)) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    // --- Duplicate email pre-check + insert ------------------------------
    if ($formError === null && $errors === []) {
        try {
            $pdo = customcore_pdo();

            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute([':email' => $values['email']]);

            if ($check->fetch() !== false) {
                $errors['email'] = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO users
                        (email, password_hash, first_name, last_name, phone, role, is_active)
                     VALUES
                        (:email, :password_hash, :first_name, :last_name, :phone, :role, 1)'
                );
                $insert->execute([
                    ':email' => $values['email'],
                    ':password_hash' => $hash,
                    ':first_name' => $values['first_name'],
                    ':last_name' => $values['last_name'],
                    ':phone' => $values['phone'] !== '' ? $values['phone'] : null,
                    ':role' => 'customer',
                ]);

                customcore_flash_success('Your account was created. You can now log in.');
                customcore_redirect('login.php');
            }
        } catch (PDOException $exception) {
            // 23000 = integrity constraint violation (e.g. duplicate email race).
            if ($exception->getCode() === '23000') {
                $errors['email'] = 'An account with that email already exists.';
            } else {
                $formError = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'We could not create your account right now. Please try again later.';
            }
        } catch (Throwable $exception) {
            $formError = customcore_is_debug()
                ? $exception->getMessage()
                : 'We could not create your account right now. Please try again later.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section auth-page" aria-labelledby="register-heading">
    <div class="auth-card">
        <header class="auth-card__header">
            <h1 id="register-heading">Create your account</h1>
            <p class="auth-card__intro">
                Join CustomCore to save custom builds, track orders, and manage your profile.
            </p>
            <p class="context-help">
                Help:
                <a href="<?php echo customcore_e(customcore_url('help/accounts.html')); ?>">Accounts guide</a>
            </p>
        </header>

        <?php if ($formError !== null) : ?>
            <div class="flash flash--error" role="alert">
                <?php echo customcore_e($formError); ?>
            </div>
        <?php endif; ?>

        <form class="form-stack auth-form" method="post" action="<?php echo customcore_e(customcore_url('register.php')); ?>" novalidate>
            <?php echo customcore_csrf_field(); ?>

            <div class="form-row--inline">
                <div class="form-row">
                    <label class="form-label" for="first_name">First name</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="<?php echo customcore_e($values['first_name']); ?>"
                        maxlength="100"
                        autocomplete="given-name"
                        required
                        <?php echo isset($errors['first_name']) ? 'aria-invalid="true" aria-describedby="first_name-error"' : ''; ?>
                    >
                    <?php if (isset($errors['first_name'])) : ?>
                        <p class="form-error" id="first_name-error"><?php echo customcore_e($errors['first_name']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label class="form-label" for="last_name">Last name</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="<?php echo customcore_e($values['last_name']); ?>"
                        maxlength="100"
                        autocomplete="family-name"
                        required
                        <?php echo isset($errors['last_name']) ? 'aria-invalid="true" aria-describedby="last_name-error"' : ''; ?>
                    >
                    <?php if (isset($errors['last_name'])) : ?>
                        <p class="form-error" id="last_name-error"><?php echo customcore_e($errors['last_name']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo customcore_e($values['email']); ?>"
                    maxlength="255"
                    autocomplete="email"
                    required
                    <?php echo isset($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : ''; ?>
                >
                <?php if (isset($errors['email'])) : ?>
                    <p class="form-error" id="email-error"><?php echo customcore_e($errors['email']); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label class="form-label" for="phone">Phone <span class="form-optional">(optional)</span></label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?php echo customcore_e($values['phone']); ?>"
                    maxlength="30"
                    autocomplete="tel"
                    <?php echo isset($errors['phone']) ? 'aria-invalid="true" aria-describedby="phone-error"' : ''; ?>
                >
                <?php if (isset($errors['phone'])) : ?>
                    <p class="form-error" id="phone-error"><?php echo customcore_e($errors['phone']); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-row--inline">
                <div class="form-row">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="<?php echo customcore_e((string) $minPasswordLength); ?>"
                        maxlength="200"
                        autocomplete="new-password"
                        required
                        <?php echo isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error password-hint"' : 'aria-describedby="password-hint"'; ?>
                    >
                    <p class="form-help" id="password-hint">At least <?php echo customcore_e((string) $minPasswordLength); ?> characters.</p>
                    <?php if (isset($errors['password'])) : ?>
                        <p class="form-error" id="password-error"><?php echo customcore_e($errors['password']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label class="form-label" for="password_confirm">Confirm password</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        minlength="<?php echo customcore_e((string) $minPasswordLength); ?>"
                        maxlength="200"
                        autocomplete="new-password"
                        required
                        <?php echo isset($errors['password_confirm']) ? 'aria-invalid="true" aria-describedby="password_confirm-error"' : ''; ?>
                    >
                    <?php if (isset($errors['password_confirm'])) : ?>
                        <p class="form-error" id="password_confirm-error"><?php echo customcore_e($errors['password_confirm']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button">Create account</button>
                <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('login.php')); ?>">
                    I already have an account
                </a>
            </div>
        </form>

        <p class="auth-card__foot">
            By creating an account you agree to use this academic demo store responsibly.
            Your password is stored only as a secure hash.
        </p>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
