<?php
declare(strict_types=1);
require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/onboarding_copy.php';

// Pure presentation shared by the guarded preview and the authenticated editor.
// No authentication, persistence or test entry point is included here.
function renderOnboardingPresentation(string $locale, int $step, bool $editor = false, string $sessionToken = ''): void
{
$copy = static fn (string $key): string => $editor ? onboardingEditorCopy($key, $locale) : onboardingCopy($key, $locale);
$oppositeLocale = $locale === 'ar' ? 'en' : 'ar';
$stepName = $copy('step_' . $step);
$stepTitle = sprintf($copy('step_of'), $step, $stepName);
$position = sprintf($copy('position'), $step);
$countries = ['JO' => 'country_jo', 'PS' => 'country_ps', 'SA' => 'country_sa', 'AE' => 'country_ae', 'EG' => 'country_eg'];
$statuses = ['student' => 'status_student', 'graduate' => 'status_graduate', 'early' => 'status_early', 'experienced' => 'status_experienced', 'independent' => 'status_independent'];
if ($editor) {
    require_once __DIR__ . '/final_profile_fields.php';
    $catalog = finalProfileCatalog();
    $countries = $catalog['countries'];
    $statuses = $catalog['statuses'];
}
$choiceLabel = static fn (mixed $label): string => $editor
    ? htmlspecialchars($label[$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $copy($label);

header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
header('Cache-Control: ' . ($editor ? 'private, no-store' : 'no-store'));
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $copy('title'); ?></title>
    <script src="<?php echo versionedAssetUrl('assets/js/landing.js'); ?>" defer></script>
    <script src="<?php echo versionedAssetUrl($editor ? 'assets/js/onboarding_editor.js' : 'assets/js/onboarding.js'); ?>" defer></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/onboarding.css'); ?>">
</head>
<body>
<a class="ao-skip" href="#onboarding-card"><?php echo $copy('skip'); ?></a>
<div id="ather-onboarding-preview" class="is-step-<?php echo $step; ?>" data-initial-step="<?php echo $step; ?>"<?php if ($editor): ?> data-draft-editor data-editor-context="<?php echo htmlspecialchars($sessionToken, ENT_QUOTES, 'UTF-8'); ?>" data-editor-copy="<?php echo htmlspecialchars(json_encode(onboardingEditorMessages($locale), JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
     data-preview-name="<?php echo $copy('preview_name'); ?>"
     data-preview-country="<?php echo $copy('preview_country'); ?>"
     data-preview-status="<?php echo $copy('preview_status'); ?>"
     data-preview-field="<?php echo $copy('preview_field'); ?>"
     data-preview-headline="<?php echo $copy('preview_headline_later'); ?>"
     data-not-entered="<?php echo $copy('not_entered'); ?>"
     data-review-email-on="<?php echo $copy('review_email_on'); ?>"
     data-review-email-off="<?php echo $copy('review_email_off'); ?>"
     data-review-phone-on="<?php echo $copy('review_phone_on'); ?>"
     data-review-phone-off="<?php echo $copy('review_phone_off'); ?>">
    <div class="ao-stage">
        <main class="ao-product-frame">
            <header class="ao-topbar">
                <div class="ao-brand">
                    <span class="ao-brand-mark" aria-hidden="true">أ</span>
                    <span class="ao-brand-name"><?php echo $copy('brand'); ?><small><?php echo $copy('brand_tagline'); ?></small></span>
                </div>
                <span class="ao-save-state"><span class="ao-save-dot" aria-hidden="true"></span><span<?php echo $editor ? ' data-save-summary' : ''; ?>><?php echo $copy('preview_state'); ?></span></span>
                <button class="ao-header-action" type="button" disabled<?php echo $editor ? ' data-save-exit' : ''; ?> title="<?php echo $copy('save_unavailable'); ?>"><?php echo $copy('save_exit'); ?></button>
            </header>

            <section class="ao-progress-shell" aria-label="<?php echo $stepTitle; ?>">
                <div class="ao-progress-top">
                    <p class="ao-progress-title" data-progress-title><?php echo $stepTitle; ?></p>
                    <span class="ao-progress-position" data-progress-position><?php echo $position; ?></span>
                </div>
                <div class="ao-progress-track" aria-hidden="true"><div class="ao-progress-fill" data-progress-fill></div></div>
                <nav class="ao-progress-steps" aria-label="<?php echo $stepTitle; ?>">
                    <?php foreach ([1, 2, 3, 4, 5] as $availableStep): ?>
                    <a class="ao-step-jump<?php echo $availableStep === $step ? ' is-current' : ''; ?>" href="?lang=<?php echo $locale; ?>&amp;step=<?php echo $availableStep; ?>" data-step-jump="<?php echo $availableStep; ?>"
                       data-title="<?php echo sprintf($copy('step_of'), $availableStep, $copy('step_' . $availableStep)); ?>"
                       data-position="<?php echo sprintf($copy('position'), $availableStep); ?>"<?php echo $availableStep === $step ? ' aria-current="step"' : ''; ?>><?php echo $copy('step_' . $availableStep); ?></a>
                    <?php endforeach; ?>
                </nav>
            </section>

            <div class="ao-workspace">
                <section class="ao-form-card" id="onboarding-card" tabindex="-1" aria-label="<?php echo $stepName; ?>">
                    <div class="ao-controls">
                        <a class="ao-language" data-language-switch href="?lang=<?php echo $oppositeLocale; ?>&amp;step=<?php echo $step; ?>" lang="<?php echo $oppositeLocale; ?>" hreflang="<?php echo $oppositeLocale; ?>" aria-label="<?php echo $copy('interface_language'); ?>: <?php echo $copy('switch_language'); ?>"><?php echo $copy('switch_language'); ?></a>
                        <button class="ao-theme hf-theme" type="button" hidden aria-label="<?php echo $copy('theme'); ?>" aria-pressed="false">◐</button>
                    </div>
                    <p class="ao-field-note" data-language-status data-error-message="<?php echo $copy('language_unavailable'); ?>" role="status" hidden></p>
                    <?php if ($editor): ?>
                    <div class="ao-draft-feedback">
                        <p data-save-status role="status" aria-live="polite" aria-atomic="true" tabindex="-1"><?php echo $copy('loading'); ?></p>
                        <div class="ao-draft-recovery"><a class="ao-secondary" data-completion-dashboard hidden href="/owner.php"><?php echo $copy('completion_dashboard'); ?></a><button class="ao-secondary" type="button" data-retry hidden><?php echo $copy('retry'); ?></button><button class="ao-secondary" type="button" data-recover hidden><?php echo $copy('recover'); ?></button><a class="ao-secondary" data-fresh-context hidden href="/owner_onboarding_editor.php"><?php echo $copy('fresh'); ?></a></div>
                    </div>
                    <noscript><p><?php echo $copy('no_js'); ?></p></noscript>
                    <?php endif; ?>
                    <button class="ao-secondary ao-preview-toggle" type="button" data-preview-toggle aria-expanded="false"
                            data-open-label="<?php echo $copy('preview_toggle_open'); ?>"
                            data-close-label="<?php echo $copy('preview_toggle_close'); ?>"><?php echo $copy('preview_toggle_open'); ?></button>

                    <section class="ao-panel" data-step-panel="1"<?php echo $step === 1 ? '' : ' hidden'; ?> aria-labelledby="step-1-heading">
                        <p class="ao-eyebrow"><?php echo $copy('step_1_eyebrow'); ?></p>
                        <h1 class="ao-heading" id="step-1-heading" tabindex="-1"><?php echo $copy('step_1_heading'); ?></h1>
                        <p class="ao-lead"><?php echo $copy('step_1_lead'); ?></p>
                        <div class="ao-fields">
                            <div class="ao-status-row">
                                <div class="ao-status-main">
                                    <span class="ao-status-icon" aria-hidden="true"><?php echo $editor ? '—' : '✓'; ?></span>
                                    <span class="ao-status-copy"><strong><?php echo $copy('email_example'); ?></strong><bdi dir="ltr"><?php echo $copy('example_email'); ?></bdi></span>
                                </div>
                                <span class="ao-badge"><?php echo $copy('example_badge'); ?></span>
                            </div>
                            <p class="ao-field-note"><?php echo $copy('email_notice'); ?></p>
                            <?php if ($editor): ?>
                            <div data-attestation-documents class="ao-attestation-documents"></div>
                            <p data-attestation-status role="status" aria-live="polite" aria-atomic="true" tabindex="-1"><?php echo $copy('att_loading'); ?></p>
                            <?php endif; ?>
                            <div class="ao-check-list">
                                <label class="ao-check"><input<?php echo $editor ? ' data-attestation-action="age_18_or_older" disabled' : ''; ?> type="checkbox" autocomplete="off"><span><strong><?php echo $copy('age'); ?></strong><small><?php echo $copy('age_hint'); ?></small></span></label>
                                <label class="ao-check"><input<?php echo $editor ? ' data-attestation-action="terms_accepted" disabled' : ''; ?> type="checkbox" autocomplete="off"><span><strong><?php echo $copy('terms'); ?></strong><small><?php echo $copy('terms_hint'); ?></small></span></label>
                                <label class="ao-check"><input<?php echo $editor ? ' data-attestation-action="privacy_notice_acknowledged" disabled' : ''; ?> type="checkbox" autocomplete="off"><span><strong><?php echo $copy('privacy'); ?></strong><small><?php echo $copy('privacy_hint'); ?></small></span></label>
                            </div>
                            <div class="ao-note"><?php echo $copy('consent_notice'); ?></div>
                            <?php if ($editor): ?>
                            <div class="ao-draft-recovery"><button class="ao-secondary" type="button" data-attestation-load disabled><?php echo $copy('att_load'); ?></button><button class="ao-primary" type="button" data-attestation-record disabled><?php echo $copy('att_record'); ?></button></div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="ao-panel" data-step-panel="2"<?php echo $step === 2 ? '' : ' hidden'; ?> aria-labelledby="step-2-heading">
                        <p class="ao-eyebrow"><?php echo $copy('step_2_eyebrow'); ?></p>
                        <h1 class="ao-heading" id="step-2-heading" tabindex="-1"><?php echo $copy('step_2_heading'); ?></h1>
                        <p class="ao-lead"><?php echo $copy('step_2_lead'); ?></p>
                        <div class="ao-fields">
                            <label class="ao-field"><span class="ao-label"><?php echo $copy('display_name'); ?></span><input class="ao-input" type="text" data-profile-name<?php echo $editor ? ' data-draft-field="display_name" disabled' : ''; ?> placeholder="<?php echo $copy('name_example'); ?>" autocomplete="off" dir="auto"><small class="ao-label-note"><?php echo $copy('name_hint'); ?></small></label>
                            <div class="ao-field-row">
                                <label class="ao-field"><span class="ao-label"><?php echo $copy('country'); ?></span><select class="ao-select" data-country<?php echo $editor ? ' data-draft-field="country_code" disabled' : ''; ?>><option value=""><?php echo $copy('country_placeholder'); ?></option><?php foreach ($countries as $countryCode => $copyKey): ?><option value="<?php echo $countryCode; ?>"><?php echo $choiceLabel($copyKey); ?></option><?php endforeach; ?></select><small class="ao-label-note"><?php echo $copy('country_limit'); ?></small></label>
                                <fieldset class="ao-field ao-language-field"><legend class="ao-label"><?php echo $copy('preferred_language'); ?></legend><div class="ao-language-options"><label class="ao-radio-card"><input type="radio"<?php echo $editor ? ' data-draft-field="preferred_language" disabled' : ''; ?> name="preview_preferred_language" value="ar" autocomplete="off"><?php echo $editor ? $choiceLabel($catalog['languages']['ar']) : $copy('arabic'); ?></label><label class="ao-radio-card"><input type="radio"<?php echo $editor ? ' data-draft-field="preferred_language" disabled' : ''; ?> name="preview_preferred_language" value="en" autocomplete="off"><?php echo $editor ? $choiceLabel($catalog['languages']['en']) : $copy('english'); ?></label></div><small class="ao-label-note"><?php echo $copy('preferred_language_hint'); ?></small><?php if ($editor): ?><button class="ao-edit-link" type="button" data-clear-language disabled><?php echo $copy('clear_language'); ?></button><?php endif; ?></fieldset>
                            </div>
                            <div class="ao-note"><?php echo $copy('phone_note'); ?></div>
                            <p class="ao-field-note"><?php echo $copy('identity_note'); ?></p>
                        </div>
                    </section>

                    <section class="ao-panel" data-step-panel="3"<?php echo $step === 3 ? '' : ' hidden'; ?> aria-labelledby="step-3-heading">
                        <p class="ao-eyebrow"><?php echo $copy('step_3_eyebrow'); ?></p>
                        <h1 class="ao-heading" id="step-3-heading" tabindex="-1"><?php echo $copy('step_3_heading'); ?></h1>
                        <p class="ao-lead"><?php echo $copy('step_3_lead'); ?></p>
                        <div class="ao-fields">
                            <p class="ao-field-note"><?php echo $copy('required_note'); ?></p>
                            <div class="ao-field-row">
                                <label class="ao-field"><span class="ao-label"><?php echo $copy('career_status'); ?></span><small class="ao-required"><?php echo $copy('required_mark'); ?></small><select class="ao-select" data-career-status<?php echo $editor ? ' data-draft-field="professional_status" disabled' : ''; ?>><option value=""><?php echo $copy('status_placeholder'); ?></option><?php foreach ($statuses as $statusValue => $copyKey): ?><option value="<?php echo $statusValue; ?>"><?php echo $choiceLabel($copyKey); ?></option><?php endforeach; ?></select><small class="ao-label-note"><?php echo $copy('status_limit'); ?></small></label>
                                <label class="ao-field"><span class="ao-label"><?php echo $copy('specialization'); ?></span><small class="ao-required"><?php echo $copy('required_mark'); ?></small><input class="ao-input" type="text" data-field<?php echo $editor ? ' data-draft-field="specialization" disabled' : ''; ?> autocomplete="off" dir="auto"><?php if ($editor): ?><small class="ao-label-note"><?php echo $copy('specialization_hint'); ?></small><?php endif; ?></label>
                            </div>
                            <label class="ao-field"><span class="ao-label"><?php echo $copy('headline'); ?> <small class="ao-required"><?php echo $copy('required_mark'); ?></small></span><input class="ao-input" type="text" data-headline<?php echo $editor ? ' data-draft-field="headline" disabled' : ''; ?> autocomplete="off" dir="auto"><small class="ao-label-note"><?php echo $copy('headline_hint'); ?></small></label>
                            <div class="ao-note"><?php echo $copy('projects_later'); ?></div>
                        </div>
                    </section>

                    <section class="ao-panel" data-step-panel="4"<?php echo $step === 4 ? '' : ' hidden'; ?> aria-labelledby="step-4-heading">
                        <p class="ao-eyebrow"><?php echo $copy('step_4_eyebrow'); ?></p>
                        <h1 class="ao-heading" id="step-4-heading" tabindex="-1"><?php echo $copy('step_4_heading'); ?></h1>
                        <p class="ao-lead"><?php echo $copy('step_4_lead'); ?></p>
                        <div class="ao-fields">
                            <label class="ao-field"><span class="ao-label"><?php echo $copy('profile_link'); ?> <small class="ao-required"><?php echo $copy('required_mark'); ?></small></span><span class="ao-input-prefix"><span dir="ltr">ather.app/</span><input class="ao-input" type="text" data-slug<?php echo $editor ? ' data-draft-field="slug_candidate" disabled' : ''; ?> autocomplete="off" dir="ltr"></span><small class="ao-label-note"><?php echo $copy('slug_hint'); ?></small></label>
                            <div class="ao-visibility-options">
                                <label class="ao-toggle-row"><span class="ao-toggle-copy"><strong><?php echo $copy('show_email'); ?></strong><small><?php echo $copy('email_visibility_hint'); ?></small></span><span class="ao-switch"><input<?php echo $editor ? ' disabled' : ''; ?> type="checkbox" data-show-email autocomplete="off"><span aria-hidden="true"></span></span></label>
                                <label class="ao-toggle-row"><span class="ao-toggle-copy"><strong><?php echo $copy('show_phone'); ?></strong><small><?php echo $copy('phone_visibility_hint'); ?></small></span><span class="ao-switch"><input<?php echo $editor ? ' disabled' : ''; ?> type="checkbox" data-show-phone autocomplete="off"><span aria-hidden="true"></span></span></label>
                            </div>
                            <div class="ao-note"><?php echo $copy('privacy_note'); ?></div>
                        </div>
                    </section>

                    <section class="ao-panel" data-step-panel="5"<?php echo $step === 5 ? '' : ' hidden'; ?> aria-labelledby="step-5-heading">
                        <p class="ao-eyebrow"><?php echo $copy('step_5_eyebrow'); ?></p>
                        <h1 class="ao-heading" id="step-5-heading" tabindex="-1"><?php echo $copy('step_5_heading'); ?></h1>
                        <p class="ao-lead"><?php echo $copy('step_5_lead'); ?></p>
                        <div class="ao-review-list"<?php echo $editor ? ' hidden aria-hidden="true"' : ''; ?>>
                            <div class="ao-review-item"><div><small><?php echo $copy('review_identity'); ?></small><strong data-review-identity><?php echo $copy('not_entered'); ?></strong></div><button class="ao-edit-link" type="button" data-edit-step="2"><?php echo $copy('edit'); ?></button></div>
                            <div class="ao-review-item"><div><small><?php echo $copy('review_profession'); ?></small><strong data-review-profession><?php echo $copy('not_entered'); ?></strong></div><button class="ao-edit-link" type="button" data-edit-step="3"><?php echo $copy('edit'); ?></button></div>
                            <div class="ao-review-item"><div><small><?php echo $copy('review_link'); ?></small><strong data-review-slug dir="ltr"><?php echo $copy('not_entered'); ?></strong></div><button class="ao-edit-link" type="button" data-edit-step="4"><?php echo $copy('edit'); ?></button></div>
                            <div class="ao-review-item"><div><small><?php echo $copy('review_privacy'); ?></small><strong data-review-privacy><?php echo $copy('review_private'); ?></strong></div><button class="ao-edit-link" type="button" data-edit-step="4"><?php echo $copy('edit'); ?></button></div>
                        </div>
                        <?php if ($editor): ?>
                        <div class="ao-completion" aria-label="<?php echo $copy('completion_initial'); ?>">
                            <p data-completion-status role="status" aria-live="polite" tabindex="-1"><?php echo $copy('completion_loading'); ?></p>
                            <p data-completion-retained hidden><?php echo $copy('completion_retained'); ?></p>
                            <div class="ao-completion-actions"><button type="button" class="ao-secondary" data-completion-review disabled><?php echo $copy('completion_review'); ?></button><button type="button" class="ao-secondary" data-completion-read disabled><?php echo $copy('completion_read'); ?></button></div>
                            <div class="ao-completion-edits">
                                <?php foreach ([2=>'review_identity',3=>'review_profession',4=>'review_link'] as $editStep=>$editKey): ?><button type="button" class="ao-edit-link" data-edit-step="<?php echo $editStep; ?>"><?php echo $copy('edit').' — '.$copy($editKey); ?></button><?php endforeach; ?>
                            </div>
                            <div data-completion-review-body hidden></div>
                            <fieldset class="ao-completion-confirmations"><legend><?php echo $copy('completion_separate'); ?></legend><div class="ao-check-list">
                                <label class="ao-check"><input type="checkbox" data-completion-confirm="confirm_private_creation" autocomplete="off" disabled><span><?php echo $copy('completion_private'); ?></span></label>
                                <label class="ao-check"><input type="checkbox" data-completion-confirm="acknowledge_private_draft" autocomplete="off" disabled><span><?php echo $copy('completion_acknowledge'); ?></span></label></div>
                            </fieldset>
                        </div>
                        <?php else: ?><div class="ao-note ao-create-note"><?php echo $copy('create_unavailable'); ?></div><?php endif; ?>
                    </section>

                    <div class="ao-actions">
                        <button class="ao-save-exit" type="button" disabled<?php echo $editor ? ' data-save-exit' : ''; ?> title="<?php echo $copy('save_unavailable'); ?>"><?php echo $copy('save_exit_safe'); ?></button>
                        <div class="ao-action-group">
                            <a class="ao-secondary" data-back href="?lang=<?php echo $locale; ?>&amp;step=<?php echo max(1, $step - 1); ?>"<?php echo $step === 1 ? ' hidden' : ''; ?>><?php echo $copy('back'); ?></a>
                            <?php if ($editor): ?><button class="ao-primary" type="button" data-next disabled<?php echo $step === 5 ? ' hidden' : ''; ?>><?php echo $copy('continue'); ?></button><?php else: ?><a class="ao-primary" data-next href="?lang=<?php echo $locale; ?>&amp;step=<?php echo min(5, $step + 1); ?>"<?php echo $step === 5 ? ' hidden' : ''; ?>><?php echo $copy('continue'); ?></a><?php endif; ?>
                            <button class="ao-primary" type="button" data-create disabled<?php echo $step === 5 ? '' : ' hidden'; ?> title="<?php echo $copy('create_unavailable'); ?>"><?php echo $copy('create'); ?></button>
                        </div>
                    </div>
                    <p class="ao-preview-footer"><?php echo $copy('preview_footer'); ?></p>
                </section>

                <aside class="ao-preview-column" data-preview-column aria-label="<?php echo $copy('preview_label'); ?>">
                    <div class="ao-preview-label"><span><?php echo $copy('preview_label'); ?></span><span class="ao-private-badge"><?php echo $copy('private_badge'); ?></span></div>
                    <article class="ao-preview-card">
                        <div class="ao-preview-cover" aria-hidden="true"></div>
                        <div class="ao-preview-body">
                            <div class="ao-avatar" data-preview-initials aria-hidden="true">أ</div>
                            <h2 class="ao-preview-name" data-preview-name dir="auto"><?php echo $copy('preview_name'); ?></h2>
                            <p class="ao-preview-headline" data-preview-headline><?php echo $copy('preview_headline_later'); ?></p>
                            <div class="ao-preview-meta"><span data-preview-status><?php echo $copy('preview_status'); ?></span><span data-preview-field><?php echo $copy('preview_field'); ?></span><span data-preview-country><?php echo $copy('preview_country'); ?></span></div>
                            <section class="ao-preview-section"><h3><?php echo $copy('preview_section'); ?></h3><p><?php echo $copy('preview_section_copy'); ?></p><div class="ao-empty-project"><?php echo $copy('preview_project'); ?></div></section>
                        </div>
                    </article>
                </aside>
            </div>
        </main>
    </div>
</div>
</body>
</html>

<?php
}
