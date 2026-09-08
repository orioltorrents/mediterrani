<?php
ob_start();
?>
<section class="public-projects__hero">
    <p class="public-projects__eyebrow"><?= htmlspecialchars(trans('projects_title'), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="public-projects__title"><?= htmlspecialchars(trans('projects_title'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="public-projects__text"><?= htmlspecialchars(trans('projects_intro'), ENT_QUOTES, 'UTF-8') ?></p>
</section>

<?php if (empty($projects)): ?>
    <div class="empty-state public-projects__empty">
        <p><?= htmlspecialchars(trans('no_published_projects'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php else: ?>
    <div class="public-projects__grid">
        <?php foreach ($projects as $project): ?>
            <?php $projectAsset = $project['logo_asset'] ?? ($project['assets'][0] ?? null); ?>
            <article class="public-project-card project-card project-card--public">
                <?php if (!empty($projectAsset['logo_path'])): ?>
                    <div class="public-project-card__media project-card__media">
                        <?php if (!empty($projectAsset['website_url'])): ?>
                            <a href="<?= htmlspecialchars((string) $projectAsset['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                                <img class="public-project-card__logo project-card__logo" src="<?= url((string) $projectAsset['logo_path']) ?>" alt="<?= htmlspecialchars((string) $projectAsset['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            </a>
                        <?php else: ?>
                            <img class="public-project-card__logo project-card__logo" src="<?= url((string) $projectAsset['logo_path']) ?>" alt="<?= htmlspecialchars((string) $projectAsset['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="public-project-card__body project-card__body">
                    <div class="public-project-card__header project-card__header">
                        <h2 class="public-project-card__title project-card__title"><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="public-project-card__status project-card__status status"><?= htmlspecialchars(trans('status_actiu'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if (!empty($project['description'])): ?>
                        <p class="public-project-card__text project-card__text"><?= htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="public-project-card__actions project-card__actions">
                        <a class="button public-project-card__button project-card__button" href="<?= url(getLanguage() . '/projectes/' . $project['slug']) ?>"><?= htmlspecialchars(trans('open'), ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
