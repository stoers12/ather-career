<?php

declare(strict_types=1);

// Shared recommendation guards, canonical ordering and the single rejection path. Loaded by evidence_hub_recommendations.php.

/** @param list<int|string> $left @param list<int|string> $right */
function evidenceHubSameRecommendationKeySet(array $left, array $right): bool
{
    sort($left, SORT_STRING);
    sort($right, SORT_STRING);
    return $left === $right;
}

function evidenceHubValidateOpaqueInputText(mixed $value, string $field): void
{
    if (!is_string($value) || $value === '') {
        evidenceHubRecommendationReject($field . '_invalid');
    }
    evidenceHubAssertRecommendationUtf8($value);
}

/** @param array<string, mixed> $value @param list<string> $required */
function evidenceHubRequireExactObjectKeys(array $value, array $required, string $category): void
{
    if (array_is_list($value) || !evidenceHubSameRecommendationKeySet(array_keys($value), $required)) {
        evidenceHubRecommendationReject($category);
    }
}

function evidenceHubAssertRecommendationNoFloat(mixed $value): void
{
    if (is_float($value)) {
        evidenceHubRecommendationReject('floating_point_forbidden');
    }
    if (is_array($value)) {
        foreach ($value as $item) {
            evidenceHubAssertRecommendationNoFloat($item);
        }
    }
}

function evidenceHubAssertRecommendationUtf8(mixed $value): void
{
    if (is_string($value) && @preg_match('//u', $value) !== 1) {
        evidenceHubRecommendationReject('invalid_utf8');
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            if (is_string($key) && @preg_match('//u', $key) !== 1) {
                evidenceHubRecommendationReject('invalid_utf8');
            }
            evidenceHubAssertRecommendationUtf8($item);
        }
    }
}

function evidenceHubAssertNoRecommendationPrivateFields(mixed $value, string $category): void
{
    $prohibited = [
        'raw_evidence_text',
        'raw_technology_label',
        'normalized_technology_label',
        'email',
        'auth0_subject',
        'cookie',
        'token',
        'filesystem_path',
        'private_media_path',
        'showcase_identity',
        'database_id',
        'owner_id',
        'user_id',
        'portfolio_id',
        'project_id',
    ];
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $item) {
        if (is_string($key) && in_array($key, $prohibited, true)) {
            evidenceHubRecommendationReject($category);
        }
        evidenceHubAssertNoRecommendationPrivateFields($item, $category);
    }
}

function evidenceHubSortRecommendationCanonical(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('evidenceHubSortRecommendationCanonical', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = evidenceHubSortRecommendationCanonical($item);
    }

    return $value;
}

function evidenceHubRecommendationReject(string $category): never
{
    throw new EvidenceHubRecommendationInvariantException($category);
}
