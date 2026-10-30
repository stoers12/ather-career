<?php

declare(strict_types=1);

// The single Phase 2 test registry: scripts/run-phase2-tests.php and the
// PHPUnit adapter (tests/Unit/LegacyPhase2CaseTest.php) both run exactly this list.

require_once __DIR__ . '/cases/EnvironmentSafetyTest.php';
require_once __DIR__ . '/cases/FixtureContractTest.php';
require_once __DIR__ . '/cases/IdentitySessionContractTest.php';
require_once __DIR__ . '/cases/OwnershipMigrationStaticTest.php';
require_once __DIR__ . '/cases/OwnerFlowStaticTest.php';
require_once __DIR__ . '/cases/PublicLifecycleStaticTest.php';
require_once __DIR__ . '/cases/PublicUrlConfigurationTest.php';
require_once __DIR__ . '/cases/PublicContactStaticTest.php';
require_once __DIR__ . '/cases/PortfolioPresentationStaticTest.php';
require_once __DIR__ . '/cases/HeroPersonalInfoCapabilityTest.php';
require_once __DIR__ . '/cases/ProfileContactTest.php';
require_once __DIR__ . '/cases/ProjectTechnologiesStaticTest.php';
require_once __DIR__ . '/cases/ExperienceCapabilityTest.php';
require_once __DIR__ . '/cases/ExperiencePresentationStaticTest.php';
require_once __DIR__ . '/cases/FooterPresentationStaticTest.php';
require_once __DIR__ . '/cases/PrivateMediaStaticTest.php';
require_once __DIR__ . '/cases/MediaNormalizationTest.php';
require_once __DIR__ . '/cases/LibvipsImageProcessorTest.php';
require_once __DIR__ . '/cases/OperationalSecurityStaticTest.php';
require_once __DIR__ . '/cases/RuntimeObservabilityStaticTest.php';
require_once __DIR__ . '/cases/EdgeSecurityStaticTest.php';
require_once __DIR__ . '/cases/EdgeSecurityDiscoveryTest.php';
require_once __DIR__ . '/cases/OperationalRecoveryStaticTest.php';
require_once __DIR__ . '/cases/Auth0OidcStaticTest.php';
require_once __DIR__ . '/cases/Auth0CallbackBoundaryTest.php';
require_once __DIR__ . '/cases/AccountSelectionStaticTest.php';
require_once __DIR__ . '/cases/OwnerWorkspacePresentationTest.php';
require_once __DIR__ . '/cases/LegacyBackendRemovalStaticTest.php';
require_once __DIR__ . '/cases/HttpValidationContractTest.php';
require_once __DIR__ . '/cases/PublicProjectJsonContractTest.php';
require_once __DIR__ . '/cases/OwnerTransactionIntegrityStaticTest.php';
require_once __DIR__ . '/cases/OwnerFrontendExperienceStaticTest.php';
require_once __DIR__ . '/cases/ResponsiveAccessibilityStaticTest.php';
require_once __DIR__ . '/cases/FrontendPerformanceResilienceStaticTest.php';
require_once __DIR__ . '/cases/StaticArchitectureGuardTest.php';
require_once __DIR__ . '/cases/TenantAuthorizationStaticTest.php';
require_once __DIR__ . '/cases/EvidenceHubContractTest.php';
require_once __DIR__ . '/cases/EvidenceTextEvaluationTest.php';
require_once __DIR__ . '/cases/EvidenceHubStorageTest.php';
require_once __DIR__ . '/cases/EvidenceHubAggregationTest.php';
require_once __DIR__ . '/cases/EvidenceHubRecommendationDispositionTest.php';
require_once __DIR__ . '/cases/EvidenceHubContractMapperTest.php';
require_once __DIR__ . '/cases/EvidenceHubConfigurationTest.php';
require_once __DIR__ . '/cases/EvidenceHubOwnerRecommendationOrchestrationTest.php';
require_once __DIR__ . '/cases/EvidenceHubOwnerRoutePresenterTest.php';
require_once __DIR__ . '/cases/EvidenceHubOwnerPageModelTest.php';
require_once __DIR__ . '/cases/EvidenceHubRecommendationActionTest.php';
require_once __DIR__ . '/cases/EvidenceHubRequiredGatesStaticTest.php';
require_once __DIR__ . '/cases/EvidenceHubUndoTest.php';
require_once __DIR__ . '/cases/EvidenceHubLocalizationTest.php';
require_once __DIR__ . '/cases/EvidenceWritingGuidanceTest.php';
require_once __DIR__ . '/cases/EvidenceHubTechnologyReviewTest.php';
require_once __DIR__ . '/cases/EvidenceHubContractV7Test.php';
require_once __DIR__ . '/cases/EvidenceHubSkillEvidenceTest.php';
require_once __DIR__ . '/cases/EvidenceHubContractV8Test.php';
require_once __DIR__ . '/cases/EvidenceTextRules110Test.php';
require_once __DIR__ . '/cases/EvidenceProjectTextRules110Test.php';
require_once __DIR__ . '/cases/EvidenceHubContractV9Test.php';
require_once __DIR__ . '/cases/EvidenceHubContractV10Test.php';
require_once __DIR__ . '/cases/EvidenceHubViewsTest.php';
require_once __DIR__ . '/cases/EvidenceTextOwnerHintsTest.php';
require_once __DIR__ . '/cases/SourceAutoloadLayoutTest.php';

/** @var array<string, array{class-string, string}> */
return [
    'shared Owner workspace locale, layout and account-form contract' => [OwnerWorkspacePresentationTest::class, 'run'],
    'provider-independent internal-user session contract' => [IdentitySessionContractTest::class, 'run'],
    'ownership migration static contract' => [OwnershipMigrationStaticTest::class, 'run'],
    'owner flow static contract' => [OwnerFlowStaticTest::class, 'run'],
    'public lifecycle static contract' => [PublicLifecycleStaticTest::class, 'run'],
    'public URL configuration and publication presentation contract' => [PublicUrlConfigurationTest::class, 'run'],
    'public contact static contract' => [PublicContactStaticTest::class, 'run'],
    'shared Portfolio presentation contract' => [PortfolioPresentationStaticTest::class, 'run'],
    'Hero Personal Info capability contract' => [HeroPersonalInfoCapabilityTest::class, 'run'],
    'Profile contact visibility and rendering' => [ProfileContactTest::class, 'run'],
    'project technologies static contract' => [ProjectTechnologiesStaticTest::class, 'run'],
    'Experience capability static contract' => [ExperienceCapabilityTest::class, 'run'],
    'Experience presentation static contract' => [ExperiencePresentationStaticTest::class, 'run'],
    'Footer presentation static contract' => [FooterPresentationStaticTest::class, 'run'],
    'private media static contract' => [PrivateMediaStaticTest::class, 'run'],
    'media dimension normalization contract' => [MediaNormalizationTest::class, 'run'],
    'bounded libvips image processor contract' => [LibvipsImageProcessorTest::class, 'run'],
    'operational security static contract' => [OperationalSecurityStaticTest::class, 'run'],
    'runtime health and observability static contract' => [RuntimeObservabilityStaticTest::class, 'run'],
    'edge security and abuse-protection static contract' => [EdgeSecurityStaticTest::class, 'run'],
    'edge security PHP discovery regression' => [EdgeSecurityDiscoveryTest::class, 'run'],
    'operational recovery and closure static contract' => [OperationalRecoveryStaticTest::class, 'run'],
    'Auth0 OIDC static contract' => [Auth0OidcStaticTest::class, 'run'],
    'Auth0 callback boundary characterization' => [Auth0CallbackBoundaryTest::class, 'run'],
    'Phase 2B account selection security and accessibility contract' => [AccountSelectionStaticTest::class, 'run'],
    'legacy backend removal static contract' => [LegacyBackendRemovalStaticTest::class, 'run'],
    'HTTP response and validation contract' => [HttpValidationContractTest::class, 'run'],
    'public Project JSON allow-list and media contract' => [PublicProjectJsonContractTest::class, 'run'],
    'owner transaction and media-integrity static contract' => [OwnerTransactionIntegrityStaticTest::class, 'run'],
    'Owner frontend experience static contract' => [OwnerFrontendExperienceStaticTest::class, 'run'],
    'responsive and accessibility static contract' => [ResponsiveAccessibilityStaticTest::class, 'run'],
    'frontend performance and resilience static contract' => [FrontendPerformanceResilienceStaticTest::class, 'run'],
    'environment safety and disposable namespace' => [EnvironmentSafetyTest::class, 'run'],
    'synthetic fixture contract and test authentication carrier' => [FixtureContractTest::class, 'run'],
    'static architecture guards' => [StaticArchitectureGuardTest::class, 'run'],
    'tenant authorization static contract' => [TenantAuthorizationStaticTest::class, 'run'],
    'Evidence Hub contracts, taxonomy, fixtures, and tenant isolation' => [EvidenceHubContractTest::class, 'run'],
    'Evidence Hub deterministic Unicode text evaluation' => [EvidenceTextEvaluationTest::class, 'run'],
    'Evidence Hub private storage and migration contract' => [EvidenceHubStorageTest::class, 'run'],
    'Evidence Hub Owner aggregation and technology mapping core' => [EvidenceHubAggregationTest::class, 'run'],
    'Evidence Hub tenant-scoped recommendation disposition repository' => [EvidenceHubRecommendationDispositionTest::class, 'run'],
    'Evidence Hub frozen contract mapper' => [EvidenceHubContractMapperTest::class, 'run'],
    'Evidence Hub stable HMAC configuration boundary' => [EvidenceHubConfigurationTest::class, 'run'],
    'Evidence Hub Owner recommendation orchestration and full candidate resolution' => [EvidenceHubOwnerRecommendationOrchestrationTest::class, 'run'],
    'Evidence Hub protected Owner route and read-only presenter' => [EvidenceHubOwnerRoutePresenterTest::class, 'run'],
    'Evidence Hub private page model, progress, and evidence persistence' => [EvidenceHubOwnerPageModelTest::class, 'run'],
    'Evidence Hub protected recommendation disposition actions' => [EvidenceHubRecommendationActionTest::class, 'run'],
    'Evidence Hub required finalization-gate registration' => [EvidenceHubRequiredGatesStaticTest::class, 'run'],
    'Evidence Hub immediate one-level Undo' => [EvidenceHubUndoTest::class, 'run'],
    'Evidence Hub Owner localization and English fail-safe' => [EvidenceHubLocalizationTest::class, 'run'],
    'Evidence editor writing guidance, starters, and non-blocking hints' => [EvidenceWritingGuidanceTest::class, 'run'],
    'Evidence Hub Owner technology review, contract v5, and scoped actions' => [EvidenceHubTechnologyReviewTest::class, 'run'],
    'Evidence Hub contract v7 evidence details and authorized publication' => [EvidenceHubContractV7Test::class, 'run'],
    'Evidence Hub canonical skill evidence recommendation' => [EvidenceHubSkillEvidenceTest::class, 'run'],
    'Evidence Hub contract v8 skill evidence isolation' => [EvidenceHubContractV8Test::class, 'run'],
    'Evidence text rules 1.1.0 calibration and frozen baseline' => [EvidenceTextRules110Test::class, 'run'],
    'Evidence project cross-field text rules 1.1.0' => [EvidenceProjectTextRules110Test::class, 'run'],
    'Evidence Hub contract v9 and explicit text rule versions' => [EvidenceHubContractV9Test::class, 'run'],
    'Evidence Hub contract v10 evidence sources' => [EvidenceHubContractV10Test::class, 'run'],
    'Evidence Hub view modes and skill gaps' => [EvidenceHubViewsTest::class, 'run'],
    'Evidence Hub text rules localized Owner hints' => [EvidenceTextOwnerHintsTest::class, 'run'],
    'src/ PSR-4 layout and one-line compatibility wrappers' => [SourceAutoloadLayoutTest::class, 'run'],
];
