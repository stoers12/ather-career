<?php

declare(strict_types=1);

/*
 * Evidence Hub interface copy. The locale is always an explicit argument so a
 * future visitor-facing view can render English without reading the Owner's
 * workspace preference. Anything other than 'ar' renders English.
 * Owner-authored portfolio content is never translated.
 */

function evidenceHubLocale(mixed $locale): string
{
    return $locale === 'ar' ? 'ar' : 'en';
}

function evidenceHubDirection(string $locale): string
{
    return evidenceHubLocale($locale) === 'ar' ? 'rtl' : 'ltr';
}

/** A language is always named in its own script, whatever the current locale. */
function evidenceHubLanguageName(string $locale): string
{
    return evidenceHubLocale($locale) === 'ar' ? 'العربية' : 'English';
}

/** @param array<string, string|int> $replacements */
function evidenceHubText(string $english, string $locale, array $replacements = []): string
{
    $text = evidenceHubLocale($locale) === 'ar' ? (evidenceHubArabicCopy()[$english] ?? $english) : $english;

    return $replacements === [] ? $text : strtr($text, array_map('strval', $replacements));
}

/**
 * Counted phrase with Arabic number agreement: one, two, 3-10, 11-99 and other.
 */
function evidenceHubCountPhrase(int $count, string $noun, string $locale): string
{
    $forms = EVIDENCE_HUB_COUNTED_NOUNS[$noun] ?? throw new InvalidArgumentException('Evidence Hub counted noun is unsupported.');
    if (evidenceHubLocale($locale) === 'en') {
        return $count . ' ' . ($count === 1 ? $forms['en'][0] : $forms['en'][1]);
    }
    $remainder = $count % 100;
    $form = match (true) {
        $count === 1 => 'one',
        $count === 2 => 'two',
        $remainder >= 3 && $remainder <= 10 => 'few',
        $remainder >= 11 && $remainder <= 99 => 'many',
        default => 'other',
    };

    return str_replace('{n}', (string) $count, $forms['ar'][$form]);
}

const EVIDENCE_HUB_COUNTED_NOUNS = [
    'project' => [
        'en' => ['project', 'projects'],
        'ar' => ['one' => 'مشروع واحد', 'two' => 'مشروعان', 'few' => '{n} مشاريع', 'many' => '{n} مشروعًا', 'other' => '{n} مشروع'],
    ],
    'mapped_technology' => [
        'en' => ['mapped technology', 'mapped technologies'],
        'ar' => ['one' => 'تقنية مصنّفة واحدة', 'two' => 'تقنيتان مصنّفتان', 'few' => '{n} تقنيات مصنّفة', 'many' => '{n} تقنيةً مصنّفة', 'other' => '{n} تقنية مصنّفة'],
    ],
    'unmapped_technology' => [
        'en' => ['unmapped technology', 'unmapped technologies'],
        'ar' => ['one' => 'تقنية غير مصنّفة واحدة', 'two' => 'تقنيتان غير مصنّفتين', 'few' => '{n} تقنيات غير مصنّفة', 'many' => '{n} تقنيةً غير مصنّفة', 'other' => '{n} تقنية غير مصنّفة'],
    ],
];

/** @return array<string, string> */
function evidenceHubArabicCopy(): array
{
    static $copy = [
        // Shell and navigation
        'Evidence Hub' => 'مركز الأدلة',
        'Evidence Hub — My Portfolio' => 'مركز الأدلة — بورتفوليو',
        'Career insights' => 'تحليلات مسارك',
        'Evidence Hub application chrome' => 'أدوات مركز الأدلة',
        'Evidence Hub sections' => 'أقسام مركز الأدلة',
        'Next steps' => 'الخطوات التالية',
        'Overview' => 'نظرة عامة',
        'Project evidence' => 'أدلة المشاريع',
        'Technologies' => 'التقنيات',
        'Progress' => 'التقدّم',
        'Skip to main content' => 'انتقل إلى المحتوى',
        'Open navigation' => 'فتح القائمة',
        'Close navigation' => 'إغلاق القائمة',
        'Collapse navigation' => 'طي القائمة',
        'Expand navigation' => 'توسيع القائمة',
        'Owner navigation' => 'تنقل المالك',
        'Ather — Evidence Hub' => 'أثر — مركز الأدلة',
        'Back to dashboard' => 'العودة إلى لوحة التحكم',
        'Evidence Hub workspace' => 'مساحة مركز الأدلة',
        'WORKSPACE' => 'مساحة العمل',
        'Projects' => 'المشاريع',
        'Credentials' => 'الشهادات',
        'Coming soon' => 'قريبًا',
        'Account options' => 'خيارات الحساب',
        'Portfolio owner' => 'صاحب البورتفوليو',
        'Private workspace' => 'مساحة خاصة',
        'Account actions' => 'إجراءات الحساب',
        'Log out' => 'تسجيل الخروج',
        'Logging out…' => 'جارٍ تسجيل الخروج…',
        'Interface language' => 'لغة الواجهة',
        'Owner workspace' => 'مساحة المالك',
        'Evidence Hub is temporarily unavailable.' => 'مركز الأدلة غير متاح مؤقتًا.',
        'Please try again shortly.' => 'يُرجى المحاولة بعد قليل.',

        // Overview page
        'Evidence saved successfully' => 'حُفظت الأدلة بنجاح',
        'Undo last evidence update' => 'تراجع عن آخر تحديث للأدلة',
        'Evidence update undone' => 'تم التراجع عن تحديث الأدلة',
        'The previous Evidence values were restored safely.' => 'استُعيدت قيم الأدلة السابقة بأمان.',
        'This Evidence changed after the saved update and can no longer be undone.' => 'تغيّرت هذه الأدلة بعد التحديث المحفوظ، ولم يعد التراجع ممكنًا.',
        'This Undo action is no longer available.' => 'لم يعد إجراء التراجع هذا متاحًا.',
        'Project evidence saved.' => 'حُفظت أدلة المشروع.',
        'Build a portfolio people can trust.' => 'ابنِ بورتفوليو يثق به الآخرون.',
        'See the evidence recorded in your projects, identify gaps, and choose a useful next step.' => 'اطّلع على الأدلة المسجّلة في مشاريعك، واكتشف ما ينقصها، واختر خطوتك التالية.',
        'General evidence state' => 'الحالة العامة للأدلة',
        'Current evidence state' => 'حالة الأدلة الحالية',
        'View next steps' => 'عرض الخطوات التالية',
        'Recommended next steps' => 'الخطوات التالية المقترحة',
        'Based on recorded evidence' => 'بناءً على الأدلة المسجّلة',
        'Three steps to begin' => 'ثلاث خطوات للبدء',
        'Add a project' => 'أضف مشروعًا',
        'Record a project you want to explain.' => 'سجّل مشروعًا تريد شرحه.',
        'Add project' => 'إضافة مشروع',
        'Describe your contribution' => 'صِف مساهمتك',
        'Record the problem, your personal role, and a measurable outcome.' => 'سجّل المشكلة، ودورك الشخصي، ونتيجة قابلة للقياس.',
        'Manage projects' => 'إدارة المشاريع',
        'Review the evidence' => 'راجع الأدلة',
        'Return here to see completed fields and remaining gaps.' => 'عُد إلى هنا لترى الحقول المكتملة وما بقي ناقصًا.',
        'View project evidence' => 'عرض أدلة المشاريع',
        '100% complete' => 'مكتمل 100%',
        'You’re all caught up' => 'لا شيء ينقصك الآن',
        'All project Evidence is complete. Review it periodically to keep your portfolio current.' => 'اكتملت أدلة جميع المشاريع. راجعها من حين لآخر ليبقى بورتفوليوك محدّثًا.',
        'Review project evidence' => 'مراجعة أدلة المشاريع',
        'Evidence Hub recommendation' => 'توصية من مركز الأدلة',
        'Project: {title}' => 'المشروع: {title}',
        'More options' => 'خيارات أخرى',
        'Snooze for 14 days' => 'تأجيل 14 يومًا',
        'Dismiss' => 'تجاهل',
        'Recommendation snoozed for 14 days.' => 'أُجّلت التوصية 14 يومًا.',
        'Recommendation dismissed.' => 'تم تجاهل التوصية.',
        'Evidence overview' => 'نظرة عامة على الأدلة',
        'Portfolio evidence overview' => 'نظرة عامة على أدلة البورتفوليو',
        'Documentation Coverage' => 'اكتمال التوثيق',
        'Unavailable' => 'غير متاح',
        'Add a project and record its three evidence fields to begin coverage.' => 'أضف مشروعًا وسجّل حقول أدلته الثلاثة لتبدأ نسبة التوثيق.',
        '{complete} of {expected} evidence fields completed' => 'اكتمل {complete} من أصل {expected} من حقول الأدلة',
        'Technology Evidence Map' => 'خريطة أدلة التقنيات',
        '{technologies} across {projects}. Record technologies in projects and review unmapped entries below.' => '{technologies} في {projects}. سجّل التقنيات في مشاريعك وراجع غير المصنّفة أدناه.',
        'Portfolio Progress' => 'تقدّم البورتفوليو',
        'Projects with all three evidence fields complete out of eligible projects.' => 'المشاريع المكتملة الحقول الثلاثة من أصل المشاريع المؤهلة.',
        'Newest updates first' => 'الأحدث تحديثًا أولًا',
        'No projects yet' => 'لا توجد مشاريع بعد',
        'Add a project to begin recording evidence.' => 'أضف مشروعًا لتبدأ تسجيل الأدلة.',
        'Filter projects' => 'تصفية المشاريع',
        'All' => 'الكل',
        'Needs attention' => 'يحتاج إلى استكمال',
        'Complete' => 'مكتمل',
        'Invalid' => 'غير صالح',
        'Missing' => 'غير موجود',
        '{count}/3 complete' => '{count}/3 مكتملة',
        'Updated date unavailable' => 'تاريخ التحديث غير متاح',
        'Updated {date}' => 'حُدّث في {date}',
        'Review & edit evidence' => 'مراجعة الأدلة وتعديلها',
        'Edit project' => 'تعديل المشروع',
        'Previous project' => 'المشروع السابق',
        'Next project' => 'المشروع التالي',
        'Project {position} of {total}' => 'المشروع {position} من {total}',
        'Showing projects {first}–{last} of {total}' => 'عرض المشاريع {first}–{last} من {total}',
        'No projects match this filter' => 'لا توجد مشاريع تطابق هذا التصنيف',
        'Choose another filter to review project evidence.' => 'اختر تصنيفًا آخر لمراجعة أدلة المشاريع.',
        'Projects using each technology' => 'المشاريع التي تستخدم كل تقنية',
        'No technology evidence yet' => 'لا توجد أدلة تقنيات بعد',
        'Add technologies to a project to see them mapped here.' => 'أضف التقنيات إلى مشروع لتظهر مصنّفة هنا.',
        'Unmapped technologies' => 'تقنيات غير مصنّفة',
        'These entries do not match the current taxonomy. Review their project records.' => 'هذه الإدخالات لا تطابق قائمة التقنيات الحالية. راجع سجلات مشاريعها.',
        'Unmapped technology {n}' => 'تقنية غير مصنّفة {n}',
        'Portfolio progress' => 'تقدّم البورتفوليو',
        '{complete} of {total} eligible projects have all three evidence fields complete.' => 'اكتملت حقول الأدلة الثلاثة في {complete} من أصل {total} من المشاريع المؤهلة.',
        'Complete-project activity: {range}.' => 'نشاط المشاريع المكتملة: {range}.',
        '{first} to {latest}' => 'من {first} إلى {latest}',

        // Readiness and states
        'Zero' => 'البداية',
        'Partial' => 'قيد الإكمال',
        'Ready' => 'جاهز',
        'No project evidence is recorded yet.' => 'لم تُسجَّل أي أدلة مشاريع بعد.',
        'No project has all three evidence fields complete yet. Continue recording the missing fields below.' => 'لا يوجد مشروع مكتمل الحقول الثلاثة بعد. تابع تسجيل الحقول الناقصة أدناه.',
        'Every eligible project has all three evidence fields complete. Keep them current.' => 'كل المشاريع المؤهلة مكتملة الحقول الثلاثة. حافظ على تحديثها.',
        'At least one project has all three evidence fields complete. Review the remaining projects below.' => 'يوجد مشروع واحد على الأقل مكتمل الحقول الثلاثة. راجع بقية المشاريع أدناه.',
        'Evidence state is unavailable.' => 'حالة الأدلة غير متاحة.',

        // Recommendations
        'Add your first project' => 'أضف مشروعك الأول',
        'Add a project to begin building portfolio evidence.' => 'أضف مشروعًا لتبدأ بناء أدلة بورتفوليوك.',
        'Portfolio foundation' => 'أساس البورتفوليو',
        'Complete project evidence' => 'أكمل أدلة المشروع',
        'Add the missing project documentation in Project Management.' => 'أضف التوثيق الناقص للمشروع من إدارة المشاريع.',
        'Add the missing Problem, Personal role, or Measurable outcome for this project.' => 'أضف ما ينقص هذا المشروع: المشكلة، أو دورك الشخصي، أو النتيجة القابلة للقياس.',
        'Edit evidence' => 'تعديل الأدلة',
        'Documentation' => 'التوثيق',
        'Review technology evidence' => 'راجع أدلة التقنيات',
        'Connect this technology to the project that demonstrates it.' => 'اربط هذه التقنية بالمشروع الذي يُثبت استخدامها.',
        'Review technology' => 'مراجعة التقنية',
        'Technology evidence' => 'أدلة التقنيات',
        'Technology mapping' => 'تصنيف التقنيات',
        'Prepare your portfolio for publishing' => 'جهّز بورتفوليوك للنشر',
        'Review your private publishing settings before making the portfolio public.' => 'راجع إعدادات النشر الخاصة قبل جعل البورتفوليو عامًا.',
        'Review publishing' => 'مراجعة النشر',
        'Publishing readiness' => 'الجاهزية للنشر',
        'Review portfolio evidence' => 'راجع أدلة البورتفوليو',
        'Review your Owner workspace for the next useful improvement.' => 'راجع مساحتك لتجد التحسين المفيد التالي.',
        'Recommended next step' => 'الخطوة التالية المقترحة',

        // Technology categories
        'Database' => 'قواعد البيانات',
        'Framework' => 'أُطر العمل',
        'Language' => 'لغات البرمجة',
        'Library' => 'المكتبات',
        'Platform' => 'المنصات',
        'Runtime' => 'بيئات التشغيل',
        'Stylesheet' => 'التنسيق',
        'Technique' => 'التقنيات التحليلية',
        'Tool' => 'الأدوات',

        // Evidence fields and reasons
        'Problem' => 'المشكلة',
        'Personal role' => 'دورك الشخصي',
        'Measurable outcome' => 'النتيجة القابلة للقياس',
        'Add a specific account of this evidence.' => 'أضف وصفًا محددًا لهذا الدليل.',
        'Replace placeholder text with details from the project.' => 'استبدل النص المؤقت بتفاصيل من المشروع.',
        'Add more detail about this part of the project.' => 'أضف تفاصيل أكثر عن هذا الجزء من المشروع.',
        'Add more useful words about this part of the project.' => 'أضف كلمات مفيدة أكثر عن هذا الجزء من المشروع.',
        'Add more varied details about this part of the project.' => 'أضف تفاصيل أكثر تنوعًا عن هذا الجزء من المشروع.',
        'Replace repeated wording with specific details.' => 'استبدل الكلمات المكررة بتفاصيل محددة.',
        'Review and save this field again.' => 'راجع هذا الحقل واحفظه مرة أخرى.',
        'Shorten this field and save it again.' => 'اختصر هذا الحقل واحفظه مرة أخرى.',
        'Remove unsupported characters and save this field again.' => 'احذف الأحرف غير المدعومة واحفظ الحقل مرة أخرى.',
        'Remove invisible characters and save this field again.' => 'احذف الأحرف غير المرئية واحفظ الحقل مرة أخرى.',
        'Remove unsupported directional characters and save this field again.' => 'احذف أحرف الاتجاه غير المدعومة واحفظ الحقل مرة أخرى.',

        // Progress stages
        'Start with a project' => 'ابدأ بمشروع',
        'Complete your first evidence-ready project' => 'أكمل أول مشروع جاهز بالأدلة',
        'One evidence-ready project' => 'مشروع واحد جاهز بالأدلة',
        'Evidence across projects' => 'أدلة عبر عدة مشاريع',
        'Historical context available' => 'يتوفر سياق زمني',
        'Complete projects span at least 30 full days.' => 'تمتد المشاريع المكتملة على 30 يومًا كاملًا على الأقل.',
        'There is not enough dated, complete project evidence to show historical trend context.' => 'لا توجد أدلة مشاريع مكتملة ومؤرخة بما يكفي لعرض اتجاه زمني.',

        // Evidence editor
        'Edit project evidence' => 'تعديل أدلة المشروع',
        'Record what the project addressed, what you personally did, and what changed.' => 'سجّل ما عالجه المشروع، وما قمت به أنت شخصيًا، وما الذي تغيّر.',
        'Describe the specific problem, who it affected, and why it mattered.' => 'صِف المشكلة المحددة، ومن تأثّر بها، ولماذا كانت مهمة.',
        'Example: Manual reporting took the team six hours each week.' => 'مثال: كان إعداد التقارير يدويًا يستغرق من الفريق ست ساعات أسبوعيًا.',
        'Explain what you personally owned, decided, or implemented.' => 'وضّح ما تولّيته أنت شخصيًا، أو قرّرته، أو نفّذته.',
        'Example: I designed the data pipeline and implemented its validation rules.' => 'مثال: صمّمت مسار معالجة البيانات ونفّذت قواعد التحقق منه.',
        'State the result using a number, comparison, or observable change.' => 'اذكر النتيجة برقم، أو مقارنة، أو تغيير ملموس.',
        'Example: Reduced weekly reporting time from six hours to one.' => 'مثال: خفّضت وقت إعداد التقرير الأسبوعي من ست ساعات إلى ساعة واحدة.',
        'Save evidence' => 'حفظ الأدلة',
        'Saving evidence…' => 'جارٍ حفظ الأدلة…',
        'Cancel' => 'إلغاء',
        'Please enter text for this field.' => 'يُرجى إدخال نص في هذا الحقل.',
        'Project evidence is temporarily unavailable.' => 'أدلة المشروع غير متاحة مؤقتًا.',
    ];

    return $copy;
}
