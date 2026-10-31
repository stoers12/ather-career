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
 *   the same order (first match wins). A query replaces the request's own, as
 *   the former rewrites did; {name} inserts the named capture.
 * - not_found: the handler for every other path. The front controller sets
 *   status 404 before loading it.
 *
 * Adding a page: add the handler in handlers/ and one line here; nothing in
 * public/.
 * A closure keeps $slug out of the handler's file scope and lets tests load the
 * table repeatedly.
 */
return (static function (): array {
    $slug = '(?<slug>[a-z0-9][a-z0-9-]{1,62}[a-z0-9])';

    return [
        'scripts' => [
            'index.php' => 'handlers/index.php',
            'owner.php' => 'handlers/owner.php',
            'owner_auth_retry.php' => 'handlers/owner_auth_retry.php',
            'owner_evidence_hub.php' => 'handlers/owner_evidence_hub.php',
            'owner_experiences.php' => 'handlers/owner_experiences.php',
            'owner_login.php' => 'handlers/owner_login.php',
            'owner_logout.php' => 'handlers/owner_logout.php',
            'owner_media.php' => 'handlers/owner_media.php',
            'owner_messages.php' => 'handlers/owner_messages.php',
            'owner_oidc_callback.php' => 'handlers/owner_oidc_callback.php',
            'owner_onboarding.php' => 'handlers/owner_onboarding.php',
            'owner_onboarding_attestation.php' => 'handlers/owner_onboarding_attestation.php',
            'owner_onboarding_completion.php' => 'handlers/owner_onboarding_completion.php',
            'owner_onboarding_draft.php' => 'handlers/owner_onboarding_draft.php',
            'owner_onboarding_editor.php' => 'handlers/owner_onboarding_editor.php',
            'owner_preview.php' => 'handlers/owner_preview.php',
            'owner_profile.php' => 'handlers/owner_profile.php',
            'owner_project_evidence.php' => 'handlers/owner_project_evidence.php',
            'owner_projects.php' => 'handlers/owner_projects.php',
            'owner_publication.php' => 'handlers/owner_publication.php',
            'owner_register.php' => 'handlers/owner_register.php',
            'owner_settings.php' => 'handlers/owner_settings.php',
            'owner_switch_account.php' => 'handlers/owner_switch_account.php',
            'p.php' => 'handlers/public_portfolio.php',
            'p_contact.php' => 'handlers/public_contact.php',
            'p_media.php' => 'handlers/public_media.php',
            'p_project.php' => 'handlers/public_project.php',
            'p_projects.php' => 'handlers/public_projects_json.php',
            'privacy.php' => 'handlers/privacy.php',
            'terms.php' => 'handlers/terms.php',
            'signin.php' => 'handlers/signin.php',
            'signup.php' => 'handlers/signup.php',
        ],
        'patterns' => [
            ['pattern' => '#^/privacy/?$#', 'script' => 'privacy.php'],
            ['pattern' => '#^/terms/?$#', 'script' => 'terms.php'],
            ['pattern' => '#^/owner/evidence-hub/?$#', 'script' => 'owner_evidence_hub.php'],
            ['pattern' => '#^/owner/projects/[1-9][0-9]*/evidence/?$#', 'script' => 'owner_project_evidence.php'],
            ['pattern' => '#^/p/' . $slug . '/media/profile/?$#', 'script' => 'p_media.php', 'query' => ['slug' => '{slug}', 'type' => 'profile']],
            ['pattern' => '#^/p/' . $slug . '/media/project/(?<id>[1-9][0-9]*)/?$#', 'script' => 'p_media.php', 'query' => ['slug' => '{slug}', 'type' => 'project', 'id' => '{id}']],
            ['pattern' => '#^/p/' . $slug . '/projects/(?<id>[1-9][0-9]*)/?$#', 'script' => 'p_project.php', 'query' => ['slug' => '{slug}', 'id' => '{id}']],
            ['pattern' => '#^/p/' . $slug . '/projects\.json$#', 'script' => 'p_projects.php', 'query' => ['slug' => '{slug}']],
            ['pattern' => '#^/p/' . $slug . '/contact/?$#', 'script' => 'p_contact.php', 'query' => ['slug' => '{slug}']],
            ['pattern' => '#^/p/' . $slug . '/?$#', 'script' => 'p.php', 'query' => ['slug' => '{slug}']],
        ],
        'not_found' => 'handlers/not_found.php',
    ];
})();
