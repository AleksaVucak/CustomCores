<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator add product.
// Protected create screen for catalogue products. Validates input, stores an optional uploaded
// image securely, inserts the product, and redirects to the product list with a flash confirmation
// (Post/Redirect/Get).
// Access: Administrator role (customcore_require_admin()).

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/admin-products.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

$formErrors = [];
$formValues = [
    'category_id' => 0,
    'name' => '',
    'brand' => 'CustomCore',
    'short_description' => '',
    'description' => '',
    'base_price' => '0.00',
    'stock_quantity' => '0',
    'spec_cpu' => '',
    'spec_gpu' => '',
    'spec_ram' => '',
    'spec_storage' => '',
    'is_featured' => 0,
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    if (!customcore_csrf_verify($token)) {
        $formErrors['form'] = 'Your session expired. Please review the details and submit again.';
        // Preserve entered values for redisplay.
        $formValues = array_merge($formValues, customcore_admin_product_validate($pdo, $_POST)['values']);
    } else {
        $validation = customcore_admin_product_validate($pdo, $_POST);
        $formErrors = $validation['errors'];
        $formValues = $validation['values'];

        $imageResult = customcore_admin_product_validate_image($_FILES['image'] ?? null);
        if (!$imageResult['ok']) {
            $formErrors['image'] = (string) $imageResult['error'];
        }

        if ($formErrors === []) {
            $storedImagePath = '';
            $movedImage = null;
            try {
                if ($imageResult['provided'] && $imageResult['file'] !== null) {
                    $storedImagePath = customcore_admin_product_store_image($imageResult['file']);
                    $movedImage = $storedImagePath;
                }

                $newId = customcore_admin_product_create($pdo, $formValues, $storedImagePath);

                customcore_flash_success('“' . $formValues['name'] . '” was added to the catalogue.');
                customcore_redirect('admin/product-edit.php?id=' . $newId);
            } catch (Throwable $exception) {
                // Roll back a stored image if the insert failed.
                if ($movedImage !== null) {
                    customcore_admin_product_delete_image($movedImage);
                }
                $formErrors['form'] = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'The product could not be saved. Please try again.';
            }
        }
    }
}

$formCategories = [];
$formBrands = [];
try {
    $formCategories = customcore_admin_product_categories($pdo);
    $formBrands = customcore_admin_product_brands($pdo);
} catch (Throwable $exception) {
    // Non-fatal: the form still renders without suggestions.
}

$formIsEdit = false;
$formImageUrl = null;
$formImagePath = '';
$formSubmitLabel = 'Add product';

$adminNavCurrent = 'products';
$loadAdminCss = true;

$pageTitle = 'Add Product | CustomCore Admin';
$pageDescription = 'Create a new CustomCore catalogue product.';
$pageKeywords = 'CustomCore, admin, add product';
$currentPage = 'admin';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Add product: shared product form for creating a catalogue item -->
<section class="content-section admin-page admin-product-form" aria-labelledby="admin-add-heading">
    <header class="admin-page__header">
        <h1 id="admin-add-heading">Add product</h1>
        <p class="admin-page__intro">Create a new catalogue system. Fields marked <span class="form-required">*</span> are required.</p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">Back to product list</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Form-level error banner (e.g. expired session) -->
    <?php if (isset($formErrors['form'])) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($formErrors['form']); ?></p>
    <?php endif; ?>

    <!-- Product form: CSRF field plus shared add/edit fieldsets partial -->
    <form
        class="admin-form"
        method="post"
        action="<?php echo customcore_e(customcore_url('admin/product-add.php')); ?>"
        enctype="multipart/form-data"
        novalidate
    >
        <?php echo customcore_csrf_field(); ?>
        <?php require __DIR__ . '/../includes/admin-product-form.php'; ?>
    </form>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
