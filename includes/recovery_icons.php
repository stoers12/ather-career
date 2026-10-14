<?php

declare(strict_types=1);

require_once __DIR__ . '/account_entry_icons.php';

/** Recovery-only Lucide shapes; the shared icon catalog remains unchanged. */
function recoveryIcon(string $name): string
{
    static $paths = [
        'lock-keyhole' => '<circle cx="12" cy="16" r="1"/><rect x="3" y="10" width="18" height="12" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3"/>',
        'folder-lock' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M2 7V5a2 2 0 0 1 2-2h4l2 4"/><rect x="9" y="13" width="6" height="5" rx="1"/><path d="M11 13v-1a1 1 0 0 1 2 0v1"/>',
        'mail-key' => '<path d="M22 10.5V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h9"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/><circle cx="18" cy="16" r="2"/><path d="m20 16 2 2-1 1"/>',
        'key-round' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'check' => '<path d="m20 6-11 11-5-5"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    ];
    if (!isset($paths[$name])) {
        return accountEntryIcon($name);
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" class="hf-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}
