<?php

declare(strict_types=1);

require_once __DIR__ . '/evidence_hub_contract_mapper.php';
require_once __DIR__ . '/owner_layout.php';

/** @param array<string, mixed> $contract */
function renderEvidenceHubOwnerPresentation(array $contract, array $actionTokens = [], string $feedback = ''): void
{
    try {
        evidenceHubContractAssertFrozenV1($contract);
    } catch (EvidenceHubContractMappingException) {
        renderEvidenceHubOwnerSafeError();
        return;
    }
    $documentation = $contract['metrics']['documentation_coverage'];
    $technology = $contract['metrics']['technology_evidence_map'];
    $progress = $contract['metrics']['portfolio_progress'];
    ?>
<div class="evidence-hub-presentation evidence-hub-dashboard" dir="rtl">
    <header class="evidence-hub-hero">
        <div class="evidence-hub-hero-copy">
            <p class="evidence-hub-kicker"><?php echo evidenceHubOwnerIcon('spark'); ?> مساحة العمل الخاصة</p>
            <h1>مركز الأدلة</h1>
            <p>مساحة هادئة لفهم جودة توثيق ملف أعمالك، وما يستحق اهتمامك بعد ذلك.</p>
        </div>
        <div class="evidence-hub-hero-actions">
            <span class="evidence-hub-hero-state"><?php echo evidenceHubOwnerIcon(evidenceHubOwnerMaturityIcon($contract['maturity']['state'])); ?><?php echo ownerEscapeHtml(evidenceHubOwnerMaturityLabel($contract['maturity']['state'])); ?></span>
            <a class="evidence-hub-project-link evidence-hub-primary-cta" href="/owner_projects.php"><span>إدارة المشاريع</span><?php echo evidenceHubOwnerIcon('arrow'); ?></a>
        </div>
    </header>
    <?php if ($feedback !== ''): ?><p class="evidence-hub-feedback" role="status" aria-live="polite" tabindex="-1" id="evidence-hub-action-feedback"><?php echo ownerEscapeHtml($feedback); ?></p><?php endif; ?>
    <section class="evidence-hub-section" aria-labelledby="evidence-hub-status-title">
        <div class="evidence-hub-section-heading"><div><p>لقطة سريعة</p><h2 id="evidence-hub-status-title">حالة ملف الأعمال</h2></div><span>مبنية على السجل الحالي</span></div>
        <div class="evidence-hub-status" aria-label="مقاييس حالة ملف الأعمال">
            <article class="evidence-hub-status-card evidence-hub-status-card--maturity"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon(evidenceHubOwnerMaturityIcon($contract['maturity']['state'])); ?></span><div><span>جاهزية الملف</span><strong><?php echo ownerEscapeHtml(evidenceHubOwnerMaturityLabel($contract['maturity']['state'])); ?></strong><small><?php echo ownerEscapeHtml(evidenceHubOwnerStatusLabel($documentation['status'])); ?></small></div></article>
            <article class="evidence-hub-status-card evidence-hub-status-card--documentation"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon('document-check'); ?></span><div><span>التوثيق</span><strong data-bidi-number dir="ltr"><?php echo $documentation['coverage_bps'] === null ? '—' : (int) $documentation['coverage_bps'] / 100; ?><?php echo $documentation['coverage_bps'] === null ? '' : '%'; ?></strong><small><bdi data-bidi-number dir="ltr"><?php echo (int) $documentation['complete_evidence_field_count']; ?> / <?php echo (int) $documentation['expected_evidence_field_count']; ?></bdi> حقول مكتملة</small></div></article>
            <article class="evidence-hub-status-card evidence-hub-status-card--technology"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon('nodes'); ?></span><div><span>خريطة التقنيات</span><strong data-bidi-number dir="ltr"><?php echo (int) $technology['mapped_occurrence_count']; ?></strong><small>تقنيات موثّقة <bdi data-bidi-number dir="ltr"><?php echo (int) $technology['unmapped_occurrence_count']; ?></bdi> تحتاج مراجعة</small></div></article>
            <article class="evidence-hub-status-card evidence-hub-status-card--progress"><span class="evidence-hub-status-icon"><?php echo evidenceHubOwnerIcon('target'); ?></span><div><span>التقدم</span><strong data-bidi-number dir="ltr"><?php echo (int) $progress['projects_with_complete_evidence']; ?> / <?php echo (int) $progress['project_count']; ?></strong><small>مشاريع مكتملة التوثيق</small></div></article>
        </div>
    </section>
    <section class="evidence-hub-section evidence-hub-documentation" aria-labelledby="evidence-hub-documentation-title">
        <div class="evidence-hub-section-heading"><div><p>التغطية</p><h2 id="evidence-hub-documentation-title">تغطية التوثيق</h2></div><span>مدى اكتمال السجل</span></div>
        <?php if ($documentation['project_count'] === 0): ?>
            <div class="evidence-hub-empty"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('folder-kanban'); ?></span><strong>لا توجد مشاريع بعد</strong><span>أضف مشروعًا لبدء بناء أدلة ملف الأعمال.</span><a class="evidence-hub-empty-link" href="/owner_projects.php?add=1">إضافة مشروع<?php echo evidenceHubOwnerIcon('arrow'); ?></a></div>
        <?php else: ?>
            <article class="evidence-hub-coverage-card"><div class="evidence-hub-coverage-kpi"><span class="evidence-hub-coverage-icon"><?php echo evidenceHubOwnerIcon('document-check'); ?></span><div><strong data-bidi-number dir="ltr"><?php echo $documentation['coverage_bps'] === null ? '—' : (int) $documentation['coverage_bps'] / 100; ?><?php echo $documentation['coverage_bps'] === null ? '' : '%'; ?></strong><span>من التوثيق المتوقع</span></div></div><div class="evidence-hub-coverage-detail"><div class="evidence-hub-progress-track"><progress value="<?php echo $documentation['coverage_bps'] === null ? 0 : (int) $documentation['coverage_bps']; ?>" max="10000">التغطية</progress></div><p>استكمل تفاصيل مشاريعك من شاشة إدارة المشاريع المعتمدة.</p></div></article>
        <?php endif; ?>
    </section>
    <section class="evidence-hub-section evidence-hub-technology" aria-labelledby="evidence-hub-technology-title">
        <div class="evidence-hub-section-heading"><div><p>التقنيات</p><h2 id="evidence-hub-technology-title">خريطة الأدلة التقنية</h2></div><span>صلة التكنولوجيا بالمشروع</span></div>
        <?php if ($technology['mappings'] === []): ?>
            <div class="evidence-hub-empty"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('nodes'); ?></span><strong>لا توجد أدلة تقنية بعد</strong><span>ستظهر التقنيات بعد تسجيل المشاريع.</span></div>
        <?php else: ?><div class="evidence-hub-technology-grid">
            <?php foreach ($technology['mappings'] as $mapping): ?><article class="evidence-hub-technology-card<?php echo $mapping['mapping_state'] === 'mapped' ? ' evidence-hub-technology-card--mapped' : ' evidence-hub-technology-card--review'; ?>"><span class="evidence-hub-technology-icon"><?php echo evidenceHubOwnerIcon($mapping['mapping_state'] === 'mapped' ? 'check' : 'alert'); ?></span><div><?php if ($mapping['mapping_state'] === 'mapped'): ?><strong><?php echo ownerEscapeHtml((string) $mapping['display_name']); ?></strong><span>تقنية موثّقة</span><?php else: ?><strong>تقنية تحتاج مراجعة</strong><span>راجِع إدخال التقنية من إدارة المشاريع.</span><?php endif; ?></div></article><?php endforeach; ?>
        </div><?php endif; ?>
    </section>
    <section class="evidence-hub-section evidence-hub-progress" aria-labelledby="evidence-hub-progress-title">
        <div class="evidence-hub-section-heading"><div><p>التقدم</p><h2 id="evidence-hub-progress-title">تقدم ملف الأعمال</h2></div><span>الاستعداد للنشر</span></div>
        <article class="evidence-hub-progress-card"><span class="evidence-hub-progress-icon"><?php echo evidenceHubOwnerIcon('target'); ?></span><div><strong><?php echo ownerEscapeHtml(evidenceHubOwnerPublicationLabel($progress['portfolio_publication_state'])); ?></strong><span><bdi data-bidi-number dir="ltr"><?php echo (int) $progress['project_count']; ?></bdi> مشاريع مسجلة، منها <bdi data-bidi-number dir="ltr"><?php echo (int) $progress['projects_with_complete_evidence']; ?></bdi> مكتملة التوثيق.</span></div></article>
    </section>
    <section class="evidence-hub-section evidence-hub-recommendations" aria-labelledby="evidence-hub-recommendations-title">
        <div class="evidence-hub-section-heading"><div><p>خطوات تالية</p><h2 id="evidence-hub-recommendations-title">التوصيات الحالية</h2></div><span>إرشاد قابل للتنفيذ</span></div>
        <?php if ($contract['recommendations'] === []): ?><div class="evidence-hub-empty evidence-hub-empty--complete"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('check'); ?></span><strong>لا توجد توصيات حالية</strong><span>لا يحتاج توثيقك الحالي إلى إجراء الآن. استمر في تحديث المشاريع عند تغيرها.</span></div>
        <?php else: ?><div class="evidence-hub-recommendation-grid">
            <?php foreach ($contract['recommendations'] as $index => $recommendation): ?><?php $action = evidenceHubOwnerRecommendationPresentationAction($recommendation['rule_id']); $tokens = $actionTokens[$index] ?? null; ?>
                <article class="evidence-hub-recommendation-card evidence-hub-recommendation-card--<?php echo ownerEscapeHtml($action['tone']); ?>" aria-label="توصية مركز الأدلة"><div class="evidence-hub-recommendation-topline"><span class="evidence-hub-recommendation-icon"><?php echo evidenceHubOwnerIcon($action['icon']); ?></span><span class="evidence-hub-recommendation-category"><?php echo ownerEscapeHtml($action['category']); ?></span></div><h3><?php echo ownerEscapeHtml($action['title']); ?></h3><p><?php echo ownerEscapeHtml($action['description']); ?></p><div class="evidence-hub-recommendation-footer"><a class="evidence-hub-cta evidence-hub-primary-cta" href="<?php echo ownerEscapeHtml($action['href']); ?>"><span><?php echo ownerEscapeHtml($action['label']); ?></span><?php echo evidenceHubOwnerIcon('arrow'); ?></a>
                    <?php if ($recommendation['lifecycle_state'] === 'active' && is_array($tokens) && isset($tokens['snooze'], $tokens['dismiss']) && is_string($tokens['snooze']) && is_string($tokens['dismiss'])): ?><div class="evidence-hub-recommendation-actions" aria-label="إجراءات التوصية"><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="snooze"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['snooze']); ?>"><button class="evidence-hub-snooze" type="submit"><?php echo evidenceHubOwnerIcon('clock'); ?><span>تأجيل 14 يومًا</span></button></form><form method="POST" action="/owner/evidence-hub"><input type="hidden" name="csrf_token" value="<?php echo ownerEscapeHtml(getCsrfToken()); ?>"><input type="hidden" name="action" value="dismiss"><input type="hidden" name="action_token" value="<?php echo ownerEscapeHtml($tokens['dismiss']); ?>"><button class="evidence-hub-dismiss" type="submit"><?php echo evidenceHubOwnerIcon('dismiss'); ?><span>تجاهل التوصية</span></button></form></div><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </section>
</div>
    <?php
}

function renderEvidenceHubOwnerSafeError(): void
{
    ?><div class="evidence-hub-presentation" dir="rtl"><header class="evidence-hub-hero"><div class="evidence-hub-hero-copy"><p class="evidence-hub-kicker"><?php echo evidenceHubOwnerIcon('spark'); ?> مساحة العمل الخاصة</p><h1>مركز الأدلة</h1></div></header><section class="evidence-hub-section"><div class="evidence-hub-empty" role="alert"><span class="evidence-hub-empty-icon"><?php echo evidenceHubOwnerIcon('alert'); ?></span><strong>مركز الأدلة غير متاح مؤقتًا.</strong><span>يرجى المحاولة لاحقًا.</span></div></section></div><?php
}

/** @return array{title: string, description: string, label: string, href: string, category: string, icon: string, tone: string} */
function evidenceHubOwnerRecommendationPresentationAction(string $ruleId): array
{
    return match ($ruleId) {
        'add_first_project' => ['title' => 'أضف مشروعك الأول', 'description' => 'سجّل مشروعًا لبدء بناء أدلة ملف الأعمال.', 'label' => 'إضافة مشروع', 'href' => '/owner_projects.php?add=1', 'category' => 'بداية الملف', 'icon' => 'folder-kanban', 'tone' => 'foundation'],
        'complete_project_evidence' => ['title' => 'استكمل أدلة المشروع', 'description' => 'راجِع توثيق المشروع من شاشة إدارة المشاريع المعتمدة.', 'label' => 'إدارة المشاريع', 'href' => '/owner_projects.php', 'category' => 'جودة التوثيق', 'icon' => 'document-check', 'tone' => 'documentation'],
        'review_unmapped_technology' => ['title' => 'راجِع الأدلة التقنية', 'description' => 'راجِع إدخالات التقنية من شاشة إدارة المشاريع المعتمدة.', 'label' => 'إدارة المشاريع', 'href' => '/owner_projects.php', 'category' => 'خريطة التقنية', 'icon' => 'nodes', 'tone' => 'technology'],
        'complete_portfolio_publication' => ['title' => 'استكمل نشر ملف الأعمال', 'description' => 'راجِع إعدادات النشر الخاصة قبل النشر.', 'label' => 'إعدادات النشر', 'href' => '/owner_publication.php', 'category' => 'جاهزية النشر', 'icon' => 'target', 'tone' => 'publication'],
        default => ['title' => 'راجِع أدلة ملف الأعمال', 'description' => 'راجِع مساحة ملف أعمالك الخاصة.', 'label' => 'إدارة المشاريع', 'href' => '/owner_projects.php', 'category' => 'خطوة تالية', 'icon' => 'spark', 'tone' => 'foundation'],
    };
}

function evidenceHubOwnerMaturityLabel(string $state): string { return match ($state) {'zero' => 'بداية جديدة', 'partial' => 'قيد التقدم', 'ready' => 'جاهز', default => 'غير متاح'}; }
function evidenceHubOwnerMaturityIcon(string $state): string { return match ($state) {'ready' => 'check', 'partial' => 'clock', 'zero' => 'spark', default => 'alert'}; }
function evidenceHubOwnerStatusLabel(string $status): string { return match ($status) {'ready' => 'الأدلة الحالية جاهزة', 'needs_attention' => 'الأدلة تحتاج انتباهًا', default => 'الأدلة غير متاحة بعد'}; }
function evidenceHubOwnerPublicationLabel(string $state): string { return match ($state) {'published' => 'تم نشر ملف الأعمال', 'unpublished' => 'ملف الأعمال غير منشور', default => 'إعدادات النشر غير مكتملة'}; }
