<?php

declare(strict_types=1);

final class OwnerFrontendExperienceStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $layout = self::read('includes/owner_layout.php');
        $feedback = self::read('includes/owner_form_feedback.php');
        $profile = self::read('owner_profile.php');
        $projects = self::read('owner_projects.php');
        $experiences = self::read('owner_experiences.php');
        $publication = self::read('owner_publication.php');
        $publicationPresentation = self::read('includes/owner_publication_presentation.php');
        $onboarding = self::read('owner_onboarding.php');
        $messages = self::read('owner_messages.php');
        $dashboard = self::read('owner.php');
        $javascript = self::read('admin.js');
        $scopedData = self::read('includes/portfolio_scoped_data.php');

        phase2Assert(str_contains($layout, "require_once __DIR__ . '/owner_form_feedback.php';"), 'Owner pages do not consistently load the shared feedback helper.');
        foreach (['ownerFieldAccessibilityAttributes', 'ownerRenderFieldError', 'ownerRenderFormFeedback', 'ownerRenderRequiredIndicator'] as $helper) {
            phase2Assert(str_contains($feedback, "function {$helper}"), "Owner feedback helper {$helper} is missing.");
        }
        phase2Assert(str_contains($feedback, 'aria-invalid="true"') && str_contains($feedback, 'aria-describedby') && str_contains($feedback, 'data-error-summary'), 'Owner field feedback is not connected to invalid controls and the page summary.');
        phase2Assert(str_contains($feedback, 'ownerEscapeHtml($successMessage)') && str_contains($feedback, 'ownerEscapeHtml($error)'), 'Owner feedback may render reflected messages without escaping.');

        foreach ([$profile, $projects, $experiences, $publicationPresentation, $onboarding] as $formSurface) {
            phase2Assert(str_contains($formSurface, 'data-owner-form'), 'An active Owner mutation form is missing progressive duplicate-submit protection.');
            phase2Assert(str_contains($formSurface, 'data-pending-label'), 'An active Owner mutation form is missing a pending label.');
        }
        foreach ([$profile, $projects, $experiences, $publicationPresentation] as $validationSurface) {
            phase2Assert(str_contains($validationSurface, 'ownerFieldAccessibilityAttributes') && str_contains($validationSurface, 'ownerRenderFieldError'), 'An Owner validation surface lacks connected field feedback.');
        }
        phase2Assert(str_contains($projects, 'ownerRenderRequiredIndicator') && str_contains($experiences, 'ownerRenderRequiredIndicator') && str_contains($publicationPresentation, 'ownerRenderRequiredIndicator'), 'Required fields are not communicated in all active Owner form surfaces.');
        phase2Assert(str_contains($profile, '$submittedSkillValue') && str_contains($publicationPresentation, '$submittedSlug'), 'Validation failures do not preserve safe Owner-entered values for skills and public slugs.');
        phase2Assert(str_contains($profile, '$submittedSkillId = ownerActionId') && !str_contains($profile, 'ownerEscapeHtml($_POST') && !str_contains($projects, 'ownerEscapeHtml($_POST') && !str_contains($experiences, 'ownerEscapeHtml($_POST'), 'Owner view code must not reflect submitted tenant/resource identifiers.');

        foreach ([$profile, $projects, $experiences, $publicationPresentation] as $destructiveSurface) {
            phase2Assert(str_contains($destructiveSurface, 'data-confirm') && str_contains($destructiveSurface, 'csrf_token'), 'A destructive or public-state action lost its confirmation or CSRF field.');
        }
        phase2Assert(str_contains($projects, 'name="remove_image"') && str_contains($javascript, "document.querySelectorAll('input[name=\"remove_image\"]')"), 'Project-image removal is missing the existing keyboard-accessible confirmation.');
        phase2Assert(str_contains($projects, 'No projects yet') && str_contains($projects, 'Add your first project'), 'Projects empty state does not provide the supported next action.');
        phase2Assert(str_contains($profile, 'No skills added yet') && str_contains($experiences, 'No experience records yet') && str_contains($messages, 'Your inbox is clear'), 'Owner collection empty states are incomplete.');
        phase2Assert(str_contains($dashboard, 'owner_experiences.php?add=1') && str_contains($dashboard, 'owner_publication.php'), 'Dashboard quick actions omit active Owner workflows.');
        $profileUpdateStart = strpos($scopedData, 'function updateAuthorizedPersonalInfo');
        $profileUpdateEnd = strpos($scopedData, '/** @return list<array<string, mixed>>', $profileUpdateStart ?: 0);
        $profileUpdate = $profileUpdateStart === false || $profileUpdateEnd === false ? '' : substr($scopedData, $profileUpdateStart, $profileUpdateEnd - $profileUpdateStart);
        phase2Assert(str_contains($profileUpdate, 'MySQL reports zero affected rows') && str_contains($profileUpdate, 'WHERE id = :resource_id') && str_contains($profileUpdate, 'portfolio_id = :authorized_portfolio_id') && str_contains($profileUpdate, 'return (int) $exists->fetchColumn() === 1;'), 'An unchanged Owner profile save must remain a scoped success while missing records stay unavailable.');

        phase2Assert(str_contains($javascript, 'form.dataset.submitting === \'1\'') && str_contains($javascript, 'data-pending-label') && str_contains($javascript, "window.addEventListener('pageshow'"), 'Owner submit controls lack duplicate protection or recovery after browser restoration.');
        phase2Assert(str_contains($javascript, "document.querySelector('[data-error-summary]')") && str_contains($javascript, 'errorSummary.focus()'), 'Owner validation responses do not move focus to a predictable error target.');
        phase2Assert(!str_contains($javascript, 'setCustomValidity') && !str_contains($javascript, 'showToast'), 'Owner JavaScript must not duplicate backend validation or make critical feedback disappear.');
        phase2Assert(str_contains($javascript, 'form.requestSubmit()') && str_contains($javascript, 'confirm-cancel') && str_contains($javascript, "event.key === 'Escape'"), 'The shared destructive/public-state confirmation is not keyboard accessible.');

        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_layout.php';
        $attributes = ownerFieldAccessibilityAttributes(['full_name' => 'Name is required.'], 'full_name', 'name-help');
        phase2Assert(str_contains($attributes, 'aria-invalid="true"') && str_contains($attributes, 'name-help') && str_contains($attributes, 'owner-field-error-full_name'), 'Rendered field attributes do not identify help and error text.');
        phase2AssertSame('', ownerFieldAccessibilityAttributes([], 'full_name'), 'A valid Owner field should not receive invalid ARIA state.');

        ob_start();
        ownerRenderFormFeedback('', ['<script>unsafe</script>'], ['full_name' => '<script>unsafe</script>']);
        $rendered = (string) ob_get_clean();
        phase2Assert(str_contains($rendered, '&lt;script&gt;unsafe&lt;/script&gt;') && !str_contains($rendered, '<script>unsafe</script>'), 'Owner feedback failed to escape reflected validation messages.');
        phase2Assert(str_contains($rendered, 'role="alert"') && str_contains($rendered, 'tabindex="-1"'), 'Owner error summary is not an accessible focus target.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
