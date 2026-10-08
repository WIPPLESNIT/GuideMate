<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Core\Upload;
use App\Models\GuideDocument;

/**
 * Shared logic for validating and storing a guide's verification documents.
 * Used during registration and when a guide re-submits after a rejection.
 */
trait HandlesGuideDocuments
{
    /**
     * Lightweight validation (presence, size, type) before persisting.
     *
     * @return array<string, string>
     */
    protected function guideDocErrors(): array
    {
        $errors = [];

        if (!Upload::present($_FILES['valid_id'] ?? null)) {
            $errors['valid_id'] = 'Valid government ID is required.';
        } elseif (($msg = $this->fileError($_FILES['valid_id'])) !== null) {
            $errors['valid_id'] = $msg;
        }

        $credType = trim((string) $this->input('credential_type', ''));
        if ($credType === '') {
            $errors['credential_type'] = 'Credential type is required.';
        } elseif (!array_key_exists($credType, GuideDocument::TYPES) || $credType === 'valid_id' || $credType === 'other') {
            $errors['credential_type'] = 'Please select a valid credential type.';
        }

        if (!Upload::present($_FILES['credential'] ?? null)) {
            $errors['credential'] = 'Credential document is required.';
        } elseif (($msg = $this->fileError($_FILES['credential'])) !== null) {
            $errors['credential'] = $msg;
        }

        foreach ($this->extraFiles() as $file) {
            if (($msg = $this->fileError($file)) !== null) {
                $errors['extra_docs'] = 'An additional document is invalid: ' . $msg;
                break;
            }
        }

        return $errors;
    }

    /**
     * Persist the uploaded documents for a guide (user id).
     */
    protected function storeGuideDocs(int $userId): void
    {
        $subdir = 'guide-docs/' . $userId;

        $path = Upload::store($_FILES['valid_id'], $subdir);
        GuideDocument::create($userId, 'valid_id', (string) $_FILES['valid_id']['name'], $path);

        $credType = (string) $this->input('credential_type', 'guide_license');
        if (!array_key_exists($credType, GuideDocument::TYPES) || $credType === 'valid_id') {
            $credType = 'other';
        }
        $path = Upload::store($_FILES['credential'], $subdir);
        GuideDocument::create($userId, $credType, (string) $_FILES['credential']['name'], $path);

        foreach ($this->extraFiles() as $file) {
            $path = Upload::store($file, $subdir);
            GuideDocument::create($userId, 'other', (string) $file['name'], $path);
        }
    }

    /**
     * Validate a single file entry without moving it.
     *
     * @param array<string, mixed> $file
     */
    protected function fileError(array $file): ?string
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 'File is too large (server upload limit).';
        }
        if ($code !== UPLOAD_ERR_OK) {
            return 'Upload failed, please try again.';
        }
        if ((int) ($file['size'] ?? 0) > Upload::MAX_BYTES) {
            return 'File must be 5MB or smaller.';
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, Upload::DOC_EXT, true)) {
            return 'Allowed types: ' . implode(', ', Upload::DOC_EXT) . '.';
        }
        return null;
    }

    /**
     * Normalize the optional extra_docs[] multi-file input.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extraFiles(): array
    {
        $out = [];
        $f = $_FILES['extra_docs'] ?? null;
        if (!is_array($f) || !isset($f['name']) || !is_array($f['name'])) {
            return $out;
        }
        foreach ($f['name'] as $i => $name) {
            if ((int) $f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $f['name'][$i],
                'type' => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
            ];
        }
        return $out;
    }
}
