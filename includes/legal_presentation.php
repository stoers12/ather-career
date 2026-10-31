<?php

declare(strict_types=1);

require_once __DIR__ . '/legal_documents.php';
require_once __DIR__ . '/presentation.php';

function legalEscape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function legalText(string $key, string $locale): string
{
    static $copy = [
        'privacy' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
        'terms' => ['ar' => 'شروط الاستخدام', 'en' => 'Terms of Use'],
        'documents' => ['ar' => 'المستندات القانونية', 'en' => 'Legal documents'],
        'contents' => ['ar' => 'في هذه الصفحة', 'en' => 'On this page'],
        'home' => ['ar' => 'أثر', 'en' => 'Ather'],
        'signin' => ['ar' => 'تسجيل الدخول', 'en' => 'Sign in'],
        'skip' => ['ar' => 'انتقل إلى المستند', 'en' => 'Skip to the document'],
        'agree' => ['ar' => 'أوافق على', 'en' => 'I agree to the'],
        'and' => ['ar' => 'و', 'en' => 'and'],
        'required' => ['ar' => 'يرجى الموافقة على شروط الاستخدام وسياسة الخصوصية للمتابعة.', 'en' => 'Please agree to the Terms of Use and Privacy Policy to continue.'],
    ];

    return legalEscape($copy[$key][$locale === 'en' ? 'en' : 'ar']);
}

function legalDocumentUrl(string $document, string $locale): string
{
    if (!isset(LEGAL_DOCUMENT_VERSIONS[$document])) { throw new InvalidArgumentException('Unknown legal document.'); }

    return '/' . $document . '?lang=' . ($locale === 'en' ? 'en' : 'ar');
}

function renderLegalLinks(string $locale, string $context): void
{
    if (!legalPagesPublished()) { return; }
    ?>
<nav class="legal-links legal-links--<?php echo legalEscape($context); ?>" aria-label="<?php echo legalText('documents', $locale); ?>">
    <a href="<?php echo legalEscape(legalDocumentUrl('privacy', $locale)); ?>"><?php echo legalText('privacy', $locale); ?></a>
    <a href="<?php echo legalEscape(legalDocumentUrl('terms', $locale)); ?>"><?php echo legalText('terms', $locale); ?></a>
</nav>
    <?php
}

function renderRegistrationLegalConsent(string $locale, bool $invalid): void
{
    if (!legalPagesPublished()) { return; }
    ?>
<div class="legal-consent">
    <label class="legal-consent-label" for="accept-legal">
        <input id="accept-legal" type="checkbox" name="accept_legal" value="1" required<?php echo $invalid ? ' aria-invalid="true" aria-describedby="legal-error"' : ''; ?>>
        <span><?php echo legalText('agree', $locale); ?> <a href="<?php echo legalEscape(legalDocumentUrl('terms', $locale)); ?>"><?php echo legalText('terms', $locale); ?></a> <?php echo legalText('and', $locale); ?> <a href="<?php echo legalEscape(legalDocumentUrl('privacy', $locale)); ?>"><?php echo legalText('privacy', $locale); ?></a></span>
    </label>
    <?php if ($invalid): ?>
    <p id="legal-error" class="legal-consent-error" role="alert"><?php echo legalText('required', $locale); ?></p>
    <?php endif; ?>
</div>
    <?php
}

/** @return array{title: string, metadata: string, sections: list<array{heading: string, body: string}>} */
function legalDocumentContent(string $document, string $locale): array
{
    legalDocumentUrl($document, $locale);
    $source = file_get_contents(__DIR__ . '/../resources/legal/' . $document . '.' . ($locale === 'en' ? 'en' : 'ar') . '.md');
    if ($source === false) { throw new RuntimeException('Legal document source unavailable.'); }
    $parts = preg_split('/^### /m', trim($source));
    $intro = explode("\n", trim(array_shift($parts)));
    $sections = [];
    foreach ($parts as $part) {
        [$heading, $body] = explode("\n", trim($part), 2);
        if (legalPrivacyContactEmail() === null) {
            $body = implode("\n", array_filter(explode("\n", $body), static fn (string $line): bool => !str_contains($line, '{{contact}}')));
        }
        $sections[] = ['heading' => $heading, 'body' => $body];
    }

    return ['title' => trim($intro[0], '*'), 'metadata' => $intro[1], 'sections' => $sections];
}

/** Only the approved copy's bold text and local document links become markup. */
function legalInline(string $text, string $document, string $locale): string
{
    $safe = legalEscape($text);
    $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe);
    $safe = preg_replace_callback('/\[([^\]]+)\]\(\/(privacy|terms)\)/u', static fn (array $match): string => '<a href="' . legalEscape(legalDocumentUrl($match[2], $locale)) . '">' . $match[1] . '</a>', $safe);
    $safe = str_replace('{{version}}', legalEscape(LEGAL_DOCUMENT_VERSIONS[$document]), $safe);
    $date = legalEscape((string) LEGAL_DOCUMENT_EFFECTIVE_DATE);
    $safe = str_replace('{{effective_date}}', '<time datetime="' . $date . '">' . $date . '</time>', $safe);
    $contact = legalPrivacyContactEmail();
    if ($contact !== null) {
        $safe = str_replace('{{contact}}', '<a href="mailto:' . legalEscape(rawurlencode($contact)) . '"><bdi dir="ltr">' . legalEscape($contact) . '</bdi></a>', $safe);
    }

    return $safe;
}

/** Render the limited Markdown used by the approved documents, escaping every text node. */
function renderLegalBlocks(string $body, string $document, string $locale): void
{
    $lines = explode("\n", $body);
    $listDepth = 0;
    $table = false;
    foreach ($lines as $line) {
        if (preg_match('/^( *)(- )(.+)$/u', $line, $match) === 1) {
            $depth = strlen($match[1]) >= 2 ? 2 : 1;
            if ($table) { echo '</tbody></table>'; $table = false; }
            if ($depth > $listDepth) { echo '<ul>'; }
            elseif ($depth === $listDepth) { echo '</li>'; }
            else { echo '</li></ul></li>'; }
            echo '<li>' . legalInline($match[3], $document, $locale);
            $listDepth = $depth;
            continue;
        }
        while ($listDepth > 0) { echo '</li></ul>'; --$listDepth; }
        if (str_starts_with($line, '|')) {
            if (preg_match('/^\|[-| ]+\|$/', $line) === 1) { continue; }
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (!$table) { echo '<table><thead><tr>'; $tag = 'th'; }
            else { echo '<tr>'; $tag = 'td'; }
            foreach ($cells as $cell) { echo '<' . $tag . ($tag === 'th' ? ' scope="col"' : '') . '>' . legalInline($cell, $document, $locale) . '</' . $tag . '>'; }
            echo '</tr>' . (!$table ? '</thead><tbody>' : '');
            $table = true;
            continue;
        }
        if ($table) { echo '</tbody></table>'; $table = false; }
        if (trim($line) !== '') { echo '<p>' . legalInline($line, $document, $locale) . '</p>'; }
    }
    while ($listDepth > 0) { echo '</li></ul>'; --$listDepth; }
    if ($table) { echo '</tbody></table>'; }
}

function renderLegalDocumentPage(string $document, string $locale): void
{
    $content = legalDocumentContent($document, $locale);
    ?>
<!DOCTYPE html>
<html lang="<?php echo legalEscape($locale); ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="index,follow">
    <title><?php echo legalEscape($content['title']); ?> | Ather</title>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/portfolio.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('assets/css/legal.css'); ?>">
</head>
<body class="portfolio-page legal-page">
<a class="portfolio-skip-link" href="#legal-document"><?php echo legalText('skip', $locale); ?></a>
<header class="legal-header">
    <a class="legal-brand" href="/?lang=<?php echo legalEscape($locale); ?>"><?php echo legalText('home', $locale); ?></a>
    <nav aria-label="<?php echo legalText('documents', $locale); ?>">
        <a href="/signin.php?lang=<?php echo legalEscape($locale); ?>"><?php echo legalText('signin', $locale); ?></a>
        <a href="<?php echo legalEscape(legalDocumentUrl($document, $locale === 'ar' ? 'en' : 'ar')); ?>" lang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" dir="auto"><?php echo $locale === 'ar' ? 'English' : 'العربية'; ?></a>
    </nav>
</header>
<main class="legal-layout">
    <nav class="legal-contents" aria-labelledby="legal-contents-title">
        <h2 id="legal-contents-title"><?php echo legalText('contents', $locale); ?></h2>
        <ol><?php foreach ($content['sections'] as $index => $section): ?><li><a href="#section-<?php echo $index + 1; ?>"><?php echo legalEscape($section['heading']); ?></a></li><?php endforeach; ?></ol>
    </nav>
    <article id="legal-document" class="legal-document" tabindex="-1" aria-labelledby="legal-title">
        <header><h1 id="legal-title"><?php echo legalEscape($content['title']); ?></h1><p class="legal-metadata"><?php echo legalInline($content['metadata'], $document, $locale); ?></p></header>
        <?php foreach ($content['sections'] as $index => $section): ?>
        <section id="section-<?php echo $index + 1; ?>" aria-labelledby="section-heading-<?php echo $index + 1; ?>">
            <h2 id="section-heading-<?php echo $index + 1; ?>"><?php echo legalEscape($section['heading']); ?></h2>
            <?php renderLegalBlocks($section['body'], $document, $locale); ?>
        </section>
        <?php endforeach; ?>
        <?php renderLegalLinks($locale, 'document'); ?>
    </article>
</main>
</body>
</html>
    <?php
}
