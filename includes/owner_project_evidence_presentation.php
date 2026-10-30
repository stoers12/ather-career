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
 * Read contains saved text and source forms. Write contains only the text form,
 * so native source mutations cannot discard an unfinished text draft.
 *
 * @param array{id:int,title:string,values:array<string,mixed>} $project
 * @param array<string,mixed> $values @param array<string,string> $fieldErrors
 * @param list<array{id:int,title:string,url:string,note:?string}> $sources
 * @param array{title:string,url:string,note:string} $sourceValues @param array<string,string> $sourceErrors
 * @param array{mode:'read'|'write',part:?string} $view
 */
function renderOwnerProjectEvidencePage(array $project, array $values, array $fieldErrors, array $sources, array $sourceValues, array $sourceErrors, array $view, string $locale): void
{
    $e = static fn (string $english): string => ownerEscapeHtml(evidenceHubText($english, $locale));
    $urls = projectEvidenceViewUrls($project['id']);
    ?><div class="evidence-hub-project-evidence" data-evidence-view-mode="<?php echo ownerEscapeHtml($view['mode']); ?>"<?php echo $view['part'] !== null ? ' data-evidence-view-part="' . ownerEscapeHtml($view['part']) . '"' : ''; ?>>
    <div class="evidence-hub-record-back"><a href="/owner/evidence-hub#project-<?php echo (int) $project['id']; ?>"><?php echo $e('All records'); ?></a><span><?php echo evidenceHubOwnerIcon('lock-keyhole'); ?><?php echo $e('Private record'); ?></span></div>
    <header class="evidence-hub-record-heading"><div class="evidence-hub-record-identity"><span class="evidence-hub-record-icon"><?php echo evidenceHubOwnerIcon('folder'); ?></span><h1 dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></h1></div><nav class="evidence-hub-mode-switch" aria-label="<?php echo $e('Record mode'); ?>"><a href="<?php echo ownerEscapeHtml($urls['read']); ?>"<?php echo $view['mode'] === 'read' ? ' aria-current="page"' : ''; ?>><?php echo $e('Read'); ?></a><a href="<?php echo ownerEscapeHtml($urls['write']); ?>"<?php echo $view['mode'] === 'write' ? ' aria-current="page"' : ''; ?>><?php echo $e('Edit'); ?></a></nav></header>
    <?php if ($view['mode'] === 'write'): renderOwnerProjectEvidenceForm($project, $values, $fieldErrors, null, $locale, $view['part']); else: ?>
    <div class="evidence-hub-reading-layout"><article class="evidence-hub-reading-paper"><p class="evidence-hub-kicker"><?php echo $e('The project record'); ?></p>
    <?php $evaluations = projectEvidenceLogicalEvaluations($values); foreach (evidenceWritingGuidanceFields() as $key => $field): $evaluation = $evaluations[$key]; $status = evidenceHubOwnerPageFieldStatus($evaluation); ?>
    <section class="evidence-hub-reading-section"><div class="evidence-hub-editor-field-heading"><h2><?php echo $e(evidenceHubPartLabel($key)); ?></h2><a href="<?php echo ownerEscapeHtml($urls['write'] . '&part=' . rawurlencode($key)); ?>" aria-label="<?php echo ownerEscapeHtml(evidenceHubText('Edit {part}', $locale, ['{part}' => evidenceHubText(evidenceHubPartLabel($key), $locale)])); ?>"><?php echo $e('Edit'); ?></a></div><span class="evidence-hub-state evidence-hub-state--<?php echo ownerEscapeHtml($status); ?>"><?php echo ownerEscapeHtml(evidenceHubOwnerPageFieldStatusLabel($status, $locale)); ?></span>
    <p class="evidence-hub-record-text" dir="auto"><?php echo ownerEscapeHtml(is_string($values[$key] ?? null) ? $values[$key] : ''); ?></p><?php if ($status !== 'complete'): ?><p class="evidence-hub-editor-reason"><?php echo $e(evidenceHubOwnerPageFieldReason($evaluation, $field['label'])); ?></p><?php endif; ?></section>
    <?php endforeach; renderOwnerProjectEvidenceSources($project['id'], $sources, $sourceValues, $sourceErrors, $locale); ?>
    </article><aside class="evidence-hub-reading-aside"><h2><?php echo $e('The work behind the result'); ?></h2><p><?php echo $e('Use this record to remember your decisions and explain your contribution in an interview or professional conversation.'); ?></p><p><?php echo $e('Sources are private and do not add a fourth quality point.'); ?></p></aside></div>
    <?php endif; ?><footer class="evidence-hub-disclosure"><p><?php echo $e('Writing and sources are supplied by you. A link or completed fields does not establish verification or assess your skill.'); ?></p></footer></div><?php
}

/** @param array{id:int,title:string,values:array<string,mixed>} $project @param array<string,mixed> $values @param array<string,string> $fieldErrors */
function renderOwnerProjectEvidenceForm(array $project, array $values, array $fieldErrors = [], ?array $evaluations = null, string $locale = 'en', ?string $initialPart = null): void
{
    $locale = evidenceHubLocale($locale);
    $e = static fn (string $english): string => ownerEscapeHtml(evidenceHubText($english, $locale));
    $projectId = $project['id'];
    $evaluations ??= projectEvidenceLogicalEvaluations($values);
    $localizedErrors = array_map(static fn (string $error): string => evidenceHubText($error, $locale), $fieldErrors);
    ?>
    <div class="evidence-hub-editor" data-evidence-editor data-evidence-rejected="<?php echo $fieldErrors === [] ? 'false' : 'true'; ?>" data-initial-part="<?php echo ownerEscapeHtml($initialPart ?? 'problem'); ?>" dir="<?php echo evidenceHubDirection($locale); ?>"><header><p class="evidence-hub-kicker"><?php echo $e('Project evidence'); ?></p><h2><?php echo $e('Edit project evidence'); ?></h2><p class="evidence-hub-editor-project" dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></p><p><?php echo $e('Record what the project addressed, what you personally did, and what changed.'); ?></p><p><?php echo $e('Quality states reflect the last server assessment. Save to assess your changes.'); ?></p></header>
        <?php ownerRenderFormFeedback('', [], $localizedErrors, evidenceHubText('Review the highlighted fields', $locale)); ?>
        <form method="POST" action="/owner/projects/<?php echo (int) $projectId; ?>/evidence" novalidate data-owner-form data-evidence-text-form data-leave-confirm="<?php echo $e('Leave this record? Your unsaved text will be lost.'); ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
            <div class="evidence-hub-writing-layout"><nav class="evidence-hub-writing-nav" aria-label="<?php echo $e('Record sections'); ?>" hidden><?php foreach (['problem' => 'Context', 'personal_role' => 'My contribution', 'measurable_outcome' => 'Outcome', 'sources-after-save' => 'Sources'] as $part => $label): ?><button type="button" data-evidence-step="<?php echo $part; ?>"><?php echo $e($label); ?></button><?php endforeach; ?><p><?php echo $e('Write clearly and take your time. You can save an unfinished record.'); ?></p></nav><div class="evidence-hub-writing-paper">
            <?php foreach (evidenceWritingGuidanceFields() as $key => $field): $evaluation = $evaluations[$key]; $status = evidenceHubOwnerPageFieldStatus($evaluation); $helpId = 'evidence-help-' . $field['anchor']; $hintsId = 'evidence-hints-' . $field['anchor']; $value = is_string($values[$key] ?? null) ? $values[$key] : ''; ?>
                <section class="evidence-hub-editor-field" id="<?php echo $field['anchor']; ?>" aria-labelledby="evidence-heading-<?php echo $field['anchor']; ?>" data-evidence-writing-field data-evidence-step-panel="<?php echo $key; ?>"><div class="evidence-hub-editor-field-heading"><h2 id="evidence-heading-<?php echo $field['anchor']; ?>"><?php echo $e(match ($key) { 'problem' => 'Why was this project built?', 'personal_role' => 'What did you personally contribute?', default => 'What was the outcome?' }); ?></h2><span class="evidence-hub-state evidence-hub-state--<?php echo $status; ?>"><?php echo evidenceHubOwnerPageFieldStatusLabel($status, $locale); ?></span></div><p id="<?php echo $helpId; ?>"><?php echo $e($field['guidance']); ?><br><span><?php echo ownerEscapeHtml(evidenceHubText('Example: {example}', $locale, ['{example}' => evidenceHubText($field['example'], $locale)])); ?></span></p><?php if ($status !== 'complete'): ?><p class="evidence-hub-editor-reason"><?php echo $e(evidenceHubOwnerPageFieldReason($evaluation, $field['label'])); ?></p><?php endif; ?>
                    <?php renderOwnerProjectEvidenceStarter($field, $locale); ?>
                    <label for="evidence-<?php echo $field['anchor']; ?>"><?php echo $e(evidenceHubPartLabel($key)); ?></label><textarea id="evidence-<?php echo $field['anchor']; ?>" name="<?php echo $key; ?>" rows="8" dir="auto"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, $key, $helpId . ' ' . $hintsId); ?>><?php echo ownerEscapeHtml($value); ?></textarea>
                    <?php renderOwnerProjectEvidenceHints($key, $value, $hintsId, $locale); ?><?php ownerRenderFieldError($localizedErrors, $key); ?></section>
            <?php endforeach; ?>
            <section class="evidence-hub-editor-field" data-evidence-step-panel="sources-after-save" id="sources-after-save"><h2><?php echo $e('Sources'); ?></h2><p><?php echo $e('Save your text first, then add or remove sources from the saved record.'); ?></p><p><?php echo $e('After saving, the hub offers immediate Undo. Opening another page ends that opportunity.'); ?></p><button type="submit"><?php echo $e('Save evidence'); ?></button></section>
            <div class="evidence-hub-step-actions" hidden><button type="button" data-evidence-previous><?php echo $e('Previous'); ?></button><button type="button" data-evidence-next><?php echo $e('Next'); ?></button></div></div></div>
            <div class="evidence-hub-editor-actions"><span data-evidence-dirty role="status" data-clean="<?php echo $e('No unsaved changes'); ?>" data-dirty="<?php echo $e('Unsaved changes'); ?>" hidden></span><button class="evidence-hub-primary-cta" type="submit" data-pending-label="<?php echo $e('Saving evidence…'); ?>"><?php echo $e('Save evidence'); ?></button><a href="/owner/projects/<?php echo (int) $projectId; ?>/evidence"><?php echo $e('Cancel'); ?></a></div>
        </form>
    </div>
    <?php
}

/**
 * Source management belongs to the saved Read view (B-07). Forms retain B-06 fields.
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
    <section class="evidence-hub-sources" id="sources" aria-labelledby="evidence-sources-heading" dir="<?php echo evidenceHubDirection($locale); ?>">
        <h2 id="evidence-sources-heading"><?php echo $e('Sources'); ?></h2>
        <p><?php echo $e('What supports this record? Add a relevant link: a specific contribution, report, demo or repository you may share.'); ?></p>
        <p><?php echo $e('{count} of {limit} sources', ['{count}' => count($sources), '{limit}' => PROJECT_EVIDENCE_SOURCE_LIMIT]); ?></p>
        <?php if ($sources !== []): ?>
        <ul class="evidence-hub-source-list">
            <?php foreach ($sources as $source): $host = parse_url($source['url'], PHP_URL_HOST); ?>
            <li><span class="evidence-hub-source-icon"><?php echo evidenceHubOwnerIcon('link'); ?></span>
                <a href="<?php echo ownerEscapeHtml($source['url']); ?>" rel="noopener noreferrer" dir="auto"><?php echo ownerEscapeHtml($source['title']); ?></a>
                <?php if (is_string($host)): ?><span dir="ltr"><?php echo ownerEscapeHtml($host); ?></span><?php endif; ?>
                <?php if ($source['note'] !== null): ?><p dir="auto"><?php echo ownerEscapeHtml($source['note']); ?></p><?php endif; ?>
                <form method="POST" action="<?php echo ownerEscapeHtml($action); ?>" data-owner-form data-confirm="<?php echo $e('Remove this source? This action cannot be undone.'); ?>" data-confirm-title="<?php echo $e('Remove source?'); ?>" data-confirm-action="<?php echo $e('Remove source'); ?>"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="remove_source"><input type="hidden" name="source_id" value="<?php echo (int) $source['id']; ?>"><button type="submit" aria-label="<?php echo $e('Remove source {title}', ['{title}' => $source['title']]); ?>"><?php echo $e('Remove'); ?></button></form>
            </li>
            <?php endforeach; ?>
        </ul>
        <p><small><?php echo $e('Sources you added · not checked or verified.'); ?></small></p>
        <?php else: ?>
        <p><?php echo $e('No sources yet. Add a link to a specific contribution, report or demo.'); ?></p>
        <?php endif; ?>
        <?php if (isset($errors['source_limit'])): ?><p class="field-error" role="alert"><?php echo $e($errors['source_limit']); ?></p><?php endif; ?>
        <?php if (count($sources) < PROJECT_EVIDENCE_SOURCE_LIMIT): ?>
        <form class="evidence-hub-add-source" method="POST" action="<?php echo ownerEscapeHtml($action); ?>" novalidate data-owner-form>
            <input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="add_source">
            <h3><?php echo $e('New source'); ?></h3><p><?php echo $e('Adding a source saves it immediately.'); ?></p>
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
