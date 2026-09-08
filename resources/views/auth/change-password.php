<?php
ob_start();
?>
<div class="auth-card auth-card--password-change">
    <h1><?= htmlspecialchars(trans('change_password_title'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="auth-card__intro"><?= htmlspecialchars(trans('change_password_intro'), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if (!empty($error)): ?>
        <p class="form-error"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form class="form" action="<?= url('canviar-contrasenya') ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label class="form__field" for="current_password"><?= htmlspecialchars(trans('current_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form__control" type="password" name="current_password" id="current_password" required autocomplete="current-password">

        <label class="form__field" for="new_password"><?= htmlspecialchars(trans('new_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form__control" type="password" name="new_password" id="new_password" required minlength="8" autocomplete="new-password">

        <label class="form__field" for="new_password_confirmation"><?= htmlspecialchars(trans('repeat_new_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input class="form__control" type="password" name="new_password_confirmation" id="new_password_confirmation" required minlength="8" autocomplete="new-password">

        <button class="button" type="submit"><?= htmlspecialchars(trans('save_password'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
