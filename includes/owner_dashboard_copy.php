<?php

declare(strict_types=1);

/** Dashboard presentation only; no Profile language or account state is written. */
function ownerDashboardCopy(string $key, string $locale): string
{
    static $copy = [
        'title' => ['ar' => 'لوحة التحكم', 'en' => 'Dashboard'],
        'welcome' => ['ar' => 'مرحبًا', 'en' => 'Welcome'],
        'subtitle' => ['ar' => 'مساحتك لترتيب خبراتك ومشاريعك.', 'en' => 'Your space to organize your experience and projects.'],
        'account' => ['ar' => 'حسابك', 'en' => 'Your account'],
        'preview' => ['ar' => 'معاينة خاصة', 'en' => 'Private preview'],
        'dashboard' => ['ar' => 'نظرة عامة', 'en' => 'Overview'],
        'profile' => ['ar' => 'الملف الشخصي', 'en' => 'Personal info'],
        'projects' => ['ar' => 'المشاريع', 'en' => 'Projects'],
        'experiences' => ['ar' => 'الخبرات', 'en' => 'Experience'],
        'messages' => ['ar' => 'الرسائل', 'en' => 'Messages'],
        'evidence_hub' => ['ar' => 'Evidence Hub', 'en' => 'Evidence Hub'],
        'publication' => ['ar' => 'إعدادات النشر', 'en' => 'Publication settings'],
        'skills' => ['ar' => 'المهارات', 'en' => 'Skills'],
        'manage_projects' => ['ar' => 'إدارة المشاريع', 'en' => 'Manage projects'],
        'manage_skills' => ['ar' => 'إدارة المهارات', 'en' => 'Manage skills'],
        'view_messages' => ['ar' => 'عرض الرسائل', 'en' => 'View messages'],
        'first_project' => ['ar' => 'ابدأ بأول مشروع', 'en' => 'Start with your first project'],
        'first_description' => ['ar' => 'وثّق ما عملت عليه، ودورك، والنتيجة التي وصلت إليها.', 'en' => 'Document what you worked on, your role, and the outcome.'],
        'next_project' => ['ar' => 'أكمل قصة مشاريعك', 'en' => 'Keep your projects growing'],
        'next_description' => ['ar' => 'أضف مشروعًا جديدًا أو تابع توثيق أعمالك الحالية.', 'en' => 'Add your next project or continue documenting your existing work.'],
        'add_project' => ['ar' => 'إضافة مشروع', 'en' => 'Add project'],
        'other_actions' => ['ar' => 'إجراءات أخرى', 'en' => 'Other actions'],
        'edit_profile' => ['ar' => 'تعديل الملف الشخصي', 'en' => 'Edit profile'],
        'add_experience' => ['ar' => 'إضافة خبرة', 'en' => 'Add experience'],
        'no_slug' => ['ar' => 'غير منشور', 'en' => 'Not published'],
        'no_slug_description' => ['ar' => 'ملفك غير ظاهر للزوار حاليًا.', 'en' => 'Your portfolio is not visible to visitors yet.'],
        'reserved' => ['ar' => 'العنوان محجوز', 'en' => 'Address reserved'],
        'reserved_description' => ['ar' => 'عنوان ملفك محجوز، لكنه لن يظهر للزوار حتى تنشره.', 'en' => 'Your address is reserved. Your portfolio is not visible until you publish it.'],
        'offline' => ['ar' => 'غير متاح للزوار', 'en' => 'Portfolio offline'],
        'offline_description' => ['ar' => 'عنوانك الدائم محفوظ، وملفك غير ظاهر للزوار.', 'en' => 'Your permanent address is preserved. Your portfolio is not visible to visitors.'],
        'published' => ['ar' => 'منشور', 'en' => 'Published'],
        'published_description' => ['ar' => 'ملفك ظاهر للزوار وفق خيارات نشر المشاريع ومعلومات الاتصال.', 'en' => 'Your portfolio is visible according to your project and contact privacy choices.'],
        'unavailable' => ['ar' => 'غير متاح حاليًا', 'en' => 'Unavailable'],
        'unavailable_title' => ['ar' => 'بيانات مساحتك غير متاحة مؤقتًا', 'en' => 'Your workspace data is temporarily unavailable'],
        'unavailable_description' => ['ar' => 'تعذّر تحميل بياناتك. أعد المحاولة دون تغيير إعداداتك.', 'en' => 'Your data could not be loaded. Try again without changing your settings.'],
        'visibility_unavailable' => ['ar' => 'حالة النشر غير متاحة', 'en' => 'Visibility unavailable'],
        'reload' => ['ar' => 'إعادة تحميل اللوحة', 'en' => 'Reload dashboard'],
        'summary' => ['ar' => 'ملخص ملفك', 'en' => 'Portfolio summary'],
        'navigation' => ['ar' => 'التنقل في مساحة العمل', 'en' => 'Workspace navigation'],
        'menu' => ['ar' => 'القائمة', 'en' => 'Menu'],
        'skip' => ['ar' => 'انتقل إلى المحتوى', 'en' => 'Skip to main content'],
        'light' => ['ar' => 'الوضع الفاتح', 'en' => 'Light theme'],
        'dark' => ['ar' => 'الوضع الداكن', 'en' => 'Dark theme'],
        'switch' => ['ar' => 'تبديل الحساب', 'en' => 'Switch account'],
        'switch_pending' => ['ar' => 'جارٍ فتح اختيار الحساب…', 'en' => 'Opening account selection…'],
        'logout' => ['ar' => 'تسجيل الخروج', 'en' => 'Log out'],
        'logout_pending' => ['ar' => 'جارٍ تسجيل الخروج…', 'en' => 'Logging out…'],
    ];
    return $copy[$key][$locale === 'en' ? 'en' : 'ar'];
}
