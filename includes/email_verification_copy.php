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
        'preview_unchanged' => ['ar' => 'معاينة معزولة ببيانات تجريبية. لم تُرسل أي رسالة ولم يتغير أي حساب أو حالة تأكيد أو صلاحية وصول. لا تمنح هذه الصفحة أي وصول.', 'en' => 'Isolated preview with fixture data. No email was sent and no account, verification status or access changed. This page grants no access.'],
        'resent_heading' => ['ar' => 'أرسلنا رسالة جديدة', 'en' => 'We sent a new email'],
        'resent_copy' => ['ar' => 'حالة إعادة إرسال تجريبية. في المسار المخطط، راجع بريدك بحثًا عن رسالة التأكيد.', 'en' => 'A fixture resend state. In the planned flow, check your inbox for the verification email.'],
        'resent_info' => ['ar' => 'في المسار المخطط، يجب ألا يكشف طلب إعادة الإرسال ما إذا كان البريد مرتبطًا بحسابات أخرى.', 'en' => 'In the planned flow, a resend request should not disclose whether the address is linked to other accounts.'],
        'expired_heading' => ['ar' => 'انتهت صلاحية الرابط', 'en' => 'This link has expired'],
        'expired_copy' => ['ar' => 'حالة انتهاء صلاحية تجريبية. في المسار المخطط، تكون الخطوة التالية طلب رابط تأكيد جديد.', 'en' => 'A fixture expired-link state. In the planned flow, the next step is to request a new verification link.'],
        'new_link' => ['ar' => 'إرسال رابط جديد', 'en' => 'Send a new link'],
        'expired_unavailable' => ['ar' => 'طلب رابط تأكيد جديد غير متاح حاليًا. يمكنك العودة إلى تسجيل الدخول.', 'en' => 'Requesting a new verification link is currently unavailable. You can return to sign in.'],
        'expired_info' => ['ar' => 'لا تشارك رابط التأكيد مع أي شخص؛ يعامَل كرابط أمني مؤقت.', 'en' => 'Do not share a verification link with anyone; treat it as a temporary security link.'],
    ];

    return htmlspecialchars($catalog[$key][$locale], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
