<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Vehicle
{
    public const TYPES = [
        'Scooter',
        'Motorcycle',
        'Sedan',
        'SUV',
        'Van',
        'E-Bike',
        'Bicycle',
        'Pickup Truck',
    ];

    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'unavailable' => 'Unavailable',
    ];

    public const STATUS_PILLS = [
        'draft' => 'pill-secondary',
        'pending' => 'pill-pending',
        'approved' => 'pill-approved',
        'rejected' => 'pill-cancelled',
        'unavailable' => 'pill-refunded',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    public static function statusPillClass(string $status): string
    {
        return self::STATUS_PILLS[$status] ?? 'pill-secondary';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT v.*, u.name AS provider_name, u.email AS provider_email, u.phone AS provider_phone,
                    r.name AS reviewer_name
             FROM vehicles v
             JOIN users u ON u.id = v.user_id
             LEFT JOIN users r ON r.id = v.reviewed_by
             WHERE v.id = ?',
            [$id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return Database::first(
            'SELECT v.*, u.name AS provider_name, u.email AS provider_email, u.phone AS provider_phone,
                    r.name AS reviewer_name
             FROM vehicles v
             JOIN users u ON u.id = v.user_id
             LEFT JOIN users r ON r.id = v.reviewed_by
             WHERE v.slug = ?',
            [$slug]
        );
    }

    /**
     * Vehicle listings owned by a specific rental provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forOwner(int $userId): array
    {
        return Database::all(
            'SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    /**
     * Counts by status for a specific rental provider.
     *
     * @return array<string, int>
     */
    public static function countForOwner(int $userId): array
    {
        $rows = Database::all(
            'SELECT status, COUNT(*) as cnt FROM vehicles WHERE user_id = ? GROUP BY status',
            [$userId]
        );
        $counts = [
            'total' => 0,
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'unavailable' => 0,
            'draft' => 0,
        ];
        foreach ($rows as $row) {
            $st = (string) $row['status'];
            $c = (int) $row['cnt'];
            $counts['total'] += $c;
            if (isset($counts[$st])) {
                $counts[$st] = $c;
            }
        }
        return $counts;
    }

    /**
     * All vehicle listings for administrators, ordered by pending first then newest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allForAdmin(?string $status = null): array
    {
        $params = [];
        $where = '';
        if ($status !== null && $status !== '' && $status !== 'all') {
            $where = 'WHERE v.status = ?';
            $params[] = $status;
        }

        return Database::all(
            "SELECT v.*, u.name AS provider_name, u.email AS provider_email,
                    r.name AS reviewer_name
             FROM vehicles v
             JOIN users u ON u.id = v.user_id
             LEFT JOIN users r ON r.id = v.reviewed_by
             {$where}
             ORDER BY CASE WHEN v.status = 'pending' THEN 0 ELSE 1 END, v.created_at DESC",
            $params
        );
    }

    public static function pendingCount(): int
    {
        $res = Database::first('SELECT COUNT(*) AS cnt FROM vehicles WHERE status = "pending"');
        return (int) ($res['cnt'] ?? 0);
    }

    /**
     * Approved and available vehicle listings visible to tourists.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function approvedAndAvailable(array $filters = []): array
    {
        $where = ['v.status = "approved"', 'v.is_available = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $like = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(v.name LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR v.description LIKE ? OR v.location LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $where[] = 'v.type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['location'])) {
            $where[] = 'v.location LIKE ?';
            $params[] = '%' . trim((string) $filters['location']) . '%';
        }

        $orderBy = 'v.created_at DESC';
        if (!empty($filters['sort'])) {
            $orderBy = match ($filters['sort']) {
                'price_asc' => 'v.price_per_day ASC',
                'price_desc' => 'v.price_per_day DESC',
                'year_desc' => 'v.year DESC',
                default => 'v.created_at DESC',
            };
        }

        $sql = 'SELECT v.*, u.name AS provider_name, u.email AS provider_email
                FROM vehicles v
                JOIN users u ON u.id = v.user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy;

        return Database::all($sql, $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = trim(($data['year'] ?? '') . ' ' . ($data['make'] ?? '') . ' ' . ($data['model'] ?? ''));
        }
        $slug = self::uniqueSlug($name);
        $status = (string) ($data['status'] ?? 'pending');
        $isAvailable = $status === 'approved' ? 1 : 0;
        $submittedAt = in_array($status, ['pending', 'approved'], true) ? date('Y-m-d H:i:s') : null;

        return Database::insert(
            'INSERT INTO vehicles (
                user_id, make, model, year, type, name, slug, description, specs,
                location, address, price_per_day, image, status, is_available,
                submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['user_id'],
                (string) ($data['make'] ?? ''),
                (string) ($data['model'] ?? ''),
                (int) ($data['year'] ?? date('Y')),
                (string) ($data['type'] ?? 'Scooter'),
                $name,
                $slug,
                (string) ($data['description'] ?? ''),
                isset($data['specs']) ? (is_array($data['specs']) ? json_encode($data['specs']) : (string) $data['specs']) : null,
                (string) ($data['location'] ?? ''),
                isset($data['address']) ? (string) $data['address'] : null,
                (float) ($data['price_per_day'] ?? 0.0),
                isset($data['image']) ? (string) $data['image'] : null,
                $status,
                $isAvailable,
                $submittedAt,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach ($data as $k => $v) {
            if ($k === 'specs' && is_array($v)) {
                $v = json_encode($v);
            }
            $fields[] = "`{$k}` = ?";
            $params[] = $v;
        }

        if ($fields === []) {
            return;
        }

        $params[] = $id;
        Database::run('UPDATE vehicles SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public static function approve(int $id, int $reviewerId): void
    {
        Database::run(
            "UPDATE vehicles
             SET status = 'approved', is_available = 1,
                 reviewed_at = NOW(), reviewed_by = ?, rejection_reason = NULL
             WHERE id = ?",
            [$reviewerId, $id]
        );
    }

    public static function reject(int $id, int $reviewerId, ?string $reason = null): void
    {
        Database::run(
            "UPDATE vehicles
             SET status = 'rejected', is_available = 0,
                 reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ?
             WHERE id = ?",
            [$reviewerId, $reason, $id]
        );
    }

    public static function resubmit(int $id): void
    {
        Database::run(
            "UPDATE vehicles
             SET status = 'pending', is_available = 0, submitted_at = NOW()
             WHERE id = ?",
            [$id]
        );
    }

    public static function toggleAvailability(int $id, bool $available): void
    {
        Database::run(
            'UPDATE vehicles
             SET is_available = ?, status = ?
             WHERE id = ?',
            [$available ? 1 : 0, $available ? 'approved' : 'unavailable', $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM vehicles WHERE id = ?', [$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function gallery(int $vehicleId): array
    {
        return Database::all(
            'SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY sort_order, id',
            [$vehicleId]
        );
    }

    public static function addImage(int $vehicleId, string $imagePath, int $sortOrder = 0): int
    {
        return Database::insert(
            'INSERT INTO vehicle_images (vehicle_id, image_path, sort_order) VALUES (?, ?, ?)',
            [$vehicleId, $imagePath, $sortOrder]
        );
    }

    public static function deleteImage(int $imageId, int $vehicleId): void
    {
        Database::run('DELETE FROM vehicle_images WHERE id = ? AND vehicle_id = ?', [$imageId, $vehicleId]);
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));
        if ($base === '') {
            $base = 'vehicle-' . bin2hex(random_bytes(3));
        }

        $slug = $base;
        $counter = 1;
        while (true) {
            $query = 'SELECT id FROM vehicles WHERE slug = ?';
            $params = [$slug];
            if ($ignoreId !== null) {
                $query .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            $existing = Database::first($query, $params);
            if ($existing === null) {
                return $slug;
            }
            $counter++;
            $slug = "{$base}-{$counter}";
        }
    }

    /**
     * Format specs array or string into clean readable array of strings.
     *
     * @param mixed $specs
     * @return array<int, string>
     */
    public static function parseSpecs(mixed $specs): array
    {
        if (is_array($specs)) {
            return array_values(array_filter(array_map('trim', $specs)));
        }
        if (is_string($specs) && $specs !== '') {
            $decoded = json_decode($specs, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }
            return array_values(array_filter(array_map('trim', explode(',', $specs))));
        }
        return [];
    }
}
