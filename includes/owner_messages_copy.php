<?php

declare(strict_types=1);

/** Message presentation copy; sender content is never translated. */
function ownerMessagesCopy(string $key, string $locale): string
{
    static $copy = [
        'title' => ['ar' => 'الرسائل', 'en' => 'Messages'],
        'subtitle' => ['ar' => 'مساحة واضحة لمراجعة فرص التواصل.', 'en' => 'A clear space to review new conversations.'],
        'list' => ['ar' => 'قائمة الرسائل', 'en' => 'Message list'],
        'unread' => ['ar' => 'غير مقروءة', 'en' => 'Unread'],
        'read' => ['ar' => 'مقروءة', 'en' => 'Read'],
        'message' => ['ar' => 'رسالة', 'en' => 'Message'],
        'back' => ['ar' => 'العودة إلى الرسائل', 'en' => 'Back to messages'],
        'mark_unread' => ['ar' => 'وضع علامة غير مقروءة', 'en' => 'Mark as unread'],
        'marking_unread' => ['ar' => 'جارٍ وضع علامة غير مقروءة…', 'en' => 'Marking as unread…'],
        'reply' => ['ar' => 'الرد عبر البريد', 'en' => 'Reply by email'],
        'reply_subject' => ['ar' => 'رد: رسالة البورتفوليو', 'en' => 'Re: Portfolio message'],
        'choose_title' => ['ar' => 'اختر رسالة لقراءتها', 'en' => 'Choose a message to read'],
        'choose_hint' => ['ar' => 'تظهر هنا الرسالة التي تفتحها من القائمة.', 'en' => 'Open a message from the list to read it here.'],
        'empty_title' => ['ar' => 'صندوق رسائلك فارغ', 'en' => 'Your inbox is clear'],
        'empty_hint' => ['ar' => 'ستظهر رسائل التواصل الجديدة هنا.', 'en' => 'New contact messages will appear here.'],
    ];

    return $copy[$key][$locale === 'en' ? 'en' : 'ar'] ?? $key;
}
