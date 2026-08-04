<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Shared admin product form fields.
// Renders the product create/edit form body. Included by admin/product-add.php and admin/product-
// edit.php so both screens stay perfectly in sync.
// Expects (set by the including page before require):
//   $formValues array<string,mixed> Field values (validated or existing row).
//   $formErrors array<string,string> Field-keyed error messages ([] if none).
//   $formCategories list<array{id:int,name:string,slug:string}>
//   $formBrands list<string> Brand suggestions for the datalist.
//   $formIsEdit bool True on the edit screen.
//   $formImageUrl ?string Resolvable URL of the current image (edit).
//   $formImagePath string Stored image_path (edit, for display).
//   $formSubmitLabel string Submit button text.
// Access control is enforced by the including page (customcore_require_admin()).

declare(strict_types=1);

if (!isset($formValues) || !is_array($formValues)) {
    return;
}
$formErrors = isset($formErrors) && is_array($formErrors) ? $formErrors : [];
$formCategories = isset($formCategories) && is_array($formCategories) ? $formCategories : [];
$formBrands = isset($formBrands) && is_array($formBrands) ? $formBrands : [];
$formIsEdit = !empty($formIsEdit);
$formImageUrl = isset($formImageUrl) && is_string($formImageUrl) ? $formImageUrl : null;
$formImagePath = isset($formImagePath) && is_string($formImagePath) ? $formImagePath : '';
$formSubmitLabel = isset($formSubmitLabel) && is_string($formSubmitLabel) ? $formSubmitLabel : 'Save product';

/**
 * Small helper: field error markup or empty string.
 */
$fieldError = static function (string $key) use ($formErrors): string {
    if (!isset($formErrors[$key]) || $formErrors[$key] === '') {
        return '';
    }
    return '<span class="form-error" role="alert">' . customcore_e($formErrors[$key]) . '</span>';
};
?>
<div class="admin-form__grid">
    <div class="form-field form-field--wide">
        <label for="field-name">Product name <span class="form-required">*</span></label>
        <input
            type="text"
            id="field-name"
            name="name"
            value="<?php echo customcore_e((string) ($formValues['name'] ?? '')); ?>"
            maxlength="200"
            required
            <?php echo isset($formErrors['name']) ? 'aria-invalid="true"' : ''; ?>
        >
        <?php echo $fieldError('name'); ?>
    </div>

    <div class="form-field">
        <label for="field-category">Category <span class="form-required">*</span></label>
        <select id="field-category" name="category_id" required <?php echo isset($formErrors['category_id']) ? 'aria-invalid="true"' : ''; ?>>
            <option value="">Choose a category…</option>
            <?php foreach ($formCategories as $category) : ?>
                <option
                    value="<?php echo customcore_e((string) $category['id']); ?>"
                    <?php echo (int) ($formValues['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>
                ><?php echo customcore_e($category['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo $fieldError('category_id'); ?>
    </div>

    <div class="form-field">
        <label for="field-brand">Brand</label>
        <input
            type="text"
            id="field-brand"
            name="brand"
            value="<?php echo customcore_e((string) ($formValues['brand'] ?? 'CustomCore')); ?>"
            maxlength="100"
            list="brand-suggestions"
            <?php echo isset($formErrors['brand']) ? 'aria-invalid="true"' : ''; ?>
        >
        <datalist id="brand-suggestions">
            <?php foreach ($formBrands as $brand) : ?>
                <option value="<?php echo customcore_e($brand); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <?php echo $fieldError('brand'); ?>
    </div>

    <div class="form-field">
        <label for="field-price">Base price (USD) <span class="form-required">*</span></label>
        <input
            type="number"
            id="field-price"
            name="base_price"
            value="<?php echo customcore_e((string) ($formValues['base_price'] ?? '0.00')); ?>"
            min="0"
            max="999999.99"
            step="0.01"
            required
            <?php echo isset($formErrors['base_price']) ? 'aria-invalid="true"' : ''; ?>
        >
        <?php echo $fieldError('base_price'); ?>
    </div>

    <div class="form-field">
        <label for="field-stock">Stock quantity <span class="form-required">*</span></label>
        <input
            type="number"
            id="field-stock"
            name="stock_quantity"
            value="<?php echo customcore_e((string) ($formValues['stock_quantity'] ?? '0')); ?>"
            min="0"
            max="100000"
            step="1"
            required
            <?php echo isset($formErrors['stock_quantity']) ? 'aria-invalid="true"' : ''; ?>
        >
        <?php echo $fieldError('stock_quantity'); ?>
    </div>

    <div class="form-field form-field--wide">
        <label for="field-short">Short description</label>
        <textarea
            id="field-short"
            name="short_description"
            rows="2"
            maxlength="500"
            <?php echo isset($formErrors['short_description']) ? 'aria-invalid="true"' : ''; ?>
        ><?php echo customcore_e((string) ($formValues['short_description'] ?? '')); ?></textarea>
        <span class="form-hint">A one-line summary shown on catalogue cards (max 500 characters).</span>
        <?php echo $fieldError('short_description'); ?>
    </div>

    <div class="form-field form-field--wide">
        <label for="field-description">Full description <span class="form-required">*</span></label>
        <textarea
            id="field-description"
            name="description"
            rows="6"
            required
            <?php echo isset($formErrors['description']) ? 'aria-invalid="true"' : ''; ?>
        ><?php echo customcore_e((string) ($formValues['description'] ?? '')); ?></textarea>
        <?php echo $fieldError('description'); ?>
    </div>

    <div class="form-field">
        <label for="field-cpu">CPU spec</label>
        <input type="text" id="field-cpu" name="spec_cpu" maxlength="150"
               value="<?php echo customcore_e((string) ($formValues['spec_cpu'] ?? '')); ?>">
    </div>

    <div class="form-field">
        <label for="field-gpu">GPU spec</label>
        <input type="text" id="field-gpu" name="spec_gpu" maxlength="150"
               value="<?php echo customcore_e((string) ($formValues['spec_gpu'] ?? '')); ?>">
    </div>

    <div class="form-field">
        <label for="field-ram">RAM spec</label>
        <input type="text" id="field-ram" name="spec_ram" maxlength="100"
               value="<?php echo customcore_e((string) ($formValues['spec_ram'] ?? '')); ?>">
    </div>

    <div class="form-field">
        <label for="field-storage">Storage spec</label>
        <input type="text" id="field-storage" name="spec_storage" maxlength="100"
               value="<?php echo customcore_e((string) ($formValues['spec_storage'] ?? '')); ?>">
    </div>

    <div class="form-field form-field--wide">
        <label for="field-image">Product image</label>
        <?php if ($formIsEdit && $formImageUrl !== null) : ?>
            <div class="admin-form__current-image">
                <img
                    src="<?php echo customcore_e($formImageUrl); ?>"
                    alt="Current product image"
                    width="120"
                    height="120"
                    loading="lazy"
                    decoding="async"
                >
                <span class="admin-table__sub"><?php echo customcore_e($formImagePath); ?></span>
            </div>
        <?php elseif ($formIsEdit && $formImagePath !== '') : ?>
            <p class="form-hint">Current image path (<?php echo customcore_e($formImagePath); ?>) could not be located on disk.</p>
        <?php endif; ?>
        <input
            type="file"
            id="field-image"
            name="image"
            accept="image/jpeg,image/png,image/webp,image/gif"
            <?php echo isset($formErrors['image']) ? 'aria-invalid="true"' : ''; ?>
        >
        <span class="form-hint">
            JPG, PNG, WEBP, or GIF, up to 2&nbsp;MB.
            <?php echo $formIsEdit ? 'Leave blank to keep the current image.' : 'Optional.'; ?>
        </span>
        <?php echo $fieldError('image'); ?>
        <?php if ($formIsEdit && ($formImageUrl !== null || $formImagePath !== '')) : ?>
            <label class="form-check">
                <input type="checkbox" name="remove_image" value="1">
                Remove the current image
            </label>
        <?php endif; ?>
    </div>

    <div class="form-field form-field--wide admin-form__toggles">
        <label class="form-check">
            <input type="checkbox" name="is_active" value="1"
                   <?php echo (int) ($formValues['is_active'] ?? 1) === 1 ? 'checked' : ''; ?>>
            Active (visible in the catalogue)
        </label>
        <label class="form-check">
            <input type="checkbox" name="is_featured" value="1"
                   <?php echo (int) ($formValues['is_featured'] ?? 0) === 1 ? 'checked' : ''; ?>>
            Featured on the homepage
        </label>
    </div>
</div>

<div class="admin-form__actions">
    <button type="submit" class="button"><?php echo customcore_e($formSubmitLabel); ?></button>
    <a class="button button--ghost" href="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">Cancel</a>
</div>
