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
        self::productionPresentationSemantics();
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

        foreach (['startOwnerSession();', "httpRegisterExceptionBoundary('owner_evidence_hub.php')", "httpRequireMethod(['GET', 'HEAD', 'POST'])", 'requireOwnerPortfolioContext($database)', 'buildConfiguredAuthorizedEvidenceHubOwnerRecommendationState'] as $required) {
            phase2Assert(str_contains($route, $required), "Protected Evidence Hub route is missing {$required}.");
        }
        phase2Assert(str_contains($route, "httpRedirect('{$canonical}', 302)"), 'Trailing-slash canonicalization does not target the no-slash route.');
        phase2Assert(str_contains($route, "'Cache-Control: no-store'"), 'Protected Evidence Hub canonical redirect is cacheable.');
        phase2Assert(str_contains($route, "=== 'HEAD'") && str_contains($route, 'exit;'), 'HEAD handling does not terminate before rendering a response body.');
        phase2Assert(!preg_match('/\$_(?:GET|REQUEST|COOKIE)\b/', $route), 'Protected Evidence Hub route accepts request input outside its protected action boundary.');
        phase2Assert(!str_contains($route, "\$_POST['recommendation_key']") && !str_contains($route, "\$_POST['rule_version']") && !str_contains($route, "\$_POST['evidence_fingerprint']") && !str_contains($route, "\$_POST['target_ref']"), 'Protected Evidence Hub route accepts client recommendation authority.');
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
        phase2Assert(str_contains($empty, 'لا توجد مشاريع بعد') && str_contains($empty, 'لا توجد توصيات حالية'), 'Empty or no-action state is not rendered from the frozen contract.');

        $partialHtml = self::render(static function () use ($partial): void { renderEvidenceHubOwnerPresentation($partial); });
        phase2Assert(str_contains($partialHtml, 'تغطية التوثيق') && str_contains($partialHtml, 'تقدم ملف الأعمال'), 'Partial contract metrics are not rendered.');

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
        phase2Assert(str_contains($rendered, 'تقنية تحتاج مراجعة'), 'Unmapped technology has no generic safe wording.');

        $recommendations = buildEvidenceHubRecommendations([
            'tenant_scope_ref' => 'route_presenter_tenant',
            'portfolio_target_identity' => 'route_presenter_portfolio',
            'projects' => [],
            'technology_mappings' => [],
            'portfolio_publication' => ['portfolio_published' => false, 'publication_prerequisites_met' => true],
        ], [], 1767225600, 'route-presenter-synthetic-hmac');
        $withRecommendation = $zero;
        $withRecommendation['recommendations'] = $recommendations;
        $recommendationHtml = self::render(static function () use ($withRecommendation): void {
            renderEvidenceHubOwnerPresentation($withRecommendation, [['snooze' => 'synthetic-snooze-token', 'dismiss' => 'synthetic-dismiss-token']]);
        });
        phase2Assert(str_contains($recommendationHtml, 'href="/owner_projects.php?add=1"'), 'Project recommendation does not use the generic authorized management screen.');
        phase2Assert(str_contains($recommendationHtml, 'name="csrf_token"') && str_contains($recommendationHtml, 'name="action_token" value="synthetic-snooze-token"') && str_contains($recommendationHtml, 'name="action_token" value="synthetic-dismiss-token"'), 'Secure recommendation forms are not retained by the presentation.');
        foreach (['evidence-hub-recommendation-topline', 'evidence-hub-recommendation-category', 'evidence-hub-recommendation-footer', 'evidence-hub-recommendation-icon', '<svg class="evidence-hub-icon"'] as $required) {
            phase2Assert(str_contains($recommendationHtml, $required), "Recommendation hierarchy is missing {$required}.");
        }
        phase2Assert(!str_contains($recommendationHtml, $recommendations[0]['recommendation_key']) && !str_contains($recommendationHtml, $recommendations[0]['evidence_fingerprint']) && !str_contains($recommendationHtml, $recommendations[0]['target']['opaque_target_ref']), 'Recommendation internals leaked into the presentation.');

        $error = self::render(static function (): void { renderEvidenceHubOwnerSafeError(); });
        phase2Assert(str_contains($error, 'مركز الأدلة غير متاح مؤقتًا.'), 'Safe error state is unavailable.');
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
        foreach (['aria-labelledby="evidence-hub-status-title"', 'aria-labelledby="evidence-hub-technology-title"', 'aria-labelledby="evidence-hub-recommendations-title"', 'aria-label="توصية مركز الأدلة"', 'ownerEscapeHtml'] as $required) {
            phase2Assert(str_contains($presentation, $required), "Evidence Hub presenter accessibility or escaping is missing {$required}.");
        }
    }

    private static function productionPresentationSemantics(): void
    {
        $layout = self::read('includes/owner_layout.php');
        $presentation = self::read('includes/evidence_hub_owner_presentation.php');
        $javascript = self::read('evidence_hub.js');
        foreach (['dir="rtl"', 'lang="ar"', 'مركز الأدلة', 'إدارة المشاريع', 'evidence-hub-sidebar-toggle', 'evidence-hub-mobile-toggle', 'evidence-hub-mobile-drawer', 'evidence-hub-theme-toggle', 'data-sidebar-tooltip', 'evidence_hub.css', 'evidence_hub.js'] as $required) {
            phase2Assert(str_contains($layout . $presentation, $required), "Production Evidence Hub shell is missing {$required}.");
        }
        foreach (['ather.evidenceHub.sidebarCollapsed', 'ather.evidenceHub.theme', "window.matchMedia('(max-width: 1100px)')", 'setTheme', 'setMobileDrawer'] as $required) {
            phase2Assert(str_contains($javascript, $required), "Evidence Hub visual state contract is missing {$required}.");
        }
        foreach (['evidence-hub-mobile-close', 'إغلاق القائمة', 'evidence-hub-mobile-backdrop', 'aria-controls="evidence-hub-mobile-drawer"'] as $required) {
            phase2Assert(str_contains($layout, $required), "Evidence Hub mobile drawer structure is missing {$required}.");
        }
        foreach (['تحرير الأدلة', '>النشاط<'] as $forbidden) {
            phase2Assert(!str_contains($layout . $presentation, $forbidden), "Production Evidence Hub shell contains forbidden {$forbidden}.");
        }
        foreach (['evidence-hub-status', 'evidence-hub-documentation', 'evidence-hub-technology', 'evidence-hub-progress', 'evidence-hub-recommendations', 'evidence-hub-hero-state', 'evidence-hub-coverage-kpi', 'evidence-hub-recommendation-topline', 'evidence-hub-recommendation-footer', 'dir="ltr"', 'csrf_token', 'action_token', 'name="action" value="snooze"', 'name="action" value="dismiss"', 'evidence-hub-snooze', 'evidence-hub-dismiss'] as $required) {
            phase2Assert(str_contains($presentation, $required), "Production Evidence Hub presentation is missing {$required}.");
        }
        foreach (['recommendation_key', 'evidence_fingerprint', 'opaque_target_ref', 'raw_label', 'canonical_key', 'tenant_scope_ref', 'portfolio_target_identity'] as $forbidden) {
            phase2Assert(!str_contains($presentation, $forbidden), "Production Evidence Hub presentation exposes {$forbidden}.");
        }
        foreach (['evidence_hub.css', 'evidence_hub.js'] as $asset) {
            phase2Assert(is_file(PHASE2_REPOSITORY_ROOT . '/' . $asset), "Production-local Evidence Hub asset {$asset} is missing.");
        }
        $css = self::read('evidence_hub.css');
        phase2Assert(str_contains($css, 'unicode-bidi:isolate'), 'Evidence Hub numeric bidi isolation is missing.');
        foreach (['--eh-canvas', '--eh-primary', '--eh-danger', '256px', '82px', '@media (max-width: 1100px)', 'prefers-reduced-motion', '[data-bidi-number]', '44px', 'data-evidence-hub-theme="dark"', 'data-sidebar-tooltip', 'evidence-hub-mobile-backdrop', 'evidence-hub-mobile-scroll-lock', 'evidence-hub-recommendation-card--documentation', 'evidence-hub-technology-card--review'] as $required) {
            phase2Assert(str_contains($css, $required), "Evidence Hub presentation CSS is missing {$required}.");
        }
        phase2Assert(!str_contains($css . $javascript, '799px'), 'Evidence Hub retains a stale 799px responsive authority.');
        foreach (['setMobileDrawer', 'mobileReturnFocus', 'evidence-hub-mobile-backdrop', 'evidence-hub-mobile-scroll-lock'] as $required) {
            phase2Assert(str_contains($javascript, $required), "Evidence Hub mobile drawer state is missing {$required}.");
        }
        phase2Assert(!preg_match('/https?:\\/\\/|@import\\s+url/i', $css . $javascript), 'Evidence Hub production assets must remain local-only with no CDN.');
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
