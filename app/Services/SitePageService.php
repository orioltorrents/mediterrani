<?php

declare(strict_types=1);

use Google\Client;
use Google\Service\Docs;
use Google\Service\Docs\Document;
use Google\Service\Docs\StructuralElement;
use GuzzleHttp\Client as HttpClient;

class SitePageService
{
    private ?PDO $connection = null;

    public function aboutContent(): ?array
    {
        $page = $this->sitePage('que-es-entorns', getLanguage());
        if ($page === null) {
            return null;
        }

        $storedContent = $this->storedPageContent($page);
        if ($storedContent !== null) {
            return $storedContent;
        }

        return null;
    }

    public function syncPage(string $slug, string $languageCode = 'ca'): array
    {
        $page = $this->sitePage($slug, $languageCode);
        if ($page === null) {
            throw new RuntimeException('No s’ha trobat la pàgina pública configurada.');
        }

        $documentId = trim((string) ($page['google_file_id'] ?? ''));
        if ($documentId === '') {
            throw new RuntimeException('La pàgina no té cap Google Doc configurat.');
        }

        try {
            $content = $this->googleDocumentContent($documentId);
            if ($content === null) {
                throw new RuntimeException('No s’ha pogut obtenir contingut del Google Doc.');
            }

            $contentJson = json_encode($content['blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $plainText = $this->blocksText($content['blocks'] ?? []);
            $versionHash = hash('sha256', $contentJson);

            $stmt = $this->pdo()->prepare(
                'UPDATE site_pages
                 SET title = :title,
                     content_json = :content_json,
                     plain_text = :plain_text,
                     version_hash = :version_hash,
                     last_synced_at = CURRENT_TIMESTAMP,
                     last_sync_status = "completed",
                     last_sync_error = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'title' => (string) ($content['title'] ?? $page['title'] ?? 'Pàgina'),
                'content_json' => $contentJson,
                'plain_text' => $plainText,
                'version_hash' => $versionHash,
                'id' => (int) $page['id'],
            ]);

            return [
                'status' => 'completed',
                'page_id' => (int) $page['id'],
                'slug' => $slug,
                'language_code' => $languageCode,
                'version_hash' => $versionHash,
                'characters' => mb_strlen($plainText, 'UTF-8'),
            ];
        } catch (Throwable $throwable) {
            $stmt = $this->pdo()->prepare(
                'UPDATE site_pages
                 SET last_sync_status = "failed",
                     last_sync_error = :last_sync_error,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'last_sync_error' => $throwable->getMessage(),
                'id' => (int) $page['id'],
            ]);

            throw $throwable;
        }
    }

    public function updateGoogleFileId(string $slug, string $languageCode, string $googleFileId): array
    {
        $slug = trim($slug);
        $languageCode = trim($languageCode) !== '' ? trim($languageCode) : 'ca';
        $googleFileId = $this->extractGoogleDocumentId(trim($googleFileId));

        if ($slug === '') {
            throw new InvalidArgumentException('No s’ha indicat cap pàgina pública.');
        }

        if ($googleFileId === '') {
            throw new InvalidArgumentException('Cal indicar un ID o URL de Google Doc vàlid.');
        }

        $page = $this->sitePage($slug, $languageCode);
        if ($page === null) {
            throw new RuntimeException('No s’ha trobat la pàgina pública configurada.');
        }

        $currentGoogleFileId = trim((string) ($page['google_file_id'] ?? ''));
        if ($currentGoogleFileId === $googleFileId) {
            return [
                'status' => 'unchanged',
                'page_id' => (int) $page['id'],
                'slug' => $slug,
                'language_code' => $languageCode,
            ];
        }

        $stmt = $this->pdo()->prepare(
            'UPDATE site_pages
             SET google_file_id = :google_file_id,
                 content_json = NULL,
                 plain_text = NULL,
                 version_hash = NULL,
                 last_synced_at = NULL,
                 last_sync_status = "never",
                 last_sync_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'google_file_id' => $googleFileId,
            'id' => (int) $page['id'],
        ]);

        return [
            'status' => 'updated',
            'page_id' => (int) $page['id'],
            'slug' => $slug,
            'language_code' => $languageCode,
        ];
    }

    private function extractGoogleDocumentId(string $input): string
    {
        if (preg_match('#/document/d/([a-zA-Z0-9_-]+)#', $input, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^[a-zA-Z0-9_-]+$/', $input) === 1) {
            return $input;
        }

        return '';
    }

    private function sitePage(string $slug, string $languageCode): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, slug, language_code, title, google_file_id, content_json, plain_text, version_hash, last_synced_at, last_sync_status, last_sync_error, is_active
             FROM site_pages
             WHERE slug = :slug AND language_code = :language_code AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'slug' => $slug,
            'language_code' => $languageCode,
        ]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($page) ? $page : null;
    }

    private function storedPageContent(array $page): ?array
    {
        $contentJson = trim((string) ($page['content_json'] ?? ''));
        if ($contentJson === '') {
            return null;
        }

        try {
            $blocks = json_decode($contentJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($blocks)) {
            return null;
        }

        return [
            'title' => (string) ($page['title'] ?? 'Pàgina'),
            'blocks' => $blocks,
            'last_synced_at' => $page['last_synced_at'] ?? null,
        ];
    }

    private function googleDocumentContent(string $documentId): ?array
    {
        $config = require dirname(__DIR__, 2) . '/config/google.php';
        if (($config['enabled'] ?? false) !== true) {
            return null;
        }

        $serviceAccountPath = $this->absolutePath((string) ($config['service_account_path'] ?? ''));
        if ($serviceAccountPath === '' || !is_file($serviceAccountPath)) {
            return null;
        }

        try {
            $client = new Client();
            $client->setAuthConfig($serviceAccountPath);
            $client->setScopes($config['scopes'] ?? []);

            $caBundlePath = $this->absolutePath((string) ($config['ca_bundle_path'] ?? ''));
            if ($caBundlePath !== '' && is_file($caBundlePath)) {
                $client->setHttpClient(new HttpClient(['verify' => $caBundlePath]));
            }

            $document = (new Docs($client))->documents->get($documentId);
            $blocks = $this->extractBlocks($document);

            return [
                'title' => $document->getTitle() ?: 'Què és Mediterrani',
                'blocks' => $blocks,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function absolutePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^[a-zA-Z]:[\\/]#', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return dirname(__DIR__, 2) . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function extractBlocks(Document $document): array
    {
        $body = $document->getBody();
        if ($body === null) {
            return [];
        }

        $blocks = [];
        $listCounters = [];
        $content = $body->getContent() ?? [];
        $listIndentBaselines = $this->listIndentBaselines($content);

        foreach ($content as $structuralElement) {
            if (!$structuralElement instanceof StructuralElement) {
                continue;
            }

            $segments = $this->extractStructuralElementSegments($structuralElement);
            $rawText = $this->segmentsText($segments);
            $typedIndentLevel = $this->typedIndentLevel($rawText);
            if ($typedIndentLevel > 0) {
                $segments = $this->removeLeadingIndentFromSegments($segments);
            }

            $text = $this->cleanGoogleText($this->segmentsText($segments));
            if ($text !== '') {
                $blocks[] = $this->blockFromStructuralElement($document, $structuralElement, $text, $segments, $typedIndentLevel, $listCounters, $listIndentBaselines);
            }
        }

        return $blocks;
    }

    private function blockFromStructuralElement(Document $document, StructuralElement $structuralElement, string $text, array $segments, int $typedIndentLevel, array &$listCounters, array $listIndentBaselines): array
    {
        $paragraph = $structuralElement->getParagraph();
        $textStyle = $this->textStyleType($structuralElement);

        if ($paragraph?->getBullet() !== null) {
            $bullet = $paragraph->getBullet();
            $listId = (string) ($bullet->getListId() ?? '');
            $nestingLevel = (int) ($bullet->getNestingLevel() ?? 0);
            $indentLevel = max($typedIndentLevel, $this->listIndentLevel($structuralElement, $nestingLevel, $listIndentBaselines[$listId] ?? null));
            $listKind = $this->listKind($document, $structuralElement);
            $ordinal = null;

            if ($listKind === 'ordered') {
                $counterKey = $listId . ':' . $indentLevel;
                $listCounters[$counterKey] = (int) ($listCounters[$counterKey] ?? 0) + 1;
                $ordinal = $listCounters[$counterKey];
            }

            return [
                'type' => 'list_item',
                'list_kind' => $listKind,
                'list_id' => $listId,
                'nesting_level' => $nestingLevel,
                'indent_level' => $indentLevel,
                'ordinal' => $ordinal,
                'text_style' => $textStyle,
                'text' => $text,
                'segments' => $segments,
            ];
        }

        return [
            'type' => $textStyle,
            'text' => $text,
            'segments' => $segments,
        ];
    }

    private function blockType(StructuralElement $structuralElement): string
    {
        return $this->textStyleType($structuralElement);
    }

    private function textStyleType(StructuralElement $structuralElement): string
    {
        $paragraph = $structuralElement->getParagraph();
        $style = $paragraph?->getParagraphStyle();
        $namedStyleType = (string) ($style?->getNamedStyleType() ?? '');

        return match ($namedStyleType) {
            'TITLE' => 'title',
            'HEADING_1' => 'heading_1',
            'HEADING_2' => 'heading_2',
            'HEADING_3' => 'heading_3',
            'HEADING_4' => 'heading_4',
            default => 'paragraph',
        };
    }

    private function listKind(Document $document, StructuralElement $structuralElement): string
    {
        $bullet = $structuralElement->getParagraph()?->getBullet();
        $listId = (string) ($bullet?->getListId() ?? '');
        $nestingLevel = (int) ($bullet?->getNestingLevel() ?? 0);
        $lists = $document->getLists() ?? [];
        $list = is_array($lists) ? ($lists[$listId] ?? null) : null;
        $levels = $list?->getListProperties()?->getNestingLevels() ?? [];
        $level = is_array($levels) ? ($levels[$nestingLevel] ?? null) : null;
        $glyphType = strtoupper((string) ($level?->getGlyphType() ?? ''));

        if (in_array($glyphType, ['DECIMAL', 'ZERO_DECIMAL', 'UPPER_ALPHA', 'ALPHA', 'LOWER_ALPHA', 'UPPER_ROMAN', 'ROMAN', 'LOWER_ROMAN'], true)) {
            return 'ordered';
        }

        return 'unordered';
    }

    private function listIndentBaselines(array $content): array
    {
        $baselines = [];

        foreach ($content as $structuralElement) {
            if (!$structuralElement instanceof StructuralElement) {
                continue;
            }

            $paragraph = $structuralElement->getParagraph();
            $bullet = $paragraph?->getBullet();
            $listId = (string) ($bullet?->getListId() ?? '');
            $magnitude = $paragraph?->getParagraphStyle()?->getIndentStart()?->getMagnitude();

            if ($listId === '' || !is_numeric($magnitude)) {
                continue;
            }

            $magnitude = (float) $magnitude;
            $baselines[$listId] = isset($baselines[$listId]) ? min($baselines[$listId], $magnitude) : $magnitude;
        }

        return $baselines;
    }

    private function listIndentLevel(StructuralElement $structuralElement, int $fallbackLevel, ?float $baseline): int
    {
        $paragraphStyle = $structuralElement->getParagraph()?->getParagraphStyle();
        $indentStart = $paragraphStyle?->getIndentStart();
        $magnitude = $indentStart?->getMagnitude();

        if (!is_numeric($magnitude)) {
            return $fallbackLevel;
        }

        $base = $baseline ?? (float) $magnitude;
        $indentLevel = (int) round(max(0.0, ((float) $magnitude - $base) / 36.0));

        return max($fallbackLevel, $indentLevel);
    }

    private function cleanGoogleText(string $text, bool $trim = true): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\x1B\[[0-9;?]*[ -\/]*[@-~]/', '', $text) ?? $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return $trim ? trim($text) : $text;
    }

    private function segmentsText(array $segments): string
    {
        $text = '';
        foreach ($segments as $segment) {
            $text .= (string) ($segment['text'] ?? '');
        }

        return $text;
    }

    private function blocksText(array $blocks): string
    {
        $text = '';
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $text .= (string) ($block['text'] ?? '') . "\n";
            }
        }

        return trim($text);
    }

    private function typedIndentLevel(string $text): int
    {
        if (preg_match('/^(\t+| {4,})/u', $text, $matches) !== 1) {
            return 0;
        }

        $indent = $matches[1];
        $tabs = substr_count($indent, "\t");
        $spaces = intdiv(strlen(str_replace("\t", '', $indent)), 4);

        return max(0, $tabs + $spaces);
    }

    private function removeLeadingIndentFromSegments(array $segments): array
    {
        foreach ($segments as $index => $segment) {
            $text = (string) ($segment['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $segments[$index]['text'] = preg_replace('/^(\t+| {4,})/u', '', $text) ?? $text;
            break;
        }

        return $segments;
    }

    private function extractStructuralElementSegments(StructuralElement $structuralElement): array
    {
        $paragraph = $structuralElement->getParagraph();
        if ($paragraph === null) {
            return [];
        }

        $segments = [];
        foreach ($paragraph->getElements() ?? [] as $paragraphElement) {
            $textRun = $paragraphElement->getTextRun();
            if ($textRun === null) {
                continue;
            }

            $text = $this->cleanGoogleText((string) $textRun->getContent(), false);
            if (trim($text) === '') {
                continue;
            }

            $style = $textRun->getTextStyle();
            $link = $style?->getLink();
            $linkUrl = $link !== null ? trim((string) ($link->getUrl() ?? '')) : '';
            $segments[] = [
                'text' => $text,
                'bold' => $style?->getBold() === true,
                'italic' => $style?->getItalic() === true,
                'link_url' => $linkUrl,
            ];
        }

        return $segments;
    }

    private function extractStructuralElementText(StructuralElement $structuralElement): string
    {
        $paragraph = $structuralElement->getParagraph();
        if ($paragraph === null) {
            return '';
        }

        $text = '';
        foreach ($paragraph->getElements() ?? [] as $paragraphElement) {
            $textRun = $paragraphElement->getTextRun();
            if ($textRun !== null) {
                $text .= (string) $textRun->getContent();
            }
        }

        return $text;
    }

    private function pdo(): PDO
    {
        if ($this->connection === null) {
            $this->connection = require dirname(__DIR__, 2) . '/config/database.php';
        }

        return $this->connection;
    }
}
