<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Small, safe file-upload helper for verification documents.
 *
 * Files are stored under public/uploads/<subdir> with a randomized name.
 * Every method validates the upload and throws a RuntimeException carrying a
 * human-friendly message, so controllers can catch it and surface the error.
 */
final class Upload
{
    /** Allowed extensions for verification documents. */
    public const DOC_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    /** Matching MIME types. */
    private const DOC_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /** Max size per file (5 MB). */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * True when the form actually submitted a file for this field.
     *
     * @param array<string, mixed>|null $file a single $_FILES[...] entry
     */
    public static function present(?array $file): bool
    {
        return $file !== null
            && isset($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Validate and save an upload. Returns the relative path suitable for
     * storing in the database (e.g. "uploads/guide-docs/42/a1b2c3d4.pdf").
     *
     * @param array<string, mixed> $file
     * @param array<int, string>|null $allowedExt override self::DOC_EXT when needed
     */
    public static function store(array $file, string $subdir, ?array $allowedExt = null): string
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            throw match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new RuntimeException('The file is larger than the server allows.'),
                UPLOAD_ERR_PARTIAL => new RuntimeException('The file only partially uploaded. Please try again.'),
                UPLOAD_ERR_NO_FILE => new RuntimeException('Please choose a file to upload.'),
                default => new RuntimeException('Upload failed with error code ' . $code . '.'),
            };
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Files must be 5 MB or smaller.');
        }

        $orig = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = $allowedExt ?? self::DOC_EXT;

        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException(
                'Invalid file type (.' . $ext . '). Allowed: ' . implode(', ', $allowed) . '.'
            );
        }

        // Sniff actual content bytes, not just client-provided MIME header.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime !== '' && !in_array($mime, self::DOC_MIME, true)) {
            throw new RuntimeException('That file does not look like a valid document or image.');
        }

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $relDir = 'uploads/' . trim($subdir, '/');
        $absDir = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new RuntimeException('Could not create the upload folder.');
        }

        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $absDir . DIRECTORY_SEPARATOR . $filename;

        if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {
            if (PHP_SAPI === 'cli' && @copy((string) $file['tmp_name'], $dest)) {
                @unlink((string) $file['tmp_name']);
            } else {
                throw new RuntimeException('Could not save the upload.');
            }
        }

        return $relDir . '/' . $filename;
    }

    /**
     * Store an image upload; returns null when no file or validation fails.
     *
     * @param array<string, mixed> $file
     */
    public static function image(array $file, string $subdir): ?string
    {
        if (!self::present($file)) {
            return null;
        }
        try {
            return self::store($file, $subdir, ['jpg', 'jpeg', 'png', 'webp']);
        } catch (RuntimeException) {
            return null;
        }
    }
}
