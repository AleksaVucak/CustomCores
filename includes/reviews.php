<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Review helper functions.
// Shared review operations for customer submission: validate rating/title/body, insert new reviews
// with status = pending (moderation queue), and inspect a user's existing review for a product so
// the form can avoid duplicates. Public display of approved reviews remains in reviews.php /
// product.php. Administrator approve/hide UI lives under admin/reviews.php.
// Access: Submission helpers expect a logged-in user. Callers must enforce login before invoking
// customcore_review_submit().

declare(strict_types=1);

/**
 * Allowed review status values (matches reviews.status ENUM).
 *
 * @return list<string>
 */
function customcore_review_statuses(): array
{
    return ['pending', 'approved', 'hidden'];
}

/**
 * Human-readable label for a review status.
 */
function customcore_review_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending moderation',
        'approved' => 'Approved',
        'hidden' => 'Hidden',
    ];

    return $labels[$status] ?? $status;
}

/**
 * Validate review form fields.
 *
 * @return array{
 * ok: bool
 * errors: array<string, string>
 * rating: int
 * title: string
 * body: string
 * }
 */
function customcore_review_validate(array $input): array
{
    $errors = [];

    $ratingRaw = $input['rating'] ?? null;
    $rating = 0;
    if (is_int($ratingRaw)) {
        $rating = $ratingRaw;
    } elseif (is_string($ratingRaw) && ctype_digit($ratingRaw)) {
        $rating = (int) $ratingRaw;
    }

    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Please choose a rating from 1 to 5 stars.';
        $rating = 0;
    }

    $title = isset($input['title']) && is_string($input['title'])
        ? trim($input['title'])
        : '';
    if ($title === '') {
        $errors['title'] = 'Please enter a review title.';
    } elseif (mb_strlen($title) > 200) {
        $errors['title'] = 'Title must be 200 characters or fewer.';
        $title = mb_substr($title, 0, 200);
    }

    $body = isset($input['body']) && is_string($input['body'])
        ? trim($input['body'])
        : '';
    if ($body === '') {
        $errors['body'] = 'Please write your review.';
    } elseif (mb_strlen($body) < 20) {
        $errors['body'] = 'Please write at least 20 characters so other customers get useful feedback.';
    } elseif (mb_strlen($body) > 5000) {
        $errors['body'] = 'Review must be 5,000 characters or fewer.';
        $body = mb_substr($body, 0, 5000);
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'rating' => $rating,
        'title' => $title,
        'body' => $body,
    ];
}

/**
 * Fetch the user's most recent non-hidden review for a product, if any.
 *
 * Used to prevent stacking multiple pending/approved reviews for the same
 * product. Hidden reviews do not block a new submission.
 *
 * @return array<string, mixed>|null
 */
function customcore_review_user_existing(PDO $pdo, int $userId, int $productId): ?array
{
    if ($userId < 1 || $productId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, rating, title, body, status, created_at
         FROM reviews
         WHERE user_id = :uid
           AND product_id = :pid
           AND status IN ("pending", "approved")
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Confirm a product exists and is active (safe to attach a review to).
 *
 * @return array<string, mixed>|null Product row or null.
 */
function customcore_review_product(PDO $pdo, int $productId): ?array
{
    if ($productId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, slug, is_active
         FROM products
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $productId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Insert a new review with status = pending.
 *
 * @return int The new review ID.
 */
function customcore_review_submit(
    PDO $pdo,
    int $userId,
    int $productId,
    int $rating,
    string $title,
    string $body
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO reviews (product_id, user_id, rating, title, body, status)
         VALUES (:pid, :uid, :rating, :title, :body, :status)'
    );
    $stmt->execute([
        ':pid' => $productId,
        ':uid' => $userId,
        ':rating' => $rating,
        ':title' => $title,
        ':body' => $body,
        ':status' => 'pending',
    ]);

    return (int) $pdo->lastInsertId();
}
