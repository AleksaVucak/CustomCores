<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Contact form helpers.
// Shared validation and storage for the public contact / support form. Guests and logged-in
// customers can submit; user_id is optional and is set only from the server session when the
// visitor is authenticated.
// Access: None. Callers may optionally pass a user_id when the visitor is logged in.

declare(strict_types=1);

/**
 * Suggested subject options for the contact form (free-text still allowed via
 * the "Other" path by typing into the subject field).
 *
 * @return list<string>
 */
function customcore_contact_subject_options(): array
{
    return [
        'General question',
        'Order support',
        'Product availability',
        'PC builder help',
        'Consultation follow-up',
        'Website feedback',
        'Other',
    ];
}

/**
 * Validate contact form fields.
 *
 * @param array<string, mixed> $input Raw form values.
 * @return array{
 * ok: bool
 * errors: array<string, string>
 * values: array{name: string, email: string, subject: string, message: string}
 * }
 */
function customcore_contact_validate(array $input): array
{
    $errors = [];

    $name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : '';
    if ($name === '') {
        $errors['name'] = 'Please enter your name.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter a name with at least 2 characters.';
    } elseif (mb_strlen($name) > 200) {
        $errors['name'] = 'Name must be 200 characters or fewer.';
        $name = mb_substr($name, 0, 200);
    }

    $email = isset($input['email']) && is_string($input['email']) ? trim($input['email']) : '';
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (mb_strlen($email) > 255) {
        $errors['email'] = 'Email must be 255 characters or fewer.';
        $email = mb_substr($email, 0, 255);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    $subject = isset($input['subject']) && is_string($input['subject']) ? trim($input['subject']) : '';
    if ($subject === '') {
        $errors['subject'] = 'Please choose or enter a subject.';
    } elseif (mb_strlen($subject) > 300) {
        $errors['subject'] = 'Subject must be 300 characters or fewer.';
        $subject = mb_substr($subject, 0, 300);
    }

    $message = isset($input['message']) && is_string($input['message']) ? trim($input['message']) : '';
    if ($message === '') {
        $errors['message'] = 'Please enter your message.';
    } elseif (mb_strlen($message) < 10) {
        $errors['message'] = 'Please write at least 10 characters so we can help.';
    } elseif (mb_strlen($message) > 5000) {
        $errors['message'] = 'Message must be 5,000 characters or fewer.';
        $message = mb_substr($message, 0, 5000);
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'values' => [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
        ],
    ];
}

/**
 * Insert a contact message. user_id may be null for guests.
 *
 * @param array{name: string, email: string, subject: string, message: string} $values
 * @return int The new contact_messages ID.
 */
function customcore_contact_create(PDO $pdo, array $values, ?int $userId = null): int
{
    $uid = ($userId !== null && $userId > 0) ? $userId : null;

    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (user_id, name, email, subject, message, is_read)
         VALUES (:uid, :name, :email, :subject, :message, 0)'
    );
    $stmt->execute([
        ':uid' => $uid,
        ':name' => $values['name'],
        ':email' => $values['email'],
        ':subject' => $values['subject'],
        ':message' => $values['message'],
    ]);

    return (int) $pdo->lastInsertId();
}
