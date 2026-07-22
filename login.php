<?php
/**
 * CustomCore — Secure Customer Login (Commit 4.2).
 *
 * File responsibility:
 *   Renders the login form and authenticates customers and admins.
 *   Verifies the password hash, blocks disabled accounts, regenerates the
 *   session ID on success, and stores the authenticated identity in the session.
 *
 * Flow:
 *   GET  — show the form (email is sticky; password is never echoed).
 *   POST — verify CSRF, look up user, verify password, check is_active,
 *          create session, flash success, redirect to the profile dashboard.
 *
 * Authentication requirements:
 *   Guests only. Logged-in users are redirected to their profile.
 *
 * Security:
 *   - CSRF token required on POST.
 *   - password_verify() against the stored bcrypt hash.
 *   - Generic "invalid email or password" message avoids user enumeration.
 *   - Dummy hash verification keeps timing similar for unknown emails.
 *   - session_regenerate_id(true) on success prevents session fixation.
 *   - Disabled accounts (is_active = 0) cannot log in.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';

customcore_session_start();

// Already authenticated users skip the login form.
customcore_require_guest();

$pageTitle = 'Log in — CustomCore';
$pageDescription = 'Log in to your CustomCore account to manage builds, orders, and your profile.';
$pageKeywords = 'CustomCore, login, sign in, account';
$currentPage = 'login';

$emailValue = '';
$formError = null;

// A valid-format bcrypt hash used only to equalize timing for unknown emails.
$dummyHash = '$2y$10$usesomesillystringforsaltusesomesillystringohnoz.9v2fJ9y7Q1Yb0m6';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    $emailValue = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if (!$csrfOk) {
        $formError = 'Your session expired. Please try logging in again.';
    } elseif ($emailValue === '' || $password === '') {
        $formError = 'Enter both your email address and password.';
    } else {
        try {
            $pdo = customcore_pdo();

            $stmt = $pdo->prepare(
                'SELECT id, email, password_hash, first_name, role, is_active
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );
            $stmt->execute([':email' => $emailValue]);
            $user = $stmt->fetch();

            $storedHash = ($user !== false && isset($user['password_hash']))
                ? (string) $user['password_hash']
                : $dummyHash;

            $passwordOk = password_verify($password, $storedHash);
            $credentialsOk = ($user !== false) && $passwordOk;

            if (!$credentialsOk) {
                $formError = 'Invalid email or password.';
            } elseif ((int) $user['is_active'] !== 1) {
                $formError = 'This account has been disabled. Please contact support.';
            } else {
                // Prevent session fixation: new ID for the authenticated session.
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_role'] = (string) $user['role'];
                $_SESSION['user_name'] = (string) $user['first_name'];
                $_SESSION['user_email'] = (string) $user['email'];

                // Seed session-security markers (Commit 4.8) so the idle/absolute
                // timeouts, user-agent binding, and ID rotation start from now.
                $now = time();
                $_SESSION['_cc_created'] = $now;
                $_SESSION['_cc_last_activity'] = $now;
                $_SESSION['_cc_last_regen'] = $now;
                $_SESSION['_cc_fp'] = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

                customcore_flash_success('Welcome back, ' . (string) $user['first_name'] . '.');

                // Return the user to the page they were sent here from, if any.
                $returnTo = customcore_take_return_to();
                if ($returnTo !== null) {
                    customcore_redirect_local($returnTo);
                }

                // Otherwise the profile dashboard (Commit 4.5), or home until then.
                $destination = is_file(__DIR__ . '/profile.php') ? 'profile.php' : 'index.php';
                customcore_redirect($destination);
            }
        } catch (Throwable $exception) {
            $formError = customcore_is_debug()
                ? $exception->getMessage()
                : 'We could not log you in right now. Please try again later.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section auth-page" aria-labelledby="login-heading">
    <div class="auth-card">
        <header class="auth-card__header">
            <h1 id="login-heading">Log in</h1>
            <p class="auth-card__intro">
                Welcome back. Log in to manage your builds, orders, and profile.
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

        <form class="form-stack auth-form" method="post" action="<?php echo customcore_e(customcore_url('login.php')); ?>" novalidate>
            <?php echo customcore_csrf_field(); ?>

            <div class="form-row">
                <label class="form-label" for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo customcore_e($emailValue); ?>"
                    maxlength="255"
                    autocomplete="email"
                    required
                    autofocus
                >
            </div>

            <div class="form-row">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    maxlength="200"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="button">Log in</button>
                <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('register.php')); ?>">
                    Create an account
                </a>
            </div>
        </form>

        <p class="auth-card__foot">
            Trouble signing in? Make sure your email is correct and your account is active.
        </p>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
