<?php

declare(strict_types=1);

/*
 * The application's route table (ENG-09). public/index.php is the only web
 * entry point; it resolves every request through this table.
 *
 * - scripts: each former public/ entry script => its handler file (relative to
 *   the application root). The entry name stays the request's identity and its
 *   URL keeps working, with an optional trailing PATH_INFO as under Apache.
 *   /owner_oidc_callback.php is registered in Auth0 and must never change.
 * - patterns: the pretty URLs that docker/apache/vhost.conf used to rewrite, in
 *   the same order (first match wins). A query replaces the request's own, as the
 *   former rewrites did; {name} inserts the named capture.
 *
 * Adding a page: add the handler file and one line here; nothing in public/.
 * A closure keeps $slug out of the handler's file scope and lets tests load the
 * table repeatedly.
 */
return (static function (): array {
    $slug = '(?<slug>[a-z0-9][a-z0-9-]{1,62}[a-z0-9])';

    return [
        'scripts' => [
            'index.php' => 'index.php',
            'owner.php' => 'owner.php',
            'owner_auth_retry.php' => 'owner_auth_retry.php',
            'owner_evidence_hub.php' => 'owner_evidence_hub.php',
            'owner_experiences.php' => 'owner_experiences.php',
            'owner_login.php' => 'owner_login.php',
            'owner_logout.php' => 'owner_logout.php',
            'owner_media.php' => 'owner_media.php',
            'owner_messages.php' => 'owner_messages.php',
            'owner_oidc_callback.php' => 'owner_oidc_callback.php',
            'owner_onboarding.php' => 'owner_onboarding.php',
            'owner_onboarding_attestation.php' => 'owner_onboarding_attestation.php',
            'owner_onboarding_completion.php' => 'owner_onboarding_completion.php',
            'owner_onboarding_draft.php' => 'owner_onboarding_draft.php',
            'owner_onboarding_editor.php' => 'owner_onboarding_editor.php',
            'owner_preview.php' => 'owner_preview.php',
            'owner_profile.php' => 'owner_profile.php',
            'owner_project_evidence.php' => 'owner_project_evidence.php',
            'owner_projects.php' => 'owner_projects.php',
            'owner_publication.php' => 'owner_publication.php',
            'owner_register.php' => 'owner_register.php',
            'owner_switch_account.php' => 'owner_switch_account.php',
            'p.php' => 'public_portfolio.php',
            'p_contact.php' => 'public_contact.php',
            'p_media.php' => 'public_media.php',
            'p_projects.php' => 'public_projects_json.php',
            'signin.php' => 'signin.php',
            'signup.php' => 'signup.php',
        ],
        'patterns' => [
            ['pattern' => '#^/owner/evidence-hub/?$#', 'script' => 'owner_evidence_hub.php'],
            ['pattern' => '#^/owner/projects/[1-9][0-9]*/evidence/?$#', 'script' => 'owner_project_evidence.php'],
            ['pattern' => '#^/p/' . $slug . '/media/profile/?$#', 'script' => 'p_media.php', 'query' => ['slug' => '{slug}', 'type' => 'profile']],
            ['pattern' => '#^/p/' . $slug . '/media/project/(?<id>[1-9][0-9]*)/?$#', 'script' => 'p_media.php', 'query' => ['slug' => '{slug}', 'type' => 'project', 'id' => '{id}']],
            ['pattern' => '#^/p/' . $slug . '/projects\.json$#', 'script' => 'p_projects.php', 'query' => ['slug' => '{slug}']],
            ['pattern' => '#^/p/' . $slug . '/contact/?$#', 'script' => 'p_contact.php', 'query' => ['slug' => '{slug}']],
            ['pattern' => '#^/p/' . $slug . '/?$#', 'script' => 'p.php', 'query' => ['slug' => '{slug}']],
        ],
    ];
})();
