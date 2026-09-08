<?php if (!empty($_SESSION['impersonation']['target'])): ?>
    <?php
    $impersonatedUser = $_SESSION['impersonation']['target'];
    $impersonatedName = trim((string) ($impersonatedUser['name'] ?? '') . ' ' . (string) ($impersonatedUser['surname'] ?? ''));
    $impersonatedName = $impersonatedName !== '' ? $impersonatedName : (string) ($impersonatedUser['email'] ?? '');
    ?>
    <div class="impersonation-banner" role="status">
        <span class="impersonation-banner__text">Estàs veient com <?= htmlspecialchars($impersonatedName, ENT_QUOTES, 'UTF-8') ?></span>
        <form class="impersonation-banner__form" method="post" action="<?= url('admin/stop-impersonation') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button class="impersonation-banner__button" type="submit"><?= htmlspecialchars(trans('back_to_admin'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </div>
<?php endif; ?>
<header class="site-header">
    <div class="brand site-header__brand">
        <a href="<?= url('') ?>">Mediterrani</a>
    </div>
    <nav class="site-header__nav">
        <?php if (!empty($_SESSION['user'])): ?>
            <?php
            $headerUser = $_SESSION['impersonation']['target'] ?? $_SESSION['user'];
            $roles = $headerUser['roles'] ?? [];
            $displayName = trim((string) ($headerUser['name'] ?? '') . ' ' . (string) ($headerUser['surname'] ?? ''));
            $displayName = $displayName !== '' ? $displayName : (string) ($headerUser['email'] ?? '');
            ?>
            <?php if (in_array('student', $roles, true)): ?>
                <a class="site-header__nav-link" href="<?= url('alumne') ?>"><?= htmlspecialchars(trans('projects'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <?php if (in_array('teacher', $roles, true)): ?>
                <a class="site-header__nav-link" href="<?= url('professor') ?>"><?= htmlspecialchars(trans('projects'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <?php if (in_array('admin', $roles, true)): ?>
                <a class="site-header__nav-link" href="<?= url('admin') ?>">Admin</a>
            <?php endif; ?>
            <?php if ($displayName !== ''): ?>
                <span class="site-header__user"><?= htmlspecialchars(sprintf(trans('logged_in_as'), $displayName), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <form class="inline-form" method="post" action="<?= url('logout') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="site-header__nav-link site-header__nav-link--button"><?= htmlspecialchars(trans('logout'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        <?php else: ?>
            <a class="site-header__nav-link" href="<?= url('login') ?>"><?= htmlspecialchars(trans('login'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    </nav>
    <nav class="lang-switcher" aria-label="Idioma">
        <?php
        $currentLang = getLanguage();
        $supportedLangs = supportedLanguages();
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = appBasePath();
        if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
            $requestPath = substr($requestPath, strlen($basePath));
        }
        $requestPath = '/' . trim($requestPath, '/');
        $queryParams = [];
        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $queryParams);
        unset($queryParams['lang']);
        $queryString = http_build_query($queryParams);
        $localizedHref = static function (string $code) use ($requestPath, $queryString, $supportedLangs): string {
            $segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn (string $segment): bool => $segment !== ''));

            if ($segments === []) {
                $path = $code;
            } elseif (in_array($segments[0], $supportedLangs, true)) {
                $segments[0] = $code;
                $path = implode('/', $segments);
            } elseif ($segments[0] === 'projectes') {
                array_unshift($segments, $code);
                $path = implode('/', $segments);
            } else {
                $href = url(implode('/', $segments));
                return $href . '?lang=' . rawurlencode($code) . ($queryString !== '' ? '&' . $queryString : '');
            }

            return url($path) . ($queryString !== '' ? '?' . $queryString : '');
        };
        $langs = [
            'ca' => 'Català',
            'es' => 'Castellano',
            'en' => 'English',
            'fr' => 'Français',
        ];
        ?>
        <?php foreach ($langs as $code => $name): ?>
            <a class="lang-switcher__link<?= $code === $currentLang ? ' lang-switcher__link--active' : '' ?>" href="<?= htmlspecialchars($localizedHref($code), ENT_QUOTES, 'UTF-8') ?>" hreflang="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(strtoupper($code), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </nav>
</header>
