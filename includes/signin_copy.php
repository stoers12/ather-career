<?php

declare(strict_types=1);

function signInCopy(string $key, string $locale): string
{
    static $catalog = [
        'title' => ['ar' => 'تسجيل الدخول إلى Ather', 'en' => 'Sign in to Ather'],
        'description' => ['ar' => 'سجّل دخولك بأمان لتكمل بناء ملفك المهني الخاص في أثر.', 'en' => 'Sign in securely to continue building your private professional portfolio in Ather.'],
        'brand' => ['ar' => 'أثر · Ather', 'en' => 'Ather · أثر'],
        'kicker' => ['ar' => 'هويتك المهنية تبدأ من عملك الحقيقي', 'en' => 'Your professional identity starts with your real work'],
        'story_heading' => ['ar' => 'اجمع مشاريعك، قصصك، وأدلتك في مكان واحد.', 'en' => 'Bring your projects, stories, and evidence together.'],
        'story_copy' => ['ar' => 'سجّل دخولك لتكمل بناء ملفك الخاص. لن ننشر أي شيء دون قرار واضح منك.', 'en' => 'Sign in to continue building your private portfolio. Nothing will be published without your explicit decision.'],
        'private' => ['ar' => 'خاص افتراضيًا', 'en' => 'Private by default'],
        'evidence' => ['ar' => 'أدلة توضّح دورك', 'en' => 'Evidence that shows your role'],
        'languages' => ['ar' => 'العربية والإنجليزية', 'en' => 'Arabic and English'],
        'footer' => ['ar' => 'من الأردن، لمسار مهني أوضح', 'en' => 'From Jordan, for a clearer career journey'],
        'heading' => ['ar' => 'تسجيل الدخول إلى Ather', 'en' => 'Sign in to Ather'],
        'subtitle' => ['ar' => 'اختر الطريقة التي استخدمتها لإنشاء حسابك الحالي.', 'en' => 'Choose the method you used to create your existing account.'],
        'google' => ['ar' => 'المتابعة باستخدام Google', 'en' => 'Continue with Google'],
        'microsoft' => ['ar' => 'المتابعة باستخدام Microsoft', 'en' => 'Continue with Microsoft'],
        'microsoft_unavailable' => ['ar' => 'Microsoft غير متاح حاليًا.', 'en' => 'Microsoft is currently unavailable.'],
        'or' => ['ar' => 'أو', 'en' => 'or'],
        'email' => ['ar' => 'المتابعة بالبريد وكلمة المرور', 'en' => 'Continue with email and password'],
        'safe' => ['ar' => 'ستفتح شاشة Auth0 الآمنة لإدخال بياناتك. لا يوجد حقل كلمة مرور داخل Ather.', 'en' => 'Auth0’s secure screen will open for your credentials. There is no password field inside Ather.'],
        'ordinary' => ['ar' => 'المتابعة عبر شاشة الدخول المعتادة', 'en' => 'Continue through the usual sign-in screen'],
        'no_account' => ['ar' => 'ليس لديك حساب؟', 'en' => 'Don’t have an account?'],
        'unavailable' => ['ar' => 'إنشاء حساب غير متاح حاليًا. التسجيل العام غير متاح بعد؛ تسجيل الدخول للحسابات الحالية فقط.', 'en' => 'Account creation is currently unavailable. Public registration is not available yet; sign-in is for existing accounts only.'],
        'theme' => ['ar' => 'المظهر الداكن', 'en' => 'Dark theme'],
        'language' => ['ar' => 'Read in English', 'en' => 'اقرأ بالعربية'],
        'back' => ['ar' => 'العودة إلى أثر', 'en' => 'Back to Ather'],
        'main' => ['ar' => 'تسجيل الدخول إلى أثر', 'en' => 'Ather sign in'],
        'skip' => ['ar' => 'انتقل إلى خيارات تسجيل الدخول', 'en' => 'Skip to sign-in options'],
    ];

    return htmlspecialchars($catalog[$key][$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
