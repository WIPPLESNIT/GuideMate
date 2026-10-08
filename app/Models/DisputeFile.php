<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class DisputeFile
{
    public static function add(int $disputeId, string $path, ?string $label = null): void
    {
        Database::run(
            'INSERT INTO dispute_files (dispute_id, file_path, label) VALUES (?, ?, ?)',
            [$disputeId, $path, $label]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forDispute(int $disputeId): array
    {
        return Database::all('SELECT * FROM dispute_files WHERE dispute_id = ?', [$disputeId]);
    }
}
