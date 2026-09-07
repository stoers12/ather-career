<?php

declare(strict_types=1);

require_once __DIR__ . '/public_lifecycle.php';
require_once __DIR__ . '/validation.php';

const PUBLIC_CONTACT_NAME_MAX_LENGTH = 100;
const PUBLIC_CONTACT_EMAIL_MAX_LENGTH = 255;
const PUBLIC_CONTACT_MESSAGE_MAX_LENGTH = 5000;

/** @return array{name: string, email: string, message: string} */
function publicContactSubmittedValues(array $submitted): array
{
    $values = [];
    foreach ([
        'name' => [PUBLIC_CONTACT_NAME_MAX_LENGTH, 'Name'],
        'email' => [PUBLIC_CONTACT_EMAIL_MAX_LENGTH, 'Email'],
        'message' => [PUBLIC_CONTACT_MESSAGE_MAX_LENGTH, 'Message'],
    ] as $field => [$maximum, $label]) {
        $values[$field] = submittedStringField($submitted, $field, $maximum, $label, true)['value'];
    }

    return $values;
}

/**
 * @return array{values: array{name: string, email: string, message: string}, errors: list<string>, field_errors: array<string, string>}
 */
function publicContactFormState(array $submitted): array
{
    $values = [];
    $fieldErrors = [];

    foreach ([
        'name' => [PUBLIC_CONTACT_NAME_MAX_LENGTH, 'Name'],
        'email' => [PUBLIC_CONTACT_EMAIL_MAX_LENGTH, 'Email'],
        'message' => [PUBLIC_CONTACT_MESSAGE_MAX_LENGTH, 'Message'],
    ] as $field => [$maximum, $label]) {
        $result = submittedStringField($submitted, $field, $maximum, $label, true);
        $values[$field] = $result['value'];
        if ($result['error'] !== null) {
            $fieldErrors[$field] = $result['error'];
        }
    }

    if (!isset($fieldErrors['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }

    return ['values' => $values, 'errors' => validationErrorList($fieldErrors), 'field_errors' => $fieldErrors];
}

/**
 * @return array{context: PublicReadContext|null, values: array{name: string, email: string, message: string}, errors: list<string>, field_errors: array<string, string>}
 */
function preparePublicContactSubmission(PDO $database, mixed $slug, array $submitted): array
{
    // This must run for every POST. A context from a prior public GET is never reused.
    $context = resolvePublicReadContext($database, $slug);
    if ($context === null) {
        return ['context' => null, ...publicContactFormState($submitted)];
    }

    return ['context' => $context, ...publicContactFormState($submitted)];
}

/** @param array{name: string, email: string, message: string} $values */
function createPublicContactMessage(PDO $database, PublicReadContext $context, array $values): int
{
    $statement = $database->prepare(
        'INSERT INTO messages (recipient_portfolio_id, name, email, message)
         VALUES (:recipient_portfolio_id, :name, :email, :message)'
    );
    $statement->execute([
        'recipient_portfolio_id' => $context->portfolioId,
        'name' => $values['name'],
        'email' => $values['email'],
        'message' => $values['message'],
    ]);

    return (int) $database->lastInsertId();
}
