<?php

declare(strict_types=1);

/** Profile presentation only; owner-authored text is never translated. */
function ownerProfileCopy(string $key, string $locale): string
{
    static $copy = [
        'title' => ['ar' => 'الملف الشخصي', 'en' => 'Profile'],
        'subtitle' => ['ar' => 'عرّف الآخرين بخبرتك، واختر المعلومات التي تظهر لهم.', 'en' => 'Introduce your expertise and choose what others can see.'],
        'sections' => ['ar' => 'أقسام الملف الشخصي', 'en' => 'Profile sections'],
        'basics' => ['ar' => 'المعلومات الأساسية', 'en' => 'Basic information'],
        'about' => ['ar' => 'النبذة والروابط', 'en' => 'Bio & links'],
        'skills' => ['ar' => 'المهارات', 'en' => 'Skills'],
        'photo' => ['ar' => 'صورة الملف', 'en' => 'Profile photo'],
        'additional' => ['ar' => 'تفاصيل إضافية: التعريف المهني والهاتف والبيانات الخاصة', 'en' => 'Additional details: headline, phones and private information'],
        'hint_full_name' => ['ar' => 'الاسم الذي سيظهر لزوارك.', 'en' => 'The name visitors will see.'],
        'hint_professional_title' => ['ar' => 'مثل: AI Engineer.', 'en' => 'For example: AI Engineer.'],
        'hint_location' => ['ar' => 'المدينة والدولة فقط.', 'en' => 'City and country only.'],
        'hint_email' => ['ar' => 'استخدم بريدًا مخصصًا للتواصل.', 'en' => 'Use an address for professional contact.'],
        'hint_phone_primary' => ['ar' => 'لا يظهر للعامة إلا إذا اخترت إظهاره أدناه.', 'en' => 'Public only if you choose to show it below.'],
        'hint_phone_secondary' => ['ar' => 'يبقى رقم الهاتف الثانوي خاصًا.', 'en' => 'Your secondary phone stays private.'],
        'save_hint' => ['ar' => 'تُحفظ معلوماتك الأساسية والنبذة والروابط معًا.', 'en' => 'Basic information, bio and links save together.'],
        'reload' => ['ar' => 'أعد تحميل الملف المحفوظ وراجعه قبل تطبيق تغييراتك مجددًا', 'en' => 'Reload saved profile and review before applying your changes again'],
        'reselect' => ['ar' => 'قد تحتاج إلى اختيار الصورة مجددًا.', 'en' => 'A selected photo may need reselection.'],
        'remove_skill_confirm' => ['ar' => 'هل تريد إزالة «%s» من مهاراتك؟ لا يمكن التراجع عن هذا الإجراء.', 'en' => 'Remove “%s” from your skills? This action cannot be undone.'],
    ];
    return $copy[$key][$locale === 'en' ? 'en' : 'ar'];
}
