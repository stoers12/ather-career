<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';
require_once __DIR__ . '/project_evidence_repository.php';
require_once __DIR__ . '/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/evidence_hub_owner_page_presentation.php';
require_once __DIR__ . '/evidence_hub_copy.php';
require_once __DIR__ . '/evidence_writing_guidance.php';
require_once __DIR__ . '/project_evidence_source_repository.php';
require_once __DIR__ . '/project_evidence_view.php';

/**
 * Project evidence page (B-07 view modes, docs/contracts/evidence-hub-views.md).
 * Baseline: both modes still render the text form and the sources, as before;
 * frontend slice FE-07 renders "read" without editable text and "write" without
 * source forms, honouring $view.
 *
 * @param array{id:int,title:string,values:array<string,mixed>} $project
 * @param array<string,mixed> $values @param array<string,string> $fieldErrors
 * @param list<array{id:int,title:string,url:string,note:?string}> $sources
 * @param array{title:string,url:string,note:string} $sourceValues @param array<string,string> $sourceErrors
 * @param array{mode:'read'|'write',part:?string} $view
 */
function renderOwnerProjectEvidencePage(array $project, array $values, array $fieldErrors, array $sources, array $sourceValues, array $sourceErrors, array $view, string $locale): void
{
    ?><div class="evidence-hub-project-evidence" data-evidence-view-mode="<?php echo $view['mode']; ?>"<?php echo $view['part'] !== null ? ' data-evidence-view-part="' . ownerEscapeHtml($view['part']) . '"' : ''; ?>><?php
    renderOwnerProjectEvidenceForm($project, $values, $fieldErrors, null, $locale);
    renderOwnerProjectEvidenceSources($project['id'], $sources, $sourceValues, $sourceErrors, $locale);
    ?></div><?php
}

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
 * Evidence sources (B-06, docs/contracts/evidence-sources.md). Minimal working
 * baseline: frontend slice FE-07 replaces this markup with the reference design.
 *
 * @param list<array{id:int,title:string,url:string,note:?string}> $sources
 * @param array{title:string,url:string,note:string} $values
 * @param array<string,string> $errors keyed title, url, note or source_limit
 */
function renderOwnerProjectEvidenceSources(int $projectId, array $sources, array $values, array $errors, string $locale): void
{
    $locale = evidenceHubLocale($locale);
    $e = static fn (string $english, array $replacements = []): string => ownerEscapeHtml(evidenceHubText($english, $locale, $replacements));
    $fieldErrors = [];
    foreach (['title', 'url', 'note'] as $field) {
        if (isset($errors[$field])) {
            $fieldErrors['source_' . $field] = evidenceHubText($errors[$field], $locale);
        }
    }
    $action = '/owner/projects/' . $projectId . '/evidence';
    ?>
    <section class="evidence-hub-editor evidence-hub-sources" id="sources" aria-labelledby="evidence-sources-heading" dir="<?php echo evidenceHubDirection($locale); ?>">
        <h2 id="evidence-sources-heading"><?php echo $e('Sources'); ?></h2>
        <p><?php echo $e('What supports this record? Add a relevant link: a specific contribution, report, demo or repository you may share.'); ?></p>
        <p><?php echo $e('{count} of {limit} sources', ['{count}' => count($sources), '{limit}' => PROJECT_EVIDENCE_SOURCE_LIMIT]); ?></p>
        <?php if ($sources !== []): ?>
        <ul class="evidence-hub-source-list">
            <?php foreach ($sources as $source): $host = parse_url($source['url'], PHP_URL_HOST); ?>
            <li>
                <a href="<?php echo ownerEscapeHtml($source['url']); ?>" rel="noopener noreferrer" dir="auto"><?php echo ownerEscapeHtml($source['title']); ?></a>
                <?php if (is_string($host)): ?><span dir="ltr"><?php echo ownerEscapeHtml($host); ?></span><?php endif; ?>
                <?php if ($source['note'] !== null): ?><p dir="auto"><?php echo ownerEscapeHtml($source['note']); ?></p><?php endif; ?>
                <form method="POST" action="<?php echo $action; ?>" data-owner-form data-confirm="<?php echo $e('Remove this source? This action cannot be undone.'); ?>" data-confirm-title="<?php echo $e('Remove source?'); ?>" data-confirm-action="<?php echo $e('Remove source'); ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="remove_source"><input type="hidden" name="source_id" value="<?php echo $source['id']; ?>"><button type="submit" aria-label="<?php echo $e('Remove source {title}', ['{title}' => $source['title']]); ?>"><?php echo $e('Remove'); ?></button></form>
            </li>
            <?php endforeach; ?>
        </ul>
        <p><small><?php echo $e('Sources you added · not checked or verified.'); ?></small></p>
        <?php else: ?>
        <p><?php echo $e('No sources yet. Add a link to a specific contribution, report or demo.'); ?></p>
        <?php endif; ?>
        <?php if (isset($errors['source_limit'])): ?><p class="field-error" role="alert"><?php echo $e($errors['source_limit']); ?></p><?php endif; ?>
        <?php if (count($sources) < PROJECT_EVIDENCE_SOURCE_LIMIT): ?>
        <form method="POST" action="<?php echo $action; ?>" novalidate data-owner-form>
            <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="add_source">
            <h3><?php echo $e('New source'); ?></h3>
            <label for="source_title"><?php echo $e('A name describing the source'); ?></label><input id="source_title" type="text" name="title" maxlength="<?php echo PROJECT_EVIDENCE_SOURCE_TITLE_MAX_LENGTH; ?>" required dir="auto" value="<?php echo ownerEscapeHtml($values['title']); ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'source_title'); ?>><?php ownerRenderFieldError($fieldErrors, 'source_title'); ?>
            <label for="source_url"><?php echo $e('URL'); ?></label><input id="source_url" type="url" name="url" maxlength="<?php echo PROJECT_EVIDENCE_SOURCE_URL_MAX_LENGTH; ?>" required dir="ltr" placeholder="https://" value="<?php echo ownerEscapeHtml($values['url']); ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'source_url'); ?>><?php ownerRenderFieldError($fieldErrors, 'source_url'); ?>
            <label for="source_note"><?php echo $e('How does it support your contribution? (optional)'); ?></label><input id="source_note" type="text" name="note" maxlength="<?php echo PROJECT_EVIDENCE_SOURCE_NOTE_MAX_LENGTH; ?>" dir="auto" value="<?php echo ownerEscapeHtml($values['note']); ?>"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, 'source_note'); ?>><?php ownerRenderFieldError($fieldErrors, 'source_note'); ?>
            <button type="submit" data-pending-label="<?php echo $e('Adding source…'); ?>"><?php echo $e('Add source'); ?></button>
        </form>
        <?php endif; ?>
        <p><?php echo $e('Only add links you may share. Keep company secrets and access tokens out of this record.'); ?></p>
    </section>
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
