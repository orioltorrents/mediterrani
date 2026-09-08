<?php

declare(strict_types=1);

use Google\Client;
use Google\Service\Docs;
use Google\Service\Docs\Document;
use Google\Service\Docs\StructuralElement;
use GuzzleHttp\Client as HttpClient;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Helpers/env.php';

$config = require dirname(__DIR__) . '/config/google.php';
$input = trim((string) ($argv[1] ?? ''));

if ($input === '') {
    fwrite(STDERR, "Ús: php scripts/test-google-doc-connection.php <GOOGLE_DOC_ID_O_URL>\n");
    exit(1);
}

if (($config['enabled'] ?? false) !== true) {
    fwrite(STDERR, "Google Sync no està activat. Revisa GOOGLE_SYNC_ENABLED=true a .env.\n");
    exit(1);
}

$documentId = extractGoogleDocumentId($input);
if ($documentId === '') {
    fwrite(STDERR, "No s'ha pogut detectar cap ID de Google Doc vàlid.\n");
    exit(1);
}

$serviceAccountPath = absolutePath((string) ($config['service_account_path'] ?? ''));
if ($serviceAccountPath === '' || !is_file($serviceAccountPath)) {
    fwrite(STDERR, "No s'ha trobat el JSON del service account: {$serviceAccountPath}\n");
    exit(1);
}

$caBundlePath = absolutePath((string) ($config['ca_bundle_path'] ?? ''));
if ($caBundlePath !== '' && !is_file($caBundlePath)) {
    fwrite(STDERR, "No s'ha trobat el paquet de certificats CA: {$caBundlePath}\n");
    exit(1);
}

try {
    $client = new Client();
    $client->setAuthConfig($serviceAccountPath);
    $client->setScopes($config['scopes'] ?? []);

    if ($caBundlePath !== '') {
        $client->setHttpClient(new HttpClient(['verify' => $caBundlePath]));
    }

    $docsService = new Docs($client);
    $document = $docsService->documents->get($documentId);
    $plainText = extractDocumentText($document);

    echo "Connexió Google Docs OK\n";
    echo 'Títol: ' . ($document->getTitle() ?: '(sense títol)') . "\n";
    echo 'Caràcters llegits: ' . mb_strlen($plainText, 'UTF-8') . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, "No s'ha pogut llegir el Google Doc.\n");
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

function extractGoogleDocumentId(string $input): string
{
    if (preg_match('#/document/d/([a-zA-Z0-9_-]+)#', $input, $matches) === 1) {
        return $matches[1];
    }

    if (preg_match('/^[a-zA-Z0-9_-]+$/', $input) === 1) {
        return $input;
    }

    return '';
}

function absolutePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^[a-zA-Z]:[\\/]#', $path) === 1 || str_starts_with($path, '/')) {
        return $path;
    }

    return dirname(__DIR__) . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function extractDocumentText(Document $document): string
{
    $body = $document->getBody();
    if ($body === null) {
        return '';
    }

    $text = '';
    foreach ($body->getContent() ?? [] as $structuralElement) {
        if ($structuralElement instanceof StructuralElement) {
            $text .= extractStructuralElementText($structuralElement);
        }
    }

    return trim($text);
}

function extractStructuralElementText(StructuralElement $structuralElement): string
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
