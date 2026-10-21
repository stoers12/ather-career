<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_writes.php';

function retireProfileImageAfterOutcome(?string $path, string $event, int $portfolioId): void
{
    try { cleanProfileImage($path, $event, $portfolioId); }
    catch (Throwable $exception) { reportApplicationError($exception, 'owner_profile.php', $event); }
}

/** The caller transfers ownership of its newly staged key to this operation. */
function saveOwnedProfileImage(PDO $database, AuthorizedPortfolioContext $context, int $profileId, int $expectedVersion, ?string $newKey): array
{
    try { $saved = saveOwnedProfilePatch($database, $context, $profileId, $expectedVersion, ['profile_image_path' => $newKey]); }
    catch (Throwable $exception) {
        if (!$exception instanceof ProfileWriteUncertainException && $newKey !== null) {
            retireProfileImageAfterOutcome($newKey, 'owner_profile_confirmed_failure_cleanup', $context->portfolioId);
        }
        throw $exception;
    }
    if ($saved['previous_image_path'] !== $newKey) {
        retireProfileImageAfterOutcome($saved['previous_image_path'], 'owner_profile_retire_committed_image', $context->portfolioId);
    }
    return $saved;
}

/** Preserve the submitted display values/token; only the validated patch is saved. */
function handleVersionedProfileAction(PDO $database, AuthorizedPortfolioContext $context, array $post, array $files, ?array $current, array $fields, array $profile): array
{
    $action = $post['action'];
    $profile = array_merge($profile, $current ?? []);
    $profile['_submitted_profile_version'] = is_string($post['expected_profile_version'] ?? null) ? $post['expected_profile_version'] : '';
    $expected = submittedProfileVersion($post['expected_profile_version'] ?? null);
    $fieldErrors = []; $patch = []; $newImagePath = null;
    $allowed = ['action', 'csrf_token', 'profile_id', 'expected_profile_version'];
    if ($action === 'save_profile') {
        $allowed = [...$allowed, ...$fields, 'public_email_present', 'public_email_visible', 'public_phone_present', 'public_phone_visible'];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $post)) { continue; }
            if (is_string($post[$field])) { $profile[$field] = $post[$field]; }
            if (in_array($field, FINAL_PROFILE_FIELDS, true)) {
                $value = $post[$field] === '' ? null : $post[$field];
                $error = is_string($post[$field]) ? finalProfileFieldError($field, $value, finalProfileCatalog())
                    : 'Use text for this field. / أدخل نصًا في هذا الحقل.';
                // An unchanged unsupported saved token is historical data, not a new selection.
                // Omit it from the patch; the original expected version still guards the save.
                if ($field !== 'specialization' && $error !== null && is_string($value)
                    && $value === ($current[$field] ?? null)) { continue; }
                if ($error !== null) { $fieldErrors[$field] = $error; }
                else { $patch[$field] = $value; }
                continue;
            }
            $label = match ($field) {
                'hero_headline' => 'Hero headline',
                'work_description' => 'Professional summary',
                default => ucwords(str_replace('_', ' ', $field)),
            };
            $validated = submittedStringField($post, $field, PERSONAL_INFO_FIELD_MAX_LENGTHS[$field], $label, $field === 'full_name');
            if ($validated['error'] !== null) { $fieldErrors[$field] = $validated['error']; }
            $patch[$field] = $validated['value'] === '' ? null : $validated['value'];
        }
        if ($expected === 0 && !array_key_exists('full_name', $post)) { $fieldErrors['full_name'] = 'Full Name is required.'; }
        if (isset($patch['email']) && filter_var($patch['email'], FILTER_VALIDATE_EMAIL) === false) { $fieldErrors['email'] = 'Please enter a valid email address.'; }
        foreach (['linkedin_url', 'github_url', 'instagram_url', 'facebook_url', 'website_url'] as $field) {
            if (isset($patch[$field]) && !isSafeHttpUrl($patch[$field])) { $fieldErrors[$field] = 'Please enter a valid HTTP or HTTPS URL.'; }
        }
        foreach (['public_email_visible' => 'public_email_present', 'public_phone_visible' => 'public_phone_present'] as $field => $marker) {
            $hasVisibility = array_key_exists($field, $post);
            $hasMarker = array_key_exists($marker, $post);
            if (($hasMarker && $post[$marker] !== '1') || ($hasVisibility && !in_array($post[$field], ['0', '1'], true))) {
                $fieldErrors[$field] = 'Please select a valid contact visibility option.';
            } elseif ($hasVisibility || $hasMarker) {
                $patch[$field] = $profile[$field] = $hasVisibility && $post[$field] === '1';
            }
        }
    }
    if (array_diff(array_keys($post), $allowed) !== [] || ($action !== 'upload_profile_image' && $files !== [])
        || ($action === 'upload_profile_image' && array_diff(array_keys($files), ['profile_image']) !== [])) {
        $fieldErrors['profile'] = 'The form contains unsupported fields. Please reload and review.';
    }
    if ($expected === null) { $fieldErrors['profile'] = 'This form has no valid profile version. Reload and review before saving.'; }
    $outdatedContact = array_key_exists('public_contact_present', $post) || array_key_exists('public_contact_visible', $post);
    if ($outdatedContact) {
        $fieldErrors['profile'] = 'This contact form is outdated. Your entries are kept here; reload and review email and primary-phone visibility separately before saving.';
    }
    if ($fieldErrors !== []) { return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, $expected === null || $outdatedContact ? 409 : 422); }
    try {
        if ($database->inTransaction()) { throw new LogicException('Profile form cannot join another transaction.'); }
        $target = ownerProfileActionTarget($database, $context, $post, $current);
        if (array_key_exists('profile_id', $post) && $target === null) {
            $fieldErrors['profile_id'] = ownerActionId($post['profile_id']) === null ? 'Please provide a valid profile ID.' : 'Profile not found.';
            return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, ownerActionId($post['profile_id']) === null ? 422 : 404);
        }
        // Reject a stale photo form before staging, and repeat atomically after staging.
        if (($current === null ? 0 : (int) $current['profile_version']) !== $expected) { throw new ProfileVersionConflictException(); }
        if ($action === 'upload_profile_image') {
            if ($target === null) { throw new ProfileVersionConflictException(); }
            $errors = [];
            $newImagePath = storeValidatedProfileImage($files['profile_image'] ?? [], $errors, $context->portfolioId);
            if ($errors !== []) { return ownerProfileActionResult($errors, $profile, null, ['profile_image' => $errors[0]], 422); }
            $patch = ['profile_image_path' => $newImagePath];
        } elseif ($action === 'remove_profile_image') {
            if ($target === null) { throw new ProfileVersionConflictException(); }
            $patch = ['profile_image_path' => null];
        }
        if ($action === 'save_profile') {
            saveOwnedProfilePatch($database, $context, $target === null ? null : (int) $target['id'], $expected, $patch);
        } else {
            $stagedKey = $newImagePath;
            $newImagePath = null; // The image operation now owns compensation.
            saveOwnedProfileImage($database, $context, (int) $target['id'], $expected, $stagedKey);
        }
        return ownerProfileActionResult([], $profile, 'owner_profile.php?' . match ($action) {
            'upload_profile_image' => 'photo_updated=1', 'remove_profile_image' => 'photo_removed=1', default => 'saved=1',
        });
    } catch (Throwable $exception) {
        if (!$exception instanceof ProfileWriteUncertainException && $newImagePath !== null) {
            retireProfileImageAfterOutcome($newImagePath, 'owner_profile_confirmed_failure_cleanup', $context->portfolioId);
        }
        if ($exception instanceof ProfileVersionConflictException) {
            $fieldErrors['profile'] = 'This profile changed after you opened the form. Your entries are kept here; reload and review before applying them again.';
            return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 409);
        }
        if ($exception instanceof AuthorizationDeniedException) {
            return ownerProfileActionResult(['You are not authorized to change this profile.'], $profile, null, [], 403);
        }
        reportApplicationError($exception, 'owner_profile.php', 'owner_profile_write_failure');
        $fieldErrors['profile'] = $exception instanceof ProfileWriteUncertainException
            ? 'The save result is uncertain and may already be saved. Reload and review before another change. A photo may need reselection.'
            : 'The requested change could not be saved. Your entries are kept here. Reload and review before trying again.';
        return ownerProfileActionResult(validationErrorList($fieldErrors), $profile, null, $fieldErrors, 503);
    }
}
