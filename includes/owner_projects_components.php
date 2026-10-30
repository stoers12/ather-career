<?php

declare(strict_types=1);

require_once __DIR__ . '/owner_projects_copy.php';

/** @param array<string, mixed> $project */
function ownerProjectsCover(array $project): void
{
    if ((string) ($project['image_path'] ?? '') !== '' && isset($project['id'])) {
        ?><img class="owner-project-cover" src="owner_media.php?type=project&amp;id=<?php echo (int) $project['id']; ?>" alt="<?php echo ownerEscapeHtml((string) $project['title']); ?>"><?php
    } else {
        ?><div class="owner-project-cover owner-project-cover-neutral" aria-hidden="true"><span></span></div><?php
    }
}

/** @param list<string> $technologies */
function ownerProjectsTags(array $technologies): void
{
    ?><div class="owner-project-tags" dir="ltr"><?php foreach (array_slice($technologies, 0, 3) as $technology): ?><span><bdi dir="auto"><?php echo ownerEscapeHtml($technology); ?></bdi></span><?php endforeach; ?><?php if (count($technologies) > 3): ?><span>+<?php echo count($technologies) - 3; ?></span><?php endif; ?></div><?php
}

/** @param array<string, mixed> $project @param array<string, string> $errors */
function ownerProjectsField(string $name, string $label, string $type, array $project, array $errors, bool $required, ?int $maximum, string $escapedHint): void
{
    $optional = ownerProjectsCopy('optional', ownerLocale());
    $requiredLabel = ownerProjectsCopy('required', ownerLocale());
    ?>
    <div class="form-field">
        <label for="<?php echo ownerEscapeHtml($name); ?>"><?php echo ownerEscapeHtml($label); ?> <em><?php echo $required ? ownerEscapeHtml($requiredLabel) : ownerEscapeHtml($optional); ?></em><?php if ($required): ?><span class="visually-hidden"><?php ownerRenderRequiredIndicator(); ?></span><?php endif; ?></label>
        <?php if ($type === 'textarea'): ?><textarea id="<?php echo ownerEscapeHtml($name); ?>" name="<?php echo ownerEscapeHtml($name); ?>" rows="6" dir="auto"<?php echo $required ? ' required' : ''; ?><?php echo ownerFieldAccessibilityAttributes($errors, $name, $name . '-help'); ?>><?php echo ownerEscapeHtml((string) ($project[$name] ?? '')); ?></textarea>
        <?php else: ?><input type="<?php echo ownerEscapeHtml($type); ?>" id="<?php echo ownerEscapeHtml($name); ?>" name="<?php echo ownerEscapeHtml($name); ?>" value="<?php echo ownerEscapeHtml((string) ($project[$name] ?? '')); ?>" dir="<?php echo $type === 'url' ? 'ltr' : 'auto'; ?>"<?php echo $maximum !== null ? ' maxlength="' . $maximum . '"' : ''; ?><?php echo $required ? ' required' : ''; ?><?php echo ownerFieldAccessibilityAttributes($errors, $name, $name . '-help'); ?>><?php endif; ?>
        <small id="<?php echo ownerEscapeHtml($name); ?>-help" class="form-hint"><?php echo $escapedHint; ?></small>
        <?php ownerRenderFieldError($errors, $name); ?>
    </div>
    <?php
}
