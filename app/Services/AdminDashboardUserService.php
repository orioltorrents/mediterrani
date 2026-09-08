<?php

declare(strict_types=1);

class AdminDashboardUserService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function users(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.name, u.surname, u.email, u.is_active, u.created_at,
                    COUNT(DISTINCT sv.id) AS visit_count
              FROM users u
              LEFT JOIN site_visits sv ON sv.user_id = u.id
              GROUP BY u.id, u.name, u.surname, u.email, u.is_active, u.created_at
              ORDER BY u.created_at DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function userRoles(): array
    {
        $stmt = $this->pdo->query(
            'SELECT ur.user_id, r.name AS role_name
              FROM user_web_roles ur
              INNER JOIN web_roles r ON r.id = ur.role_id
              ORDER BY ur.user_id, r.name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function classMemberships(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT cm.user_id, cm.class_id, c.class_name AS class_name, c.class_code,
                        c.academic_year_id, ay.name AS academic_year_name
                  FROM class_members cm
                  INNER JOIN classes c ON c.id = cm.class_id
                  INNER JOIN academic_years ay ON ay.id = c.academic_year_id
                  ORDER BY cm.user_id, ay.start_year, c.class_name'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function classTeachers(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT ct.class_id, ct.user_id, c.class_name AS class_name, c.class_code, u.name, u.surname
                  FROM class_teachers ct
                  INNER JOIN classes c ON c.id = ct.class_id
                  INNER JOIN users u ON u.id = ct.user_id
                  ORDER BY c.class_name, u.name, u.surname'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function buildGeoMapPoints(array $geoStats): array
    {
        $points = [];
        $countryCoordinates = [
            'ES' => [40.4168, -3.7038],
            'PT' => [38.7223, -9.1393],
            'FR' => [48.8566, 2.3522],
            'GB' => [51.5072, -0.1276],
            'DE' => [52.52, 13.405],
            'IT' => [41.9028, 12.4964],
            'BE' => [50.8503, 4.3517],
            'NL' => [52.3676, 4.9041],
            'CH' => [46.948, 7.4474],
            'US' => [38.9072, -77.0369],
            'CA' => [45.4215, -75.6972],
            'MX' => [19.4326, -99.1332],
            'BR' => [-15.7939, -47.8828],
            'AR' => [-34.6037, -58.3816],
            'CL' => [-33.4489, -70.6693],
            'PE' => [-12.0464, -77.0428],
            'CO' => [4.711, -74.0721],
        ];

        foreach ($geoStats as $row) {
            $countryCode = strtoupper(trim((string) ($row['country_code'] ?? '')));
            if ($countryCode === '') {
                continue;
            }

            $coordinates = $countryCoordinates[$countryCode] ?? null;
            if ($coordinates === null) {
                continue;
            }

            $region = trim((string) ($row['region'] ?? ''));
            if ($countryCode === 'ES') {
                $regionLower = function_exists('mb_strtolower') ? mb_strtolower($region) : strtolower($region);
                if ($regionLower !== '' && preg_match('/catal|barcel|girona|lleida|tarragon/i', $regionLower) === 1) {
                    $coordinates = [41.3874, 2.1686];
                }
            }

            $points[] = [
                'country_code' => $countryCode,
                'region' => $region !== '' ? $region : 'Desconegut',
                'total' => (int) ($row['total'] ?? 0),
                'lat' => $coordinates[0],
                'lng' => $coordinates[1],
            ];
        }

        return $points;
    }
}
