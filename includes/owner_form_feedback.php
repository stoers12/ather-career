<?php

declare(strict_types=1);

/** @param array<string, string> $fieldErrors */
function ownerFieldErrorMessage(array $fieldErrors, string $field): ?string
{
    $message = $fieldErrors[$field] ?? null;

    return is_string($message) && $message !== '' ? $message : null;
}

function ownerFieldErrorId(string $field): string
{
    $safeField = preg_replace('/[^a-z0-9_-]/i', '-', $field) ?? 'field';

    return 'owner-field-error-' . trim($safeField, '-');
}

/** @param array<string, string> $fieldErrors */
function ownerFieldAccessibilityAttributes(array $fieldErrors, string $field, ?string $helpId = null): string
{
    $describedBy = [];
    if ($helpId !== null && $helpId !== '') {
        $describedBy[] = $helpId;
    }

    $error = ownerFieldErrorMessage($fieldErrors, $field);
    if ($error !== null) {
        $describedBy[] = ownerFieldErrorId($field);
    }

    $attributes = $error === null ? '' : ' aria-invalid="true"';
    if ($describedBy !== []) {
        $attributes .= ' aria-describedby="' . ownerEscapeHtml(implode(' ', $describedBy)) . '"';
    }

    return $attributes;
}

/** @param array<string, string> $fieldErrors */
function ownerRenderFieldError(array $fieldErrors, string $field): void
{
    $error = ownerFieldErrorMessage($fieldErrors, $field);
    if ($error === null) {
        return;
    }
    ?>
    <p class="field-error" id="<?php echo ownerEscapeHtml(ownerFieldErrorId($field)); ?>"><?php echo ownerEscapeHtml($error); ?></p>
    <?php
}

function ownerRenderRequiredIndicator(): void
{
    ?>
    <span class="required-indicator" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span>
    <?php
}

/** @param list<string> $errors @param array<string, string> $fieldErrors */
function ownerRenderFormFeedback(string $successMessage, array $errors, array $fieldErrors = []): void
{
    if ($successMessage !== '') {
        ?>
        <p class="status-message" role="status" aria-live="polite"><?php echo ownerEscapeHtml($successMessage); ?></p>
        <?php
    }

    $messages = $errors;
    foreach ($fieldErrors as $error) {
        if (is_string($error) && $error !== '' && !in_array($error, $messages, true)) {
            $messages[] = $error;
        }
    }
    if ($messages === []) {
        return;
    }
    ?>
    <section class="status-message error owner-error-summary" id="owner-form-error-summary" role="alert" tabindex="-1" data-error-summary>
        <h2>Review the highlighted fields</h2>
        <ul>
            <?php foreach ($messages as $error): ?>
                <li><?php echo ownerEscapeHtml($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}
