<?php

declare(strict_types=1);

require_once __DIR__ . '/portfolio_scoped_data.php';
require_once __DIR__ . '/public_lifecycle.php';
require_once __DIR__ . '/profile_presentation.php';
require_once __DIR__ . '/storage.php';

function ownerMediaDescriptor(PDO $database, AuthorizedPortfolioContext $context, mixed $type, mixed $resourceId): ?array
{
    if ($type === 'profile') {
        $profile = loadAuthorizedPersonalInfo($database, $context);
        $originalKey = is_array($profile) && is_string($profile['profile_image_path'] ?? null) ? $profile['profile_image_path'] : null;
        $presentationKey = $originalKey === null ? null : generateProfilePresentationImage($originalKey, $context->portfolioId);

        return $presentationKey === null ? null : privateMediaDescriptor($presentationKey, $context->portfolioId, 'profile_presentation');
    }
    if ($type !== 'project') {
        return null;
    }
    $projectId = authorizationPositiveInteger($resourceId);
    $project = $projectId === null ? null : findAuthorizedProject($database, $context, $projectId);

    return $project === null ? null : privateMediaDescriptor($project['image_path'] ?? null, $context->portfolioId, 'projects');
}

function publicMediaDescriptor(PDO $database, PublicReadContext $context, mixed $type, mixed $resourceId): ?array
{
    if ($type === 'profile') {
        $profile = loadPublicPersonalInfo($database, $context);
        $originalKey = is_array($profile) && is_string($profile['profile_image_path'] ?? null) ? $profile['profile_image_path'] : null;
        $presentationKey = $originalKey === null ? null : generateProfilePresentationImage($originalKey, $context->portfolioId);

        return $presentationKey === null ? null : privateMediaDescriptor($presentationKey, $context->portfolioId, 'profile_presentation');
    }
    if ($type !== 'project') {
        return null;
    }
    $projectId = authorizationPositiveInteger($resourceId);
    if ($projectId === null) {
        return null;
    }
    $statement = $database->prepare(
        'SELECT image_path
         FROM projects
         WHERE id = :resource_id
           AND portfolio_id = :public_portfolio_id
         LIMIT 1'
    );
    $statement->execute(['resource_id' => $projectId, 'public_portfolio_id' => $context->portfolioId]);
    $key = $statement->fetchColumn();

    return $key === false ? null : privateMediaDescriptor($key, $context->portfolioId, 'projects');
}
