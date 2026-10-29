<?php

declare(strict_types=1);

/** Synthetic inputs only. IDs, counts and reasons may be reported; text must not be logged. */
function evidenceText110CalibrationFixtures(): array
{
    require_once __DIR__ . '/../Legacy/cases/EvidenceTextEvaluationTest.php';
    $golden = json_decode((string) file_get_contents(__DIR__ . '/fixtures/evidence-hub-golden-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
    $materialize = new ReflectionMethod(EvidenceTextEvaluationTest::class, 'materializeGoldenInput');
    $rows = [];
    foreach ($golden['threshold_boundary_matrix'] as $matrix) {
        foreach ($matrix['cases'] as $case) {
            $case['generator_profile'] = $matrix['generator_profile'];
            $rows[] = ['id' => $case['id'], 'field' => $matrix['field'], 'value' => $materialize->invoke(null, $case, $golden['text_generator_profiles']), 'group' => 'existing synthetic boundary'];
        }
    }
    foreach (['text_policy_cases', 'repetition_cases'] as $family) {
        foreach ($golden[$family] as $case) {
            $rows[] = ['id' => $case['id'], 'field' => $case['field'], 'value' => $materialize->invoke(null, $case, $golden['text_generator_profiles']), 'group' => ($case['input_spec']['kind'] ?? null) === 'repeat_scalar' ? 'storage only' : 'existing policy'];
        }
    }
    foreach (EVIDENCE_TEXT_FIELD_POLICIES as $field => $policy) {
        foreach (['en' => ['a','b','c','d','e','f','g','h'], 'ar' => ['ا','ب','ج','د','ه','و','ز','ح']] as $language => $alphabet) {
            foreach ([-1, 0, 1] as $offset) {
                $grapheme = new ReflectionMethod(EvidenceTextEvaluationTest::class, 'graphemeBoundaryText');
                $distinct = new ReflectionMethod(EvidenceTextEvaluationTest::class, 'distinctBoundaryText');
                foreach ([
                    'graphemes' => $grapheme->invoke(null, $alphabet, $policy['minimum_content_graphemes'] + $offset, $policy['minimum_useful_tokens']),
                    'tokens' => $grapheme->invoke(null, $alphabet, $policy['minimum_content_graphemes'] + 12, $policy['minimum_useful_tokens'] + $offset),
                    'distinct' => $distinct->invoke(null, $alphabet, max($policy['minimum_useful_tokens'], $policy['minimum_distinct_tokens'] + 3), $policy['minimum_distinct_tokens'] + $offset),
                ] as $boundary => $value) {
                    $rows[] = ['id' => "INLINE-{$field}-{$language}-{$boundary}-{$offset}", 'field' => $field, 'value' => $value, 'group' => 'existing synthetic boundary'];
                }
            }
        }
        foreach ([0, 1] as $offset) $rows[] = ['id' => "INLINE-{$field}-max-{$offset}", 'field' => $field, 'value' => str_repeat('a', $policy['maximum_unicode_scalars'] + $offset), 'group' => 'storage only'];
    }
    $inline = [
        'non-scalar' => ['bad'], 'invalid-utf8' => "\xC3\x28", 'null' => null, 'empty' => " \t\r\n ",
        'composed' => 'Café 24 API users completed updates safely today', 'decomposed' => "Cafe\u{0301} 24 API users completed updates safely today",
        'tatweel' => 'مــــرحبا', 'arabic-diacritics' => 'كَتَبَ', 'arabic-bare' => 'كتب', 'arabic-digits' => '١٢٣', 'digits' => '123',
        'emoji-punctuation' => '😀 !!!', 'whitespace' => " alpha\tbeta\r\ngamma ", 'zwnj' => "می\u{200C}شود", 'zwj' => "a\u{200D}b",
        'zero-width' => "a\u{200B}b", 'word-joiner' => "a\u{2060}b", 'bom' => "a\u{FEFF}b", 'control' => "a\x01b", 'control85' => "a\u{0085}b", 'bidi' => "a\u{202E}b", 'isolate' => "a\u{2066}b",
        'placeholder-en' => "  TEST\t", 'placeholder-ar' => 'لاحقاً', 'placeholder-substring' => 'test project documentation with useful details',
        'repetition-ar' => 'مشروع مشروع مشروع خطة تنفيذ', 'repetition-case' => 'Build BUILD build plan delivery',
        'outcome-ar' => 'رفعت سرعة التحميل 35 بالمئة وخفضت أخطاء الطلبات خلال أسبوعين', 'outcome-en' => 'Improved page speed by 35 percent and reduced request failures',
        'diverse-nonsense' => 'blorf quax zindle mivora plenum drask velora quinset ramblex torvi nexora',
    ];
    foreach ($inline as $id => $value) {
        $field = in_array($id, ['non-scalar','invalid-utf8','null','empty','placeholder-en','placeholder-substring','diverse-nonsense'], true) ? 'problem_statement' : (in_array($id, ['placeholder-ar','repetition-ar','repetition-case','tatweel'], true) ? 'personal_role' : 'measurable_outcome');
        $rows[] = ['id' => 'INLINE-' . $id, 'field' => $field, 'value' => $value, 'group' => 'existing policy'];
    }
    foreach ([[59,100], [3,5], [61,100]] as [$dominant,$total]) {
        $method = new ReflectionMethod(EvidenceTextEvaluationTest::class, 'repetitionText');
        $rows[] = ['id' => "INLINE-repetition-{$dominant}-{$total}", 'field' => 'problem_statement', 'value' => $method->invoke(null, 'echo', 'other', $dominant, $total), 'group' => 'existing repetition'];
    }
    $naturalEn = 'Residents using the community library portal struggled to find available books because catalogue entries lacked consistent topic labels and branch opening times. Staff recorded reservations in separate spreadsheets, which created confusing queues during busy afternoons. Our interviews identified accessibility barriers for keyboard users and missing explanations when a title was already borrowed. The redesign needed clear navigation, searchable categories, reliable availability indicators, and helpful guidance for first time visitors. We collected anonymized journey observations in a synthetic study, compared common tasks, and documented practical constraints before proposing changes. A responsive interface allowed readers to explore recommendations, filter by subject, inspect loan periods, and choose a nearby collection point. Librarians reviewed the prototype against their daily routines and suggested simpler terminology for membership requirements. We separated presentation concerns from catalogue queries, validated every request, and handled temporary connection failures with understandable messages. Search results preserved the chosen filters while detailed pages explained renewal limits, waiting lists, and alternative editions. Carefully structured headings supported screen readers, visible focus outlines clarified keyboard movement, and concise summaries reduced unnecessary scrolling. A small evaluation session measured task completion time, unsuccessful searches, and reservation errors across several representative scenarios. Feedback informed adjustments to spacing, contrast, button labels, and confirmation messages. The final documentation described tradeoffs, test cases, deployment prerequisites, and future maintenance responsibilities without claiming results beyond the observations.';
    $naturalAr = 'واجه مستخدمو بوابة المكتبة المجتمعية صعوبة في العثور على الكتب المتاحة لأن سجلات الفهرس افتقرت إلى تصنيفات واضحة ومواعيد الفروع. سجل الموظفون الحجوزات في جداول منفصلة مما أدى إلى طوابير مربكة خلال ساعات الازدحام. كشفت المقابلات عن عوائق أمام مستخدمي لوحة المفاتيح وغياب التفسير عندما يكون الكتاب معاراً. احتاج التصميم الجديد إلى تنقل واضح وبحث مرن ومؤشرات موثوقة للتوفر وإرشادات مفيدة للزوار الجدد. جمع الفريق ملاحظات اصطناعية مجهولة الهوية وقارن المهام المعتادة ووثق القيود العملية قبل اقتراح الحلول. أتاحت الواجهة المتجاوبة استعراض التوصيات وتصفية العناوين حسب الموضوع وفحص مدة الإعارة واختيار نقطة استلام قريبة. راجع أمناء المكتبة النموذج الأولي وفق إجراءاتهم اليومية واقترحوا مصطلحات أبسط لمتطلبات العضوية. فصلنا العرض عن استعلامات الفهرسة وتحققنا من المدخلات وعالجنا انقطاع الاتصال برسائل مفهومة. احتفظت نتائج البحث بالمرشحات المختارة بينما شرحت صفحات التفاصيل حدود التجديد وقوائم الانتظار والطبعات البديلة. دعمت العناوين المنظمة قارئات الشاشة وحددت مؤشرات التركيز حركة التنقل وخففت الملخصات المختصرة الحاجة إلى التمرير. قاست جلسة التقييم زمن الإنجاز وعمليات البحث الفاشلة وأخطاء الحجز ضمن سيناريوهات متنوعة. وجهت الملاحظات تحسين المسافات والتباين وتسميات الأزرار ورسائل التأكيد. وصفت الوثائق النهائية المفاضلات وحالات الاختبار ومتطلبات التشغيل ومسؤوليات الصيانة دون ادعاء نتائج تتجاوز المشاهدات. قدمنا أمثلة تعليمية توضح التعامل مع الاستثناءات وأسباب القرارات التقنية والبدائل المتاحة عند نقص البيانات. ساعدت المراجعة اللغوية على إزالة الغموض وتوحيد أسماء الأقسام وتوضيح إجراءات التواصل مع الدعم. ركزت الخطة المستقبلية على الاستدامة وسهولة التطوير وإمكانية تتبع التغييرات وتوثيق الدروس المستفادة من التجربة.';
    $naturalEn .= ' Reviewers also examined offline behaviour, data retention choices, permission boundaries, administrative workflows, backup recovery exercises, and the clarity of support instructions for volunteers.';
    $naturalAr .= ' شملت المراجعة إجراءات النسخ الاحتياطي واستعادة الخدمة وتوزيع الصلاحيات وحماية الخصوصية ومراقبة الأعطال وتحديث دليل المتطوعين. قارنت اللجنة بدائل البحث المتقدم ودرست تكلفة الصيانة ومتطلبات التوسع وأثر التغييرات على سهولة الاستخدام. اختبر المشاركون نماذج مختلفة لشاشات التسجيل وإدارة الاشتراك وإرسال التنبيهات واختيار أوقات الزيارة المناسبة. أثمرت المناقشات خطة عملية تحدد الأولويات ومواعيد المراجعة ومعايير قبول التحسينات المقترحة.';
    // Near-limit natural prose: trim at a word boundary without inventing repeated padding.
    foreach (['en' => $naturalEn, 'ar' => $naturalAr] as $language => $value) {
        $value = mb_substr($value, 0, 1950, 'UTF-8');
        $value = preg_replace('/\s+\S*$/u', '', $value);
        $rows[] = ['id' => 'NATURAL-long-' . $language, 'field' => 'problem_statement', 'value' => $value, 'group' => 'natural'];
    }
    foreach (['special' => 'I documented www addresses, III milestones, C++ modules, 10000 records, the 2020-2024 timeline, Schifffahrt terminology, coffee breaks and keep alive connections for the delivery team.', 'stretch-en' => 'The library search interface was sooooo helpful because clear filters improved navigation and readers found available titles quickly.', 'stretch-ar' => 'كان تصميم بوابة المكتبة رااااائع لأنه ساعد القراء على العثور على الكتب المتاحة وفهم شروط الاستعارة بسهولة وكانت النتيجة رائع'] as $id => $value) {
        $rows[] = ['id' => 'NATURAL-' . $id, 'field' => 'problem_statement', 'value' => $value, 'group' => 'natural'];
    }
    foreach ([
        'stretch-ar' => 'زاد الأداء كثيراً جداااااااااااااا',
        'stretch-en' => 'The app was sooooooooooooo slow',
        'run-only-ar' => 'هههههههههههههههه خطة عمل تنفيذ هههههه',
        'run-only-en' => 'aaaaaaaaaaaaaaaa plan build ship zzzzzzz',
        'rotation-en' => implode(' ', array_fill(0, 5, 'The app was slow today')),
        'rotation-ar' => implode(' ', array_fill(0, 5, 'كان تطبيق العمل بطيئا اليوم')),
        'tatweel' => 'زاد الأداء كثيــــــــــــــــراً جدا',
        'punctuation' => 'زاد الأداء كثيراً جدا !!!!!!!!!!!!!!!!!!!!',
        'emoji' => 'زاد الأداء كثيراً جدا 😀😀😀😀😀😀😀😀😀😀',
    ] as $id => $value) $rows[] = ['id' => 'ADVERSARIAL-' . $id, 'field' => str_starts_with($id, 'rotation') ? 'problem_statement' : 'measurable_outcome', 'value' => $value, 'group' => in_array($id, ['tatweel','punctuation','emoji'], true) ? 'already harmless' : 'adversarial'];
    return $rows;
}

function evidenceText110ProjectFixtures(): array
{
    $paragraph = 'Readers could not locate available books because inconsistent catalogue labels and disconnected reservation records made the library portal difficult to navigate.';
    $outcome = 'Reservation errors dropped after clear confirmations guided readers through each collection step.';
    return [
        'exact-en' => ['values' => array_fill_keys(evidenceTextFieldNames(), $paragraph), 'duplicate_fields' => ['personal_role','measurable_outcome']],
        'exact-ar' => ['values' => array_fill_keys(evidenceTextFieldNames(), 'واجه القراء صعوبة في العثور على الكتب المتاحة بسبب غياب التصنيفات الواضحة وتشتت سجلات الحجز بين أقسام المكتبة المختلفة.'), 'duplicate_fields' => ['personal_role','measurable_outcome']],
        'contained-outcome' => ['values' => ['problem_statement' => $paragraph . ' ' . $outcome, 'personal_role' => 'I designed keyboard navigation and reviewed request validation with librarians during prototype testing.', 'measurable_outcome' => $outcome], 'duplicate_fields' => ['measurable_outcome']],
        'shared-phrase-en' => ['values' => ['problem_statement' => $paragraph, 'personal_role' => 'For the library portal I implemented accessible navigation, wrote validation tests, and reviewed catalogue queries.', 'measurable_outcome' => 'The library portal reduced reservation errors by 35 percent during the synthetic evaluation.'], 'duplicate_fields' => []],
        'shared-phrase-ar' => ['values' => ['problem_statement' => 'واجه القراء صعوبة في استخدام بوابة المكتبة بسبب غياب التصنيفات الواضحة وتشتت سجلات الحجز بين الأقسام المختلفة.', 'personal_role' => 'صممت التنقل عبر لوحة المفاتيح في بوابة المكتبة وراجعت استعلامات الفهرس مع الفريق التقني.', 'measurable_outcome' => 'خفضت بوابة المكتبة أخطاء الحجز بنسبة 35 بالمئة خلال التجربة الاصطناعية.'], 'duplicate_fields' => []],
        'few-shingles-contained' => ['values' => ['problem_statement' => $paragraph . ' Accelerated catalogue queries significantly.', 'personal_role' => null, 'measurable_outcome' => 'Accelerated catalogue queries significantly.'], 'duplicate_fields' => []],
        'few-shingles-exact' => ['values' => ['problem_statement' => null, 'personal_role' => 'Accelerated catalogue queries significantly.', 'measurable_outcome' => 'Accelerated catalogue queries significantly.'], 'duplicate_fields' => ['measurable_outcome']],
    ];
}
