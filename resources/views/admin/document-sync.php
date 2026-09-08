<?php
ob_start();
$csrfToken = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="card admin-panel" id="document-sync">
    <div class="admin-panel__header">
        <div>
            <p class="eyebrow">Documents</p>
            <h1>Sincronització de documents</h1>
        </div>
    </div>

    <p>Pega aquí el JSON exportat des de Google Sheets o des de l'Apps Script.</p>

    <?php if (!empty($error)): ?>
        <div class="flash-message error">
            <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($result)): ?>
        <div class="flash-message success">
            Import completat: <?= (int) ($result['documents_imported'] ?? 0) ?> documents, <?= (int) ($result['sources_imported'] ?? 0) ?> fonts, <?= (int) ($result['fragments_imported'] ?? 0) ?> fragments, <?= (int) ($result['rules_imported'] ?? 0) ?> regles.
        </div>
        <?php if (!empty($result['warnings'])): ?>
            <div class="card" style="margin-top: 1rem; padding: 1rem;">
                <h2>Warnings</h2>
                <ul>
                    <?php foreach ($result['warnings'] as $warning): ?>
                        <li><?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form class="form" method="post" action="<?= url('admin/sync-documents') ?>">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <label class="form__field" for="json_payload">JSON</label>
        <textarea class="form__control" id="json_payload" name="json_payload" rows="24" style="font-family: monospace; min-height: 420px;"><?= htmlspecialchars((string) ($jsonPayload ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

        <div class="actions" style="margin-top: 1rem;">
            <button class="button" type="submit">Importar JSON</button>
            <a class="button button--secondary" href="<?= url('admin') ?>">Torna al dashboard</a>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
