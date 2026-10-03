<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_layout.php';
require_once __DIR__ . '/project_evidence_repository.php';
require_once __DIR__ . '/evidence_hub_owner_page_model.php';
require_once __DIR__ . '/evidence_hub_owner_page_presentation.php';

/** @param array{id:int,title:string,values:array<string,mixed>} $project @param array<string,mixed> $values @param array<string,string> $fieldErrors */
function renderOwnerProjectEvidenceForm(array $project, array $values, array $fieldErrors = [], ?array $evaluations = null): void
{
    $projectId = $project['id'];
    $evaluations ??= projectEvidenceLogicalEvaluations($values);
    $fields = [
        'problem' => ['label' => 'Problem', 'guidance' => 'Describe the specific problem, who it affected, and why it mattered.', 'example' => 'Example: Manual reporting took the team six hours each week.', 'anchor' => 'problem'],
        'personal_role' => ['label' => 'Personal role', 'guidance' => 'Explain what you personally owned, decided, or implemented.', 'example' => 'Example: I designed the data pipeline and implemented its validation rules.', 'anchor' => 'personal-role'],
        'measurable_outcome' => ['label' => 'Measurable outcome', 'guidance' => 'State the result using a number, comparison, or observable change.', 'example' => 'Example: Reduced weekly reporting time from six hours to one.', 'anchor' => 'measurable-outcome'],
    ];
    ?>
    <div class="evidence-hub-editor" dir="ltr"><header><p class="evidence-hub-kicker">Project evidence</p><h1>Edit project evidence</h1><p class="evidence-hub-editor-project" dir="auto"><?php echo ownerEscapeHtml($project['title']); ?></p><p>Record what the project addressed, what you personally did, and what changed.</p></header>
        <?php ownerRenderFormFeedback('', [], $fieldErrors); ?>
        <form method="POST" action="/owner/projects/<?php echo $projectId; ?>/evidence" novalidate data-owner-form><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>">
            <?php foreach ($fields as $key => $field): $evaluation = $evaluations[$key]; $status = evidenceHubOwnerPageFieldStatus($evaluation); $helpId = 'evidence-help-' . $field['anchor']; ?>
                <section class="evidence-hub-editor-field" id="<?php echo $field['anchor']; ?>" aria-labelledby="evidence-heading-<?php echo $field['anchor']; ?>"><div class="evidence-hub-editor-field-heading"><h2 id="evidence-heading-<?php echo $field['anchor']; ?>"><?php echo $field['label']; ?></h2><span class="evidence-hub-state evidence-hub-state--<?php echo $status; ?>"><?php echo evidenceHubOwnerPageFieldStatusLabel($status); ?></span></div><p id="<?php echo $helpId; ?>"><?php echo $field['guidance']; ?><br><span><?php echo $field['example']; ?></span></p><?php if ($status !== 'complete'): ?><p class="evidence-hub-editor-reason"><?php echo ownerEscapeHtml(evidenceHubOwnerPageFieldReason($evaluation, $field['label'])); ?></p><?php endif; ?><label for="evidence-<?php echo $field['anchor']; ?>"><?php echo $field['label']; ?></label><textarea id="evidence-<?php echo $field['anchor']; ?>" name="<?php echo $key; ?>" rows="5"<?php echo ownerFieldAccessibilityAttributes($fieldErrors, $key, $helpId); ?>><?php echo ownerEscapeHtml(is_string($values[$key] ?? null) ? $values[$key] : ''); ?></textarea><?php ownerRenderFieldError($fieldErrors, $key); ?></section>
            <?php endforeach; ?>
            <div class="evidence-hub-editor-actions"><button class="evidence-hub-primary-cta" type="submit" data-pending-label="Saving evidence…">Save evidence</button><a href="/owner/evidence-hub#project-<?php echo $projectId; ?>">Cancel</a></div>
        </form>
    </div>
    <?php
}
