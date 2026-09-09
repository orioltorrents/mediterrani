<?php

declare(strict_types=1);

class AdminEvidenceService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function summary(): array
    {
        try {
            $categories = $this->pdo->query(
                'SELECT ec.id, ec.nom, ec.color_code, COUNT(e.id) AS evidence_count
                   FROM evidencies_categoria ec
              LEFT JOIN evidencies e ON e.evidencia_categoria_id = ec.id
               GROUP BY ec.id, ec.nom, ec.color_code
               ORDER BY ec.id'
            )->fetchAll(PDO::FETCH_ASSOC);

            $types = $this->pdo->query(
                'SELECT e.tipus_evidencia, ec.nom AS category_name, ec.color_code,
                        COUNT(*) AS evidence_count, ec.id AS category_id
                   FROM evidencies e
                   INNER JOIN evidencies_categoria ec ON ec.id = e.evidencia_categoria_id
               GROUP BY ec.id, ec.nom, ec.color_code, e.tipus_evidencia
               ORDER BY ec.id, e.tipus_evidencia'
            )->fetchAll(PDO::FETCH_ASSOC);

            $evidences = $this->pdo->query(
                'SELECT e.titol, e.tipus_evidencia, ec.nom AS category_name, ec.color_code
                   FROM evidencies e
                   INNER JOIN evidencies_categoria ec ON ec.id = e.evidencia_categoria_id
               ORDER BY ec.id, e.id'
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($types as &$type) {
                $type['label'] = $this->typeLabel((string) $type['tipus_evidencia']);
                $type['evidence_count'] = (int) $type['evidence_count'];
            }
            unset($type);

            return [
                'count' => count($evidences),
                'categories' => array_map(static function (array $category): array {
                    $category['evidence_count'] = (int) $category['evidence_count'];
                    return $category;
                }, $categories),
                'types' => $types,
                'evidences' => $evidences,
            ];
        } catch (Throwable) {
            return [
                'count' => 0,
                'categories' => [],
                'types' => [],
                'evidences' => [],
            ];
        }
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'nota_aula' => 'Nota d’aula',
            'tasca' => 'Tasca',
            'apunts' => 'Apunts',
            'exercici' => 'Exercici',
            'registre_camp' => 'Registre de camp',
            'informe' => 'Informe',
            'maqueta' => 'Maqueta',
            'presentacio' => 'Presentació',
            'resolucio_problemes' => 'Resolució de problemes',
            'autoevaluacio', 'autoavaluacio' => 'Autoavaluació',
            'coavaluacio' => 'Coavaluació',
            'heteroavaluacio' => 'Heteroavaluació',
            'audio' => 'Àudio',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
