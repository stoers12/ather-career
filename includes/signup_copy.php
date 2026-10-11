<?php

declare(strict_types=1);

function signUpCopy(string $key, string $locale): string
{
    static $catalog = [
        'description' => ['ar' => 'خيارات إنشاء حساب أثر. التسجيل العام غير متاح حاليًا.', 'en' => 'Ather account creation options. Public registration is currently unavailable.'],
        'title' => ['ar' => 'إنشاء حساب | أثر · Ather', 'en' => 'Sign up | Ather'],
        'main' => ['ar' => 'إنشاء حساب أثر', 'en' => 'Ather sign up'],
        'skip' => ['ar' => 'انتقل إلى خيارات إنشاء الحساب', 'en' => 'Skip to account creation options'],
        'heading' => ['ar' => 'ابدأ بناء أثرك', 'en' => 'Start building your impact'],
        'subtitle' => ['ar' => 'أنشئ حسابك، ثم ابنِ ملفك المهني الخاص خطوة بخطوة.', 'en' => 'Create your account, then build your private professional portfolio step by step.'],
        'story_copy' => ['ar' => 'ابنِ ملفك المهني الخاص خطوة بخطوة. لا يُنشر شيء دون قرار صريح منك.', 'en' => 'Build your private professional portfolio step by step. Nothing is published without your explicit decision.'],
        'google' => ['ar' => 'الإنشاء باستخدام Google', 'en' => 'Create with Google'],
        'microsoft' => ['ar' => 'الإنشاء باستخدام Microsoft', 'en' => 'Create with Microsoft'],
        'email' => ['ar' => 'الإنشاء بالبريد وكلمة المرور', 'en' => 'Create with email and password'],
        'age' => ['ar' => 'النسخة التجريبية مخصصة لمن أعمارهم 18 عامًا فأكثر. ستراجع الشروط والخصوصية قبل إكمال ملفك.', 'en' => 'The beta is intended for people aged 18 and over. You will review the terms and privacy policy before completing your portfolio.'],
        'existing' => ['ar' => 'لديك حساب بالفعل؟', 'en' => 'Already have an account?'],
        'signin' => ['ar' => 'سجّل الدخول', 'en' => 'Sign in'],
    ];

    return htmlspecialchars($catalog[$key][$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
