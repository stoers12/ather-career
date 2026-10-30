<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_experiences_copy.php';

/** @param list<array<string, mixed>> $experiences @param array<string, mixed> $editingExperience
 * @param list<string> $formErrors @param array<string, string> $fieldErrors */
function renderOwnerExperiencesPage(array $experiences, array $editingExperience, bool $showExperienceForm, string $formMode, string $experienceMaximumMonth, string $pageMessage, string $databaseError, array $formErrors, array $fieldErrors): void
{
    $locale = ownerLocale();
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerExperiencesCopy($key, $locale));
    ?>
<div class="owner-experiences">
<header class="admin-page-header"><div class="admin-page-header-copy"><h1><?php echo $copy($showExperienceForm ? ($formMode === 'edit' ? 'edit_title' : 'add') : 'title'); ?></h1><p class="admin-page-description"><?php echo $copy($showExperienceForm ? 'editor_subtitle' : 'subtitle'); ?></p></div><?php if (!$showExperienceForm): ?><a class="button-primary" href="owner_experiences.php?add=1"><?php echo evidenceHubOwnerIcon('plus'); ?><?php echo $copy('add'); ?></a><?php endif; ?></header>
<?php ownerRenderFormFeedback($pageMessage, $databaseError === '' ? $formErrors : [$databaseError], $fieldErrors); ?>
<?php if ($showExperienceForm): ?>
<div class="owner-experience-editor">
<form class="owner-experience-form" method="POST" action="owner_experiences.php" data-owner-form>
    <input type="hidden" name="action" value="<?php echo $formMode === 'edit' ? 'update' : 'add'; ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
    <?php if ($formMode === 'edit'): ?><input type="hidden" name="id" value="<?php echo (int) $editingExperience['id']; ?>"><?php endif; ?>
    <div class="form-grid">
    <?php foreach (['role_title' => EXPERIENCE_ROLE_TITLE_MAX_LENGTH, 'organization' => EXPERIENCE_ORGANIZATION_MAX_LENGTH, 'location' => EXPERIENCE_LOCATION_MAX_LENGTH] as $field => $maximum): ?>
    <div class="form-field"><label for="<?php echo $field; ?>"><?php echo $copy($field); ?> <em><?php echo $copy($field === 'location' ? 'optional' : 'required'); ?></em><?php if ($field !== 'location'): ?><span class="visually-hidden"><?php ownerRenderRequiredIndicator(); ?></span><?php endif; ?></label><input type="text" id="<?php echo $field; ?>" name="<?php echo $field; ?>" dir="auto" maxlength="<?php echo $maximum; ?>" value="<?php echo ownerEscapeHtml((string) $editingExperience[$field]); ?>"<?php echo $field === 'location' ? '' : ' required'; ?><?php echo ownerFieldAccessibilityAttributes($fieldErrors, $field); ?>><?php ownerRenderFieldError($fieldErrors, $field); ?></div>
    <?php endforeach; ?>
    <div class="form-field"><label for="experience_type"><?php echo $copy('experience_type'); ?> <em><?php echo $copy('required'); ?></em><span class="visually-hidden"><?php ownerRenderRequiredIndicator(); ?></span></label><select id="experience_type" name="experience_type" required<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'experience_type'); ?>><option value=""><?php echo $copy('select_type'); ?></option><?php foreach (EXPERIENCE_TYPE_VALUES as $type): ?><option value="<?php echo ownerEscapeHtml($type); ?>"<?php echo (string) $editingExperience['experience_type'] === $type ? ' selected' : ''; ?>><?php echo $copy($type); ?></option><?php endforeach; ?></select><?php ownerRenderFieldError($fieldErrors, 'experience_type'); ?></div>
    <div class="form-field"><label for="start_month"><?php echo $copy('start_month'); ?> <em><?php echo $copy('required'); ?></em><span class="visually-hidden"><?php ownerRenderRequiredIndicator(); ?></span></label><input type="month" id="start_month" name="start_month" dir="ltr" value="<?php echo ownerEscapeHtml((string) $editingExperience['start_month']); ?>" max="<?php echo ownerEscapeHtml($experienceMaximumMonth); ?>" required<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'start_month'); ?>><?php ownerRenderFieldError($fieldErrors, 'start_month'); ?></div>
    <div class="form-field"><label for="end_month"><?php echo $copy('end_month'); ?> <em><?php echo $copy('end_required'); ?></em></label><input type="month" id="end_month" name="end_month" dir="ltr" value="<?php echo ownerEscapeHtml((string) $editingExperience['end_month']); ?>" max="<?php echo ownerEscapeHtml($experienceMaximumMonth); ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'end_month', 'end-month-help'); ?>><small id="end-month-help" class="form-hint"><?php echo $copy('end_hint'); ?></small><?php ownerRenderFieldError($fieldErrors, 'end_month'); ?></div>
    <div class="form-field-full"><label class="checkbox-field" for="is_current"><input id="is_current" type="checkbox" name="is_current" value="1"<?php echo experienceIsCurrent($editingExperience['is_current'] ?? false) ? ' checked' : ''; ?><?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'is_current', 'end-month-help'); ?>><span><?php echo $copy('is_current'); ?></span></label><?php ownerRenderFieldError($fieldErrors, 'is_current'); ?></div>
    <div class="form-field form-field-full"><label for="description"><?php echo $copy('description'); ?> <em><?php echo $copy('optional'); ?></em></label><textarea id="description" name="description" rows="4" dir="auto" maxlength="<?php echo EXPERIENCE_DESCRIPTION_MAX_LENGTH; ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'description', 'experience-description-help'); ?>><?php echo ownerEscapeHtml((string) $editingExperience['description']); ?></textarea><small class="form-hint" id="experience-description-help"><?php echo $copy('description_hint'); ?></small><?php ownerRenderFieldError($fieldErrors, 'description'); ?></div>
    </div>
    <footer class="form-actions"><?php if ($pageMessage !== ''): ?><span class="owner-experience-saved" role="status"><?php echo $copy('saved'); ?></span><?php endif; ?><a class="owner-experience-cancel" href="owner_experiences.php"><?php echo $copy('cancel'); ?></a><button class="button-primary" type="submit" data-pending-label="<?php echo $copy('saving'); ?>"><?php echo $copy('save'); ?></button></footer>
</form>
<?php if ($formMode === 'edit'): ?><div class="owner-experience-editor-delete"><?php ownerExperiencesDeleteForm($editingExperience, $locale); ?></div><?php endif; ?>
</div>
<?php else: ?>
<?php if ($experiences === []): ?><div class="empty-state"><strong><?php echo $copy('empty_title'); ?></strong><p><?php echo $copy('empty_hint'); ?></p><a class="button-secondary" href="owner_experiences.php?add=1"><?php echo $copy('empty_action'); ?></a></div><?php endif; ?>
<div class="owner-experience-list">
<?php foreach ($experiences as $experience): ?>
<?php $type = (string) $experience['experience_type']; $icons = ['employment' => 'owner-briefcase-business', 'training' => 'book-open-check', 'internship' => 'briefcase', 'volunteer' => 'user-round', 'leadership' => 'badge-check']; ?>
<article class="owner-experience-card" data-experience-type="<?php echo ownerEscapeHtml($type); ?>">
    <span class="owner-experience-icon"><?php echo evidenceHubOwnerIcon($icons[$type] ?? 'briefcase'); ?></span>
    <div class="owner-experience-content"><h2 dir="auto"><?php echo ownerEscapeHtml((string) $experience['role_title']); ?></h2><p dir="auto"><bdi><?php echo ownerEscapeHtml((string) $experience['organization']); ?></bdi><?php if ((string) ($experience['location'] ?? '') !== ''): ?> · <bdi><?php echo ownerEscapeHtml((string) $experience['location']); ?></bdi><?php endif; ?></p>
    <div class="owner-experience-meta"><span class="owner-experience-type"><?php echo $copy($type); ?></span><span class="owner-experience-dates"><bdi dir="ltr"><?php echo ownerEscapeHtml((string) $experience['start_month']); ?></bdi> — <?php if (experienceIsCurrent($experience['is_current'] ?? false)): ?><span><?php echo $copy('present'); ?></span><?php else: ?><bdi dir="ltr"><?php echo ownerEscapeHtml((string) $experience['end_month']); ?></bdi><?php endif; ?></span></div>
    <?php if ((string) ($experience['description'] ?? '') !== ''): ?><p class="owner-experience-description" dir="auto"><?php echo ownerEscapeHtml((string) $experience['description']); ?></p><?php endif; ?></div>
    <div class="owner-experience-actions"><a class="button-secondary" href="owner_experiences.php?edit=<?php echo (int) $experience['id']; ?>"><?php echo $copy('edit'); ?></a><?php ownerExperiencesDeleteForm($experience, $locale); ?></div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
    <?php
}

/** @param array<string, mixed> $experience */
function ownerExperiencesDeleteForm(array $experience, string $locale): void
{
    $copy = static fn (string $key): string => ownerEscapeHtml(ownerExperiencesCopy($key, $locale));
    $confirmation = sprintf(ownerExperiencesCopy('delete_confirm', $locale), (string) $experience['role_title'], (string) $experience['organization']);
    ?>
<form class="owner-experience-delete" method="POST" action="owner_experiences.php" data-owner-form data-confirm="<?php echo ownerEscapeHtml($confirmation); ?>" data-confirm-title="<?php echo $copy('delete_title'); ?>" data-confirm-action="<?php echo $copy('delete_action'); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="id" value="<?php echo (int) $experience['id']; ?>"><button class="button-danger" type="submit" data-pending-label="<?php echo $copy('deleting'); ?>"><?php echo $copy('delete'); ?></button></form>
    <?php
}
