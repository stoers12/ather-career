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
/** One reference vocabulary for the three evidence parts on cards, record and editor (FE-07 review). */
function evidenceHubPartLabel(string $part): string
{
    return match ($part) {
        // Logical keys, plus the storage name of the first part.
        'problem', 'problem_statement' => 'Context',
        'personal_role' => 'My contribution',
        'measurable_outcome' => 'Outcome',
        default => throw new InvalidArgumentException('Evidence part is unsupported.'),
    };
}

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
        'Appearance' => 'المظهر',
        'Light appearance' => 'المظهر الفاتح',
        'Dark appearance' => 'المظهر الداكن',
        'EVIDENCE WORKSPACE' => 'مساحة الأدلة',
        'Keep your project context, your role and outcomes in private records you can revisit.' => 'رتّب سياق مشاريعك، ودورك، ونتائجها في سجلات خاصة يمكنك الرجوع إليها.',
        'Private to you' => 'خاص بك',
        'Evidence Hub views' => 'عروض مركز الأدلة',
        'Records' => 'السجلات',
        'Your path insights' => 'تحليلات مسارك',
        'No current recommendations' => 'لا توجد توصيات حاليًا',
        'Review your records for their current quality states. Snoozing or dismissing a next step does not complete evidence.' => 'راجع سجلاتك للاطلاع على حالات جودتها الحالية. تأجيل الخطوة التالية أو تجاهلها لا يُكمل الأدلة.',
        'Open record' => 'فتح السجل',
        'Without sources' => 'دون مصادر',
        'Skills without project evidence' => 'مهارات دون أدلة من المشاريع',
        'These claimed skills have no project technology evidence yet. This list is independent of next steps and their snooze or dismiss actions.' => 'هذه المهارات المذكورة لا تدعمها تقنيات مشروع بعد. هذه القائمة مستقلة عن الخطوات التالية وتأجيلها أو تجاهلها.',
        'No skills without project evidence.' => 'لا توجد مهارات دون أدلة من المشاريع.',
        'Writing and sources are supplied by you. A link or completed fields does not establish verification or assess your skill.' => 'الكتابة والمصادر من إضافتك. وجود رابط أو اكتمال الحقول لا يعني التحقق من صحته أو تقييم مهاراتك.',
        'Published project count unavailable.' => 'عدد المشاريع المنشورة غير متاح.',
        'Evidence confidence' => 'الثقة في كفاية الأدلة',
        'High' => 'عالية',
        'Medium' => 'متوسطة',
        'Low' => 'منخفضة',
        'Confidence describes evidence sufficiency, not your competence or the probability of it.' => 'تصف الثقة كفاية الأدلة، ولا تصف كفاءتك أو احتمالها.',
        '{observations} observations; {recommended} recommended.' => '{observations} ملاحظات؛ العدد الموصى به {recommended}.',
        'No projects are available.' => 'لا توجد مشاريع متاحة.',
        'Only one project is observed. Even high confidence has this sample limitation.' => 'تستند الملاحظة إلى مشروع واحد فقط. حتى الثقة العالية يحدّها حجم هذه العينة.',
        'The sample is below the recommended number of projects.' => 'العينة أقل من عدد المشاريع الموصى به.',
        'Some evidence fields are incomplete.' => 'بعض حقول الأدلة غير مكتملة.',
        'All records' => 'كل السجلات',
        'Private record' => 'سجل خاص',
        'Record mode' => 'طريقة عرض السجل',
        'Read' => 'قراءة',
        'Edit' => 'تحرير',
        'The project record' => 'قصة المشروع',
        'Edit {part}' => 'تحرير {part}',
        'The work behind the result' => 'الصورة وراء النتيجة',
        'Use this record to remember your decisions and explain your contribution in an interview or professional conversation.' => 'هذا السجل يساعدك على تذكّر قراراتك، وشرح مساهمتك في مقابلة أو نقاش مهني.',
        'Sources are private and do not add a fourth quality point.' => 'المصادر خاصة ولا تضيف نقطة جودة رابعة.',
        'Leave this record? Your unsaved text will be lost.' => 'هل تريد مغادرة السجل؟ ستفقد النص غير المحفوظ.',
        'Record sections' => 'أجزاء السجل',
        'Context' => 'السياق',
        'My contribution' => 'مساهمتي',
        'Outcome' => 'النتيجة',
        'Write clearly and take your time. You can save an unfinished record.' => 'اكتب بوضوح، وأكمل على مهل. يمكنك حفظ سجل غير مكتمل.',
        'Save your text first, then add or remove sources from the saved record.' => 'احفظ النص أولًا، ثم أضف المصادر أو أزلها من السجل المحفوظ.',
        'After saving, the hub offers immediate Undo. Opening another page ends that opportunity.' => 'بعد الحفظ، يتيح المركز التراجع فورًا. فتح صفحة أخرى يُنهي هذه الفرصة.',
        'Previous' => 'السابق',
        'Next' => 'التالي',
        'No unsaved changes' => 'لا توجد تغييرات غير محفوظة',
        'Unsaved changes' => 'تغييرات غير محفوظة',
        'Adding a source saves it immediately.' => 'إضافة المصدر تحفظه مباشرة.',
        'Quality states reflect the last server assessment. Save to assess your changes.' => 'حالات الجودة تعكس آخر تقييم من الخادم. احفظ لتقييم تغييراتك.',
        'Why was this project built?' => 'لماذا بُني هذا المشروع؟',
        'What did you personally contribute?' => 'ما الذي نفّذته أنت؟',
        'What was the outcome?' => 'ما الذي خرجت به؟',
        'Are you sure?' => 'هل أنت متأكد؟',
        'Continue' => 'متابعة',
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
        'Map this technology to a known one, fix its spelling, or propose it.' => 'اربط هذه التقنية بتقنية معروفة، أو صحّح إملاءها، أو اقترح إضافتها.',
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
        "Stretched letters don't count. Add real details instead." => 'الحروف المكررة لا تُحتسب. أضف تفاصيل حقيقية بدلاً منها.',
        'The same few words repeat. Describe it in more varied, specific words.' => 'تتكرر الكلمات نفسها كثيراً. صِف بكلمات أكثر تنوعاً وتحديداً.',
        'This repeats another field of the same project. Write what is specific to this field.' => 'هذا النص يكرر حقلاً آخر في المشروع نفسه. اكتب ما يخص هذا الحقل تحديداً.',
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
        'Show evidence for a skill' => 'أثبت مهارة بمشروع',
        '{skill} is in your skills, but no project uses it yet. Add a project that uses it, or review your skills.' => '{skill} ضمن مهاراتك، لكن لا يوجد مشروع يستخدمها بعد. أضف مشروعاً يستخدمها، أو راجع مهاراتك.',
        'Skill evidence' => 'دليل المهارات',
        'Skills' => 'المهارات',
        'Review skills' => 'راجع المهارات',
        'Historical context available' => 'يتوفر سياق زمني',
        'Public projects: {published} of {total}.' => 'المشاريع المنشورة للزوار: {published} من {total}.',
        'Public projects appear to visitors only after the portfolio is published.' => 'تظهر المشاريع العامة للزوار فقط بعد نشر البورتفوليو.',
        'Complete projects span at least 30 full days.' => 'تمتد المشاريع المكتملة على 30 يومًا كاملًا على الأقل.',
        'There is not enough dated, complete project evidence to show historical trend context.' => 'لا توجد أدلة مشاريع مكتملة ومؤرخة بما يكفي لعرض اتجاه زمني.',

        'Based on {projects}; {recommended} give a more reliable picture.' => 'مبني على {projects}؛ {recommended} تعطي صورة أدق.',
        'Recognized technologies: {percent}%' => 'التقنيات المعروفة: {percent}%',

        // Evidence editor
        'Edit project evidence' => 'تعديل أدلة المشروع',
        'Record what the project addressed, what you personally did, and what changed.' => 'سجّل ما عالجه المشروع، وما قمت به أنت شخصيًا، وما الذي تغيّر.',
        'Save evidence' => 'حفظ الأدلة',
        'Saving evidence…' => 'جارٍ حفظ الأدلة…',
        'Cancel' => 'إلغاء',
        'Please enter text for this field.' => 'يُرجى إدخال نص في هذا الحقل.',
        'Review the highlighted fields' => 'راجع الحقول المحددة',
        'Project evidence is temporarily unavailable.' => 'أدلة المشروع غير متاحة مؤقتًا.',

        // Evidence writing guidance (evidence_writing_guidance.php)
        'Describe the specific problem, who it affected, and why it mattered.' => 'صِف المشكلة المحددة، ومن تأثّر بها، ولماذا كانت مهمة.',
        'Manual weekly reporting took the operations team six hours and delayed decisions every Monday.' => 'كان إعداد التقارير الأسبوعية يدويًا يستغرق من فريق العمليات ست ساعات ويؤخّر القرارات كل يوم اثنين.',
        '[Who] had [problem], which mattered because [impact].' => '[مَن] واجه [المشكلة]، وكان ذلك مهمًا لأن [الأثر].',
        'Explain what you personally owned, decided, or implemented.' => 'وضّح ما تولّيته أنت شخصيًا، أو قرّرته، أو نفّذته.',
        'I designed the data pipeline and implemented its validation rules.' => 'صمّمت مسار معالجة البيانات ونفّذت قواعد التحقق منه.',
        'I [action] the [part] by [how].' => 'دوري: [ماذا فعلت] في [أي جزء] عبر [كيف].',
        'State the result using a number, comparison, or observable change.' => 'اذكر النتيجة برقم، أو مقارنة، أو تغيير ملموس.',
        'Cut weekly reporting time from 6 hours to 1 hour.' => 'خفّضت وقت إعداد التقرير الأسبوعي من 6 ساعات إلى ساعة واحدة.',
        '[Metric]: [before] → [after]' => '[المقياس]: [قبل] ← [بعد]',
        'Example: {example}' => 'مثال: {example}',
        'Sentence starter' => 'بداية مقترحة',
        'Use this starter' => 'استخدم هذه البداية',
        'Replace each [bracketed] part with details from your project.' => 'استبدل كل جزء بين [قوسين] بتفاصيل من مشروعك.',
        'No number yet. If you have one, add a count, percentage, or before-and-after value.' => 'لا يوجد رقم بعد. إن توفّر لديك، أضف عددًا أو نسبة مئوية أو قيمة قبل وبعد.',

        // Technology review (evidence_hub_technology_review*.php)
        'These entries do not match the technology list. Fix a spelling in the project, map the entry to a known technology, or propose it.' => 'هذه التقنيات غير موجودة في قائمة التقنيات. صحّح الإملاء في المشروع، أو اربطها بتقنية معروفة، أو اقترح إضافتها.',
        'Fix the spelling in a project:' => 'صحّح الإملاء في مشروع:',
        'Map to a known technology' => 'اربطها بتقنية معروفة',
        'Choose a technology' => 'اختر تقنية',
        'Map technology' => 'ربط التقنية',
        'Mapped by you' => 'ربطتها أنت',
        'Technologies you mapped' => 'تقنيات ربطتها أنت',
        'Remove mapping' => 'إزالة الربط',
        'Not in the list? Propose it' => 'غير موجودة في القائمة؟ اقترح إضافتها',
        'Your proposal for this technology is pending.' => 'اقتراحك لهذه التقنية قيد المراجعة.',
        'Technology name' => 'اسم التقنية',
        'Category' => 'الفئة',
        'Choose a category' => 'اختر فئة',
        'Note (optional)' => 'ملاحظة (اختياري)',
        'Send proposal' => 'إرسال الاقتراح',
        'Your technology proposals' => 'اقتراحاتك للتقنيات',
        'Withdraw proposal' => 'سحب الاقتراح',
        'Pending review' => 'قيد المراجعة',
        'Withdrawn' => 'مسحوب',
        'Accepted' => 'مقبول',
        'Not accepted' => 'غير مقبول',
        'Technology mapped.' => 'تم ربط التقنية.',
        'Technology mapping removed.' => 'تمت إزالة ربط التقنية.',
        'Technology proposal sent.' => 'تم إرسال اقتراح التقنية.',
        'Technology proposal withdrawn.' => 'تم سحب اقتراح التقنية.',
        'Enter the technology name.' => 'أدخل اسم التقنية.',
        'Use 60 characters or fewer for the technology name.' => 'استخدم 60 حرفًا أو أقل لاسم التقنية.',
        'This technology is already in the list. Map it instead.' => 'هذه التقنية موجودة في القائمة. اربطها بدلًا من اقتراحها.',
        'Choose a category.' => 'اختر فئة.',
        'Remove unsupported characters from the note.' => 'احذف الأحرف غير المدعومة من الملاحظة.',
        'Use 280 characters or fewer for the note.' => 'استخدم 280 حرفًا أو أقل للملاحظة.',
        'A proposal for this technology is already pending.' => 'يوجد اقتراح قيد المراجعة لهذه التقنية.',
        // Evidence sources (B-06)
        'Sources' => 'المصادر',
        'What supports this record? Add a relevant link: a specific contribution, report, demo or repository you may share.' => 'ما الذي يدعم هذا السجل؟ أضف رابطًا ذا صلة: مساهمة محددة، أو تقرير، أو عرض عملي، أو مستودع مسموح لك بمشاركته.',
        '{count} of {limit} sources' => '{count} من {limit} مصادر',
        'Sources you added · not checked or verified.' => 'مصادر أضفتها أنت · لم تُفحص ولم يُتحقق من صحتها.',
        'No sources yet. Add a link to a specific contribution, report or demo.' => 'لا توجد مصادر بعد. أضف رابطًا لمساهمة محددة، أو تقرير، أو عرض عملي.',
        'Remove this source? This action cannot be undone.' => 'هل تريد إزالة هذا المصدر؟ لا يمكن التراجع عن هذا الإجراء.',
        'Remove source?' => 'إزالة المصدر؟',
        'Remove source' => 'إزالة المصدر',
        'Remove source {title}' => 'إزالة المصدر {title}',
        'Remove' => 'إزالة',
        'New source' => 'مصدر جديد',
        'A name describing the source' => 'اسم يوضح محتوى المصدر',
        'URL' => 'الرابط',
        'How does it support your contribution? (optional)' => 'كيف يدعم مساهمتك؟ (اختياري)',
        'Adding source…' => 'جارٍ إضافة المصدر…',
        'Add source' => 'إضافة مصدر',
        'Only add links you may share. Keep company secrets and access tokens out of this record.' => 'أضف روابط مسموحًا لك بمشاركتها فقط. لا تضع في هذا السجل أسرار شركة أو رموز دخول.',
        'This project already has 10 sources. Remove one before adding another.' => 'لهذا المشروع 10 مصادر بالفعل. أزل مصدرًا قبل إضافة آخر.',
        'Please enter valid text.' => 'يُرجى إدخال نص صالح.',
        'Keep the title to 120 characters or fewer.' => 'اجعل الاسم 120 حرفًا أو أقل.',
        'Keep the link to 500 characters or fewer.' => 'اجعل الرابط 500 حرف أو أقل.',
        'Keep the note to 300 characters or fewer.' => 'اجعل الملاحظة 300 حرف أو أقل.',
        'Give the source a name.' => 'اكتب اسمًا للمصدر.',
        'Please enter a valid HTTP or HTTPS URL.' => 'أدخل رابط HTTP أو HTTPS صحيحًا.',
        'Add a source' => 'أضف مصدرًا',
        'Manage sources' => 'إدارة المصادر',
        'Add a source to your evidence' => 'أضف مصدرًا يدعم أدلتك',
        'The evidence for this project is complete. Add a link that supports it: a report, a demo or a specific contribution you may share.' => 'أدلة هذا المشروع مكتملة. أضف رابطًا يدعمها: تقريرًا، أو عرضًا عمليًا، أو مساهمة محددة مسموحًا لك بمشاركتها.',
        'Evidence sources' => 'مصادر الأدلة',
    ];

    return $copy;
}
