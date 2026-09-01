<?php

declare(strict_types=1);

require_once __DIR__ . '/public_lifecycle.php';
require_once __DIR__ . '/validation.php';

const PUBLIC_CONTACT_NAME_MAX_LENGTH = 100;
const PUBLIC_CONTACT_EMAIL_MAX_LENGTH = 255;
const PUBLIC_CONTACT_MESSAGE_MAX_LENGTH = 5000;

/**
 * @return array{context: PublicReadContext|null, values: array{name: string, email: string, message: string}|null, errors: list<string>}
 */
function preparePublicContactSubmission(PDO $database, mixed $slug, array $submitted): array
{
    // This must run for every POST. A context from a prior public GET is never reused.
    $context = resolvePublicReadContext($database, $slug);
    if ($context === null) {
        return ['context' => null, 'values' => null, 'errors' => []];
    }

    $name = isset($submitted['name']) && is_string($submitted['name']) ? trim($submitted['name']) : '';
    $email = isset($submitted['email']) && is_string($submitted['email']) ? trim($submitted['email']) : '';
    $message = isset($submitted['message']) && is_string($submitted['message']) ? trim($submitted['message']) : '';
    $errors = [];

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (($length = utf8CharacterLength($name)) === null || $length > PUBLIC_CONTACT_NAME_MAX_LENGTH) {
        $errors[] = 'Name must be 100 characters or fewer.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (($length = utf8CharacterLength($email)) === null || $length > PUBLIC_CONTACT_EMAIL_MAX_LENGTH) {
        $errors[] = 'Email must be 255 characters or fewer.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($message === '') {
        $errors[] = 'Message is required.';
    } elseif (($length = utf8CharacterLength($message)) === null || $length > PUBLIC_CONTACT_MESSAGE_MAX_LENGTH) {
        $errors[] = 'Message must be 5000 characters or fewer.';
    }

    return [
        'context' => $context,
        'values' => $errors === [] ? ['name' => $name, 'email' => $email, 'message' => $message] : null,
        'errors' => $errors,
    ];
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
