<?php
/**
 * CustomCore — Application entry point (homepage).
 *
 * File responsibility:
 *   Public homepage root. Expanded in Stage 3 with featured products from MySQL.
 *
 * Stage 1.1 note:
 *   This file establishes the web root. Shared header/footer/navigation arrive
 *   in Commits 1.4–1.7; catalogue content arrives in Stage 3.
 */

declare(strict_types=1);

$pageTitle = 'CustomCore — Custom Gaming PC Store & Builder';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CustomCore is a custom gaming PC store and PC-building platform with configurable prebuilts and a guided builder.">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body>
    <a href="#main-content">Skip to content</a>

    <header>
        <p><strong>CustomCore</strong></p>
        <!-- Shared navigation include arrives in Commit 1.4 / 1.7 -->
    </header>

    <main id="main-content">
        <h1>CustomCore</h1>
        <p>
            Custom gaming PC store and guided PC builder. The shared site foundation
            (layout, styles, scripts, and database connection) is being assembled in Stage 1.
        </p>
        <p>
            Planning documentation lives in the <code>docs/</code> directory.
            Catalogue, accounts, builder, and administrator features follow in later stages.
        </p>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> CustomCore</p>
        <!-- Shared footer include arrives in Commit 1.4 -->
    </footer>
</body>
</html>
