<?php
/**
 * CustomCore — Validated Checkout Form (Commit 6.4).
 *
 * File responsibility:
 *   Displays a checkout form with shipping address, contact information, and
 *   simulated payment method selection. Validates all fields on the server
 *   (and client via checkout.js). On valid submission, stores the validated
 *   data in the session so Commit 6.5 can convert the cart into an order.
 *
 * Flow:
 *   GET  — verify cart is not empty; show form pre-filled with profile data.
 *   POST — CSRF + field validation; on success store in session and redirect
 *          to order creation (Commit 6.5 will handle the actual insert).
 *
 * Authentication requirements:
 *   Logged-in customer (customcore_require_login).
 *
 * Security:
 *   - CSRF token required.
 *   - No real payment data collected (card numbers, CVV, etc.) — only a
 *     payment-method label like "pay_on_pickup" or "simulated_credit".
 *   - All outputs escaped via customcore_e().
 *   - Cart contents verified server-side before allowing checkout.
 *   - Shipping fields validated to reasonable lengths matching schema.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/cart.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'cart';

// ---------------------------------------------------------------------------
// Load cart and verify it is not empty
// ---------------------------------------------------------------------------

$cartItems = [];
$subtotal = 0.00;
$cartError = null;

try {
    $pdo = customcore_pdo();
    $cartId = customcore_cart_id($pdo, $userId);
    $cartItems = customcore_cart_items($pdo, $cartId);
    $subtotal = customcore_cart_subtotal($cartItems);
} catch (Throwable $exception) {
    $cartError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your cart. Please try again.';
}

if ($cartItems === [] && $cartError === null) {
    customcore_flash_warning('Your cart is empty. Add items before checking out.');
    customcore_redirect('cart.php');
}

// ---------------------------------------------------------------------------
// Pre-fill form values from user profile
// ---------------------------------------------------------------------------

$values = [
    'shipping_name' => '',
    'shipping_phone' => '',
    'shipping_addr1' => '',
    'shipping_addr2' => '',
    'shipping_city' => '',
    'shipping_prov' => '',
    'shipping_postal' => '',
    'payment_method' => 'pay_on_pickup',
];

try {
    $profileStmt = $pdo->prepare(
        'SELECT first_name, last_name, phone, address_line1, address_line2,
                city, province, postal_code
         FROM users
         WHERE id = :uid
         LIMIT 1'
    );
    $profileStmt->execute([':uid' => $userId]);
    $profile = $profileStmt->fetch();

    if ($profile !== false) {
        $fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
        $values['shipping_name'] = $fullName;
        $values['shipping_phone'] = (string) ($profile['phone'] ?? '');
        $values['shipping_addr1'] = (string) ($profile['address_line1'] ?? '');
        $values['shipping_addr2'] = (string) ($profile['address_line2'] ?? '');
        $values['shipping_city'] = (string) ($profile['city'] ?? '');
        $values['shipping_prov'] = (string) ($profile['province'] ?? '');
        $values['shipping_postal'] = (string) ($profile['postal_code'] ?? '');
    }
} catch (Throwable $e) {
    // Non-critical — form can be filled manually.
}

// ---------------------------------------------------------------------------
// Supported payment methods (labels only — no real payment processing)
// ---------------------------------------------------------------------------

$paymentMethods = [
    'pay_on_pickup' => 'Pay on pickup',
    'simulated_credit' => 'Credit card (simulated — no real data collected)',
    'simulated_debit' => 'Debit card (simulated — no real data collected)',
    'simulated_paypal' => 'PayPal (simulated)',
];

// ---------------------------------------------------------------------------
// Handle POST — validate and store checkout data
// ---------------------------------------------------------------------------

/** @var array<string, string> */
$errors = [];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!customcore_csrf_verify(isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null)) {
        $formError = 'Your session expired. Please review the form and submit again.';
    }

    // Collect + trim
    $values['shipping_name'] = isset($_POST['shipping_name']) && is_string($_POST['shipping_name'])
        ? trim($_POST['shipping_name']) : '';
    $values['shipping_phone'] = isset($_POST['shipping_phone']) && is_string($_POST['shipping_phone'])
        ? trim($_POST['shipping_phone']) : '';
    $values['shipping_addr1'] = isset($_POST['shipping_addr1']) && is_string($_POST['shipping_addr1'])
        ? trim($_POST['shipping_addr1']) : '';
    $values['shipping_addr2'] = isset($_POST['shipping_addr2']) && is_string($_POST['shipping_addr2'])
        ? trim($_POST['shipping_addr2']) : '';
    $values['shipping_city'] = isset($_POST['shipping_city']) && is_string($_POST['shipping_city'])
        ? trim($_POST['shipping_city']) : '';
    $values['shipping_prov'] = isset($_POST['shipping_prov']) && is_string($_POST['shipping_prov'])
        ? trim($_POST['shipping_prov']) : '';
    $values['shipping_postal'] = isset($_POST['shipping_postal']) && is_string($_POST['shipping_postal'])
        ? trim($_POST['shipping_postal']) : '';
    $values['payment_method'] = isset($_POST['payment_method']) && is_string($_POST['payment_method'])
        ? trim($_POST['payment_method']) : '';

    // ----- Validate shipping -----
    if ($values['shipping_name'] === '') {
        $errors['shipping_name'] = 'Full name is required.';
    } elseif (mb_strlen($values['shipping_name']) > 200) {
        $errors['shipping_name'] = 'Name must be 200 characters or fewer.';
    }

    if ($values['shipping_phone'] === '') {
        $errors['shipping_phone'] = 'Phone number is required.';
    } elseif (mb_strlen($values['shipping_phone']) > 30) {
        $errors['shipping_phone'] = 'Phone number must be 30 characters or fewer.';
    } elseif (!preg_match('/^[0-9+()\-.\s]+$/', $values['shipping_phone'])) {
        $errors['shipping_phone'] = 'Phone number contains invalid characters.';
    }

    if ($values['shipping_addr1'] === '') {
        $errors['shipping_addr1'] = 'Address line 1 is required.';
    } elseif (mb_strlen($values['shipping_addr1']) > 255) {
        $errors['shipping_addr1'] = 'Address line 1 must be 255 characters or fewer.';
    }

    if ($values['shipping_addr2'] !== '' && mb_strlen($values['shipping_addr2']) > 255) {
        $errors['shipping_addr2'] = 'Address line 2 must be 255 characters or fewer.';
    }

    if ($values['shipping_city'] === '') {
        $errors['shipping_city'] = 'City is required.';
    } elseif (mb_strlen($values['shipping_city']) > 100) {
        $errors['shipping_city'] = 'City must be 100 characters or fewer.';
    }

    if ($values['shipping_prov'] === '') {
        $errors['shipping_prov'] = 'Province / state is required.';
    } elseif (mb_strlen($values['shipping_prov']) > 100) {
        $errors['shipping_prov'] = 'Province / state must be 100 characters or fewer.';
    }

    if ($values['shipping_postal'] === '') {
        $errors['shipping_postal'] = 'Postal / ZIP code is required.';
    } elseif (mb_strlen($values['shipping_postal']) > 20) {
        $errors['shipping_postal'] = 'Postal / ZIP code must be 20 characters or fewer.';
    }

    // ----- Validate payment method -----
    if (!array_key_exists($values['payment_method'], $paymentMethods)) {
        $errors['payment_method'] = 'Please select a valid payment method.';
    }

    // ----- On success: store in session and redirect to order placement -----
    if ($formError === null && $errors === []) {
        $_SESSION['_cc_checkout'] = $values;
        customcore_redirect('order-confirmation.php');
    }
}

// ---------------------------------------------------------------------------
// Page metadata
// ---------------------------------------------------------------------------

$pageTitle = 'Checkout — CustomCore';
$pageDescription = 'Complete your order with shipping and payment details.';
$pageKeywords = 'CustomCore, checkout, shipping, payment, order';
$currentPage = 'checkout';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section checkout-page" aria-labelledby="checkout-heading" data-checkout-page>
    <header class="checkout-page__header">
        <h1 id="checkout-heading">Checkout</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help centre</a>
            — fill out your shipping details and choose a payment method to complete your order.
        </p>
    </header>

    <?php if ($cartError !== null): ?>
        <div class="flash flash--error" role="alert">
            <?php echo customcore_e($cartError); ?>
        </div>
        <p><a href="<?php echo customcore_e(customcore_url('cart.php')); ?>">&larr; Return to cart</a></p>
    <?php else: ?>

        <?php if ($formError !== null): ?>
            <div class="flash flash--error" role="alert">
                <?php echo customcore_e($formError); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="flash flash--warning" role="alert">
                <strong>Please correct the following:</strong>
                <ul class="flash__list">
                    <?php foreach ($errors as $msg): ?>
                        <li><?php echo customcore_e($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="checkout-layout">
            <form
                id="checkout-form"
                class="checkout-form"
                method="post"
                action="<?php echo customcore_e(customcore_url('checkout.php')); ?>"
                novalidate
                data-checkout-form
            >
                <?php echo customcore_csrf_field(); ?>

                <!-- Shipping details -->
                <fieldset class="checkout-fieldset">
                    <legend class="checkout-fieldset__legend">Shipping address</legend>

                    <div class="form-row">
                        <label class="form-label" for="shipping_name">Full name <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="shipping_name"
                            name="shipping_name"
                            value="<?php echo customcore_e($values['shipping_name']); ?>"
                            maxlength="200"
                            autocomplete="name"
                            required
                            <?php echo isset($errors['shipping_name']) ? ' aria-invalid="true" aria-describedby="err-shipping-name"' : ''; ?>
                        >
                        <?php if (isset($errors['shipping_name'])): ?>
                            <p class="form-error" id="err-shipping-name"><?php echo customcore_e($errors['shipping_name']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="shipping_phone">Phone number <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="tel"
                            id="shipping_phone"
                            name="shipping_phone"
                            value="<?php echo customcore_e($values['shipping_phone']); ?>"
                            maxlength="30"
                            autocomplete="tel"
                            required
                            <?php echo isset($errors['shipping_phone']) ? ' aria-invalid="true" aria-describedby="err-shipping-phone"' : ''; ?>
                        >
                        <?php if (isset($errors['shipping_phone'])): ?>
                            <p class="form-error" id="err-shipping-phone"><?php echo customcore_e($errors['shipping_phone']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="shipping_addr1">Address line 1 <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="shipping_addr1"
                            name="shipping_addr1"
                            value="<?php echo customcore_e($values['shipping_addr1']); ?>"
                            maxlength="255"
                            autocomplete="address-line1"
                            required
                            <?php echo isset($errors['shipping_addr1']) ? ' aria-invalid="true" aria-describedby="err-shipping-addr1"' : ''; ?>
                        >
                        <?php if (isset($errors['shipping_addr1'])): ?>
                            <p class="form-error" id="err-shipping-addr1"><?php echo customcore_e($errors['shipping_addr1']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="shipping_addr2">Address line 2 <span class="optional">(optional)</span></label>
                        <input
                            type="text"
                            id="shipping_addr2"
                            name="shipping_addr2"
                            value="<?php echo customcore_e($values['shipping_addr2']); ?>"
                            maxlength="255"
                            autocomplete="address-line2"
                            <?php echo isset($errors['shipping_addr2']) ? ' aria-invalid="true" aria-describedby="err-shipping-addr2"' : ''; ?>
                        >
                        <?php if (isset($errors['shipping_addr2'])): ?>
                            <p class="form-error" id="err-shipping-addr2"><?php echo customcore_e($errors['shipping_addr2']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-row form-row--half">
                        <div class="form-row">
                            <label class="form-label" for="shipping_city">City <span class="required" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="shipping_city"
                                name="shipping_city"
                                value="<?php echo customcore_e($values['shipping_city']); ?>"
                                maxlength="100"
                                autocomplete="address-level2"
                                required
                                <?php echo isset($errors['shipping_city']) ? ' aria-invalid="true" aria-describedby="err-shipping-city"' : ''; ?>
                            >
                            <?php if (isset($errors['shipping_city'])): ?>
                                <p class="form-error" id="err-shipping-city"><?php echo customcore_e($errors['shipping_city']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="shipping_prov">Province / State <span class="required" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="shipping_prov"
                                name="shipping_prov"
                                value="<?php echo customcore_e($values['shipping_prov']); ?>"
                                maxlength="100"
                                autocomplete="address-level1"
                                required
                                <?php echo isset($errors['shipping_prov']) ? ' aria-invalid="true" aria-describedby="err-shipping-prov"' : ''; ?>
                            >
                            <?php if (isset($errors['shipping_prov'])): ?>
                                <p class="form-error" id="err-shipping-prov"><?php echo customcore_e($errors['shipping_prov']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="shipping_postal">Postal / ZIP code <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="shipping_postal"
                            name="shipping_postal"
                            value="<?php echo customcore_e($values['shipping_postal']); ?>"
                            maxlength="20"
                            autocomplete="postal-code"
                            required
                            <?php echo isset($errors['shipping_postal']) ? ' aria-invalid="true" aria-describedby="err-shipping-postal"' : ''; ?>
                        >
                        <?php if (isset($errors['shipping_postal'])): ?>
                            <p class="form-error" id="err-shipping-postal"><?php echo customcore_e($errors['shipping_postal']); ?></p>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <!-- Payment method -->
                <fieldset class="checkout-fieldset">
                    <legend class="checkout-fieldset__legend">Payment method</legend>
                    <p class="checkout-fieldset__note">
                        This is a simulated checkout — no real payment information is collected or processed.
                    </p>

                    <div class="checkout-payment-options">
                        <?php foreach ($paymentMethods as $key => $label): ?>
                            <label class="checkout-payment-option">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="<?php echo customcore_e($key); ?>"
                                    <?php echo $values['payment_method'] === $key ? ' checked' : ''; ?>
                                    required
                                >
                                <span class="checkout-payment-option__label">
                                    <?php echo customcore_e($label); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if (isset($errors['payment_method'])): ?>
                        <p class="form-error"><?php echo customcore_e($errors['payment_method']); ?></p>
                    <?php endif; ?>
                </fieldset>

                <!-- Submit -->
                <div class="checkout-submit">
                    <button type="submit" class="button button--primary checkout-submit__btn">
                        Place order — $<?php echo customcore_e(number_format($subtotal, 2)); ?>
                    </button>
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('cart.php')); ?>">
                        &larr; Return to cart
                    </a>
                </div>
            </form>

            <!-- Order summary sidebar -->
            <aside class="checkout-summary" aria-labelledby="summary-heading">
                <h2 id="summary-heading" class="checkout-summary__title">Order summary</h2>
                <ul class="checkout-summary__list">
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                        $isBuild = $item['item_type'] === 'saved_build';
                        $lineTotal = $item['line_total'];
                        ?>
                        <li class="checkout-summary__item">
                            <span class="checkout-summary__name">
                                <?php echo customcore_e($item['name']); ?>
                                <?php if (!$isBuild && $item['quantity'] > 1): ?>
                                    <span class="checkout-summary__qty">&times;<?php echo customcore_e((string) $item['quantity']); ?></span>
                                <?php endif; ?>
                                <?php if ($isBuild): ?>
                                    <span class="checkout-summary__badge">Build</span>
                                <?php endif; ?>
                            </span>
                            <span class="checkout-summary__price">
                                $<?php echo customcore_e(number_format($lineTotal, 2)); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="checkout-summary__total">
                    <span>Subtotal</span>
                    <span>$<?php echo customcore_e(number_format($subtotal, 2)); ?></span>
                </div>
                <p class="checkout-summary__note">
                    Taxes and final total calculated at order confirmation.
                </p>
            </aside>
        </div>

    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
