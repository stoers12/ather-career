<?php

declare(strict_types=1);

final class EvidenceHubOwnerRoutePresenterTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_presentation.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_recommendations.php';

        $fixtures = self::json('tests/phase2/fixtures/evidence-hub-owner-page-fixtures.json');
        self::routeAndWrapperContracts($fixtures);
        self::ownerGuardsAndSafeConfigurationFailure();
        self::contractOnlyPresentation($fixtures);
        self::navigationAndAccessibilityContracts();
        self::browserSupportContract();
    }

    /** @param array<string, mixed> $fixtures */
    private static function routeAndWrapperContracts(array $fixtures): void
    {
        $route = self::read('owner_evidence_hub.php');
        $wrapper = self::read('public/owner_evidence_hub.php');
        $productionVhost = self::read('docker/apache/production-vhost.conf');
        $developmentVhost = self::read('docker/apache/development-vhost.conf');
        $canonical = $fixtures['canonical_path'];
        phase2Assert(is_string($canonical), 'Owner page fixture canonical path is invalid.');

        foreach (['startOwnerSession();', "httpRegisterExceptionBoundary('owner_evidence_hub.php')", "httpRequireMethod(['GET', 'HEAD'])", 'requireOwnerPortfolioContext($database)', 'buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState'] as $required) {
            phase2Assert(str_contains($route, $required), "Protected Evidence Hub route is missing {$required}.");
        }
        phase2Assert(str_contains($route, "httpRedirect('{$canonical}', 302)"), 'Trailing-slash canonicalization does not target the no-slash route.');
        phase2Assert(str_contains($route, "'Cache-Control: no-store'"), 'Protected Evidence Hub canonical redirect is cacheable.');
        phase2Assert(str_contains($route, "=== 'HEAD'") && str_contains($route, 'exit;'), 'HEAD handling does not terminate before rendering a response body.');
        phase2Assert(!preg_match('/\$_(?:GET|POST|REQUEST|COOKIE)\b/', $route), 'Protected Evidence Hub route accepts request input.');
        phase2Assert(!str_contains($route, 'target_ref') && !str_contains($route, 'recommendation_key') && !str_contains($route, 'evidence_fingerprint'), 'Protected Evidence Hub route accepts client recommendation authority.');
        phase2AssertSame("<?php\n\nrequire dirname(__DIR__) . '/app/owner_evidence_hub.php';\n", str_replace("\r\n", "\n", self::read('public/owner_evidence_hub.php')), 'Production wrapper diverged from the root handler convention.');
        foreach ([$productionVhost, $developmentVhost] as $vhost) {
            phase2Assert(str_contains($vhost, '^/owner/evidence-hub/?$') && str_contains($vhost, '/owner_evidence_hub.php'), 'Apache route rewrite is missing or accepts a different path.');
        }
    }

    private static function ownerGuardsAndSafeConfigurationFailure(): void
    {
        $route = self::read('owner_evidence_hub.php');
        phase2Assert(str_contains($route, 'catch (EvidenceHubHmacConfigurationException $exception)'), 'Missing or invalid HMAC configuration does not fail closed at the route boundary.');
        phase2Assert(str_contains($route, 'Evidence Hub is temporarily unavailable.'), 'HMAC configuration failure does not have a generic safe response.');
        phase2Assert(!str_contains($route, 'EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY'), 'Route source unnecessarily exposes the HMAC variable name.');
        phase2Assert(str_contains($route, 'reportApplicationError($exception, \'owner_evidence_hub.php\', \'owner_evidence_hub_configuration\')'), 'HMAC configuration failure does not use the existing observability boundary.');
    }

    /** @param array<string, mixed> $fixtures */
    private static function contractOnlyPresentation(array $fixtures): void
    {
        $golden = self::json('tests/phase2/fixtures/evidence-hub-golden-fixtures.json');
        $zero = self::payload($golden, 'PAYLOAD-ZERO');
        $partial = self::payload($golden, 'PAYLOAD-PARTIAL');
        $ready = self::payload($golden, 'PAYLOAD-READY');
        $mappedDisplay = $fixtures['escaped_mapped_display'];
        $unmappedRawLabel = $fixtures['unmapped_raw_label'];
        phase2Assert(is_string($mappedDisplay) && is_string($unmappedRawLabel), 'Owner page presentation fixture is invalid.');

        $empty = self::render(static function () use ($zero): void { renderEvidenceHubOwnerPresentation($zero); });
        phase2Assert(str_contains($empty, 'No projects yet') && str_contains($empty, 'No current actions'), 'Empty or no-action state is not rendered from the frozen contract.');

        $partialHtml = self::render(static function () use ($partial): void { renderEvidenceHubOwnerPresentation($partial); });
        phase2Assert(str_contains($partialHtml, 'Documentation coverage') && str_contains($partialHtml, 'Portfolio progress'), 'Partial contract metrics are not rendered.');

        $ready['metrics']['technology_evidence_map']['mappings'][0]['display_name'] = $mappedDisplay;
        $ready['metrics']['technology_evidence_map']['mappings'][] = [
            'raw_label' => $unmappedRawLabel,
            'mapping_state' => 'unmapped',
            'taxonomy_version' => 'v1',
            'canonical_id' => null,
            'canonical_key' => null,
            'display_name' => null,
            'category' => null,
        ];
        $ready['metrics']['technology_evidence_map']['status'] = 'needs_attention';
        $ready['metrics']['technology_evidence_map']['reason_codes'] = ['TECHNOLOGY_MAPPED', 'TECHNOLOGY_UNMAPPED'];
        $ready['metrics']['technology_evidence_map']['technology_occurrence_count'] = 2;
        $ready['metrics']['technology_evidence_map']['mapped_occurrence_count'] = 1;
        $ready['metrics']['technology_evidence_map']['unmapped_occurrence_count'] = 1;
        $ready['metrics']['technology_evidence_map']['distinct_technology_count'] = 2;
        $ready['metrics']['technology_evidence_map']['distinct_mapped_technology_count'] = 1;
        $ready['metrics']['technology_evidence_map']['distinct_unmapped_technology_count'] = 1;
        $rendered = self::render(static function () use ($ready): void { renderEvidenceHubOwnerPresentation($ready); });
        phase2Assert(str_contains($rendered, '&lt;script&gt;synthetic-display&lt;/script&gt;'), 'Mapped technology display text is not escaped.');
        phase2Assert(!str_contains($rendered, $unmappedRawLabel), 'Unmapped raw technology label was disclosed.');
        phase2Assert(str_contains($rendered, 'Unmapped technology needs review'), 'Unmapped technology has no generic safe wording.');

        $recommendations = buildEvidenceHubRecommendations([
            'tenant_scope_ref' => 'route_presenter_tenant',
            'portfolio_target_identity' => 'route_presenter_portfolio',
            'projects' => [],
            'technology_mappings' => [],
            'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
        ], [], 1767225600, 'route-presenter-synthetic-hmac');
        $withRecommendation = $zero;
        $withRecommendation['recommendations'] = $recommendations;
        $recommendationHtml = self::render(static function () use ($withRecommendation): void { renderEvidenceHubOwnerPresentation($withRecommendation); });
        phase2Assert(str_contains($recommendationHtml, 'href="/owner_projects.php?add=1"'), 'Project recommendation does not use the generic authorized management screen.');
        phase2Assert(!str_contains($recommendationHtml, $recommendations[0]['recommendation_key']) && !str_contains($recommendationHtml, $recommendations[0]['evidence_fingerprint']) && !str_contains($recommendationHtml, $recommendations[0]['target']['opaque_target_ref']), 'Recommendation internals leaked into the presentation.');

        $error = self::render(static function (): void { renderEvidenceHubOwnerSafeError(); });
        phase2Assert(str_contains($error, 'Evidence Hub is temporarily unavailable.'), 'Safe error state is unavailable.');
        foreach ($fixtures['forbidden_markers'] as $forbidden) {
            phase2Assert(!str_contains($rendered . $recommendationHtml . $error, $forbidden), "Owner presentation disclosed forbidden {$forbidden} data.");
        }
    }

    private static function navigationAndAccessibilityContracts(): void
    {
        $layout = self::read('includes/owner_layout.php');
        $presentation = self::read('includes/evidence_hub_owner_presentation.php');
        foreach (["'evidence_hub' => ['/owner/evidence-hub', 'Evidence Hub']", 'aria-current="page"', 'href="#main-content"'] as $required) {
            phase2Assert(str_contains($layout, $required), "Owner navigation accessibility is missing {$required}.");
        }
        foreach (['<section aria-labelledby="evidence-hub-summary-title">', '<section aria-labelledby="evidence-hub-technology-title">', '<section aria-labelledby="evidence-hub-recommendations-title">', 'aria-label="Evidence Hub recommendation"', 'ownerEscapeHtml'] as $required) {
            phase2Assert(str_contains($presentation, $required), "Evidence Hub presenter accessibility or escaping is missing {$required}.");
        }
    }

    private static function browserSupportContract(): void
    {
        foreach (['tests/phase2/support/evidence-hub-owner-visual.php', 'tests/phase2/support/evidence-hub-owner-visual.cjs'] as $path) {
            phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/' . $path), "Evidence Hub browser acceptance support {$path} is missing.");
        }
    }

    /** @param callable():void $render */
    private static function render(callable $render): string
    {
        ob_start();
        $render();
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $fixtures @return array<string, mixed> */
    private static function payload(array $fixtures, string $id): array
    {
        foreach ($fixtures['positive_payloads'] as $case) {
            if (($case['id'] ?? null) === $id && is_array($case['payload'] ?? null)) {
                return $case['payload'];
            }
        }
        throw new RuntimeException("Golden payload {$id} is unavailable.");
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        $contents = self::read($path);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("Fixture {$path} is invalid.");
        }
        return $decoded;
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");
        return $contents;
    }
}
