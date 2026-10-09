<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/landing_copy.php';
require_once __DIR__ . '/includes/landing_icons.php';

httpRegisterExceptionBoundary('index.php');
httpRequireMethod(['GET', 'HEAD']);

$locale = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'ar';
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $locale);
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo landingCopy('description', $locale); ?>">
    <meta name="color-scheme" content="light dark">
    <title><?php echo landingCopy('title', $locale); ?></title>
    <script src="<?php echo versionedAssetUrl('landing.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo versionedAssetUrl('landing.css'); ?>">
</head>
<body>
<a class="hf-skip" href="#hf-start"><?php echo landingCopy('skip', $locale); ?></a>
<div id="ather-hi-fi">
    <main class="hf-page" id="hf-page" aria-label="<?php echo landingCopy('main_label', $locale); ?>">
      <nav class="hf-nav" aria-label="<?php echo landingCopy('nav_label', $locale); ?>">
        <div class="hf-brand"><span class="hf-brand-mark"><?php echo landingIcon('fingerprint'); ?></span><span><?php echo landingCopy('brand', $locale); ?></span></div>
        <div class="hf-nav-links"><a class="hf-nav-link" href="#hf-why"><?php echo landingCopy('nav_why', $locale); ?></a><a class="hf-nav-link" href="#hf-how"><?php echo landingCopy('nav_how', $locale); ?></a><a class="hf-nav-link" href="#hf-stories"><?php echo landingCopy('nav_stories', $locale); ?></a></div>
        <div class="hf-nav-actions"><button class="hf-theme" type="button" aria-label="<?php echo landingCopy('theme', $locale); ?>" aria-pressed="false" hidden><?php echo landingIcon('moon'); ?></button><a class="hf-lang" href="?lang=<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" lang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" hreflang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" aria-label="<?php echo landingCopy('language', $locale); ?>" dir="auto"><?php echo $locale === 'ar' ? 'EN' : 'العربية'; ?></a><a class="hf-action hf-action-secondary" href="owner_login.php"><?php echo landingCopy('sign_in', $locale); ?></a><a class="hf-action hf-action-primary" href="#hf-signin"><?php echo landingCopy('start', $locale); ?></a></div>
        <button class="hf-menu-trigger" id="hf-menu-trigger" type="button" aria-expanded="false" aria-controls="hf-mobile-menu" aria-label="<?php echo landingCopy('menu_open', $locale); ?>" data-open-label="<?php echo landingCopy('menu_open', $locale); ?>" data-close-label="<?php echo landingCopy('menu_close', $locale); ?>" hidden><?php echo landingIcon('menu'); ?></button>
      </nav>
      <div class="hf-mobile-menu" id="hf-mobile-menu"><a class="hf-lang" href="?lang=<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" lang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" hreflang="<?php echo $locale === 'ar' ? 'en' : 'ar'; ?>" aria-label="<?php echo landingCopy('language', $locale); ?>" dir="auto"><?php echo $locale === 'ar' ? 'EN' : 'العربية'; ?></a><button class="hf-theme" type="button" aria-label="<?php echo landingCopy('theme', $locale); ?>" aria-pressed="false" hidden><?php echo landingIcon('moon'); ?></button><a href="#hf-why"><?php echo landingCopy('nav_why', $locale); ?></a><a href="#hf-how"><?php echo landingCopy('nav_how', $locale); ?></a><a href="#hf-stories"><?php echo landingCopy('nav_stories', $locale); ?></a><a class="hf-action hf-action-secondary" href="owner_login.php"><?php echo landingCopy('sign_in', $locale); ?></a><a class="hf-action hf-action-primary" href="#hf-signin"><?php echo landingCopy('start', $locale); ?></a></div>

      <section class="hf-hero" id="hf-start" tabindex="-1">
        <div>
          <div class="hf-eyebrow"><?php echo landingCopy('hero_eyebrow', $locale); ?></div>
          <h1><?php echo landingCopy('hero_heading', $locale); ?><br><span class="hf-gradient-text"><?php echo landingCopy('hero_impact', $locale); ?></span></h1>
          <p class="hf-lead"><?php echo landingCopy('hero_lead', $locale); ?></p>
          <div class="hf-hero-actions"><a class="hf-action hf-action-primary" href="#hf-how"><?php echo landingIcon('sparkles'); ?><?php echo landingCopy('explore', $locale); ?></a><a class="hf-action hf-action-secondary" href="#hf-stories"><?php echo landingIcon('play-circle'); ?><?php echo landingCopy('see_how', $locale); ?></a></div>
          <div class="hf-beta-note"><?php echo landingIcon('map-pin'); ?><?php echo landingCopy('beta', $locale); ?></div>
        </div>

        <div class="hf-profile-wrap">
          <div class="hf-orbit" aria-hidden="true"></div>
          <article class="hf-profile" aria-label="<?php echo landingCopy('example_label', $locale); ?>">
            <div class="hf-profile-cover"></div>
            <div class="hf-profile-body">
              <div class="hf-avatar"><?php echo landingIcon('user'); ?></div>
              <div class="hf-profile-name"><?php echo landingCopy('example_name', $locale); ?></div>
              <div class="hf-profile-role"><?php echo landingCopy('example_role', $locale); ?></div>
              <div class="hf-project">
                <div class="hf-project-head"><div><div class="hf-project-title"><bdi dir="ltr"><?php echo landingCopy('careerfit', $locale); ?></bdi></div><div class="hf-project-type"><?php echo landingCopy('example_type', $locale); ?></div></div><span class="hf-proof-badge"><?php echo landingIcon('badge-check'); ?><?php echo landingCopy('example_badge', $locale); ?></span></div>
                <div class="hf-proof-grid">
                  <div class="hf-proof"><div class="hf-proof-label"><?php echo landingCopy('problem', $locale); ?></div><div class="hf-proof-value"><?php echo landingCopy('example_problem', $locale); ?></div></div>
                  <div class="hf-proof"><div class="hf-proof-label"><?php echo landingCopy('my_role', $locale); ?></div><div class="hf-proof-value"><?php echo landingCopy('example_my_role', $locale); ?></div></div>
                  <div class="hf-proof"><div class="hf-proof-label"><?php echo landingCopy('outcome', $locale); ?></div><div class="hf-proof-value"><?php echo landingCopy('example_outcome', $locale); ?></div></div>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section class="hf-principles" id="hf-why"><div class="hf-principle-title"><?php echo landingCopy('principles_heading', $locale); ?></div><div class="hf-principle"><?php echo landingIcon('search-check'); ?><?php echo landingCopy('principle_why', $locale); ?></div><div class="hf-principle"><?php echo landingIcon('fingerprint'); ?><?php echo landingCopy('principle_role', $locale); ?></div><div class="hf-principle"><?php echo landingIcon('route'); ?><?php echo landingCopy('principle_learning', $locale); ?></div></section>

      <section class="hf-section is-surface" id="hf-how">
        <div class="hf-section-head"><div><div class="hf-eyebrow"><?php echo landingCopy('how_eyebrow', $locale); ?></div><h2><?php echo landingCopy('how_heading', $locale); ?></h2></div><p class="hf-section-copy"><?php echo landingCopy('how_copy', $locale); ?></p></div>
        <div class="hf-steps">
          <article class="hf-step"><div class="hf-step-number"><?php echo landingCopy('step_one', $locale); ?></div><h3><?php echo landingCopy('step_one_heading', $locale); ?></h3><p><?php echo landingCopy('step_one_copy', $locale); ?></p></article>
          <article class="hf-step"><div class="hf-step-number"><?php echo landingCopy('step_two', $locale); ?></div><h3><?php echo landingCopy('step_two_heading', $locale); ?></h3><p><?php echo landingCopy('step_two_copy', $locale); ?></p></article>
          <article class="hf-step"><div class="hf-step-number"><?php echo landingCopy('step_three', $locale); ?></div><h3><?php echo landingCopy('step_three_heading', $locale); ?></h3><p><?php echo landingCopy('step_three_copy', $locale); ?></p></article>
        </div>
      </section>

      <section class="hf-section" id="hf-stories">
        <div class="hf-story">
          <div><div class="hf-eyebrow"><?php echo landingCopy('evidence_eyebrow', $locale); ?></div><h2><?php echo landingCopy('evidence_heading', $locale); ?></h2><p class="hf-section-copy"><?php echo landingCopy('evidence_copy', $locale); ?></p><a class="hf-action hf-action-secondary" href="#hf-values"><?php echo landingCopy('evidence_action', $locale); ?></a></div>
          <div class="hf-story-card">
            <div class="hf-story-row"><span class="hf-story-icon"><?php echo landingIcon('circle-help'); ?></span><div><div class="hf-story-label"><?php echo landingCopy('problem', $locale); ?></div><div class="hf-story-value"><?php echo landingCopy('evidence_problem', $locale); ?></div></div></div>
            <div class="hf-story-row"><span class="hf-story-icon"><?php echo landingIcon('user-check'); ?></span><div><div class="hf-story-label"><?php echo landingCopy('personal_role', $locale); ?></div><div class="hf-story-value"><?php echo landingCopy('evidence_role', $locale); ?></div></div></div>
            <div class="hf-story-row"><span class="hf-story-icon"><?php echo landingIcon('target'); ?></span><div><div class="hf-story-label"><?php echo landingCopy('outcome', $locale); ?></div><div class="hf-story-value"><?php echo landingCopy('evidence_outcome', $locale); ?></div></div></div>
          </div>
        </div>
      </section>

      <section class="hf-section is-surface" id="hf-values">
        <div class="hf-section-head"><div><div class="hf-eyebrow"><?php echo landingCopy('values_eyebrow', $locale); ?></div><h2><?php echo landingCopy('values_heading', $locale); ?></h2></div><p class="hf-section-copy"><?php echo landingCopy('values_copy', $locale); ?></p></div>
        <div class="hf-values">
          <article class="hf-value"><div class="hf-value-icon"><?php echo landingIcon('archive'); ?></div><h3><?php echo landingCopy('memory_heading', $locale); ?></h3><p><?php echo landingCopy('memory_copy', $locale); ?></p></article>
          <article class="hf-value"><div class="hf-value-icon"><?php echo landingIcon('megaphone'); ?></div><h3><?php echo landingCopy('ethical_heading', $locale); ?></h3><p><?php echo landingCopy('ethical_copy', $locale); ?></p></article>
          <article class="hf-value"><div class="hf-value-icon"><?php echo landingIcon('trending-up'); ?></div><h3><?php echo landingCopy('growth_heading', $locale); ?></h3><p><?php echo landingCopy('growth_copy', $locale); ?></p></article>
        </div>
      </section>

      <section class="hf-section">
        <div class="hf-privacy"><div><div class="hf-eyebrow"><?php echo landingCopy('privacy_eyebrow', $locale); ?></div><h2><?php echo landingCopy('privacy_heading', $locale); ?></h2><p><?php echo landingCopy('privacy_copy', $locale); ?></p></div><div class="hf-privacy-list"><div class="hf-privacy-item"><?php echo landingIcon('lock'); ?><?php echo landingCopy('private_build', $locale); ?></div><div class="hf-privacy-item"><?php echo landingIcon('eye'); ?><?php echo landingCopy('preview', $locale); ?></div><div class="hf-privacy-item"><?php echo landingIcon('sliders-horizontal'); ?><?php echo landingCopy('visibility', $locale); ?></div><div class="hf-privacy-item"><?php echo landingIcon('phone-off'); ?><?php echo landingCopy('phone_private', $locale); ?></div></div></div>
      </section>

      <section class="hf-final" id="hf-signin" aria-describedby="hf-availability"><h2><?php echo landingCopy('final_heading', $locale); ?><br><?php echo landingCopy('final_impact', $locale); ?></h2><p><?php echo landingCopy('final_copy', $locale); ?></p><p class="hf-availability" id="hf-availability"><?php echo landingCopy('availability', $locale); ?></p><a class="hf-action hf-action-primary" href="owner_login.php"><?php echo landingIcon('arrow-left'); ?><?php echo landingCopy('existing_sign_in', $locale); ?></a></section>

      <footer class="hf-footer"><div class="hf-brand"><span class="hf-brand-mark"><?php echo landingIcon('fingerprint'); ?></span><span><?php echo landingCopy('footer_brand', $locale); ?></span></div><div class="hf-footer-links"><span><?php echo landingCopy('footer_privacy', $locale); ?></span><span><?php echo landingCopy('footer_terms', $locale); ?></span><span><?php echo landingCopy('footer_security', $locale); ?></span><span><?php echo landingCopy('footer_contact', $locale); ?></span></div><span><?php echo landingCopy('footer_tagline', $locale); ?></span></footer>
    </main>
</div>
</body>
</html>
