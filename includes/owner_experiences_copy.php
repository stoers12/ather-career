<?php

declare(strict_types=1);

/** Experience presentation only; owner-authored content is never translated. */
function ownerExperiencesCopy(string $key, string $locale): string
{
    static $copy = [
        'title' => ['ar' => 'الخبرات', 'en' => 'Experience'],
        'subtitle' => ['ar' => 'اعرض مسارك، مع توضيح نوع كل تجربة ومدتها.', 'en' => 'Show your journey, with the type and dates of each experience.'],
        'add' => ['ar' => 'إضافة خبرة', 'en' => 'Add experience'],
        'edit_title' => ['ar' => 'تعديل الخبرة', 'en' => 'Edit experience'],
        'editor_subtitle' => ['ar' => 'يمكن أن تكون وظيفة أو تدريبًا أو تجربة قيادية.', 'en' => 'It can be a job, training, or a leadership experience.'],
        'role_title' => ['ar' => 'المسمى', 'en' => 'Role / Title'],
        'organization' => ['ar' => 'الجهة', 'en' => 'Organization'],
        'location' => ['ar' => 'الموقع', 'en' => 'Location'],
        'experience_type' => ['ar' => 'نوع الخبرة', 'en' => 'Experience type'],
        'select_type' => ['ar' => 'اختر نوع الخبرة', 'en' => 'Select type'],
        'employment' => ['ar' => 'وظيفة', 'en' => 'Employment'],
        'training' => ['ar' => 'تدريب', 'en' => 'Training'],
        'internship' => ['ar' => 'تدريب عملي', 'en' => 'Internship'],
        'volunteer' => ['ar' => 'عمل تطوعي', 'en' => 'Volunteer'],
        'leadership' => ['ar' => 'قيادة', 'en' => 'Leadership'],
        'start_month' => ['ar' => 'تاريخ البداية', 'en' => 'Start month'],
        'end_month' => ['ar' => 'تاريخ النهاية', 'en' => 'End month'],
        'required' => ['ar' => 'مطلوب', 'en' => 'Required'],
        'optional' => ['ar' => 'اختياري', 'en' => 'Optional'],
        'end_required' => ['ar' => 'مطلوب ما لم تكن مستمرة', 'en' => 'Required unless current'],
        'end_hint' => ['ar' => 'اتركه فارغًا إذا كانت مستمرة. تاريخ النهاية مطلوب للخبرة المنتهية.', 'en' => 'Leave empty if ongoing. An end month is required for an ended experience.'],
        'is_current' => ['ar' => 'هذه الخبرة مستمرة', 'en' => 'This experience is ongoing'],
        'present' => ['ar' => 'حتى الآن', 'en' => 'Present'],
        'description' => ['ar' => 'الوصف', 'en' => 'Description'],
        'description_hint' => ['ar' => 'أضف وصفًا مختصرًا لدورك ومساهمتك إن رغبت.', 'en' => 'Optionally describe your role and contribution.'],
        'save' => ['ar' => 'حفظ الخبرة', 'en' => 'Save experience'],
        'saving' => ['ar' => 'جارٍ حفظ الخبرة…', 'en' => 'Saving experience…'],
        'saved' => ['ar' => 'تم الحفظ', 'en' => 'Saved'],
        'cancel' => ['ar' => 'إلغاء', 'en' => 'Cancel'],
        'edit' => ['ar' => 'تعديل', 'en' => 'Edit'],
        'delete' => ['ar' => 'حذف', 'en' => 'Delete'],
        'delete_title' => ['ar' => 'حذف الخبرة؟', 'en' => 'Delete experience?'],
        'delete_action' => ['ar' => 'حذف الخبرة', 'en' => 'Delete experience'],
        'delete_confirm' => ['ar' => 'هل تريد حذف «%s» لدى «%s»؟ لا يمكن التراجع عن هذا الإجراء.', 'en' => 'Delete “%s” at “%s”? This action cannot be undone.'],
        'deleting' => ['ar' => 'جارٍ حذف الخبرة…', 'en' => 'Deleting experience…'],
        'empty_title' => ['ar' => 'لا توجد خبرات بعد', 'en' => 'No experience records yet'],
        'empty_hint' => ['ar' => 'أضف خبرة عندما تكون مستعدًا.', 'en' => 'Add an experience record when you are ready.'],
        'empty_action' => ['ar' => 'إضافة أول خبرة', 'en' => 'Add your first experience'],
    ];
    return $copy[$key][$locale === 'en' ? 'en' : 'ar'];
}
