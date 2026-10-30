<?php

declare(strict_types=1);

/** Settings presentation copy; authored portfolio content is never translated. */
function ownerSettingsCopy(string $key, string $locale): string
{
    static $copy = [
        'eyebrow' => ['ar' => 'تفضيلاتك', 'en' => 'YOUR PREFERENCES'],
        'title' => ['ar' => 'الإعدادات', 'en' => 'Settings'],
        'subtitle' => ['ar' => 'مساحة عمل تناسبك، وحساب تعرف كيف تتحكم فيه.', 'en' => 'A workspace that fits you. An account you can manage.'],
        'sections' => ['ar' => 'أقسام الإعدادات', 'en' => 'Settings sections'],
        'appearance' => ['ar' => 'المظهر واللغة', 'en' => 'Appearance & language'],
        'help' => ['ar' => 'المساعدة', 'en' => 'Help'],
        'make_yours' => ['ar' => 'اجعل المساحة مناسبة لك', 'en' => 'MAKE IT YOURS'],
        'appearance_intro' => ['ar' => 'اختيارات بسيطة لعملٍ أكثر راحة.', 'en' => 'A few thoughtful choices for a comfortable workspace.'],
        'workspace_appearance' => ['ar' => 'مظهر مساحة العمل', 'en' => 'Workspace appearance'],
        'light' => ['ar' => 'فاتح', 'en' => 'Light'],
        'dark' => ['ar' => 'داكن', 'en' => 'Dark'],
        'system' => ['ar' => 'اتباع الجهاز', 'en' => 'Use device setting'],
        'system_hint' => ['ar' => '«اتباع الجهاز» يتغير تلقائيًا مع إعداد المظهر في جهازك.', 'en' => 'Device setting follows changes to your system appearance automatically.'],
        'workspace_language' => ['ar' => 'لغة مساحة العمل', 'en' => 'Workspace language'],
        'language_hint' => ['ar' => 'لغة القوائم والأزرار. محتوى مشاريعك يبقى كما كتبته.', 'en' => 'The language of menus and controls. Your project content stays as written.'],
        'public_language' => ['ar' => 'لغة ملف الأعمال المنشور', 'en' => 'Published portfolio language'],
        'public_hint' => ['ar' => 'لغة المحتوى مستقلة عن لغة القوائم. يرى زوار ملف الأعمال المحتوى باللغة الإنجليزية دائمًا.', 'en' => 'Content language is separate from navigation. Visitors always see the published portfolio in English.'],
        'text_size' => ['ar' => 'حجم النص', 'en' => 'Text size'],
        'text_hint' => ['ar' => 'كبّر نصوص مساحة العمل لتحسين القراءة.', 'en' => 'Make workspace text larger for easier reading.'],
        'default' => ['ar' => 'افتراضي', 'en' => 'Default'],
        'large' => ['ar' => 'كبير', 'en' => 'Large'],
        'motion' => ['ar' => 'الحركة', 'en' => 'Motion'],
        'motion_hint' => ['ar' => 'قلّل الحركات والمؤثرات إن كانت تشتت انتباهك.', 'en' => 'Reduce movement and effects if they distract you.'],
        'reduce' => ['ar' => 'تقليل الحركة', 'en' => 'Reduce motion'],
        'browser_note' => ['ar' => 'تُحفظ هذه التفضيلات في هذا المتصفح فقط.', 'en' => 'These preferences are saved in this browser only.'],
        'help_eyebrow' => ['ar' => 'إجابات قريبة منك', 'en' => 'ANSWERS AT HAND'],
        'help_intro' => ['ar' => 'إجابات على الأسئلة التي قد توقفك أثناء العمل.', 'en' => 'Answers to questions that may come up while you work.'],
        'q_language' => ['ar' => 'هل يتغير محتوى مشاريعي مع لغة مساحة العمل؟', 'en' => 'Does my project content change with the workspace language?'],
        'a_language' => ['ar' => 'لا. يتغير نص القوائم والأزرار فقط. تبقى أسماء مشاريعك وأوصافها وكل ما كتبته كما هو؛ لا تُترجم تلقائيًا.', 'en' => 'No. Only menus and controls change language. Your project names, descriptions and other authored content stay as written; they are not translated automatically.'],
        'q_theme' => ['ar' => 'ما الفرق بين اتباع الجهاز والوضع الداكن؟', 'en' => 'What is the difference between device setting and dark?'],
        'a_theme' => ['ar' => 'اتباع الجهاز يطابق إعداد المظهر في جهازك ويتغير معه تلقائيًا. أما الوضع الداكن فيبقى داكنًا حتى تختار مظهرًا آخر في هذا المتصفح.', 'en' => 'Device setting follows your system appearance and changes with it automatically. Dark stays dark until you choose another appearance in this browser.'],
        'q_visitors' => ['ar' => 'هل يرى الزوار اختيارات مظهري؟', 'en' => 'Do visitors see my appearance choices?'],
        'a_visitors' => ['ar' => 'لا. تؤثر هذه التفضيلات على مساحة عملك الخاصة فقط، ولا تغيّر شكل البورتفوليو المنشور أو ما يراه الزوار.', 'en' => 'No. These preferences affect your private workspace only. They do not change the published portfolio or what visitors see.'],
        'q_storage' => ['ar' => 'أين تُحفظ هذه التفضيلات؟', 'en' => 'Where are these preferences saved?'],
        'a_storage' => ['ar' => 'يُحفظ المظهر وحجم النص والحركة في هذا المتصفح فقط، ولا تنتقل إلى جهاز أو متصفح آخر. تُحفظ لغة مساحة العمل أيضًا كتفضيل لهذا المتصفح. قد يعيد مسح بيانات الموقع هذه الاختيارات إلى الإعدادات الافتراضية.', 'en' => 'Appearance, text size and motion are saved in this browser only and do not follow you to another device or browser. Workspace language is also remembered for this browser. Clearing site data may reset these choices to their defaults.'],
    ];
    return $copy[$key][$locale === 'en' ? 'en' : 'ar'];
}
