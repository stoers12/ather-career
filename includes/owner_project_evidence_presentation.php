<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';
require_once __DIR__ . '/project_evidence_repository.php';
require_once __DIR__ . '/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/evidence_hub_copy.php';
require_once __DIR__ . '/evidence_writing_guidance.php';

/** @param array{id:int,title:string,values:array<string,mixed>} $project @param array<string,mixed> $values @param array<string,string> $fieldErrors */
function renderOwnerProjectEvidenceForm(array $project, array $values, array $fieldErrors = [], ?array $evaluations = null, string $locale = 'en'): void
{
    $locale = evidenceHubLocale($locale);
    $e = static fn (string $english): string => ownerEscapeHtml(evidenceHubText($english, $locale));
    $projectId = $project['id'];
    $evaluations ??= projectEvidenceLogicalEvaluations($values);
    $localizedErrors = array_map(static fn (string $error): string => evidenceHubText($error, $locale), $fieldErrors);
    ?>
    <div class="evidence-hub-editor" dir="<?php echo evidenceHubDirection($locale); ?>"><header><p class="evidence-hub-kicker"><?php echo $e('Project evidence'); ?></p><h1><?php echo $e('Edit project evidence'); ?></h1><p class="evidence-hub-editor-project" dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></p><p><?php echo $e('Record what the project addressed, what you personally did, and what changed.'); ?></p></header>
        <?php ownerRenderFormFeedback('', [], $localizedErrors, evidenceHubText('Review the highlighted fields', $locale)); ?>
        <form method="POST" action="/owner/projects/<?php echo $projectId; ?>/evidence" novalidate data-owner-form><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
            <?php foreach (evidenceWritingGuidanceFields() as $key => $field): $evaluation = $evaluations[$key]; $status = evidenceHubOwnerPageFieldStatus($evaluation); $helpId = 'evidence-help-' . $field['anchor']; $hintsId = 'evidence-hints-' . $field['anchor']; $value = is_string($values[$key] ?? null) ? $values[$key] : ''; ?>
                <section class="evidence-hub-editor-field" id="<?php echo $field['anchor']; ?>" aria-labelledby="evidence-heading-<?php echo $field['anchor']; ?>" data-evidence-writing-field><div class="evidence-hub-editor-field-heading"><h2 id="evidence-heading-<?php echo $field['anchor']; ?>"><?php echo $e($field['label']); ?></h2><span class="evidence-hub-state evidence-hub-state--<?php echo $status; ?>"><?php echo evidenceHubOwnerPageFieldStatusLabel($status, $locale); ?></span></div><p id="<?php echo $helpId; ?>"><?php echo $e($field['guidance']); ?><br><span><?php echo ownerEscapeHtml(evidenceHubText('Example: {example}', $locale, ['{example}' => evidenceHubText($field['example'], $locale)])); ?></span></p><?php if ($status !== 'complete'): ?><p class="evidence-hub-editor-reason"><?php echo $e(evidenceHubOwnerPageFieldReason($evaluation, $field['label'])); ?></p><?php endif; ?>
                    <?php renderOwnerProjectEvidenceStarter($field, $locale); ?>
                    <label for="evidence-<?php echo $field['anchor']; ?>"><?php echo $e($field['label']); ?></label><textarea id="evidence-<?php echo $field['anchor']; ?>" name="<?php echo $key; ?>" rows="5"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, $key, $helpId . ' ' . $hintsId); ?>><?php echo ownerEscapeHtml($value); ?></textarea>
                    <?php renderOwnerProjectEvidenceHints($key, $value, $hintsId, $locale); ?><?php ownerRenderFieldError($localizedErrors, $key); ?></section>
            <?php endforeach; ?>
            <div class="evidence-hub-editor-actions"><button class="evidence-hub-primary-cta" type="submit" data-pending-label="<?php echo $e('Saving evidence…'); ?>"><?php echo $e('Save evidence'); ?></button><a href="/owner/evidence-hub#project-<?php echo $projectId; ?>"><?php echo $e('Cancel'); ?></a></div>
        </form>
    </div>
    <?php
}

/**
 * The starter stays readable without JavaScript; the insert control is
 * revealed by evidence_hub.js and only fills an empty field.
 *
 * @param array{starter: string} $field
 */
function renderOwnerProjectEvidenceStarter(array $field, string $locale): void
{
    $starter = evidenceHubText($field['starter'], $locale);
    ?>
    <div class="evidence-hub-editor-starter" data-evidence-starter><p><span><?php echo ownerEscapeHtml(evidenceHubText('Sentence starter', $locale)); ?>:</span> <bdi><?php echo ownerEscapeHtml($starter); ?></bdi></p><button type="button" data-evidence-insert-starter data-starter="<?php echo ownerEscapeHtml($starter); ?>" hidden><?php echo ownerEscapeHtml(evidenceHubText('Use this starter', $locale)); ?></button></div>
    <?php
}

/** Every applicable hint is rendered once; the server sets its initial state and the editor script keeps it current while typing. */
function renderOwnerProjectEvidenceHints(string $field, string $value, string $hintsId, string $locale): void
{
    $active = evidenceWritingActiveHints($field, $value, $locale);
    ?>
    <div class="evidence-hub-editor-hints" id="<?php echo $hintsId; ?>" aria-live="polite"><?php foreach (evidenceWritingHintsForField($field, $locale) as $id => $rule): ?><p class="evidence-hub-editor-hint" data-evidence-writing-hint="<?php echo $id; ?>" data-hint-pattern="<?php echo ownerEscapeHtml($rule['pattern']); ?>" data-hint-when="<?php echo $rule['when']; ?>"<?php echo in_array($id, $active, true) ? '' : ' hidden'; ?>><?php echo ownerEscapeHtml(evidenceHubText($rule['message'], $locale)); ?></p><?php endforeach; ?></div>
    <?php
}
