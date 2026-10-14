<?php

declare(strict_types=1);

function recoveryCopy(string $key, string $locale): string
{
    static $copy = [
        'title_forgot' => ['ar' => 'معاينة نسيان كلمة المرور | أثر', 'en' => 'Forgot password preview | Ather'],
        'title_reset' => ['ar' => 'معاينة إعادة تعيين كلمة المرور | أثر', 'en' => 'Reset password preview | Ather'],
        'main' => ['ar' => 'معاينة استعادة الوصول إلى أثر', 'en' => 'Ather access recovery preview'],
        'skip' => ['ar' => 'انتقل إلى معاينة الاستعادة', 'en' => 'Skip to recovery preview'],
        'kicker' => ['ar' => 'استعادة الوصول إلى أثر', 'en' => 'Recover access to Ather'],
        'story_heading' => ['ar' => "عد إلى حسابك.\nوأكمل الأثر الذي بدأته.", 'en' => "Back to your account.\nOn with your impact."],
        'story_copy' => ['ar' => 'طريق واضح للعودة إلى مشاريعك وأدلتك، مع الحفاظ على خصوصية حسابك.', 'en' => 'A clear path back to your projects and evidence, with your account privacy in mind.'],
        'point_one' => ['ar' => 'خطوات واضحة لاستعادة الوصول', 'en' => 'Clear steps to recover access'],
        'point_two' => ['ar' => 'كلمة المرور تبقى خاصة بك', 'en' => 'Your password stays private'],
        'point_three' => ['ar' => 'مشاريعك وأدلتك تبقى مرتبطة بحسابك', 'en' => 'Your projects and evidence stay with your account'],
        'story_footer' => ['ar' => 'الخصوصية جزء من رحلتك، من الدخول حتى النشر.', 'en' => 'Privacy is part of your journey, from sign-in to publishing.'],
        'forgot_heading' => ['ar' => 'نسيت كلمة المرور؟', 'en' => 'Forgot your password?'],
        'forgot_subtitle' => ['ar' => 'أدخل بريدك المرتبط بالحساب لبدء استعادة الوصول.', 'en' => 'Enter the email associated with your account to start recovering access.'],
        'email_label' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email address'],
        'email_hint' => ['ar' => 'استخدم البريد الذي تسجّل الدخول به إلى حسابك.', 'en' => 'Use the email you sign in with.'],
        'send_action' => ['ar' => 'إرسال رابط الاستعادة', 'en' => 'Send recovery link'],
        'forgot_notice' => ['ar' => 'إذا كان البريد مرتبطًا بحساب مؤهل للاستعادة، فستصلك تعليمات المتابعة. لن نعرض معلومات عن وجود الحساب.', 'en' => 'If this email belongs to an account eligible for recovery, you will receive instructions. We will not reveal whether an account exists.'],
        'return' => ['ar' => 'العودة إلى تسجيل الدخول', 'en' => 'Back to sign in'],
        'forgot_footer' => ['ar' => 'معاينة تصميم فقط؛ حقول الاستعادة والإرسال غير مفعّلة.', 'en' => 'Design preview only. Recovery fields and sending are inactive.'],
        'reset_heading' => ['ar' => "كلمة مرور جديدة.\nعودة آمنة لحسابك.", 'en' => "A new password.\nA safe return."],
        'reset_subtitle' => ['ar' => 'اختر كلمة مرور طويلة وفريدة، ثم أكدها للمتابعة.', 'en' => 'Choose a long, unique password, then confirm it to continue.'],
        'password_label' => ['ar' => 'كلمة المرور الجديدة', 'en' => 'New password'],
        'password_hint' => ['ar' => 'تجنب كلمة مرور سبق أن استخدمتها لحساب آخر.', 'en' => 'Avoid a password you already use for another account.'],
        'confirm_label' => ['ar' => 'تأكيد كلمة المرور الجديدة', 'en' => 'Confirm new password'],
        'reset_action' => ['ar' => 'حفظ كلمة المرور الجديدة', 'en' => 'Save new password'],
        'reset_notice' => ['ar' => 'في المسار الفعلي، تُدخل كلمة المرور وتحدّثها عبر صفحة Auth0 الآمنة.', 'en' => 'In the actual flow, password entry and updates take place on the secure Auth0 page.'],
        'reset_footer' => ['ar' => 'معاينة تصميم فقط؛ لا تُدخل أو تُحفظ أي كلمة مرور.', 'en' => 'Design preview only. No password can be entered or saved.'],
    ];

    return htmlspecialchars($copy[$key][$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
