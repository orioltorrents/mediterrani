<?php
ob_start();
$activationToken = (string) ($token ?? '');
$isValid = (bool) ($isValid ?? false);
?>
<div class="auth-card auth-card--password-change">
    <h1>Activa el teu compte</h1>

    <?php if (!empty($error)): ?>
        <p class="form-error"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($isValid && empty($success)): ?>
        <p class="auth-card__intro">Defineix una contrasenya personal per accedir a Mediterrani.</p>
        <form class="form" action="<?= url('activar-compte') ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($activationToken, ENT_QUOTES, 'UTF-8') ?>">

            <label class="form__field" for="password">Contrasenya</label>
            <input class="form__control" type="password" name="password" id="password" required minlength="8" autocomplete="new-password">

            <label class="form__field" for="password_confirmation">Repeteix la contrasenya</label>
            <input class="form__control" type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password">

            <button class="button" type="submit">Activar compte</button>
        </form>
    <?php elseif (!empty($success)): ?>
        <p class="auth-card__intro">El compte s’ha activat correctament. Ja pots iniciar sessió.</p>
        <a class="button" href="<?= url('login') ?>">Anar a l’inici de sessió</a>
    <?php else: ?>
        <p class="form-error">L’enllaç d’activació no és vàlid o ha caducat.</p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
