<?php
declare(strict_types=1);
require_once __DIR__ . '/final_profile_fields.php';

function finalProfileEscape(string $value): string
{
    // Forged invalid UTF-8 cannot be rendered literally; substitute for feedback
    // only. The validator rejects it and no replacement text is ever stored.
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderFinalProfileFields(array $profile, array $errors): void
{
    $catalog = finalProfileCatalog();
    $controls = [
        'country_code' => ['countries', 'Professional location country/territory / بلد أو إقليم الموقع المهني', 'Self-declared professional location, not nationality. / موقعك المهني الذي تصفه بنفسك، وليس الجنسية.'],
        'preferred_language' => ['languages', 'Profile language preference / اللغة المفضلة للملف', 'A profile preference; does not translate content or change the interface. / تفضيل للملف، لا يترجم المحتوى ولا يغيّر لغة الواجهة.'],
        'professional_status' => ['statuses', 'Professional status / الوضع المهني', 'Choose one self-description. Overlap is possible; no option has priority or affects access. / اختر وصفًا واحدًا لنفسك؛ قد تتداخل الأوصاف ولا أولوية بينها ولا أثر لها على صلاحية الوصول.'],
    ];
    echo '<h2 class="form-section-title">Private professional details / تفاصيل مهنية خاصة</h2>';
    foreach ($controls as $field => [$group, $label, $hint]) {
        $value = (string) ($profile[$field] ?? '');
        echo '<label class="form-field" for="' . $field . '"><span dir="auto">' . finalProfileEscape($label) . ' <em>(optional / اختياري)</em></span>';
        echo '<select id="' . $field . '" name="' . $field . '"' . ownerFieldAccessibilityAttributes($errors, $field, $field . '-help') . '>';
        echo '<option value="">Not specified / غير محدد</option>';
        if ($value !== '' && !array_key_exists($value, $catalog[$group])) {
            echo '<option value="' . finalProfileEscape($value) . '" selected>Saved/entered value outside catalog / قيمة خارج القائمة: ' . finalProfileEscape($value) . '</option>';
        }
        foreach ($catalog[$group] as $token => $names) {
            echo '<option value="' . finalProfileEscape($token) . '"' . ($value === $token ? ' selected' : '') . '>'
                . finalProfileEscape($names['en'] . ' — ' . $names['ar']) . '</option>';
        }
        echo '</select><small class="form-hint" dir="auto" id="' . $field . '-help">' . finalProfileEscape($hint) . '</small></label>';
        ownerRenderFieldError($errors, $field);
    }
    // HTML maxlength counts UTF-16 units; server validation counts code points.
    echo '<label class="form-field" for="specialization"><span>Specialization / التخصص <em>(optional / اختياري)</em></span>'
        . '<input type="text" id="specialization" name="specialization" dir="auto" value="' . finalProfileEscape((string) ($profile['specialization'] ?? '')) . '"'
        . ownerFieldAccessibilityAttributes($errors, 'specialization', 'specialization-help') . '>'
        . '<small class="form-hint" dir="auto" id="specialization-help">Field of work or study, distinct from your professional title. Up to 150 Unicode characters / 600 UTF-8 bytes; text is kept exactly. These four details stay private. / مجال العمل أو الدراسة، ويختلف عن المسمى المهني. حتى 150 محرف Unicode و600 بايت UTF-8 دون تعديل النص. تبقى هذه التفاصيل الأربعة خاصة.</small></label>';
    ownerRenderFieldError($errors, 'specialization');
}
