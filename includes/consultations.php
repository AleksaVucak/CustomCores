<?php
/**
 * CustomCore — Consultation helper functions (Commit 7.3).
 *
 * File responsibility:
 *   Shared logic for PC consultation requests: allowed status values and
 *   labels/classes, budget option list, server-side validation of the request
 *   form, and insertion of a new request (status = open). Attachments arrive in
 *   Commit 7.4; customer history and admin responses in Commits 7.5–7.6.
 *
 * Authentication requirements:
 *   Creation expects a logged-in user (consultation_requests.user_id FK).
 *   Callers must enforce login before customcore_consultation_create().
 */

declare(strict_types=1);

/**
 * Allowed consultation status values (matches consultation_requests.status ENUM).
 *
 * @return list<string>
 */
function customcore_consultation_statuses(): array
{
    return ['open', 'in_progress', 'answered', 'closed'];
}

/**
 * Human-readable label for a consultation status.
 */
function customcore_consultation_status_label(string $status): string
{
    $labels = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'answered' => 'Answered',
        'closed' => 'Closed',
    ];

    return $labels[$status] ?? $status;
}

/**
 * CSS modifier class for a consultation status badge.
 */
function customcore_consultation_status_class(string $status): string
{
    $known = customcore_consultation_statuses();

    return in_array($status, $known, true)
        ? 'consult-status--' . $status
        : 'consult-status--open';
}

/**
 * Selectable budget ranges for the request form. Stored as a label string.
 *
 * @return list<string>
 */
function customcore_consultation_budget_options(): array
{
    return [
        'Under $1,000',
        '$1,000 – $1,500',
        '$1,500 – $2,000',
        '$2,000 – $3,000',
        '$3,000 – $4,000',
        '$4,000+',
        'Not sure yet',
    ];
}

/**
 * Validate consultation request form fields.
 *
 * Required: budget (from the allowed list), games, software, performance_goals.
 * Optional: notes.
 *
 * @param array<string, mixed> $input Raw form values.
 * @return array{
 *   ok: bool,
 *   errors: array<string, string>,
 *   values: array{budget: string, games: string, software: string, performance_goals: string, notes: string}
 * }
 */
function customcore_consultation_validate(array $input): array
{
    $errors = [];

    $budget = isset($input['budget']) && is_string($input['budget']) ? trim($input['budget']) : '';
    if ($budget === '') {
        $errors['budget'] = 'Please select an approximate budget.';
    } elseif (!in_array($budget, customcore_consultation_budget_options(), true)) {
        $errors['budget'] = 'Please choose a budget from the list.';
        $budget = '';
    }

    $textFields = [
        'games' => [
            'label' => 'the games you play',
            'min' => 3,
            'max' => 2000,
        ],
        'software' => [
            'label' => 'the software you use',
            'min' => 2,
            'max' => 2000,
        ],
        'performance_goals' => [
            'label' => 'your performance goals',
            'min' => 3,
            'max' => 2000,
        ],
    ];

    $values = [
        'budget' => $budget,
        'games' => '',
        'software' => '',
        'performance_goals' => '',
        'notes' => '',
    ];

    foreach ($textFields as $field => $rules) {
        $value = isset($input[$field]) && is_string($input[$field]) ? trim($input[$field]) : '';

        if ($value === '') {
            $errors[$field] = 'Please describe ' . $rules['label'] . '.';
        } elseif (mb_strlen($value) < $rules['min']) {
            $errors[$field] = 'Please provide a little more detail about ' . $rules['label'] . '.';
        } elseif (mb_strlen($value) > $rules['max']) {
            $errors[$field] = 'Please keep this under ' . number_format($rules['max']) . ' characters.';
            $value = mb_substr($value, 0, $rules['max']);
        }

        $values[$field] = $value;
    }

    $notes = isset($input['notes']) && is_string($input['notes']) ? trim($input['notes']) : '';
    if (mb_strlen($notes) > 2000) {
        $errors['notes'] = 'Please keep notes under 2,000 characters.';
        $notes = mb_substr($notes, 0, 2000);
    }
    $values['notes'] = $notes;

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'values' => $values,
    ];
}

/**
 * Insert a new consultation request (status = open).
 *
 * @param array{budget: string, games: string, software: string, performance_goals: string, notes: string} $values
 * @return int The new consultation request ID.
 */
function customcore_consultation_create(PDO $pdo, int $userId, array $values): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO consultation_requests
            (user_id, budget, games, software, performance_goals, notes, status)
         VALUES
            (:uid, :budget, :games, :software, :goals, :notes, :status)'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':budget' => $values['budget'],
        ':games' => $values['games'],
        ':software' => $values['software'],
        ':goals' => $values['performance_goals'],
        ':notes' => ($values['notes'] === '' ? null : $values['notes']),
        ':status' => 'open',
    ]);

    return (int) $pdo->lastInsertId();
}
