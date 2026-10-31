<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RentalReportFile
{
    public static function add(int $rentalId, string $path, ?string $label = null): void
    {
        Database::run(
            'INSERT INTO rental_report_files (rental_id, file_path, label) VALUES (?, ?, ?)',
            [$rentalId, $path, $label]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forRental(int $rentalId): array
    {
        return Database::all(
            'SELECT * FROM rental_report_files WHERE rental_id = ? ORDER BY id ASC',
            [$rentalId]
        );
    }

    public static function deleteForRental(int $rentalId): void
    {
        Database::run('DELETE FROM rental_report_files WHERE rental_id = ?', [$rentalId]);
    }
}
