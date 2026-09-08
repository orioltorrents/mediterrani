<?php
ob_start();
?>
<section class="hero hero--home public-home">
    <div class="public-home__content">
        <div class="public-home__brand-row" aria-label="Logotip del projecte">
            <img class="public-home__brand public-home__brand--intermunicipal" src="<?= url('assets/logos/intermunicipal/logo-inter-2017-transparent.png') ?>" alt="Intermunicipal">
        </div>
        <p class="public-home__eyebrow"><?= htmlspecialchars(trans('educational_environment'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="hero__title public-home__title">
            <span><?= htmlspecialchars(trans('home_title_greeting'), ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars(trans('home_title_name'), ENT_QUOTES, 'UTF-8') ?></span>
        </h1>
        <p class="hero__text public-home__text"><?= htmlspecialchars(trans('home_intro'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="actions hero__actions public-home__actions">
            <a class="button" href="<?= url(getLanguage() . '/que-es-entorns') ?>"><?= htmlspecialchars(trans('enter'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</section>


<?php
$collaborators = [
    ['name' => 'Ajuntament de Sant Sadurní', 'logo' => 'collaboradors/Ajuntament-SantSadurni.png', 'url' => 'https://www.santsadurni.cat', 'logo_class' => 'public-home-collaborator-card__logo--medium'],
];
?>

<section class="public-home-collaborators" aria-labelledby="collaborators-title">
    <div class="public-home-collaborators__header">
        <p class="public-home-collaborators__eyebrow"><?= htmlspecialchars(trans('collaborators'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="public-home-collaborators__grid">
        <?php foreach ($collaborators as $collaborator): ?>
            <article class="public-home-collaborator-card">
                <?php if (!empty($collaborator['url'])): ?>
                    <a class="public-home-collaborator-card__link" href="<?= htmlspecialchars((string) $collaborator['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                        <img class="public-home-collaborator-card__logo<?= !empty($collaborator['logo_class']) ? ' ' . htmlspecialchars($collaborator['logo_class'], ENT_QUOTES, 'UTF-8') : '' ?>" src="<?= url('assets/logos/' . $collaborator['logo']) ?>" alt="<?= htmlspecialchars($collaborator['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    </a>
                <?php else: ?>
                    <img class="public-home-collaborator-card__logo<?= !empty($collaborator['logo_class']) ? ' ' . htmlspecialchars($collaborator['logo_class'], ENT_QUOTES, 'UTF-8') : '' ?>" src="<?= url('assets/logos/' . $collaborator['logo']) ?>" alt="<?= htmlspecialchars($collaborator['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
