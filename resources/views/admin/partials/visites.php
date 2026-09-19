<?php
/** @var mixed $analytics */
/** @var mixed $geoMapPoints */
?>
<section id="visites" class="card admin-panel admin-collapsible is-collapsed">
    <div class="admin-panel__header">
        <h2>Visites</h2>
        <div class="admin-actions">
            <span class="status">Analítica bàsica</span>
            <button class="collapse-toggle" type="button" data-collapse="visites-content">Mostrar</button>
        </div>
    </div>
    <div id="visites-content" class="admin-collapsible__content">
        <div class="admin-summary__section-grid">
            <div class="admin-summary__card"><div class="admin-summary__icon">👁</div><div class="admin-summary__body"><span class="admin-summary__label">Visites totals</span><strong class="admin-summary__value"><?= (int) ($analytics['total_visits'] ?? 0) ?></strong><span class="admin-summary__desc">Activitat registrada al web</span></div></div>
            <div class="admin-summary__card"><div class="admin-summary__icon">🔁</div><div class="admin-summary__body"><span class="admin-summary__label">Sessions úniques</span><strong class="admin-summary__value"><?= (int) ($analytics['unique_sessions'] ?? 0) ?></strong><span class="admin-summary__desc">Sessions diferenciades</span></div></div>
            <div class="admin-summary__card"><div class="admin-summary__icon">👤</div><div class="admin-summary__body"><span class="admin-summary__label">Usuaris reconeguts</span><strong class="admin-summary__value"><?= (int) ($analytics['unique_users'] ?? 0) ?></strong><span class="admin-summary__desc">Usuaris amb visites identificades</span></div></div>
        </div>
        <?php $pageStats = is_array($analytics['page_stats'] ?? null) ? $analytics['page_stats'] : []; ?>
        <?php $classVisitStats = is_array($analytics['current_class_visit_stats'] ?? null) ? $analytics['current_class_visit_stats'] : []; ?>
        <h3>Visites per classe</h3>
        <?php if ($classVisitStats !== []): ?>
            <div class="admin-table__wrapper">
                <table class="admin-table admin-table--compact">
                    <thead><tr><th>Classe</th><th>Alumnes</th><th>Visites</th><th>Alumnes amb visites</th><th>Alumnes sense visites</th></tr></thead>
                    <tbody>
                        <?php foreach ($classVisitStats as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['class_code'] ?: $row['class_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) ($row['total_students'] ?? 0) ?></td>
                                <td><?= (int) ($row['page_visits'] ?? 0) ?></td>
                                <td><?= (int) ($row['students_with_visits'] ?? 0) ?></td>
                                <td><?= (int) ($row['students_without_visits'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Encara no hi ha dades de visites per classe.</p>
        <?php endif; ?>
        <div class="admin-summary__section-grid">
            <section class="admin-subpanel">
                <h3>Dispositius</h3>
                <?php $deviceStats = is_array($analytics['device_stats'] ?? null) ? $analytics['device_stats'] : []; ?>
                <?php foreach ($deviceStats as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['device_type'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?>
            </section>
            <section class="admin-subpanel">
                <h3>Sistemes operatius</h3>
                <?php $osStats = is_array($analytics['os_stats'] ?? null) ? $analytics['os_stats'] : []; ?>
                <?php foreach ($osStats as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['os_family'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?>
            </section>
        </div>
        <section class="admin-subpanel">
            <h3>Mapa de visites</h3>
            <div class="admin-geo-map" data-geo-map data-geo-points="<?= htmlspecialchars(json_encode($geoMapPoints ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>"></div>
        </section>
        <div class="admin-summary__section-grid">
            <section class="admin-subpanel"><h3>Geografia</h3><?php foreach (($analytics['geo_stats'] ?? []) as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['country_code'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?></section>
            <section class="admin-subpanel"><h3>Pàgines més vistes</h3><?php foreach ($pageStats as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?></section>
        </div>
        <?php if ($pageStats !== []): ?>
            <div class="admin-table__wrapper">
                <table class="admin-table admin-table--compact">
                    <thead><tr><th>Pàgina</th><th>Visites</th></tr></thead>
                    <tbody>
                        <?php foreach ($pageStats as $row): ?>
                            <tr><td><?= htmlspecialchars((string) ($row['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) ($row['total'] ?? 0) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Encara no hi ha visites registrades.</p>
        <?php endif; ?>
        <?php $recentVisits = is_array($analytics['recent_visits'] ?? null) ? $analytics['recent_visits'] : []; ?>
        <h3>Visites recents</h3>
        <?php if ($recentVisits !== []): ?>
            <div class="admin-table__wrapper">
                <table class="admin-table admin-table--compact">
                    <thead><tr><th>Data</th><th>Pàgina</th><th>Usuari</th><th>País</th><th>Dispositiu</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentVisits as $visit): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($visit['visited_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($visit['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(trim((string) ($visit['name'] ?? '') . ' ' . (string) ($visit['surname'] ?? '')) ?: 'Visitant', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($visit['country_code'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($visit['device_type'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Encara no hi ha visites recents.</p>
        <?php endif; ?>
    </div>
</section>
