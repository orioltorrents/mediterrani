<?php
ob_start();
$aboutContent = is_array($aboutContent ?? null) ? $aboutContent : null;
$blocks = is_array($aboutContent['blocks'] ?? null) ? $aboutContent['blocks'] : [];

if (!function_exists('publicAboutRenderSegments')) {
    function publicAboutRenderSegments(array $segments, string $fallbackText): string
    {
        if ($segments === []) {
            return htmlspecialchars($fallbackText, ENT_QUOTES, 'UTF-8');
        }

        $html = '';
        foreach ($segments as $segment) {
            $text = htmlspecialchars((string) ($segment['text'] ?? ''), ENT_QUOTES, 'UTF-8');
            if ($text === '') {
                continue;
            }

            if (($segment['italic'] ?? false) === true) {
                $text = '<em>' . $text . '</em>';
            }

            if (($segment['bold'] ?? false) === true) {
                $text = '<strong>' . $text . '</strong>';
            }

            $linkUrl = trim((string) ($segment['link_url'] ?? ''));
            if ($linkUrl !== '' && filter_var($linkUrl, FILTER_VALIDATE_URL) !== false && in_array(parse_url($linkUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $safeUrl = htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8');
                $text = '<a class="public-about__link" href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
            }

            $html .= $text;
        }

        return $html;
    }

    function publicAboutRenderBlockContent(array $block): string
    {
        $segments = is_array($block['segments'] ?? null) ? $block['segments'] : [];

        return publicAboutRenderSegments($segments, (string) ($block['text'] ?? ''));
    }

    function publicAboutRenderList(array $items, int &$position, int $level): string
    {
        $current = $items[$position] ?? [];
        $listKind = (string) ($current['list_kind'] ?? 'unordered');
        $tag = $listKind === 'ordered' ? 'ol' : 'ul';
        $start = $listKind === 'ordered' && isset($current['ordinal']) ? ' start="' . max(1, (int) $current['ordinal']) . '"' : '';
        $html = '<' . $tag . ' class="public-about__list public-about__list--' . htmlspecialchars($listKind, ENT_QUOTES, 'UTF-8') . ' public-about__list--level-' . max(0, $level) . '"' . $start . '>';

        while ($position < count($items)) {
            $item = $items[$position];
            $itemLevel = (int) ($item['indent_level'] ?? $item['nesting_level'] ?? 0);
            if ($itemLevel < $level) {
                break;
            }

            $itemListKind = (string) ($item['list_kind'] ?? 'unordered');
            if ($itemLevel === $level && $itemListKind !== $listKind) {
                break;
            }

            if ($itemLevel > $level) {
                $html .= publicAboutRenderList($items, $position, $itemLevel);
                continue;
            }

            $textStyle = (string) ($item['text_style'] ?? 'paragraph');
            $html .= '<li class="public-about__list-item public-about__list-item--' . htmlspecialchars($textStyle, ENT_QUOTES, 'UTF-8') . ' public-about__list-item--level-' . max(0, $itemLevel) . '">';
            $html .= publicAboutRenderBlockContent($item);
            $position++;

            while ($position < count($items) && (int) ($items[$position]['indent_level'] ?? $items[$position]['nesting_level'] ?? 0) > $level) {
                $html .= publicAboutRenderList($items, $position, (int) ($items[$position]['indent_level'] ?? $items[$position]['nesting_level'] ?? 0));
            }

            $html .= '</li>';
        }

        return $html . '</' . $tag . '>';
    }
}
?>
<section class="public-about" aria-labelledby="about-title">
    <article class="public-about__card">
        <p class="public-home__eyebrow">Mediterrani</p>
        <?php if ($blocks !== []): ?>
            <?php $mainTitleIdUsed = false; ?>
            <?php for ($index = 0; $index < count($blocks); $index++): ?>
                <?php
                $block = $blocks[$index];
                $type = (string) ($block['type'] ?? 'paragraph');
                $contentHtml = publicAboutRenderBlockContent($block);
                ?>
                <?php if ($type === 'list_item'): ?>
                    <?php
                    $listBlocks = [];
                    while ($index < count($blocks) && (string) ($blocks[$index]['type'] ?? '') === 'list_item') {
                        $listBlocks[] = $blocks[$index];
                        $index++;
                    }
                    $index--;
                    $listPosition = 0;
                    while ($listPosition < count($listBlocks)) {
                        echo publicAboutRenderList($listBlocks, $listPosition, (int) ($listBlocks[$listPosition]['indent_level'] ?? $listBlocks[$listPosition]['nesting_level'] ?? 0));
                    }
                    ?>
                <?php endif; ?>

                <?php if ($type === 'title' || $type === 'heading_1'): ?>
                    <?php $titleId = !$mainTitleIdUsed ? 'about-title' : 'about-title-' . (int) $index; ?>
                    <?php $mainTitleIdUsed = true; ?>
                    <h1 id="<?= $titleId ?>" class="public-about__title"><?= $contentHtml ?></h1>
                <?php elseif ($type === 'heading_2'): ?>
                    <h2 class="public-about__subtitle"><?= $contentHtml ?></h2>
                <?php elseif ($type === 'heading_3'): ?>
                    <h3 class="public-about__heading public-about__heading--level-3"><?= $contentHtml ?></h3>
                <?php elseif ($type === 'heading_4'): ?>
                    <h4 class="public-about__heading public-about__heading--level-4"><?= $contentHtml ?></h4>
                <?php elseif ($type === 'paragraph'): ?>
                    <p class="public-about__text"><?= $contentHtml ?></p>
                <?php endif; ?>
            <?php endfor; ?>
        <?php else: ?>
            <h1 id="about-title" class="public-about__title"><?= htmlspecialchars(trans('about_fallback_title'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="public-about__text"><?= htmlspecialchars(trans('about_fallback_text'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </article>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
