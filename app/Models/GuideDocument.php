<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Verification documents submitted by a guide (ID, license, accreditation,
 * certificates, employment proof, association membership, etc.).
 */
final class GuideDocument
{
    /** Human labels for the document types we accept. */
    public const TYPES = [
        'valid_id' => 'Valid government ID',
        'guide_license' => 'Tour guide license',
        'tourism_accreditation' => 'DOT / tourism accreditation',
        'certificate' => 'Training certificate',
        'employment' => 'Employment verification',
        'association' => 'Association membership',
        'other' => 'Other supporting document',
    ];

    public static function create(int $userId, string $type, ?string $label, string $path): int
    {
        return Database::insert(
            'INSERT INTO guide_documents (user_id, doc_type, label, file_path) VALUES (?, ?, ?, ?)',
            [$userId, $type, $label, $path]
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM guide_documents WHERE id = ?', [$id]);
    }

    /**
     * @return ?array<string, mixed>
     */
    public static function findByPath(string $path): ?array
    {
        $clean = ltrim($path, '/');
        return Database::first('SELECT * FROM guide_documents WHERE file_path = ? OR file_path = ?', [$clean, '/' . $clean]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        return Database::all('SELECT * FROM guide_documents WHERE user_id = ? ORDER BY id', [$userId]);
    }

    public static function deleteForUser(int $userId): void
    {
        Database::run('DELETE FROM guide_documents WHERE user_id = ?', [$userId]);
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
