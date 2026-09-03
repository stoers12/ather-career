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
    foreach (['name', 'email', 'message'] as $field) {
        $value = isset($submitted[$field]) && is_string($submitted[$field]) ? trim($submitted[$field]) : '';
        $values[$field] = utf8CharacterLength($value) === null ? '' : $value;
    }

    return $values;
}

/**
 * @return array{values: array{name: string, email: string, message: string}, errors: list<string>, field_errors: array<string, string>}
 */
function publicContactFormState(array $submitted): array
{
    $values = publicContactSubmittedValues($submitted);
    $errors = [];
    $fieldErrors = [];

    if ($values['name'] === '') {
        $fieldErrors['name'] = 'Name is required.';
    } elseif (utf8CharacterLength($values['name']) > PUBLIC_CONTACT_NAME_MAX_LENGTH) {
        $fieldErrors['name'] = 'Name must be 100 characters or fewer.';
    }

    if ($values['email'] === '') {
        $fieldErrors['email'] = 'Email is required.';
    } elseif (utf8CharacterLength($values['email']) > PUBLIC_CONTACT_EMAIL_MAX_LENGTH) {
        $fieldErrors['email'] = 'Email must be 255 characters or fewer.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }

    if ($values['message'] === '') {
        $fieldErrors['message'] = 'Message is required.';
    } elseif (utf8CharacterLength($values['message']) > PUBLIC_CONTACT_MESSAGE_MAX_LENGTH) {
        $fieldErrors['message'] = 'Message must be 5000 characters or fewer.';
    }

    foreach ($fieldErrors as $error) {
        $errors[] = $error;
    }

    return ['values' => $values, 'errors' => $errors, 'field_errors' => $fieldErrors];
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
