<?php
/** @var mixed $classroomSummary */
/** @var mixed $classrooms */
/** @var mixed $classroomMembers */
/** @var mixed $csrfToken */
?>
<section id="classroom" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header"><h2>Classroom</h2><div class="admin-actions"><span class="status"><?= (int) ($classroomSummary['total'] ?? count($classrooms)) ?> classrooms</span><button class="collapse-toggle" type="button" data-collapse="classroom-content">Mostrar</button></div></div>
            <div id="classroom-content" class="admin-collapsible__content">
                <section class="card admin-subpanel admin-collapsible is-collapsed">
                    <div class="admin-panel__header"><h3>Classrooms</h3><div class="admin-actions"><span class="status"><?= (int) ($classroomSummary['total'] ?? count($classrooms)) ?> classrooms</span><button class="collapse-toggle" type="button" data-collapse="classrooms-content">Mostrar</button></div></div>
                    <div id="classrooms-content" class="admin-collapsible__content">
                    <section class="admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header"><h4>Importar Classrooms</h4><button class="collapse-toggle" type="button" data-collapse="import-classrooms-content">Mostrar</button></div>
                        <div id="import-classrooms-content" class="admin-collapsible__content">
                            <p class="muted admin-csv-import__help">Headers obligatoris: <code>project_academic_years.id,classrooms.classroom_key,classrooms.classroom_name</code>. També pots afegir <code>classrooms.classroom_url,classrooms.google_classroom_id</code>.</p>
                            <p class="muted admin-csv-import__help">L’edició del projecte és obligatòria perquè cada Classroom quedi vinculat al projecte i curs correctes.</p>
                            <pre class="admin-csv-import__example"><code>project_academic_years.id,classrooms.classroom_key,classrooms.classroom_name,classrooms.classroom_url,classrooms.google_classroom_id
4,1ESOA-MEDITERRANI,Mediterrani 1ESO A,https://classroom.google.com/c/123456,123456</code></pre>
                            <form class="admin-form" method="post" action="<?= url('admin') ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_classrooms">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <label>Fitxer CSV<input type="file" name="classrooms_file" accept=".csv,text/csv" required></label>
                                <button class="button" type="submit">Importar Classrooms</button>
                            </form>
                        </div>
                    </section>
                    <section class="admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header"><h4>Llista actual de Classrooms</h4><button class="collapse-toggle" type="button" data-collapse="classrooms-list-content">Mostrar</button></div>
                        <div id="classrooms-list-content" class="admin-collapsible__content">
                            <?php if ($classrooms !== []): ?><div class="admin-table__wrapper"><table class="admin-table admin-table--compact"><thead><tr><th>Nom</th><th>Clau</th><th>Curs</th><th>Membres</th><th>Estat</th></tr></thead><tbody><?php foreach ($classrooms as $classroom): ?><tr><td><?= htmlspecialchars((string) ($classroom['classroom_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($classroom['classroom_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($classroom['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) ($classroom['member_count'] ?? 0) ?></td><td><?= ((int) ($classroom['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="muted">Encara no hi ha cap Classroom carregat.</p><?php endif; ?>
                        </div>
                    </section>
                    </div>
                </section>
                <section class="card admin-subpanel admin-collapsible is-collapsed">
                    <div class="admin-panel__header"><h3>Membres dels Classrooms</h3><div class="admin-actions"><span class="status"><?= (int) ($classroomSummary['members'] ?? 0) ?> membres actius</span><button class="collapse-toggle" type="button" data-collapse="classroom-members-content">Mostrar</button></div></div>
                    <div id="classroom-members-content" class="admin-collapsible__content">
                        <section class="admin-subpanel admin-collapsible is-collapsed">
                            <div class="admin-panel__header"><h4>Importar membres</h4><button class="collapse-toggle" type="button" data-collapse="import-classroom-members-content">Mostrar</button></div>
                            <div id="import-classroom-members-content" class="admin-collapsible__content">
                                <p class="muted admin-csv-import__help">Headers obligatoris: <code>academic_year,classroom_key,email</code>. Opcionals: <code>project_slug,classroom_name,classroom_url,google_classroom_id,name,surname,google_user_id,google_photo_url</code>.</p>
                                <pre class="admin-csv-import__example"><code>academic_year,classroom_key,email,name,surname,google_user_id
2025-2026,1ESOA-MEDITERRANI,alumne@example.com,Aiman,Garcia,google-user-id</code></pre>
                                <form class="admin-form" method="post" action="<?= url('admin') ?>" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="import_classroom_members">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <label>Fitxer CSV<input type="file" name="classroom_members_file" accept=".csv,text/csv" required></label>
                                    <button class="button" type="submit">Importar membres</button>
                                </form>
                                <p class="muted">Els usuaris han d’existir prèviament a <code>users</code>. La importació actualitza les relacions i evita duplicats.</p>
                            </div>
                        </section>
                        <section class="admin-subpanel admin-collapsible is-collapsed">
                            <div class="admin-panel__header"><h4>Alumnes dels Classrooms</h4><button class="collapse-toggle" type="button" data-collapse="classroom-students-content">Mostrar</button></div>
                            <div id="classroom-students-content" class="admin-collapsible__content">
                                <?php if ($classroomMembers !== []): ?><div class="admin-table__wrapper"><table class="admin-table admin-table--compact"><thead><tr><th>Classroom</th><th>Alumne</th><th>Email</th><th>Google ID</th><th>Estat</th></tr></thead><tbody><?php foreach ($classroomMembers as $member): ?><tr><td><?= htmlspecialchars((string) ($member['classroom_name'] ?? $member['classroom_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(trim((string) ($member['name'] ?? '') . ' ' . (string) ($member['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?: 'Sense nom' ?></td><td><?= htmlspecialchars((string) ($member['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($member['google_user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= ((int) ($member['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="muted">Encara no hi ha alumnes importats als Classrooms.</p><?php endif; ?>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </section>
