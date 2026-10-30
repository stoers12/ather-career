<?php

declare(strict_types=1);

/** Publication presentation copy; the public URL always comes from the handler. */
function ownerPublicationCopy(string $key, string $locale): string
{
    static $copy = [
        'title' => ['ar' => 'النشر والمشاركة', 'en' => 'Publishing and sharing'],
        'subtitle' => ['ar' => 'تحكم بما يظهر للعامة، وراجع بورتفوليوك قبل مشاركته.', 'en' => 'Control public visibility and review your portfolio before sharing.'],
        'published_title' => ['ar' => 'بورتفوليوك منشور', 'en' => 'Your Portfolio is live'],
        'published_hint' => ['ar' => 'التغييرات المحفوظة على المحتوى الظاهر تنعكس مباشرةً على البورتفوليو العام.', 'en' => 'Changes saved while published become publicly visible immediately.'],
        'reserved_title' => ['ar' => 'رابط بورتفوليوك محجوز', 'en' => 'Your public address is reserved'],
        'reserved_hint' => ['ar' => 'هذا الرابط غير متاح للعامة حتى تنشر البورتفوليو.', 'en' => 'This address is not live until you publish the Portfolio.'],
        'offline_title' => ['ar' => 'الرابط العام غير متاح حاليًا', 'en' => 'Your public link is offline'],
        'offline_hint' => ['ar' => 'رابطك الدائم محفوظ. أعد النشر لإتاحة البورتفوليو للعامة.', 'en' => 'Your permanent public address is preserved. Publish again to make the Portfolio public.'],
        'no_slug_title' => ['ar' => 'اختر رابط بورتفوليوك العام', 'en' => 'Choose your public address'],
        'no_slug_hint' => ['ar' => 'احجز رابطًا عامًا قبل نشر البورتفوليو.', 'en' => 'Reserve a permanent public slug before publishing your Portfolio.'],
        'url' => ['ar' => 'الرابط العام', 'en' => 'Public Portfolio URL'],
        'copy' => ['ar' => 'نسخ', 'en' => 'Copy Link'],
        'copied' => ['ar' => 'تم نسخ رابط البورتفوليو.', 'en' => 'Portfolio link copied.'],
        'copy_failed' => ['ar' => 'تعذر نسخ الرابط. حدده وانسخه يدويًا.', 'en' => 'Copying the Portfolio link failed. Select and copy the link manually.'],
        'view' => ['ar' => 'عرض البورتفوليو العام', 'en' => 'View Portfolio'],
        'preview' => ['ar' => 'معاينة البورتفوليو', 'en' => 'Preview portfolio'],
        'projects' => ['ar' => 'إدارة ظهور المشاريع', 'en' => 'Manage project visibility'],
        'private' => ['ar' => 'أدلة المشاريع ومصادرها تبقى خاصة بك.', 'en' => 'Project evidence and sources stay private.'],
        'slug' => ['ar' => 'اسم الرابط العام', 'en' => 'Public slug'],
        'required' => ['ar' => 'مطلوب', 'en' => 'Required'],
        'slug_hint' => ['ar' => 'استخدم 3–64 حرفًا إنجليزيًا صغيرًا أو رقمًا، مع شرطات مفردة. يصبح الرابط دائمًا بعد أول نشر.', 'en' => 'Use 3–64 lowercase letters, numbers, and single hyphens. This address becomes permanent after publishing.'],
        'save_slug' => ['ar' => 'حفظ الرابط', 'en' => 'Save public slug'],
        'update_slug' => ['ar' => 'تحديث الرابط', 'en' => 'Update public slug'],
        'saving_slug' => ['ar' => 'جارٍ حفظ الرابط…', 'en' => 'Saving public slug…'],
        'publish' => ['ar' => 'نشر البورتفوليو', 'en' => 'Publish Portfolio'],
        'publishing' => ['ar' => 'جارٍ نشر البورتفوليو…', 'en' => 'Publishing Portfolio…'],
        'publish_confirm' => ['ar' => 'نشر هذا البورتفوليو؟ سيتمكن أي شخص لديه الرابط العام من مشاهدته.', 'en' => 'Publish this Portfolio? Anyone with the public link will be able to view it.'],
        'stop_title' => ['ar' => 'إيقاف الظهور العام', 'en' => 'Turn off public visibility'],
        'stop_hint' => ['ar' => 'يمكن إعادة النشر لاحقًا.', 'en' => 'You can publish again later.'],
        'unpublish' => ['ar' => 'إلغاء النشر', 'en' => 'Unpublish'],
        'unpublish_title' => ['ar' => 'إلغاء نشر البورتفوليو؟', 'en' => 'Unpublish Portfolio?'],
        'unpublishing' => ['ar' => 'جارٍ إيقاف الظهور العام…', 'en' => 'Taking Portfolio offline…'],
        'unpublish_confirm' => ['ar' => 'إيقاف الظهور العام؟ لن يتمكن الزوار من فتح الرابط العام حتى تعيد النشر.', 'en' => 'Take this Portfolio offline? Visitors will no longer be able to open its public link until you publish it again.'],
    ];

    return $copy[$key][$locale === 'en' ? 'en' : 'ar'] ?? $key;
}
