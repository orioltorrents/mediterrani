<?php
$editableUser = is_array($editableUser ?? null) ? $editableUser : [];
$editableUserId = (int) ($editableUser['id'] ?? 0);
$editableUserRoles = is_array($editableUser['roles'] ?? null) ? $editableUser['roles'] : [];
$editableUserIsAdmin = in_array('admin', $editableUserRoles, true);
$editableUserIsTeacher = in_array('teacher', $editableUserRoles, true);
?>
<form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>">
    <input type="hidden" name="action" value="update_student">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="student_id" value="<?= $editableUserId ?>">
    <div class="form__grid form__grid--compact">
        <label>Nom<input type="text" name="name" value="<?= htmlspecialchars((string) ($editableUser['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
        <label>Cognoms<input type="text" name="surname" value="<?= htmlspecialchars((string) ($editableUser['surname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <label>Email<input type="email" name="email" value="<?= htmlspecialchars((string) ($editableUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
        <?php if (!$editableUserIsTeacher): ?>
            <label>Classe<select name="class_id"><?php $renderClassOptions(isset($editableUser['class_id']) ? (int) $editableUser['class_id'] : null); ?></select></label>
        <?php endif; ?>
    </div>
    <?php if ($editableUserIsTeacher): ?>
        <div class="form__group">
            <label>Classes del professor</label>
            <div class="form__choices"><?php $renderTeacherClassChoices($editableUser['teacher_class_ids'] ?? []); ?></div>
        </div>
    <?php endif; ?>
    <div class="form__group">
        <label>Rols</label>
        <div class="form__choices"><?php $renderRoleChoices($editableUserRoles); ?></div>
    </div>
    <?php if ($editableUserIsAdmin): ?>
        <input type="hidden" name="is_active" value="1">
        <p class="muted">Aquest usuari té rol admin i es manté actiu.</p>
    <?php else: ?>
        <label class="form__check"><input type="checkbox" name="is_active" value="1" <?= ((int) ($editableUser['is_active'] ?? 0) === 1) ? 'checked' : '' ?>> Usuari actiu</label>
    <?php endif; ?>
    <button class="button" type="submit">Guardar canvis</button>
</form>
