<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ATHER_ONBOARDING_PREVIEW', true);
$root = dirname(__DIR__);
$checks = 0;
$expected = [
    'ar' => [
        'steps' => ['جاهزية الحساب', 'الهوية العامة', 'الملخص المهني', 'رابط الملف والخصوصية', 'المراجعة والإنشاء'],
        'headings' => ['لنبدأ بحساب آمن وواضح', 'كيف تريد أن يعرفك الناس؟', 'عرّف الناس باتجاهك المهني', 'اختر رابطك وتحكّم بما يظهر', 'هذه بداية أثرك، راجعها مرة أخيرة'],
        'readiness' => 'نبدأ بمراجعة هذه المتطلبات الأساسية قبل إعداد ملفك. لن ننشر أي شيء دون قرارك الصريح.',
        'phone' => 'رقم الهاتف خاص. لا تجمع هذه المعاينة رقم هاتف؛ ستُطبّق متطلباته وإعداداته وفق السياسة المعتمدة عند الربط الفعلي.',
        'email' => 'هذه حالة توضيحية، ولا تثبت تأكيد بريدك أو جاهزية حسابك.',
        'terms' => 'نص الشروط ورابطها ونسختها غير متاحة بعد؛ هذا الاختيار توضيحي فقط.',
        'privacy' => 'نص الإشعار ورابطه ونسخته غير متاحة بعد. لن تصبح بياناتك عامة تلقائيًا.',
        'consent' => 'تبديل هذه المربعات لا يسجل موافقة أو إقرارًا ولا يحفظ أي بيانات.',
        'identity' => 'الهوية العامة اسم للخطوة فقط؛ ملفك لا يُنشر من هذه المعاينة.',
        'footer' => 'معاينة تصميم فقط؛ لا حفظ ولا موافقات ولا إنشاء أو نشر للملف.',
        'create' => 'الإنشاء غير متاح في هذه المعاينة؛ لم تُعتمد متطلبات الربط الفعلي بعد.',
        'slug' => 'رابط توضيحي فقط؛ لا نفحص التوفر أو نحجزه، وقواعد الروابط النهائية لم تُعتمد.',
    ],
    'en' => [
        'steps' => ['Account readiness', 'Public identity', 'Professional snapshot', 'Profile link and privacy', 'Review and create'],
        'headings' => ['Start with a clear, secure account', 'How would you like people to know you?', 'Show your professional direction', 'Choose a link and review visibility', 'Review the start of your impact'],
        'readiness' => 'Start by reviewing these basic requirements before setting up your profile. Nothing will be published without your explicit decision.',
        'phone' => 'Your phone number is private. This preview does not collect it; phone requirements and settings will follow the approved policy during functional integration.',
        'email' => 'This is an illustration. It does not establish your email or account readiness.',
        'terms' => 'The Terms text, link and version are not available yet; this choice is illustrative only.',
        'privacy' => 'The Notice text, link and version are not available yet. Your information will not become public automatically.',
        'consent' => 'Toggling these boxes records no consent or acknowledgement and saves no data.',
        'identity' => '“Public identity” names this section; this preview does not publish a profile.',
        'footer' => 'Design preview only. No saving, consent, profile creation or publishing occurs.',
        'create' => 'Creation is unavailable in this preview; functional setup requirements are not approved yet.',
        'slug' => 'Illustrative link only; availability is not checked or reserved, and final link rules are not approved.',
    ],
];
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$render = static function (array $query) use ($root): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = $query;
    ob_start();
    try {
        require $root . '/tests/phase2/support/onboarding-preview.php';
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};

try {
    $_SESSION = ['existing_marker' => 'unchanged'];
    foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
        foreach ([1, 2, 3, 4, 5] as $step) {
            $html = $render(['lang' => $locale, 'step' => (string) $step]);
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            $node = static fn (string $expression): ?DOMNode => $xpath->query($expression)->item(0);
            $copy = $expected[$locale];
            $check(str_contains($html, '<html lang="' . $locale . '" dir="' . $direction . '">'), 'Document language or direction mismatch.');
            $check($xpath->query('//form|//iframe|//input[@type="password"]')->length === 0, 'A preview must not submit or handle credentials.');
            $check($xpath->query('//section[@data-step-panel]')->length === 5, 'Five panels must be implemented.');
            $check($xpath->query('//a[@data-step-jump]')->length === 5, 'All five steps must navigate.');
            foreach ($copy['steps'] as $index => $label) {
                $check($node('(//nav[contains(@class,"ao-progress-steps")]/*)[' . ($index + 1) . ']')?->textContent === $label, 'Step label or locale mismatch.');
                $check($node('//a[@data-step-jump="' . ($index + 1) . '"]')?->attributes?->getNamedItem('href')?->nodeValue === '?lang=' . $locale . '&step=' . ($index + 1), 'Step jump destination mismatch.');
            }
            $check($node('//a[@data-back]')?->attributes?->getNamedItem('href')?->nodeValue === '?lang=' . $locale . '&step=' . max(1, $step - 1), 'Back destination mismatch.');
            $check($node('//a[@data-next]')?->attributes?->getNamedItem('href')?->nodeValue === '?lang=' . $locale . '&step=' . min(5, $step + 1), 'Continue destination mismatch.');
            $check($node('//a[@data-language-switch]')?->attributes?->getNamedItem('href')?->nodeValue === '?lang=' . ($locale === 'ar' ? 'en' : 'ar') . '&step=' . $step, 'Language switch destination mismatch.');
            $check($node('//section[@data-step-panel="' . $step . '"]/h1')?->textContent === $copy['headings'][$step - 1], 'Active heading or locale mismatch.');
            $check($node('//section[@data-step-panel="1"]/p[contains(@class,"ao-lead")]')?->textContent === $copy['readiness'], 'Account-readiness subtitle mismatch.');
            $check($node('//section[@data-step-panel="2"]//div[contains(@class,"ao-note")]')?->textContent === $copy['phone'], 'Private phone disclosure mismatch.');
            foreach ([1, 2, 3, 4, 5] as $panelStep) {
                $check(($node('//section[@data-step-panel="' . $panelStep . '"]')?->attributes?->getNamedItem('hidden') !== null) === ($panelStep !== $step), 'Panel visibility mismatch.');
            }
            $check($node('//p[contains(@class,"ao-field-note") and contains(.,"' . ($locale === 'ar' ? 'هذه حالة' : 'This is an illustration') . '")]')?->textContent === $copy['email'], 'Illustrative email notice mismatch.');
            $check($node('(//label[contains(@class,"ao-check")]/span/small)[2]')?->textContent === $copy['terms'], 'Terms limitation mismatch.');
            $check($node('(//label[contains(@class,"ao-check")]/span/small)[3]')?->textContent === $copy['privacy'], 'Privacy limitation mismatch.');
            $check($node('//section[@data-step-panel="1"]//div[contains(@class,"ao-note")]')?->textContent === $copy['consent'], 'Consent boundary copy mismatch.');
            $check($node('//section[@data-step-panel="2"]//p[contains(@class,"ao-field-note")]')?->textContent === $copy['identity'], 'Publication boundary copy mismatch.');
            $check($node('//p[contains(@class,"ao-preview-footer")]')?->textContent === $copy['footer'], 'Preview footer mismatch.');
            $check($xpath->query('//input[@type="checkbox"]')->length === 5 && $xpath->query('//input[@type="checkbox" and @checked]')->length === 0, 'All consent and visibility controls must start unchecked.');
            $check($xpath->query('//input[@data-profile-name and (@value or @name)]')->length === 0, 'Display name must start empty and unnamed.');
            $check($xpath->query('//select[@data-country]/option[@value="JO" or @value="PS" or @value="SA" or @value="AE" or @value="EG"]')->length === 5, 'Expected ISO-coded preview countries.');
            $check($xpath->query('//select[@data-country]/option[@value="Other"]')->length === 0, 'Fake Other country option found.');
            $check($xpath->query('//input[@type="radio" and @checked]')->length === 0, 'Preferred profile language must start unselected.');
            $check($xpath->query('//button[contains(@class,"ao-header-action") and @disabled] | //button[contains(@class,"ao-save-exit") and @disabled]')->length === 2, 'Save actions must be inactive.');
            $check($xpath->query('//select[@data-career-status]/option[@value="student" or @value="graduate" or @value="early" or @value="experienced" or @value="independent"]')->length === 5, 'Approved status examples missing.');
            $selectedStatus = $node('(//select[@data-career-status]/option[@selected])[last()]') ?? $node('(//select[@data-career-status]/option)[1]');
            $check($selectedStatus?->attributes?->getNamedItem('value')?->nodeValue === '', 'Professional status must start empty.');
            $check($xpath->query('//input[@data-field or @data-headline or @data-slug][@value or @name or @maxlength or @pattern]')->length === 0, 'New presentation fields must start empty without final validation rules.');
            $check($xpath->query('//button[@data-edit-step]')->length === 4, 'Review edit controls missing.');
            $check($xpath->query('//a[@data-next]')->length === 1 && $xpath->query('//*[@data-create]')->length === 1 && $xpath->query('//button[@data-create and @disabled]')->length === 1, 'Exactly one disabled Create button is required.');
            $check($node('//section[@data-step-panel="4"]//small[contains(@class,"ao-label-note")]')?->textContent === $copy['slug'], 'Slug limitation mismatch.');
            $check($node('//section[@data-step-panel="5"]//div[contains(@class,"ao-create-note")]')?->textContent === $copy['create'], 'Creation limitation mismatch.');
            foreach ($xpath->query('//a[@href]') as $link) {
                $href = $link->getAttribute('href');
                $check($href === '#onboarding-card' || preg_match('/^\?lang=(ar|en)&step=[1-5]$/', $href) === 1, 'Unexpected preview link destination.');
            }
            $check($xpath->query('//script[@src]')->length === 2 && str_contains($html, 'onboarding.js?v='), 'Unexpected script dependency.');
            $check($render(['lang' => $locale, 'step' => (string) $step, 'verified' => '1', 'consent' => '1', 'name' => 'private', 'token' => 'unsafe']) === $html, 'Query claims changed the preview.');
            $check($_SESSION === ['existing_marker' => 'unchanged'] && session_status() === PHP_SESSION_NONE, 'Preview touched the session.');
        }
    }
    $script = file_get_contents($root . '/onboarding.js');
    $fixture = file_get_contents($root . '/tests/phase2/support/onboarding-preview.php');
    foreach (['localStorage', 'sessionStorage', 'XMLHttpRequest', 'sendBeacon', 'WebSocket', 'document.cookie', 'window.name', '.submit('] as $forbidden) {
        $check(!str_contains($script, $forbidden), 'Onboarding interaction script contains a forbidden persistence or request API.');
    }
    foreach (['session_start(', 'setcookie(', 'getDatabaseConnection(', 'createOwnedPortfolio('] as $forbidden) {
        $check(!str_contains($fixture, $forbidden), 'Preview fixture crosses an authentication or write boundary.');
    }
    echo 'PASS onboarding steps 1–5 presentation boundaries: ' . $checks . " checks.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL onboarding presentation: ' . $exception->getMessage() . "\n");
    exit(1);
}
