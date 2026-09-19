<?php
/** @var mixed $users */
/** @var mixed $csrfToken */
/** @var mixed $renderClassOptions */
/** @var mixed $renderRoleChoices */
/** @var mixed $avatarMatches */
/** @var mixed $avatarUnmatchedFiles */
/** @var mixed $avatarAmbiguousFiles */
/** @var mixed $avatarUsersWithoutPhoto */
/** @var mixed $userAvatarPreview */
/** @var mixed $classes */
/** @var mixed $studentUsers */
/** @var mixed $teacherUsers */
?>
<section id="usuaris" class="card admin-panel admin-collapsible">
            <div class="admin-panel__header">
                <h2>Usuaris</h2>
                <div class="admin-actions">
                    <span class="status"><?= count($users) ?> usuaris</span>
                    <button class="collapse-toggle" type="button" data-collapse="usuaris-content">Amagar</button>
                </div>
            </div>
            <div id="usuaris-content" class="admin-collapsible__content">
                <div class="admin-panels admin-panels--stacked">
                    <section id="crear-usuari" class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Crear usuari</h3>
                            <button class="collapse-toggle" type="button" data-collapse="crear-usuari-content">Mostrar</button>
                        </div>
                        <div id="crear-usuari-content" class="admin-collapsible__content">
                            <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                <input type="hidden" name="action" value="create_user">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form__grid form__grid--compact">
                                    <label>Nom<input type="text" name="name" required></label>
                                    <label>Cognoms<input type="text" name="surname"></label>
                                    <label>Email<input type="email" name="email" required></label>
                                    <label>Contrasenya<input type="password" name="password" required autocomplete="new-password"></label>
                                    <label>Classe<select name="class_id"><?php $renderClassOptions(null); ?></select></label>
                                </div>
                                <div class="form__group"><label>Rols</label><div class="form__choices"><?php $renderRoleChoices(['student']); ?></div></div>
                                <label class="form__check"><input type="checkbox" name="is_active" value="1" checked> Usuari actiu</label>
                                <button class="button" type="submit">Crear usuari</button>
                            </form>
                        </div>
                    </section>

                    <section id="importar-usuaris" class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Importar CSV</h3>
                            <button class="collapse-toggle" type="button" data-collapse="importar-usuaris-content">Mostrar</button>
                        </div>
                        <div id="importar-usuaris-content" class="admin-collapsible__content">
                            <p class="muted admin-csv-import__help">Capçaleres CSV: <code>users.name,users.surname,users.email,classes.class_code,web_roles.name,users.is_active,project_academic_years.id,project_teams.team_name,project_roles.id</code>. No incloguis cap columna de contrasenya.</p>
                            <p class="muted admin-csv-import__help">Cal indicar l’edició del projecte, la classe, el nom de l’equip i un <code>project_roles.id</code> existent. Si l’equip no existeix dins de l’edició i la classe, es crea automàticament i el seu codi es construeix com <code>26-27_mediterrani_1ESOA-01</code>.</p>
                            <p class="muted admin-csv-import__help">Exemple amb dades actuals: l’edició <code>4</code> és Mediterrani 2026-2027, la classe <code>26-27_1ESOA</code> existeix i el rol de projecte <code>1</code> és <code>generic</code>.</p>
                            <pre class="admin-csv-import__example"><code>users.name,users.surname,users.email,classes.class_code,web_roles.name,users.is_active,project_academic_years.id,project_teams.team_name,project_roles.id
Laia,Serra,laia.serra@example.com,26-27_1ESOA,student,1,4,1ESOA-01,1
Nil,Ferrer,nil.ferrer@example.com,26-27_1ESOA,student,1,4,1ESOA-01,1</code></pre>
                            <p class="muted admin-csv-import__help">Aquest exemple crea un enllaç d’activació per a cada alumne, crea el codi <code>26-27_mediterrani_1ESOA-01</code> i assigna els dos alumnes al mateix equip.</p>
                            <form class="admin-form" method="post" action="<?= url('admin') ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_students">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <label>Fitxer CSV<input type="file" name="students_file" accept=".csv,text/csv" required></label>
                                <button class="button" type="submit">Importar CSV</button>
                            </form>
                        </div>
                    </section>

                    <section id="fotos-usuaris" class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Fotos d’usuaris</h3>
                            <div class="admin-actions">
                                <span class="status"><?= (int) ($userAvatarPreview['totalMatches'] ?? 0) ?> coincidències</span>
                                <button class="collapse-toggle" type="button" data-collapse="fotos-usuaris-content">Mostrar</button>
                            </div>
                        </div>
                        <div id="fotos-usuaris-content" class="admin-collapsible__content">
                            <p class="muted admin-csv-import__help">Carpeta privada: <code>storage/uploads/<?= htmlspecialchars((string) ($userAvatarPreview['relativeDirectory'] ?? 'user-avatars/originals'), ENT_QUOTES, 'UTF-8') ?></code>. Formats acceptats: <code>.jpg</code>, <code>.jpeg</code>, <code>.png</code>, <code>.webp</code>.</p>
                            <div class="admin-avatar-summary">
                                <span class="status"><?= (int) ($userAvatarPreview['totalFiles'] ?? 0) ?> fotos trobades</span>
                                <span class="status"><?= count($avatarMatches) ?> coincidències segures</span>
                                <span class="status"><?= (int) ($userAvatarPreview['totalUpdates'] ?? 0) ?> pendents de vincular</span>
                                <span class="status"><?= count($avatarUnmatchedFiles) ?> sense coincidència</span>
                                <span class="status"><?= count($avatarAmbiguousFiles) ?> ambigües</span>
                            </div>
                            <?php if ($avatarMatches !== []): ?>
                                <form class="admin-form admin-form--inline" method="post" action="<?= url('admin') ?>">
                                    <input type="hidden" name="action" value="sync_user_avatars">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <button class="button" type="submit">Vincular coincidències segures</button>
                                </form>
                                <div class="admin-table__wrapper">
                                    <table class="admin-table admin-table--compact">
                                        <thead><tr><th>Alumne</th><th>Fitxer</th><th>Estat</th></tr></thead>
                                        <tbody>
                                            <?php foreach (array_slice($avatarMatches, 0, 20) as $match): ?>
                                                <?php $matchUser = $match['user'] ?? []; $matchFile = $match['file'] ?? []; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(trim((string) ($matchUser['name'] ?? '') . ' ' . (string) ($matchUser['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) ($matchFile['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= !empty($match['will_update']) ? 'Pendent de vincular' : 'Ja vinculada' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($avatarMatches) > 20): ?><p class="muted">Es mostren les primeres 20 coincidències.</p><?php endif; ?>
                            <?php else: ?>
                                <p class="muted">No s’han trobat coincidències segures. Revisa que els fitxers segueixin el patró <code>Cognoms, Nom.jpeg</code>.</p>
                            <?php endif; ?>
                            <?php if ($avatarUnmatchedFiles !== []): ?>
                                <details class="admin-details"><summary>Fotos sense coincidència (<?= count($avatarUnmatchedFiles) ?>)</summary><ul class="admin-compact-list"><?php foreach (array_slice($avatarUnmatchedFiles, 0, 40) as $file): ?><li><?= htmlspecialchars((string) ($file['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></details>
                            <?php endif; ?>
                            <?php if ($avatarAmbiguousFiles !== []): ?>
                                <details class="admin-details"><summary>Fotos amb coincidència ambigua (<?= count($avatarAmbiguousFiles) ?>)</summary><ul class="admin-compact-list"><?php foreach (array_slice($avatarAmbiguousFiles, 0, 40) as $item): ?><li><?= htmlspecialchars((string) ($item['file']['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></details>
                            <?php endif; ?>
                            <?php if ($avatarUsersWithoutPhoto !== []): ?>
                                <details class="admin-details"><summary>Alumnes sense foto vinculada ni fitxer detectat (<?= count($avatarUsersWithoutPhoto) ?>)</summary><ul class="admin-compact-list"><?php foreach (array_slice($avatarUsersWithoutPhoto, 0, 40) as $user): ?><li><?= htmlspecialchars(trim((string) ($user['surname'] ?? '') . ', ' . (string) ($user['name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></details>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <section id="alumnes-seccio" class="card admin-subpanel admin-collapsible">
                    <div class="admin-panel__header">
                        <h3>Alumnes</h3>
                        <div class="admin-actions"><span id="alumnes-count" class="status"><?= count($studentUsers) ?> alumnes</span><button class="collapse-toggle" type="button" data-collapse="alumnes-content">Amagar</button></div>
                    </div>
                    <div id="alumnes-content" class="admin-collapsible__content">
                        <div class="admin-filters" data-user-filter="students-table" data-count-target="alumnes-count" data-count-label="alumnes">
                            <label>Cerca<input type="search" data-user-search placeholder="Nom o email"></label>
                            <label>Estat<select data-status-filter><option value="all">Tots</option><option value="active">Actius</option><option value="inactive">Inactius</option></select></label>
                            <button class="admin-filters__chip is-active" type="button" data-value="all">Totes</button>
                            <?php foreach ($classes as $class): ?>
                                <button class="admin-filters__chip" type="button" data-value="<?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-table__wrapper">
                            <table id="students-table" class="admin-table admin-table--compact" data-sortable-table>
                                <thead><tr><th data-sort-type="text">Nom</th><th>Foto</th><th data-sort-type="text">Email</th><th data-sort-type="text">Classe</th><th data-sort-type="text">Equip</th><th data-sort-type="number">Visites</th><th data-sort-type="text">Estat</th><th>Accions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($studentUsers as $user): ?>
                                        <?php $userId = (int) ($user['id'] ?? 0); $classCode = (string) ($user['class_code'] ?? ''); $userName = trim((string) ($user['name'] ?? '')); $userSurname = trim((string) ($user['surname'] ?? '')); $userTeamLabels = array_values($studentTeamLabels[$userId] ?? []); ?>
                                        <tr data-user-row data-class="<?= htmlspecialchars($classCode, ENT_QUOTES, 'UTF-8') ?>" data-status="<?= ((int) ($user['is_active'] ?? 0) === 1) ? 'active' : 'inactive' ?>" data-search="<?= htmlspecialchars(strtolower(trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? '') . ' ' . (string) ($user['email'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>">
                                            <td data-sort-value="<?= htmlspecialchars($userSurname . ' ' . $userName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim($userName . ' ' . $userSurname), ENT_QUOTES, 'UTF-8') ?></td>
                                             <td><?php if (trim((string) ($user['avatar_url'] ?? '')) !== ''): ?><button type="button" class="user-avatar-trigger" data-avatar-src="<?= url('user-avatar/' . $userId) ?>" data-avatar-name="<?= htmlspecialchars(trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" title="Fes clic per ampliar"><img class="user-avatar" src="<?= url('user-avatar/' . $userId) ?>" alt="Foto de <?= htmlspecialchars((string) ($user['name'] ?? 'alumne'), ENT_QUOTES, 'UTF-8') ?>"></button><?php else: ?><span class="user-avatar user-avatar--placeholder" aria-label="Sense foto">?</span><?php endif; ?></td>
                                            <td><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($classCode !== '' ? $classCode : 'Sense classe', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($userTeamLabels !== [] ? implode(', ', $userTeamLabels) : 'Sense equip', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= (int) ($user['visit_count'] ?? 0) ?></td>
                                            <td><?= ((int) ($user['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td>
                                            <td>
                                                <div class="admin-row-actions">
                                                    <button class="button button--small" type="button" data-target="student-editor-<?= $userId ?>">Editar</button>
                                                    <form class="inline-form" method="post" action="<?= url('admin/impersonate-student') ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="student_id" value="<?= $userId ?>">
                                                        <button class="button button--small button--secondary" type="submit">Veure com alumne</button>
                                                    </form>
                                                    <form class="inline-form" method="post" action="<?= url('admin') ?>" data-confirm="Generar un nou enllaç de reset per a aquest alumne?">
                                                        <input type="hidden" name="action" value="generate_student_password_reset">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="student_id" value="<?= $userId ?>">
                                                        <button class="button button--small button--secondary" type="submit">Generar reset</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="student-editor-<?= $userId ?>" class="student-editor-row"><td colspan="8"><?php $editableUser = $user; include __DIR__ . '/user-editor-form.php'; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="professors-seccio" class="card admin-subpanel admin-collapsible is-collapsed">
                    <div class="admin-panel__header">
                        <h3>Professors</h3>
                        <div class="admin-actions"><span class="status"><?= count($teacherUsers) ?> professors</span><button class="collapse-toggle" type="button" data-collapse="professors-content">Mostrar</button></div>
                    </div>
                    <div id="professors-content" class="admin-collapsible__content">
                        <div class="admin-table__wrapper">
                            <table class="admin-table admin-table--compact">
                                <thead><tr><th>Nom</th><th>Email</th><th>Rols</th><th>Classe</th><th>Estat</th><th>Accions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($teacherUsers as $teacher): ?>
                                        <?php $userId = (int) ($teacher['id'] ?? 0); ?>
                                        <tr>
                                            <td><?= htmlspecialchars(trim((string) ($teacher['name'] ?? '') . ' ' . (string) ($teacher['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($teacher['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(implode(', ', $teacher['roles'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(($teacher['teacher_class_codes'] ?? []) !== [] ? implode(', ', $teacher['teacher_class_codes']) : 'Sense classe', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= ((int) ($teacher['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td>
                                            <td><button class="button button--small" type="button" data-target="teacher-editor-<?= $userId ?>">Editar</button></td>
                                        </tr>
                                        <tr id="teacher-editor-<?= $userId ?>" class="student-editor-row"><td colspan="6"><?php $editableUser = $teacher; include __DIR__ . '/user-editor-form.php'; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </section>
