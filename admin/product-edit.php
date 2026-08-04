<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator edit product.
// Protected edit screen for a single catalogue product. Updates price, stock, description, specs,
// feature/active flags, and the product image (replace or remove). Uses Post/Redirect/Get with
// flash confirmations.
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

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($productId < 1 && isset($_POST['product_id'])) {
    $productId = (int) $_POST['product_id'];
}

$product = $productId > 0 ? customcore_admin_product_fetch($pdo, $productId) : null;

if ($product === null) {
    customcore_flash_error('That product could not be found.');
    customcore_redirect('admin/products.php');
}

$formErrors = [];
$formValues = [
    'category_id' => (int) $product['category_id'],
    'name' => (string) $product['name'],
    'brand' => (string) $product['brand'],
    'short_description' => (string) $product['short_description'],
    'description' => (string) $product['description'],
    'base_price' => (string) $product['base_price'],
    'stock_quantity' => (string) $product['stock_quantity'],
    'spec_cpu' => (string) $product['spec_cpu'],
    'spec_gpu' => (string) $product['spec_gpu'],
    'spec_ram' => (string) $product['spec_ram'],
    'spec_storage' => (string) $product['spec_storage'],
    'is_featured' => (int) $product['is_featured'],
    'is_active' => (int) $product['is_active'],
];

$currentImagePath = (string) $product['image_path'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    if (!customcore_csrf_verify($token)) {
        $formErrors['form'] = 'Your session expired. Please review the details and submit again.';
        $formValues = array_merge($formValues, customcore_admin_product_validate($pdo, $_POST, $productId)['values']);
    } else {
        $validation = customcore_admin_product_validate($pdo, $_POST, $productId);
        $formErrors = $validation['errors'];
        $formValues = $validation['values'];

        $imageResult = customcore_admin_product_validate_image($_FILES['image'] ?? null);
        if (!$imageResult['ok']) {
            $formErrors['image'] = (string) $imageResult['error'];
        }

        $removeImage = !empty($_POST['remove_image']);

        if ($formErrors === []) {
            $newImagePath = null; // null => keep current
            $movedImage = null;
            try {
                if ($imageResult['provided'] && $imageResult['file'] !== null) {
                    $newImagePath = customcore_admin_product_store_image($imageResult['file']);
                    $movedImage = $newImagePath;
                } elseif ($removeImage) {
                    $newImagePath = '';
                }

                customcore_admin_product_update($pdo, $productId, $formValues, $newImagePath);

                // Once the row is updated, clean up the replaced/removed upload.
                if ($newImagePath !== null && $currentImagePath !== '' && $currentImagePath !== $newImagePath) {
                    customcore_admin_product_delete_image($currentImagePath);
                }

                customcore_flash_success('“' . $formValues['name'] . '” was updated.');
                customcore_redirect('admin/product-edit.php?id=' . $productId);
            } catch (Throwable $exception) {
                if ($movedImage !== null) {
                    customcore_admin_product_delete_image($movedImage);
                }
                $formErrors['form'] = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'The product could not be saved. Please try again.';
            }
        }
    }

    // Re-fetch the stored path so the form shows the correct current image.
    $refreshed = customcore_admin_product_fetch($pdo, $productId);
    if ($refreshed !== null) {
        $currentImagePath = (string) $refreshed['image_path'];
    }
}

$formCategories = [];
$formBrands = [];
try {
    $formCategories = customcore_admin_product_categories($pdo);
    $formBrands = customcore_admin_product_brands($pdo);
} catch (Throwable $exception) {
    // Non-fatal.
}

$formIsEdit = true;
$formImageUrl = customcore_product_image_url($currentImagePath);
$formImagePath = $currentImagePath;
$formSubmitLabel = 'Save changes';

$adminNavCurrent = 'products';
$loadAdminCss = true;

$pageTitle = 'Edit ' . (string) $product['name'] . ' | CustomCore Admin';
$pageDescription = 'Edit a CustomCore catalogue product.';
$pageKeywords = 'CustomCore, admin, edit product';
$currentPage = 'admin';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Edit product: shared product form pre-filled with existing values -->
<section class="content-section admin-page admin-product-form" aria-labelledby="admin-edit-heading">
    <header class="admin-page__header">
        <h1 id="admin-edit-heading">Edit product</h1>
        <p class="admin-page__intro">
            Editing <strong><?php echo customcore_e((string) $product['name']); ?></strong>.
            Changes appear in the store immediately when the product is active.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">Back to product list</a>
            <?php if ((int) $formValues['is_active'] === 1) : ?>
                ·
                <a href="<?php echo customcore_e(customcore_url('product.php?slug=' . rawurlencode((string) $product['slug']))); ?>" target="_blank" rel="noopener">View in store</a>
            <?php endif; ?>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Form-level error banner (e.g. expired session) -->
    <?php if (isset($formErrors['form'])) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($formErrors['form']); ?></p>
    <?php endif; ?>

    <!-- Product form: CSRF + product id plus shared add/edit fieldsets partial -->
    <form
        class="admin-form"
        method="post"
        action="<?php echo customcore_e(customcore_url('admin/product-edit.php?id=' . $productId)); ?>"
        enctype="multipart/form-data"
        novalidate
    >
        <?php echo customcore_csrf_field(); ?>
        <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
        <?php require __DIR__ . '/../includes/admin-product-form.php'; ?>
    </form>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
