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
        return is_array($file)
            && isset($file['error'])
            && !is_array($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Validate and store a single uploaded file. Returns the path relative to
     * the public/ directory (e.g. "uploads/guide-docs/12/ab12.pdf").
     *
     * @param array<string, mixed> $file a single $_FILES[...] entry
     * @param array<int, string>   $allowedExt
     * @throws RuntimeException on any validation or move failure
     */
    public static function store(
        array $file,
        string $subdir,
        array $allowedExt = self::DOC_EXT,
        int $maxBytes = self::MAX_BYTES
    ): string {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload.');
        }

        switch ((int) $file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file was selected.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('File is too large (server limit).');
            default:
                throw new RuntimeException('Upload failed, please try again.');
        }

        if ((int) $file['size'] > $maxBytes) {
            throw new RuntimeException('File must be ' . (int) round($maxBytes / 1048576) . 'MB or smaller.');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException('Allowed file types: ' . implode(', ', $allowedExt) . '.');
        }

        // Verify the real content type, not just the extension.
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

        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save the upload.');
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
