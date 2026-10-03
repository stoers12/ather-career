<?php

declare(strict_types=1);

final class PublicUrlConfigurationTest
{
    public static function run(TestEnvironment $environment): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_lifecycle.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/public_url.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/owner_publication_presentation.php';

        self::withEnvironment('https://localhost:8443', 'development', static function (): void {
            $_SERVER['HTTP_HOST'] = 'attacker.example.test';
            phase2AssertSame('https://localhost:8443/p/momen-qasim-al-omari', publicPortfolioUrl('momen-qasim-al-omari'), 'Public URL must use the configured local origin and validated slug.');
            $_SERVER['HTTP_HOST'] = 'another-attacker.example.test';
            phase2AssertSame('https://localhost:8443/p/momen-qasim-al-omari', publicPortfolioUrl('momen-qasim-al-omari'), 'A malicious Host header must not alter the public URL.');
        });
        self::withEnvironment('https://localhost:8443/', 'development', static function (): void {
            phase2AssertSame('https://localhost:8443/p/owner-slug', publicPortfolioUrl('owner-slug'), 'Trailing slashes must normalize centrally.');
        });
        self::withEnvironment('https://portfolio.example.test:9443', 'production', static function (): void {
            phase2AssertSame('https://portfolio.example.test:9443/p/owner-slug', publicPortfolioUrl('owner-slug'), 'An explicitly configured port must be preserved.');
        });
        self::assertConfigurationRejected(null, 'development', 'Missing PUBLIC_BASE_URL must fail closed.');
        self::assertConfigurationRejected('ftp://localhost:8443', 'development', 'Unsupported schemes must be rejected.');
        self::assertConfigurationRejected('http://localhost:8443', 'production', 'Production HTTP must be rejected.');
        self::assertConfigurationRejected('https://owner@example.test', 'development', 'User information must be rejected.');
        self::assertConfigurationRejected('https://example.test?query=value', 'development', 'Query strings must be rejected.');
        self::assertConfigurationRejected('https://example.test#fragment', 'development', 'Fragments must be rejected.');
        self::assertConfigurationRejected('https://example.test/public', 'development', 'Unexpected paths must be rejected.');
        self::assertConfigurationRejected('//example.test', 'development', 'Protocol-relative URLs must be rejected.');
        self::assertConfigurationRejected('http://example.test', 'development', 'HTTP origins must be limited to loopback development hosts.');

        self::withEnvironment('https://localhost:8443', 'development', static function (): void {
            self::assertThrows(static fn (): string => publicPortfolioUrl('bad--slug'), 'Invalid slugs must be rejected by the existing slug contract.');
            self::assertThrows(static fn (): string => publicPortfolioUrl('p'), 'Reserved slugs must remain rejected by the existing slug contract.');
        });

        self::withEnvironment('https://localhost:8443', 'development', static function (): void {
            $_SESSION = [];
            $noSlug = self::render(['public_slug' => null, 'is_published' => 0, 'published_at' => null]);
            phase2Assert(str_contains($noSlug, 'Choose your public address') && !str_contains($noSlug, 'publication-public-url') && !str_contains($noSlug, 'View Portfolio') && !str_contains($noSlug, 'Copy Link'), 'No-slug state must not show a fake public URL or actions.');

            $reservedState = ['public_slug' => 'reserved-owner', 'is_published' => 0, 'published_at' => null];
            $reserved = self::render($reservedState);
            phase2Assert(str_contains($reserved, 'Your public address is reserved') && str_contains($reserved, 'https://localhost:8443/p/reserved-owner') && !str_contains($reserved, 'View Portfolio') && !str_contains($reserved, 'Copy Link') && !str_contains($reserved, 'Your Portfolio is live'), 'Reserved-but-unpublished state must remain readable without misleading live actions.');

            $publishedState = ['public_slug' => 'published-owner', 'is_published' => 1, 'published_at' => '2026-01-01 00:00:00'];
            $published = self::render($publishedState);
            $publishedUrl = 'https://localhost:8443/p/published-owner';
            phase2Assert(str_contains($published, 'Your Portfolio is live') && str_contains($published, 'Changes saved while published become publicly visible immediately.') && str_contains($published, 'href="' . $publishedUrl . '" target="_blank" rel="noopener noreferrer"') && str_contains($published, '<button class="button-secondary publication-copy-link" type="button" hidden data-copy-public-url="publication-public-url"') && str_contains($published, '<code>' . $publishedUrl . '</code>') && str_contains($published, 'Unpublish'), 'Published state must render the exact server-built View and Copy controls with live-editing guidance.');

            $offlineState = ['public_slug' => 'offline-owner', 'is_published' => 0, 'published_at' => '2026-01-01 00:00:00'];
            $offline = self::render($offlineState);
            phase2Assert(str_contains($offline, 'Your public link is offline') && str_contains($offline, 'https://localhost:8443/p/offline-owner') && str_contains($offline, 'Publish Portfolio') && !str_contains($offline, 'View Portfolio') && !str_contains($offline, 'Copy Link') && !str_contains($offline, 'Changes saved while published become publicly visible immediately.'), 'Unpublished permanent-slug state must remain offline without misleading live actions.');
        });

        self::withEnvironment('https://portfolio.example.test:9443', 'development', static function (): void {
            $longSlug = 'a-' . str_repeat('long-', 11) . 'slug';
            $long = self::render(['public_slug' => $longSlug, 'is_published' => 1, 'published_at' => '2026-01-01 00:00:00']);
            phase2Assert(str_contains($long, 'https://portfolio.example.test:9443/p/' . $longSlug), 'Long canonical URLs must remain server-rendered and selectable.');
        });

        $urlSource = self::read('includes/public_url.php');
        $publicationSource = self::read('includes/owner_publication_presentation.php');
        $publicationRoute = self::read('owner_publication.php');
        $script = self::read('admin.js');
        $stylesheet = self::read('admin.css');
        $developmentCompose = self::read('docker-compose.yml');
        $productionCompose = self::read('docker-compose.production.yml');
        $productionGuard = self::read('scripts/check-production-security.php');
        phase2Assert(str_contains($urlSource, 'requirePublicSlug($slug)') && !preg_match('/HTTP_HOST|SERVER_NAME|X-Forwarded|Forwarded|\$_SERVER\s*\[/i', $urlSource), 'The public URL builder must use the shared slug contract and must not trust request headers.');
        phase2Assert(str_contains($publicationRoute, 'ownerPublicationPublicUrl($state)') && str_contains($publicationSource, 'data-copy-public-url="publication-public-url"') && str_contains($publicationSource, 'target="_blank" rel="noopener noreferrer"'), 'Publication presentation must use the central URL builder and safe View/Copy controls.');
        phase2Assert(str_contains($script, 'navigator.clipboard.writeText(publicUrl.textContent.trim())') && str_contains($script, "feedback.textContent = 'Portfolio link copied.'") && str_contains($script, 'Copying the Portfolio link failed. Select and copy the link manually.') && str_contains($script, 'button.hidden = false') && !str_contains($script, 'execCommand'), 'Copy Link must copy the rendered value, announce success/failure, and stay hidden without Clipboard support.');
        phase2Assert(str_contains($stylesheet, '.publication-public-url') && str_contains($stylesheet, 'overflow-wrap:anywhere') && str_contains($stylesheet, 'min-height:44px') && str_contains($stylesheet, '.publication-destructive-action'), 'Publication URL/actions must wrap safely, meet touch-target sizing, and isolate the destructive action.');
        phase2Assert(str_contains($developmentCompose, 'PUBLIC_BASE_URL: ${PUBLIC_BASE_URL:?PUBLIC_BASE_URL must be configured}') && str_contains($developmentCompose, 'APP_ENV: ${APP_ENV:-development}'), 'Owner Compose must explicitly forward PUBLIC_BASE_URL and environment mode.');
        phase2Assert(str_contains($productionCompose, 'PUBLIC_BASE_URL: ${PUBLIC_BASE_URL:?PUBLIC_BASE_URL must be configured}') && str_contains($productionCompose, 'APP_ENV: production') && !str_contains($productionCompose, 'PUBLIC_BASE_URL: https://localhost'), 'Production Compose must require an explicit non-localhost PUBLIC_BASE_URL and set production mode.');
        phase2Assert(str_contains($productionGuard, 'publicBaseUrl();') && str_contains($productionGuard, 'PUBLIC_BASE_URL must be configured as a valid HTTPS origin.'), 'Production startup must fail safely for a missing or invalid public URL configuration.');
    }

    /** @param array{public_slug: string|null, is_published: int, published_at: string|null} $state */
    private static function render(array $state): string
    {
        ob_start();
        renderOwnerPublicationPresentation($state, ownerPublicationPublicUrl($state));
        $rendered = ob_get_clean();
        phase2Assert(is_string($rendered), 'Publication presentation did not render.');

        return $rendered;
    }

    private static function assertConfigurationRejected(?string $baseUrl, string $environment, string $message): void
    {
        self::withEnvironment($baseUrl, $environment, static function () use ($message): void {
            self::assertThrows(static fn (): string => publicPortfolioUrl('owner-slug'), $message);
        });
    }

    private static function assertThrows(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (PublicUrlConfigurationException | PublicLifecycleValidationException) {
            return;
        }
        throw new RuntimeException($message);
    }

    private static function withEnvironment(?string $baseUrl, string $environment, callable $operation): void
    {
        $originalBaseUrl = getenv('PUBLIC_BASE_URL');
        $originalEnvironment = getenv('APP_ENV');
        $originalHost = $_SERVER['HTTP_HOST'] ?? null;
        putenv('PUBLIC_BASE_URL' . ($baseUrl === null ? '' : '=' . $baseUrl));
        putenv('APP_ENV=' . $environment);
        try {
            $operation();
        } finally {
            putenv('PUBLIC_BASE_URL' . (is_string($originalBaseUrl) ? '=' . $originalBaseUrl : ''));
            putenv('APP_ENV' . (is_string($originalEnvironment) ? '=' . $originalEnvironment : ''));
            if ($originalHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $originalHost;
            }
        }
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
