<?php

declare(strict_types=1);

final class EvidenceTextOwnerHintsTest
{
    public static function run(): void
    {
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_owner_page_model.php';
        require_once PHASE2_REPOSITORY_ROOT . '/includes/evidence_hub_copy.php';
        foreach ([
            'CHARACTER_RUN_SUSPECTED' => ["Stretched letters don't count. Add real details instead.",'الحروف المكررة لا تُحتسب. أضف تفاصيل حقيقية بدلاً منها.'],
            'LOW_DISTINCT_TOKEN_RATIO' => ['The same few words repeat. Describe it in more varied, specific words.','تتكرر الكلمات نفسها كثيراً. صِف بكلمات أكثر تنوعاً وتحديداً.'],
            'DUPLICATED_ACROSS_FIELDS' => ['This repeats another field of the same project. Write what is specific to this field.','هذا النص يكرر حقلاً آخر في المشروع نفسه. اكتب ما يخص هذا الحقل تحديداً.'],
        ] as $reason => [$english,$arabic]) {
            phase2AssertSame($english,evidenceHubOwnerPageFieldReason(['reason_codes'=>[$reason]],'Problem'),'The approved English hint changed.');
            phase2AssertSame($arabic,evidenceHubText($english,'ar'),'The approved Arabic hint changed or is missing.');
            phase2AssertSame($english,evidenceHubText($english,'unsupported'),'Unsupported locales must remain English.');
            foreach (['public_projects_json.php','public/p_projects.php','includes/public_lifecycle.php','public/assets/js/portfolio.js'] as $path) {
                $source = (string) file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
                phase2Assert(!str_contains($source,$reason) && !str_contains($source,$english),'An Owner hint reached a visitor route.');
            }
        }
    }
}
