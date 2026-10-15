<?php

declare(strict_types=1);

// The preview router or focused CLI test must opt in. Production never routes here.
if (!defined('ATHER_ONBOARDING_PREVIEW') || ATHER_ONBOARDING_PREVIEW !== true || !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 3) . '/includes/http.php';
require_once dirname(__DIR__, 3) . '/includes/presentation.php';
require_once dirname(__DIR__, 3) . '/includes/onboarding_copy.php';

httpRequireMethod(['GET', 'HEAD']);
$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
$step = ($_GET['step'] ?? null) === '2' ? 2 : 1;
$oppositeLocale = $locale === 'ar' ? 'en' : 'ar';
$stepName = onboardingCopy('step_' . $step, $locale);
$stepTitle = sprintf(onboardingCopy('step_of', $locale), $step, $stepName);
$position = sprintf(onboardingCopy('position', $locale), $step);
$countries = ['JO' => 'country_jo', 'PS' => 'country_ps', 'SA' => 'country_sa', 'AE' => 'country_ae', 'EG' => 'country_eg'];

header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self'");
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo onboardingCopy('title', $locale); ?></title>
    <script src="<?php echo versionedAssetUrl('landing.js'); ?>" defer></script>
    <script src="<?php echo versionedAssetUrl('onboarding.js'); ?>" defer></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('onboarding.css'); ?>">
</head>
<body>
<a class="ao-skip" href="#onboarding-card"><?php echo onboardingCopy('skip', $locale); ?></a>
<div id="ather-onboarding-preview" class="is-step-<?php echo $step; ?>" data-initial-step="<?php echo $step; ?>"
     data-preview-name="<?php echo onboardingCopy('preview_name', $locale); ?>"
     data-preview-country="<?php echo onboardingCopy('preview_country', $locale); ?>">
    <div class="ao-stage">
        <main class="ao-product-frame">
            <header class="ao-topbar">
                <div class="ao-brand">
                    <span class="ao-brand-mark" aria-hidden="true">أ</span>
                    <span class="ao-brand-name"><?php echo onboardingCopy('brand', $locale); ?><small><?php echo onboardingCopy('brand_tagline', $locale); ?></small></span>
                </div>
                <span class="ao-save-state"><span class="ao-save-dot" aria-hidden="true"></span><?php echo onboardingCopy('preview_state', $locale); ?></span>
                <button class="ao-header-action" type="button" disabled title="<?php echo onboardingCopy('save_unavailable', $locale); ?>"><?php echo onboardingCopy('save_exit', $locale); ?></button>
            </header>

            <section class="ao-progress-shell" aria-label="<?php echo $stepTitle; ?>">
                <div class="ao-progress-top">
                    <p class="ao-progress-title" data-progress-title><?php echo $stepTitle; ?></p>
                    <span class="ao-progress-position" data-progress-position><?php echo $position; ?></span>
                </div>
                <div class="ao-progress-track" aria-hidden="true"><div class="ao-progress-fill" data-progress-fill></div></div>
                <nav class="ao-progress-steps" aria-label="<?php echo $stepTitle; ?>">
                    <?php foreach ([1, 2] as $availableStep): ?>
                    <a class="ao-step-jump<?php echo $availableStep === $step ? ' is-current' : ''; ?>" href="?lang=<?php echo $locale; ?>&amp;step=<?php echo $availableStep; ?>" data-step-jump="<?php echo $availableStep; ?>"
                       data-title="<?php echo sprintf(onboardingCopy('step_of', $locale), $availableStep, onboardingCopy('step_' . $availableStep, $locale)); ?>"
                       data-position="<?php echo sprintf(onboardingCopy('position', $locale), $availableStep); ?>"<?php echo $availableStep === $step ? ' aria-current="step"' : ''; ?>><?php echo onboardingCopy('step_' . $availableStep, $locale); ?></a>
                    <?php endforeach; ?>
                    <?php foreach ([3, 4, 5] as $futureStep): ?>
                    <span class="ao-step-future" aria-disabled="true" title="<?php echo onboardingCopy('future_step', $locale); ?>"><?php echo onboardingCopy('step_' . $futureStep, $locale); ?></span>
                    <?php endforeach; ?>
                </nav>
            </section>

            <div class="ao-workspace">
                <section class="ao-form-card" id="onboarding-card" tabindex="-1" aria-label="<?php echo $stepName; ?>">
                    <div class="ao-controls">
                        <a class="ao-language" data-language-switch href="?lang=<?php echo $oppositeLocale; ?>&amp;step=<?php echo $step; ?>" lang="<?php echo $oppositeLocale; ?>" hreflang="<?php echo $oppositeLocale; ?>" aria-label="<?php echo onboardingCopy('interface_language', $locale); ?>: <?php echo onboardingCopy('switch_language', $locale); ?>"><?php echo onboardingCopy('switch_language', $locale); ?></a>
                        <button class="ao-theme hf-theme" type="button" hidden aria-label="<?php echo onboardingCopy('theme', $locale); ?>" aria-pressed="false">◐</button>
                    </div>
                    <button class="ao-secondary ao-preview-toggle" type="button" data-preview-toggle aria-expanded="false"
                            data-open-label="<?php echo onboardingCopy('preview_toggle_open', $locale); ?>"
                            data-close-label="<?php echo onboardingCopy('preview_toggle_close', $locale); ?>"><?php echo onboardingCopy('preview_toggle_open', $locale); ?></button>

                    <section class="ao-panel" data-step-panel="1"<?php echo $step === 1 ? '' : ' hidden'; ?> aria-labelledby="step-1-heading">
                        <p class="ao-eyebrow"><?php echo onboardingCopy('step_1_eyebrow', $locale); ?></p>
                        <h1 class="ao-heading" id="step-1-heading" tabindex="-1"><?php echo onboardingCopy('step_1_heading', $locale); ?></h1>
                        <p class="ao-lead"><?php echo onboardingCopy('step_1_lead', $locale); ?></p>
                        <div class="ao-fields">
                            <div class="ao-status-row">
                                <div class="ao-status-main">
                                    <span class="ao-status-icon" aria-hidden="true">✓</span>
                                    <span class="ao-status-copy"><strong><?php echo onboardingCopy('email_example', $locale); ?></strong><bdi dir="ltr"><?php echo onboardingCopy('example_email', $locale); ?></bdi></span>
                                </div>
                                <span class="ao-badge"><?php echo onboardingCopy('example_badge', $locale); ?></span>
                            </div>
                            <p class="ao-field-note"><?php echo onboardingCopy('email_notice', $locale); ?></p>
                            <div class="ao-check-list">
                                <label class="ao-check"><input type="checkbox" autocomplete="off"><span><strong><?php echo onboardingCopy('age', $locale); ?></strong><small><?php echo onboardingCopy('age_hint', $locale); ?></small></span></label>
                                <label class="ao-check"><input type="checkbox" autocomplete="off"><span><strong><?php echo onboardingCopy('terms', $locale); ?></strong><small><?php echo onboardingCopy('terms_hint', $locale); ?></small></span></label>
                                <label class="ao-check"><input type="checkbox" autocomplete="off"><span><strong><?php echo onboardingCopy('privacy', $locale); ?></strong><small><?php echo onboardingCopy('privacy_hint', $locale); ?></small></span></label>
                            </div>
                            <div class="ao-note"><?php echo onboardingCopy('consent_notice', $locale); ?></div>
                        </div>
                    </section>

                    <section class="ao-panel" data-step-panel="2"<?php echo $step === 2 ? '' : ' hidden'; ?> aria-labelledby="step-2-heading">
                        <p class="ao-eyebrow"><?php echo onboardingCopy('step_2_eyebrow', $locale); ?></p>
                        <h1 class="ao-heading" id="step-2-heading" tabindex="-1"><?php echo onboardingCopy('step_2_heading', $locale); ?></h1>
                        <p class="ao-lead"><?php echo onboardingCopy('step_2_lead', $locale); ?></p>
                        <div class="ao-fields">
                            <label class="ao-field"><span class="ao-label"><?php echo onboardingCopy('display_name', $locale); ?></span><input class="ao-input" type="text" data-profile-name placeholder="<?php echo onboardingCopy('name_example', $locale); ?>" autocomplete="off" dir="auto"><small class="ao-label-note"><?php echo onboardingCopy('name_hint', $locale); ?></small></label>
                            <div class="ao-field-row">
                                <label class="ao-field"><span class="ao-label"><?php echo onboardingCopy('country', $locale); ?></span><select class="ao-select" data-country><option value=""><?php echo onboardingCopy('country_placeholder', $locale); ?></option><?php foreach ($countries as $countryCode => $copyKey): ?><option value="<?php echo $countryCode; ?>"><?php echo onboardingCopy($copyKey, $locale); ?></option><?php endforeach; ?></select><small class="ao-label-note"><?php echo onboardingCopy('country_limit', $locale); ?></small></label>
                                <fieldset class="ao-field ao-language-field"><legend class="ao-label"><?php echo onboardingCopy('preferred_language', $locale); ?></legend><div class="ao-language-options"><label class="ao-radio-card"><input type="radio" name="preview_preferred_language" value="ar" autocomplete="off"><?php echo onboardingCopy('arabic', $locale); ?></label><label class="ao-radio-card"><input type="radio" name="preview_preferred_language" value="en" autocomplete="off"><?php echo onboardingCopy('english', $locale); ?></label></div><small class="ao-label-note"><?php echo onboardingCopy('preferred_language_hint', $locale); ?></small></fieldset>
                            </div>
                            <div class="ao-note"><?php echo onboardingCopy('phone_note', $locale); ?></div>
                            <p class="ao-field-note"><?php echo onboardingCopy('identity_note', $locale); ?></p>
                        </div>
                    </section>

                    <div class="ao-actions">
                        <button class="ao-save-exit" type="button" disabled title="<?php echo onboardingCopy('save_unavailable', $locale); ?>"><?php echo onboardingCopy('save_exit_safe', $locale); ?></button>
                        <div class="ao-action-group">
                            <a class="ao-secondary" data-back href="?lang=<?php echo $locale; ?>&amp;step=1"<?php echo $step === 1 ? ' hidden' : ''; ?>><?php echo onboardingCopy('back', $locale); ?></a>
                            <a class="ao-primary" data-next href="?lang=<?php echo $locale; ?>&amp;step=2"<?php echo $step === 2 ? ' hidden' : ''; ?>><?php echo onboardingCopy('continue', $locale); ?></a>
                            <button class="ao-primary" type="button" data-next-unavailable disabled<?php echo $step === 1 ? ' hidden' : ''; ?> title="<?php echo onboardingCopy('next_unavailable', $locale); ?>"><?php echo onboardingCopy('continue', $locale); ?></button>
                        </div>
                    </div>
                    <p class="ao-next-note" data-next-note<?php echo $step === 1 ? ' hidden' : ''; ?>><?php echo onboardingCopy('next_unavailable', $locale); ?></p>
                    <p class="ao-preview-footer"><?php echo onboardingCopy('preview_footer', $locale); ?></p>
                </section>

                <aside class="ao-preview-column" data-preview-column aria-label="<?php echo onboardingCopy('preview_label', $locale); ?>">
                    <div class="ao-preview-label"><span><?php echo onboardingCopy('preview_label', $locale); ?></span><span class="ao-private-badge"><?php echo onboardingCopy('private_badge', $locale); ?></span></div>
                    <article class="ao-preview-card">
                        <div class="ao-preview-cover" aria-hidden="true"></div>
                        <div class="ao-preview-body">
                            <div class="ao-avatar" data-preview-initials aria-hidden="true">أ</div>
                            <h2 class="ao-preview-name" data-preview-name dir="auto"><?php echo onboardingCopy('preview_name', $locale); ?></h2>
                            <p class="ao-preview-headline"><?php echo onboardingCopy('preview_headline', $locale); ?></p>
                            <div class="ao-preview-meta"><span><?php echo onboardingCopy('preview_status', $locale); ?></span><span><?php echo onboardingCopy('preview_field', $locale); ?></span><span data-preview-country><?php echo onboardingCopy('preview_country', $locale); ?></span></div>
                            <section class="ao-preview-section"><h3><?php echo onboardingCopy('preview_section', $locale); ?></h3><p><?php echo onboardingCopy('preview_section_copy', $locale); ?></p><div class="ao-empty-project"><?php echo onboardingCopy('preview_project', $locale); ?></div></section>
                        </div>
                    </article>
                </aside>
            </div>
        </main>
    </div>
</div>
</body>
</html>
