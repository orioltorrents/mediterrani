<?php
ob_start();
$project = is_array($project ?? null) ? $project : null;
$team = is_array($team ?? null) ? $team : null;
$teams = is_array($teams ?? null) ? $teams : [];
$roles = array_values(array_map('strval', $currentUser['roles'] ?? []));
$canViewAllTeams = in_array('teacher', $roles, true) || in_array('guest_teacher', $roles, true) || in_array('coordinator', $roles, true) || in_array('admin', $roles, true);
$editionQuery = isset($projectAcademicYearId) && $projectAcademicYearId > 0 ? '?edicio=' . (int) $projectAcademicYearId : '';
?>
<div class="public-project-detail">
    <div class="breadcrumb">
        <a href="<?= url(getLanguage() . '/projectes/' . ($project['slug'] ?? '')) . $editionQuery ?>">&larr; <?= htmlspecialchars(trans('back_to_project'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>

    <header class="public-project-detail__header card">
        <span class="public-project-detail__status status">Grups i equips</span>
        <h1 class="public-project-detail__title"><?= htmlspecialchars((string) ($project['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
    </header>

    <div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
        <?php if ($canViewAllTeams): ?>
            <?php if ($teams !== []): ?>
                <?php foreach ($teams as $team): ?>
                    <section class="public-project-groups__team">
                        <h2><?= htmlspecialchars((string) ($team['team_name'] ?: $team['team_code']), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><strong>Codi:</strong> <?= htmlspecialchars((string) $team['team_code'], ENT_QUOTES, 'UTF-8') ?><?php if (!empty($team['class_group'])): ?> · <strong>Classe:</strong> <?= htmlspecialchars((string) $team['class_group'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                        <?php if (!empty($team['members'])): ?><div class="admin-table__wrapper"><table class="admin-table"><thead><tr><th>Alumne/a</th><th>Email</th><th>Rols de projecte</th></tr></thead><tbody><?php foreach ($team['members'] as $member): ?><tr><td><?= htmlspecialchars((string) $member['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $member['email'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($member['role_names'] ?: 'Sense rol assignat'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="muted">Aquest equip encara no té components assignats.</p><?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php else: ?><div class="empty-state"><p>No s'han trobat equips en aquesta edició.</p></div><?php endif; ?>
        <?php elseif ($team !== null): ?>
            <h2 style="margin-top: 0; color: var(--leaf);">Codi d'equip: <?= htmlspecialchars((string) ($team['team_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if (!empty($team['team_name'])): ?>
                <p><strong>Nom de l'equip:</strong> <?= htmlspecialchars((string) $team['team_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if (!empty($team['class_group'])): ?>
                <p><strong>Grup classe:</strong> <?= htmlspecialchars((string) $team['class_group'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <h3 style="margin-top: 1.5rem; margin-bottom: 0.75rem;">Membres del teu grup</h3>
            <?php if (!empty($team['members'])): ?>
                <div class="admin-table__wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Alumne/a</th>
                                <th>Email</th>
                                <th>Rols de projecte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team['members'] as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($member['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($member['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($member['role_names'] ?? 'Sense rol assignat'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="muted">No s'han trobat membres en aquest grup.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>Encara no estàs assignat a cap grup o equip en aquesta edició del projecte.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
