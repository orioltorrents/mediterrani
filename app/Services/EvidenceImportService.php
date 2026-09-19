<?php
namespace App\Services;

use PDO;
use Exception;

class EvidenceImportService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function processEvidenceData(int $projectAcademicYearId, array $rows): array {
        $results = ['inserted' => 0, 'errors' => []];

        $stmtInsert = $this->db->prepare("
            INSERT INTO evidencies_alumnes 
            (user_id, project_academic_year_id, objectiu_id, evidencia_categoria_id, evidencia_tipus_id, observacio)
            VALUES (:user_id, :pay_id, :obj_id, :cat_id, :tipus_id, :observacio)
        ");

        foreach ($rows as $index => $row) {
            $email = $row['email'] ?? null;
            $codiOa = $row['codi_oa'] ?? null;
            $nomTipus = $row['nom_tipus'] ?? null;
            $observacio = $row['observacio'] ?? null;

            if (!$email || !$codiOa || !$observacio) {
                $results['errors'][] = "Fila $index: Falten dades obligatòries (email, codi_oa, observacio).";
                continue;
            }

            $userId = $this->resolveUserId($email);
            if (!$userId) {
                $results['errors'][] = "Fila $index: L'alumne amb correu $email no existeix.";
                continue; 
            }

            // Obtenim tant el tipus_id com el categoria_id lligat directament de la taula
            $tipusData = $nomTipus ? $this->resolveTipusData($nomTipus) : null;
            $tipusId = $tipusData ? (int) $tipusData['id'] : null;
            $categoriaId = $tipusData ? (int) $tipusData['evidencia_categoria_id'] : null;

            try {
                $stmtInsert->execute([
                    ':user_id' => $userId,
                    ':pay_id' => $projectAcademicYearId,
                    ':obj_id' => (int) $codiOa,
                    ':cat_id' => $categoriaId,
                    ':tipus_id' => $tipusId,
                    ':observacio' => $observacio
                ]);
                $results['inserted']++;
            } catch (Exception $e) {
                $results['errors'][] = "Fila $index: Error inserint dades - " . $e->getMessage();
            }
        }

        return $results;
    }

    private function resolveUserId(string $email): ?int {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        return $stmt->fetchColumn() ?: null;
    }

    private function resolveTipusData(string $nom): ?array {
        // Busca el tipus pel seu nom o títol i retorna també la categoria associada
        $stmt = $this->db->prepare("SELECT id, evidencia_categoria_id FROM evidencies_tipus WHERE tipus_evidencia = :nom OR titol = :nom LIMIT 1");
        $stmt->execute([':nom' => $nom]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}