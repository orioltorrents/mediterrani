<?php
ob_start();
$languagePrefix = getLanguage();
$editionQuery = !empty($projectAcademicYearId) ? '?edicio=' . (int) $projectAcademicYearId : '';
?>
<section class="page-header">
    <?php if (!empty($project['slug'])): ?>
        <p class="breadcrumb"><a href="<?= url($languagePrefix . '/projectes/' . $project['slug']) . $editionQuery ?>"><?= htmlspecialchars(trans('back_to_project'), ENT_QUOTES, 'UTF-8') ?></a></p>
        <h1><?= htmlspecialchars(sprintf(trans('documents_of'), $project['title']), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($projectAcademicYear['academic_year_name'])): ?>
        <p class="status"><?= htmlspecialchars(sprintf(trans('academic_year_label'), (string) $projectAcademicYear['academic_year_name']), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    <?php else: ?>
        <h1><?= htmlspecialchars(trans('documents_not_found'), ENT_QUOTES, 'UTF-8') ?></h1>
    <?php endif; ?>
</section>

<?php if (empty($documents)): ?>
    <div class="empty-state">
        <p><?= htmlspecialchars(trans('no_published_documents'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php else: ?>
    <div class="stack" style="display: grid; gap: 1rem;">
        <?php foreach ($documents as $document): ?>
            <article class="card" style="padding: 1rem; opacity: <?= !empty($document['is_visible']) ? '1' : '0.5' ?>;">
                <div class="admin-panel__header">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars(trans('document'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h2><?= htmlspecialchars((string) $document['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <span class="status"><?= htmlspecialchars(!empty($document['is_visible']) ? trans('visible') : trans('hidden'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <p><strong><?= htmlspecialchars(trans('slug'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars((string) $document['slug'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong><?= htmlspecialchars(trans('base_visibility'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars((string) $document['default_visibility'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($context['is_admin']) || !empty($context['is_teacher']) || !empty($context['is_assigned_teacher'])): ?>
                    <p><strong><?= htmlspecialchars(trans('notes_label'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars((string) ($document['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if (!empty($document['sources'])): ?>
                    <h3><?= htmlspecialchars(trans('sources'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <ul>
                        <?php foreach ($document['sources'] as $source): ?>
                            <li>
                                <?= htmlspecialchars((string) $source['source_type'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($source['source_url'])): ?>
                                    - <a href="<?= htmlspecialchars((string) $source['source_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(trans('open_lower'), ENT_QUOTES, 'UTF-8') ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3><?= htmlspecialchars(trans('fragments'), ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if (empty($document['fragments'])): ?>
                    <p><?= htmlspecialchars(trans('no_fragments'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <div class="stack" style="display: grid; gap: 0.75rem;">
                        <?php foreach ($document['fragments'] as $fragment): ?>
                            <section class="card" style="padding: 0.75rem; background: <?= !empty($fragment['is_visible']) ? '#fff' : '#f7f7f7' ?>; border: 1px solid #ddd;">
                                <div class="admin-panel__header">
                                    <div>
                                        <h4><?= htmlspecialchars((string) $fragment['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                        <p class="muted"><?= htmlspecialchars(sprintf(trans('key_format'), (string) $fragment['fragment_key'], (string) $fragment['content_format']), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="status"><?= htmlspecialchars(!empty($fragment['is_visible']) ? trans('visible') : trans('hidden'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php if (!empty($fragment['is_visible'])): ?>
                                    <div class="prose">
                                        <?= nl2br(htmlspecialchars((string) ($fragment['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted"><?= htmlspecialchars(trans('fragment_not_visible'), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
