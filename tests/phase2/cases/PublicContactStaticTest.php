<?php

declare(strict_types=1);

final class PublicContactStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $contact = self::read('includes/public_contact.php');
        $route = self::read('public_contact.php');
        $portfolio = self::read('public_portfolio.php');
        $vhost = self::read('docker/apache/production-vhost.conf');

        phase2Assert(str_contains($contact, 'preparePublicContactSubmission') && str_contains($contact, 'resolvePublicReadContext($database, $slug)'), 'P2J-06 must resolve the public slug for every contact submission.');
        phase2Assert(str_contains($contact, 'createPublicContactMessage(PDO $database, PublicReadContext $context') && str_contains($contact, "'recipient_portfolio_id' => \$context->portfolioId"), 'P2J-06 recipient authority must come from PublicReadContext only.');
        phase2Assert(!str_contains($contact, "['recipient_portfolio_id']") && !str_contains($contact, "['portfolio_id']") && !str_contains($contact, "['user_id']"), 'P2J-06 must not accept client recipient identifiers.');
        phase2Assert(str_contains($route, '$_SERVER[\'REQUEST_METHOD\'] !== \'POST\'') && str_contains($route, 'preparePublicContactSubmission') && str_contains($route, 'consumeRateLimit') && str_contains($route, 'true, 303'), 'P2J-06 contact route must be POST-only, re-resolve, rate-limit, and PRG.');
        phase2Assert(!str_contains($route, 'requireOwnerPortfolioContext') && !str_contains($route, '$_SESSION') && !str_contains($route, 'recipient_portfolio_id') && !str_contains($route, 'portfolio_id') && !str_contains($route, 'user_id'), 'P2J-06 public contact route must not use owner or client recipient authority.');
        phase2Assert(str_contains($portfolio, 'action="/p/<?php echo rawurlencode($slug); ?>/contact"') && !str_contains($portfolio, 'name="recipient_portfolio_id"') && !str_contains($portfolio, 'name="portfolio_id"') && !str_contains($portfolio, 'name="user_id"'), 'P2J-06 public form must post only to the slug contact route without recipient identifiers.');
        phase2Assert(str_contains($vhost, '/contact/?$ /p_contact.php?slug=$1'), 'P2J-06 contact route is not wired through the production public vhost.');
    }

    private static function read(string $relativePath): string
    {
        $contents = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $relativePath);
        phase2Assert(is_string($contents), "{$relativePath} is unreadable.");

        return $contents;
    }
}
