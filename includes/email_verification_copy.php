<?php

declare(strict_types=1);

function emailVerificationCopy(string $key, string $locale): string
{
    static $catalog = [
        'title' => ['ar' => 'معاينة تأكيد البريد | أثر · Ather', 'en' => 'Email verification preview | Ather'],
        'main' => ['ar' => 'معاينة تأكيد البريد في أثر', 'en' => 'Ather email verification preview'],
        'skip' => ['ar' => 'انتقل إلى حالة التأكيد التجريبية', 'en' => 'Skip to the verification fixture'],
        'kicker' => ['ar' => 'خطوة واحدة قبل بناء ملفك', 'en' => 'One step before building your portfolio'],
        'story_heading' => ['ar' => 'تأكيد البريد يحمي هويتك المهنية.', 'en' => 'Email verification protects your professional identity.'],
        'story_copy' => ['ar' => 'نتأكد من أن وسيلة الدخول لك قبل إنشاء ملف أو نشر أي محتوى باسمك.', 'en' => 'Confirming that the sign-in method belongs to you comes before creating a portfolio or publishing in your name.'],
        'identity' => ['ar' => 'منع إنشاء ملفات بهوية غير مؤكدة', 'en' => 'Prevent portfolios with an unverified identity'],
        'links' => ['ar' => 'روابط محدودة وآمنة', 'en' => 'Limited, secure links'],
        'private' => ['ar' => 'لا نشر قبل إكمال الخطوات', 'en' => 'No publishing before completing the steps'],
        'footer' => ['ar' => 'الأمان جزء من الثقة، وليس خطوة إضافية فقط', 'en' => 'Security is part of trust, beyond an extra step'],
        'preview' => ['ar' => 'معاينة معزولة ببيانات تجريبية. لم تُرسل رسالة ولم يُنشأ حساب أو يُؤكد بريد. لا تمنح هذه الصفحة أي صلاحية وصول.', 'en' => 'Isolated preview with fixture data. No email was sent, no account was created and no address was verified. This page grants no access.'],
        'pending_heading' => ['ar' => 'تحقق من بريدك', 'en' => 'Check your email'],
        'pending_copy' => ['ar' => 'في هذه المعاينة، تظهر خطوة انتظار رسالة التأكيد للبريد التجريبي التالي.', 'en' => 'This preview shows the step for awaiting a verification email at the following fixture address.'],
        'refresh' => ['ar' => 'تحققت من بريدي', 'en' => 'I have verified my email'],
        'resend' => ['ar' => 'إعادة إرسال رسالة التأكيد', 'en' => 'Resend verification email'],
        'pending_unavailable' => ['ar' => 'التحقق من الحالة وإعادة الإرسال غير متاحين حاليًا.', 'en' => 'Verification refresh and resend are currently unavailable.'],
        'signin' => ['ar' => 'العودة إلى تسجيل الدخول', 'en' => 'Return to sign in'],
        'pending_info' => ['ar' => 'في المسار المخطط، قد تصل الرسالة إلى البريد غير المرغوب فيه. يلزم تأكيد موثوق من الخادم قبل المتابعة.', 'en' => 'In the planned flow, the message may reach your spam folder. Trusted server confirmation is required before continuing.'],
        'verified_heading' => ['ar' => 'تم تأكيد بريدك', 'en' => 'Your email is verified'],
        'verified_copy' => ['ar' => 'حالة نجاح تجريبية. في المسار المخطط، تكون الخطوة التالية إعداد ملفك المهني الخاص.', 'en' => 'A fixture success state. In the planned flow, the next step is to set up your private professional portfolio.'],
        'onboarding' => ['ar' => 'ابدأ إعداد ملفك', 'en' => 'Start setting up your portfolio'],
        'onboarding_unavailable' => ['ar' => 'الانتقال إلى إعداد الملف من هذه الصفحة غير متاح حاليًا.', 'en' => 'Continuing to portfolio setup from this page is currently unavailable.'],
        'verified_info' => ['ar' => 'في المسار المخطط، سيبقى ملفك خاصًا حتى تنهي الإعداد وتختار النشر بنفسك.', 'en' => 'In the planned flow, your portfolio stays private until you finish setup and choose to publish.'],
    ];

    return htmlspecialchars($catalog[$key][$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
