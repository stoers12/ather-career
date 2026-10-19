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
        'country_code' => ['countries', 'Country/territory / البلد أو الإقليم', 'Where you work or study. / بلد أو إقليم عملك أو دراستك.'],
        'preferred_language' => ['languages', 'Preferred profile language / اللغة المفضلة للملف', 'Your preference for this profile. / اللغة التي تفضلها لهذا الملف.'],
        'professional_status' => ['statuses', 'Professional status / الوضع المهني', 'Choose the closest description of your current situation. / اختر الوصف الأقرب لوضعك المهني الحالي.'],
    ];
    echo '<h2 class="form-section-title">Private professional details / تفاصيل مهنية خاصة</h2>';
    foreach ($controls as $field => [$group, $label, $hint]) {
        $value = (string) ($profile[$field] ?? '');
        echo '<label class="form-field" for="' . $field . '"><span dir="auto">' . finalProfileEscape($label) . ' <em>(optional / اختياري)</em></span>';
        echo '<select id="' . $field . '" name="' . $field . '"' . ownerFieldAccessibilityAttributes($errors, $field, $field . '-help') . '>';
        echo '<option value="">Not specified / غير محدد</option>';
        if ($value !== '' && !array_key_exists($value, $catalog[$group])) {
            $choiceHint = isset($errors[$field]) ? 'Unlisted choice / خيار خارج القائمة: ' : 'Older saved choice / اختيار محفوظ سابقًا: ';
            echo '<option value="' . finalProfileEscape($value) . '" selected>' . $choiceHint . finalProfileEscape($value) . '</option>';
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
        . '<small class="form-hint" dir="auto" id="specialization-help">Your field of work or study, distinct from your professional title. Up to 150 characters. These four details stay private. / مجال عملك أو دراستك، ويختلف عن مسماك المهني. حتى 150 حرفًا. تبقى هذه التفاصيل الأربعة خاصة.</small></label>';
    ownerRenderFieldError($errors, 'specialization');
}
