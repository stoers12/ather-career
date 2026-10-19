<?php
declare(strict_types=1);

const FINAL_PROFILE_FIELDS = ['country_code', 'preferred_language', 'professional_status', 'specialization'];

/** Checked-in, reviewed candidate data. No request input or runtime network. */
function finalProfileCatalog(): array
{
    static $catalog;
    return $catalog ??= json_decode(file_get_contents(__DIR__ . '/../data/profile-catalog/catalog.json'), true, 32, JSON_THROW_ON_ERROR);
}

/** Pure final-storage validator: NULL is optional; strings are preserved exactly.
 * Empty HTML form text is mapped to NULL by the form boundary, not here.
 */
function finalProfileFieldError(string $field, mixed $value, array $catalog): ?string
{
    if (!in_array($field, FINAL_PROFILE_FIELDS, true)) { throw new InvalidArgumentException('Unknown final profile field.'); }
    if ($value === null) { return null; }
    if (!is_string($value)) { return 'Use text for this field. / أدخل نصًا في هذا الحقل.'; }
    if ($field !== 'specialization') {
        $group = match ($field) { 'country_code' => 'countries', 'preferred_language' => 'languages', default => 'statuses' };
        return array_key_exists($value, $catalog[$group]) ? null
            : 'Choose an option from the list. / اختر خيارًا من القائمة.';
    }
    if (preg_match('//u', $value) !== 1 || strlen($value) > 600 || mb_strlen($value, 'UTF-8') > 150
        || preg_match('/\p{Cc}/u', $value) === 1 || preg_match('/[^\p{Z}\s]/u', $value) !== 1) {
        return 'Enter up to 150 characters with visible text and no line breaks, or leave this blank. / أدخل نصًا ظاهرًا لا يتجاوز 150 حرفًا دون فواصل أسطر، أو اترك الحقل فارغًا.';
    }
    return null;
}
