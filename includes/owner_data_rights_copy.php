<?php

declare(strict_types=1);

function ownerDataRightsCopy(string $key, string $locale): string
{
    $copy = [
        'title' => ['ar' => 'بياناتك', 'en' => 'Your data'],
        'intro' => ['ar' => 'نسخة لك، وقرارك في الاحتفاظ بحسابك.', 'en' => 'Keep a copy of your work. Choose what happens to your account.'],
        'identity' => ['ar' => 'نؤكّد هويتك قبل تنزيل بياناتك أو طلب حذف حسابك.', 'en' => 'We confirm your identity before you download your data or request account deletion.'],
        'confirm' => ['ar' => 'أكّد هويتك', 'en' => 'Confirm it is you'],
        'download' => ['ar' => 'نزّل بياناتي', 'en' => 'Download my data'],
        'zip' => ['ar' => 'ملف ZIP يضم بياناتك، ونسخة مقروءة منها، وصورك الأصلية.', 'en' => 'A ZIP with your data, a readable copy, and your original images.'],
        'delete' => ['ar' => 'حذف حسابي', 'en' => 'Delete my account'],
        'warning' => ['ar' => 'تختفي صفحتك العامة فورًا. لديك %d يومًا لتغيير رأيك بتسجيل الدخول وإلغاء الحذف. بعدها تُحذف كل بياناتك وصورك نهائيًا، بما فيها حساب تسجيل الدخول.', 'en' => 'Your public page disappears immediately. You have %d days to change your mind by signing in and cancelling deletion. After that, all your data and images are permanently deleted, including your sign-in account.'],
        'review' => ['ar' => 'مراجعة طلب الحذف', 'en' => 'Review account deletion'],
        'phrase_before' => ['ar' => 'اكتب', 'en' => 'Type'],
        'phrase_after' => ['ar' => 'للتأكيد', 'en' => 'to confirm'],
        'request' => ['ar' => 'جدولة حذف حسابي', 'en' => 'Schedule account deletion'],
        'reauthenticate' => ['ar' => 'انتهت مهلة تأكيد هويتك. أكّد هويتك مجددًا للمتابعة.', 'en' => 'Your identity confirmation has expired. Confirm it is you again to continue.'],
        'confirmation' => ['ar' => 'لم يتطابق النص. اكتب النص المطلوب تمامًا لتأكيد طلب الحذف.', 'en' => 'The text did not match. Type the requested text to confirm account deletion.'],
        'scheduled_title' => ['ar' => 'حسابك مجدول للحذف', 'en' => 'Your account is scheduled for deletion'],
        'hidden' => ['ar' => 'صفحتك العامة مخفية الآن.', 'en' => 'Your public page is already hidden.'],
        'due' => ['ar' => 'موعد الحذف النهائي', 'en' => 'Permanent deletion date'],
        'permanent' => ['ar' => 'في هذا الموعد تُحذف كل بياناتك وصورك وحساب تسجيل الدخول نهائيًا.', 'en' => 'On this date, all your data, images and sign-in account will be permanently deleted.'],
        'cancel_hint' => ['ar' => 'غيّرت رأيك؟ ألغِ الحذف ليعود كل شيء كما كان.', 'en' => 'Changed your mind? Cancel deletion to restore everything as it was.'],
        'cancel' => ['ar' => 'إلغاء الحذف', 'en' => 'Cancel deletion'],
        'confirm_download' => ['ar' => 'أكّد هويتك لتنزيل بياناتك', 'en' => 'Confirm it is you to download your data'],
        'logout' => ['ar' => 'تسجيل الخروج', 'en' => 'Log out'],
        'scheduled_notice' => ['ar' => 'تمت جدولة حذف حسابك. يمكنك تسجيل الدخول خلال 30 يومًا وإلغاء الحذف إن غيّرت رأيك.', 'en' => 'Your account deletion is scheduled. If you change your mind, sign in within 30 days and cancel deletion.'],
        'cancelled_notice' => ['ar' => 'تم إلغاء حذف حسابك. عاد كل شيء كما كان.', 'en' => 'Account deletion cancelled. Everything is restored.'],
    ];
    return $copy[$key][$locale === 'ar' ? 'ar' : 'en'];
}
