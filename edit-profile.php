<?php
/**
 * CustomCore — Edit Profile (Commit 4.6).
 *
 * File responsibility:
 *   Lets the logged-in customer update their own name, email, phone, and
 *   address, and change their password. Two independent forms share the page:
 *   "details" and "password". All updates are scoped to the session user id.
 *
 * Authentication requirements:
 *   Logged-in customer or admin (require_login). Never edits another user.
 *
 * Security:
 *   - CSRF token required on both forms.
 *   - Email uniqueness enforced (excluding self) + users.email UNIQUE index.
 *   - Password change requires the current password (password_verify).
 *   - New password hashed with password_hash(); session ID regenerated.
 *   - All output escaped via customcore_e().
 *
 * Completion test:
 *   Updates validate and persist correctly.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';

customcore_require_login();

$userId = customcore_current_user_id();
$minPasswordLength = 8;

/** @var array<string,string> $values Sticky detail values. */
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'province' => '',
    'postal_code' => '',
];

$detailErrors = [];
$passwordErrors = [];
$pageError = null;

// ---------------------------------------------------------------------------
// Load the current user (own record only)
// ---------------------------------------------------------------------------

$user = null;

try {
    $pdo = customcore_pdo();

    $stmt = $pdo->prepare(
        'SELECT id, email, first_name, last_name, phone,
                address_line1, address_line2, city, province, postal_code,
                password_hash, role, is_active
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if ($user === false || (int) ($user['is_active'] ?? 0) !== 1) {
        customcore_logout();
        customcore_session_start();
        customcore_flash_error('Your account is no longer available. Please contact support.');
        customcore_redirect('login.php');
    }

    // Seed sticky values from the database (overwritten by POST on error).
    foreach ($values as $key => $_) {
        $values[$key] = (string) ($user[$key] ?? '');
    }
} catch (Throwable $exception) {
    $pageError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Your profile is temporarily unavailable. Please try again later.';
}

// ---------------------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------------------

$formAction = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user !== null && $pageError === null) {
    $formAction = isset($_POST['form_action']) && is_string($_POST['form_action'])
        ? $_POST['form_action']
        : '';

    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    // ----- Details form --------------------------------------------------
    if ($formAction === 'details') {
        $values['first_name'] = isset($_POST['first_name']) && is_string($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $values['last_name'] = isset($_POST['last_name']) && is_string($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $values['email'] = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        $values['phone'] = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
        $values['address_line1'] = isset($_POST['address_line1']) && is_string($_POST['address_line1']) ? trim($_POST['address_line1']) : '';
        $values['address_line2'] = isset($_POST['address_line2']) && is_string($_POST['address_line2']) ? trim($_POST['address_line2']) : '';
        $values['city'] = isset($_POST['city']) && is_string($_POST['city']) ? trim($_POST['city']) : '';
        $values['province'] = isset($_POST['province']) && is_string($_POST['province']) ? trim($_POST['province']) : '';
        $values['postal_code'] = isset($_POST['postal_code']) && is_string($_POST['postal_code']) ? trim($_POST['postal_code']) : '';

        if (!$csrfOk) {
            $pageError = 'Your session expired. Please review the form and save again.';
        }

        if ($values['first_name'] === '') {
            $detailErrors['first_name'] = 'First name is required.';
        } elseif (mb_strlen($values['first_name']) > 100) {
            $detailErrors['first_name'] = 'First name must be 100 characters or fewer.';
        }

        if ($values['last_name'] === '') {
            $detailErrors['last_name'] = 'Last name is required.';
        } elseif (mb_strlen($values['last_name']) > 100) {
            $detailErrors['last_name'] = 'Last name must be 100 characters or fewer.';
        }

        if ($values['email'] === '') {
            $detailErrors['email'] = 'Email address is required.';
        } elseif (mb_strlen($values['email']) > 255) {
            $detailErrors['email'] = 'Email address must be 255 characters or fewer.';
        } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $detailErrors['email'] = 'Enter a valid email address.';
        }

        if ($values['phone'] !== '') {
            if (mb_strlen($values['phone']) > 30) {
                $detailErrors['phone'] = 'Phone number must be 30 characters or fewer.';
            } elseif (!preg_match('/^[0-9+()\-.\s]+$/', $values['phone'])) {
                $detailErrors['phone'] = 'Phone number contains invalid characters.';
            }
        }

        if (mb_strlen($values['address_line1']) > 255) {
            $detailErrors['address_line1'] = 'Address line 1 must be 255 characters or fewer.';
        }
        if (mb_strlen($values['address_line2']) > 255) {
            $detailErrors['address_line2'] = 'Address line 2 must be 255 characters or fewer.';
        }
        if (mb_strlen($values['city']) > 100) {
            $detailErrors['city'] = 'City must be 100 characters or fewer.';
        }
        if (mb_strlen($values['province']) > 100) {
            $detailErrors['province'] = 'Province must be 100 characters or fewer.';
        }
        if (mb_strlen($values['postal_code']) > 20) {
            $detailErrors['postal_code'] = 'Postal code must be 20 characters or fewer.';
        }

        if ($pageError === null && $detailErrors === []) {
            try {
                // Email uniqueness excluding the current user.
                $dupe = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
                $dupe->execute([':email' => $values['email'], ':id' => $userId]);

                if ($dupe->fetch() !== false) {
                    $detailErrors['email'] = 'Another account already uses that email address.';
                } else {
                    $update = $pdo->prepare(
                        'UPDATE users SET
                            first_name = :first_name,
                            last_name = :last_name,
                            email = :email,
                            phone = :phone,
                            address_line1 = :address_line1,
                            address_line2 = :address_line2,
                            city = :city,
                            province = :province,
                            postal_code = :postal_code
                         WHERE id = :id'
                    );
                    $update->execute([
                        ':first_name' => $values['first_name'],
                        ':last_name' => $values['last_name'],
                        ':email' => $values['email'],
                        ':phone' => $values['phone'] !== '' ? $values['phone'] : null,
                        ':address_line1' => $values['address_line1'],
                        ':address_line2' => $values['address_line2'],
                        ':city' => $values['city'],
                        ':province' => $values['province'],
                        ':postal_code' => $values['postal_code'],
                        ':id' => $userId,
                    ]);

                    $_SESSION['user_name'] = $values['first_name'];
                    $_SESSION['user_email'] = $values['email'];

                    customcore_flash_success('Your profile details were updated.');
                    customcore_redirect('profile.php');
                }
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $detailErrors['email'] = 'Another account already uses that email address.';
                } else {
                    $pageError = customcore_is_debug()
                        ? $exception->getMessage()
                        : 'We could not save your changes right now. Please try again later.';
                }
            } catch (Throwable $exception) {
                $pageError = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'We could not save your changes right now. Please try again later.';
            }
        }
    }

    // ----- Password form -------------------------------------------------
    if ($formAction === 'password') {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $newPasswordConfirm = isset($_POST['new_password_confirm']) && is_string($_POST['new_password_confirm']) ? $_POST['new_password_confirm'] : '';

        if (!$csrfOk) {
            $pageError = 'Your session expired. Please try changing your password again.';
        }

        if ($currentPassword === '') {
            $passwordErrors['current_password'] = 'Enter your current password.';
        }

        if ($newPassword === '') {
            $passwordErrors['new_password'] = 'Enter a new password.';
        } elseif (strlen($newPassword) < $minPasswordLength) {
            $passwordErrors['new_password'] = 'New password must be at least ' . $minPasswordLength . ' characters.';
        } elseif (strlen($newPassword) > 200) {
            $passwordErrors['new_password'] = 'New password must be 200 characters or fewer.';
        }

        if ($newPasswordConfirm === '') {
            $passwordErrors['new_password_confirm'] = 'Confirm your new password.';
        } elseif ($newPassword !== '' && !hash_equals($newPassword, $newPasswordConfirm)) {
            $passwordErrors['new_password_confirm'] = 'New passwords do not match.';
        }

        // Verify the current password only if the basics passed.
        if ($pageError === null && !isset($passwordErrors['current_password'])) {
            $storedHash = (string) ($user['password_hash'] ?? '');
            if (!password_verify($currentPassword, $storedHash)) {
                $passwordErrors['current_password'] = 'Your current password is incorrect.';
            }
        }

        if ($pageError === null && $passwordErrors === []) {
            try {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $update->execute([':hash' => $newHash, ':id' => $userId]);

                // New credentials → new session ID.
                session_regenerate_id(true);
                $_SESSION['_cc_last_regen'] = time();

                customcore_flash_success('Your password was changed.');
                customcore_redirect('profile.php');
            } catch (Throwable $exception) {
                $pageError = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'We could not change your password right now. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Edit profile — CustomCore';
$pageDescription = 'Update your CustomCore account details and password.';
$pageKeywords = 'CustomCore, edit profile, account settings, change password';
$currentPage = 'profile';
$accountNavCurrent = 'edit-profile';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page" aria-labelledby="edit-heading">
    <header class="profile-page__header">
        <h1 id="edit-heading">Edit profile</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/accounts.html')); ?>">Accounts guide</a>
        </p>
    </header>

    <?php if ($pageError !== null) : ?>
        <div class="flash flash--error" role="alert">
            <?php echo customcore_e($pageError); ?>
        </div>
    <?php endif; ?>

    <div class="layout-split layout-split--account">
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($user !== null) : ?>
                <section class="edit-section" aria-labelledby="details-heading">
                    <h2 id="details-heading">Account details</h2>
                    <form class="form-stack" method="post" action="<?php echo customcore_e(customcore_url('edit-profile.php')); ?>" novalidate>
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="form_action" value="details">

                        <div class="form-row--inline">
                            <div class="form-row">
                                <label class="form-label" for="first_name">First name</label>
                                <input type="text" id="first_name" name="first_name"
                                    value="<?php echo customcore_e($values['first_name']); ?>"
                                    maxlength="100" autocomplete="given-name" required
                                    <?php echo isset($detailErrors['first_name']) ? 'aria-invalid="true" aria-describedby="first_name-error"' : ''; ?>>
                                <?php if (isset($detailErrors['first_name'])) : ?>
                                    <p class="form-error" id="first_name-error"><?php echo customcore_e($detailErrors['first_name']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-row">
                                <label class="form-label" for="last_name">Last name</label>
                                <input type="text" id="last_name" name="last_name"
                                    value="<?php echo customcore_e($values['last_name']); ?>"
                                    maxlength="100" autocomplete="family-name" required
                                    <?php echo isset($detailErrors['last_name']) ? 'aria-invalid="true" aria-describedby="last_name-error"' : ''; ?>>
                                <?php if (isset($detailErrors['last_name'])) : ?>
                                    <p class="form-error" id="last_name-error"><?php echo customcore_e($detailErrors['last_name']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="email">Email address</label>
                            <input type="email" id="email" name="email"
                                value="<?php echo customcore_e($values['email']); ?>"
                                maxlength="255" autocomplete="email" required
                                <?php echo isset($detailErrors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : ''; ?>>
                            <?php if (isset($detailErrors['email'])) : ?>
                                <p class="form-error" id="email-error"><?php echo customcore_e($detailErrors['email']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="phone">Phone <span class="form-optional">(optional)</span></label>
                            <input type="tel" id="phone" name="phone"
                                value="<?php echo customcore_e($values['phone']); ?>"
                                maxlength="30" autocomplete="tel"
                                <?php echo isset($detailErrors['phone']) ? 'aria-invalid="true" aria-describedby="phone-error"' : ''; ?>>
                            <?php if (isset($detailErrors['phone'])) : ?>
                                <p class="form-error" id="phone-error"><?php echo customcore_e($detailErrors['phone']); ?></p>
                            <?php endif; ?>
                        </div>

                        <fieldset>
                            <legend>Address <span class="form-optional">(optional)</span></legend>

                            <div class="form-row">
                                <label class="form-label" for="address_line1">Address line 1</label>
                                <input type="text" id="address_line1" name="address_line1"
                                    value="<?php echo customcore_e($values['address_line1']); ?>"
                                    maxlength="255" autocomplete="address-line1"
                                    <?php echo isset($detailErrors['address_line1']) ? 'aria-invalid="true" aria-describedby="address_line1-error"' : ''; ?>>
                                <?php if (isset($detailErrors['address_line1'])) : ?>
                                    <p class="form-error" id="address_line1-error"><?php echo customcore_e($detailErrors['address_line1']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="form-row">
                                <label class="form-label" for="address_line2">Address line 2</label>
                                <input type="text" id="address_line2" name="address_line2"
                                    value="<?php echo customcore_e($values['address_line2']); ?>"
                                    maxlength="255" autocomplete="address-line2"
                                    <?php echo isset($detailErrors['address_line2']) ? 'aria-invalid="true" aria-describedby="address_line2-error"' : ''; ?>>
                                <?php if (isset($detailErrors['address_line2'])) : ?>
                                    <p class="form-error" id="address_line2-error"><?php echo customcore_e($detailErrors['address_line2']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="form-row--inline">
                                <div class="form-row">
                                    <label class="form-label" for="city">City</label>
                                    <input type="text" id="city" name="city"
                                        value="<?php echo customcore_e($values['city']); ?>"
                                        maxlength="100" autocomplete="address-level2"
                                        <?php echo isset($detailErrors['city']) ? 'aria-invalid="true" aria-describedby="city-error"' : ''; ?>>
                                    <?php if (isset($detailErrors['city'])) : ?>
                                        <p class="form-error" id="city-error"><?php echo customcore_e($detailErrors['city']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="form-row">
                                    <label class="form-label" for="province">Province</label>
                                    <input type="text" id="province" name="province"
                                        value="<?php echo customcore_e($values['province']); ?>"
                                        maxlength="100" autocomplete="address-level1"
                                        <?php echo isset($detailErrors['province']) ? 'aria-invalid="true" aria-describedby="province-error"' : ''; ?>>
                                    <?php if (isset($detailErrors['province'])) : ?>
                                        <p class="form-error" id="province-error"><?php echo customcore_e($detailErrors['province']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="form-row">
                                    <label class="form-label" for="postal_code">Postal code</label>
                                    <input type="text" id="postal_code" name="postal_code"
                                        value="<?php echo customcore_e($values['postal_code']); ?>"
                                        maxlength="20" autocomplete="postal-code"
                                        <?php echo isset($detailErrors['postal_code']) ? 'aria-invalid="true" aria-describedby="postal_code-error"' : ''; ?>>
                                    <?php if (isset($detailErrors['postal_code'])) : ?>
                                        <p class="form-error" id="postal_code-error"><?php echo customcore_e($detailErrors['postal_code']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </fieldset>

                        <div class="form-actions">
                            <button type="submit" class="button">Save details</button>
                            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('profile.php')); ?>">Cancel</a>
                        </div>
                    </form>
                </section>

                <section class="edit-section" aria-labelledby="password-heading">
                    <h2 id="password-heading">Change password</h2>
                    <form class="form-stack" method="post" action="<?php echo customcore_e(customcore_url('edit-profile.php')); ?>" novalidate>
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="form_action" value="password">

                        <div class="form-row">
                            <label class="form-label" for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password"
                                maxlength="200" autocomplete="current-password" required
                                <?php echo isset($passwordErrors['current_password']) ? 'aria-invalid="true" aria-describedby="current_password-error"' : ''; ?>>
                            <?php if (isset($passwordErrors['current_password'])) : ?>
                                <p class="form-error" id="current_password-error"><?php echo customcore_e($passwordErrors['current_password']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-row--inline">
                            <div class="form-row">
                                <label class="form-label" for="new_password">New password</label>
                                <input type="password" id="new_password" name="new_password"
                                    minlength="<?php echo customcore_e((string) $minPasswordLength); ?>" maxlength="200"
                                    autocomplete="new-password" required
                                    <?php echo isset($passwordErrors['new_password']) ? 'aria-invalid="true" aria-describedby="new_password-error new_password-hint"' : 'aria-describedby="new_password-hint"'; ?>>
                                <p class="form-help" id="new_password-hint">At least <?php echo customcore_e((string) $minPasswordLength); ?> characters.</p>
                                <?php if (isset($passwordErrors['new_password'])) : ?>
                                    <p class="form-error" id="new_password-error"><?php echo customcore_e($passwordErrors['new_password']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-row">
                                <label class="form-label" for="new_password_confirm">Confirm new password</label>
                                <input type="password" id="new_password_confirm" name="new_password_confirm"
                                    minlength="<?php echo customcore_e((string) $minPasswordLength); ?>" maxlength="200"
                                    autocomplete="new-password" required
                                    <?php echo isset($passwordErrors['new_password_confirm']) ? 'aria-invalid="true" aria-describedby="new_password_confirm-error"' : ''; ?>>
                                <?php if (isset($passwordErrors['new_password_confirm'])) : ?>
                                    <p class="form-error" id="new_password_confirm-error"><?php echo customcore_e($passwordErrors['new_password_confirm']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="button">Change password</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
