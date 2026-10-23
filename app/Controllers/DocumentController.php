<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\HandlesGuideDocuments;
use App\Core\AdminAuth;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\GuideDocument;
use App\Models\User;
use RuntimeException;

final class DocumentController extends Controller
{
    use HandlesGuideDocuments;

    /**
     * User account documents page: lists all verification documents submitted
     * by the authenticated partner/user with status badges, file existence
     * verification, and direct view/download controls.
     */
    public function myDocuments(): void
    {
        if (!Auth::check()) {
            if ($this->isJson()) {
                $this->json(['error' => 'Unauthorized'], 401);
                return;
            }
            flash('error', 'Please sign in to view your documents.');
            redirect('/login');
        }

        $user = Auth::user();
        if ($user === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'Your session has expired.'], 401);
                return;
            }
            flash('error', 'Your session has expired. Please sign in again.');
            redirect('/login');
        }

        $userId = (int) $user['id'];
        $documents = GuideDocument::forUser($userId);

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        foreach ($documents as &$d) {
            $rel = ltrim((string) ($d['file_path'] ?? ''), '/');
            $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $exists = $rel !== '' && file_exists($abs) && is_file($abs);
            $d['file_exists'] = $exists;
            $d['file_size'] = $exists ? filesize($abs) : 0;
            $d['ext'] = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        }
        unset($d);

        if ($this->isJson()) {
            $this->json([
                'documents' => $documents,
                'status' => (string) ($user['guide_status'] ?? 'none'),
            ]);
            return;
        }

        $guideStatus = (string) ($user['guide_status'] ?? 'none');
        $isVerified = $guideStatus === 'approved';
        $verifyBadge = match ($guideStatus) {
            'approved' => ['cls' => 'pill-confirmed', 'label' => 'Verified'],
            'rejected' => ['cls' => 'pill-disputed', 'label' => 'Application rejected'],
            'pending' => ['cls' => 'pill-pending', 'label' => 'Pending review'],
            default => ['cls' => 'pill-pending', 'label' => 'Not submitted'],
        };

        $errors = errors();
        $showReupload = !empty($errors) || $this->input('action') === 'reupload' || $this->input('reupload') === '1';

        $this->view('documents/index', [
            'title' => 'My Documents',
            'user' => $user,
            'documents' => $documents,
            'verifyBadge' => $verifyBadge,
            'guideStatus' => $guideStatus,
            'isVerified' => $isVerified,
            'errors' => $errors,
            'showReupload' => $showReupload,
        ]);
    }

    /**
     * Handle document re-upload and updates from the Documents page.
     */
    public function upload(): void
    {
        $this->verifyCsrf();

        if (!Auth::check()) {
            if ($this->isJson()) {
                $this->json(['error' => 'Unauthorized'], 401);
                return;
            }
            flash('error', 'Please sign in to update your documents.');
            redirect('/login');
        }

        $user = Auth::user();
        if ($user === null) {
            flash('error', 'Session expired. Please sign in again.');
            redirect('/login');
        }

        $uid = (int) $user['id'];
        $errors = $this->guideDocErrors();

        if ($errors !== []) {
            if ($this->isJson()) {
                $this->json(['errors' => $errors], 422);
                return;
            }
            flash_keep_old($errors, $_POST, '/documents?action=reupload');
        }

        // Replace any previously submitted documents with the new set
        GuideDocument::deleteForUser($uid);
        try {
            $this->storeGuideDocs($uid);
        } catch (RuntimeException $e) {
            if ($this->isJson()) {
                $this->json(['error' => 'We could not save your documents: ' . $e->getMessage()], 500);
                return;
            }
            flash('error', 'We could not save your documents: ' . $e->getMessage());
            redirect('/documents?action=reupload');
        }

        User::setGuideStatus($uid, 'pending');
        Auth::refreshUser();

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => 'Your documents were submitted successfully and are now pending review.',
                'status' => 'pending',
            ]);
            return;
        }

        flash('success', 'Your documents were submitted successfully and are now pending review.');
        redirect('/documents');
    }

    /**
     * View document details and preview for a specific document ID.
     */
    public function show(string|int $id): void
    {
        $id = (int) $id;
        if ($id <= 0) {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid document ID.'], 404);
                return;
            }
            abort(404, 'Invalid document ID.');
        }

        $doc = GuideDocument::find($id);
        if ($doc === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'The requested document record could not be found.'], 404);
                return;
            }
            abort(404, 'The requested document record could not be found.');
        }

        if (!$this->ensureAuthorizedForDoc($doc)) {
            return;
        }

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $rel = ltrim((string) ($doc['file_path'] ?? ''), '/');
        $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $fileExists = $rel !== '' && file_exists($abs) && is_file($abs);

        // If direct stream requested via query param
        if ($this->input('inline') === '1') {
            if (!$fileExists) {
                if ($this->isJson()) {
                    $this->json(['error' => 'The document file is missing from the server.'], 404);
                    return;
                }
                abort(404, 'The document file is missing or has been removed from the server.');
            }
            $filename = !empty($doc['label']) ? (string) $doc['label'] : basename($rel);
            self::outputFile($abs, $filename, false);
        }

        if ($this->input('download') === '1') {
            if (!$fileExists) {
                if ($this->isJson()) {
                    $this->json(['error' => 'The document file is missing from the server.'], 404);
                    return;
                }
                abort(404, 'The document file is missing or has been removed from the server.');
            }
            $filename = !empty($doc['label']) ? (string) $doc['label'] : basename($rel);
            self::outputFile($abs, $filename, true);
        }

        $owner = User::find((int) $doc['user_id']);
        $guideStatus = (string) ($owner['guide_status'] ?? 'pending');
        $statusLabel = match ($guideStatus) {
            'approved' => 'Verified',
            'rejected' => 'Rejected',
            'pending' => 'Under Review',
            default => ucfirst($guideStatus),
        };
        $statusClass = match ($guideStatus) {
            'approved' => 'pill-confirmed',
            'rejected' => 'pill-disputed',
            'pending' => 'pill-pending',
            default => 'pill-pending',
        };

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $mime = $fileExists ? self::detectMimeType($abs, $ext) : '';
        $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
        $isPdf = $mime === 'application/pdf' || $ext === 'pdf';
        $fileSize = $fileExists ? (int) filesize($abs) : 0;
        $docLabel = !empty($doc['label']) ? (string) $doc['label'] : basename($rel);
        $typeLabel = GuideDocument::typeLabel((string) ($doc['doc_type'] ?? 'other'));

        if ($this->isJson() || $this->input('json') === '1') {
            $this->json([
                'id' => (int) $doc['id'],
                'user_id' => (int) $doc['user_id'],
                'label' => $docLabel,
                'doc_type' => (string) ($doc['doc_type'] ?? 'other'),
                'doc_type_label' => $typeLabel,
                'file_url' => url('/documents/file/' . (int) $doc['id']),
                'raw_url' => url('/' . $rel),
                'download_url' => url('/documents/download/' . (int) $doc['id']),
                'file_exists' => $fileExists,
                'file_size' => $fileSize,
                'file_size_formatted' => $fileExists ? round($fileSize / 1024, 1) . ' KB' : '0 KB',
                'mime_type' => $mime,
                'ext' => $ext,
                'is_image' => $isImage,
                'is_pdf' => $isPdf,
                'owner_name' => (string) ($owner['name'] ?? 'User #' . $doc['user_id']),
                'owner_email' => (string) ($owner['email'] ?? ''),
                'status' => $guideStatus,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'created_at' => (string) ($doc['created_at'] ?? ''),
                'created_at_formatted' => !empty($doc['created_at']) ? date('M j, Y g:i A', strtotime((string) $doc['created_at'])) : '',
            ]);
            return;
        }

        $this->view('documents/show', [
            'title' => $docLabel,
            'doc' => $doc,
            'owner' => $owner,
            'fileExists' => $fileExists,
            'fileSize' => $fileSize,
            'relPath' => $rel,
            'mime' => $mime,
            'isImage' => $isImage,
            'isPdf' => $isPdf,
            'docLabel' => $docLabel,
            'typeLabel' => $typeLabel,
            'status' => $guideStatus,
            'statusLabel' => $statusLabel,
            'statusClass' => $statusClass,
        ]);
    }

    /**
     * Securely serve a guide verification file by user ID and filename.
     * Handles /uploads/guide-docs/{userId}/{filename}
     */
    public function serveFile(string|int $userId, string $filename): void
    {
        $userId = (int) $userId;
        $cleanFilename = basename((string) $filename);

        if ($userId <= 0 || $cleanFilename === '') {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid document path requested.'], 404);
                return;
            }
            abort(404, 'Invalid document path requested.');
        }

        if (!$this->ensureAuthorizedForUser($userId)) {
            return;
        }

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $abs = $publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'guide-docs'
            . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $cleanFilename;

        if (!file_exists($abs) || !is_file($abs)) {
            if ($this->isJson()) {
                $this->json(['error' => "The verification document '{$cleanFilename}' was not found on the server."], 404);
                return;
            }
            abort(404, "The verification document '{$cleanFilename}' could not be found or has been removed from the server.");
        }

        // Look up original label if available
        $doc = GuideDocument::findByPath("uploads/guide-docs/{$userId}/{$cleanFilename}");
        $displayName = !empty($doc['label']) ? (string) $doc['label'] : $cleanFilename;

        self::outputFile($abs, $displayName, false);
    }

    /**
     * Securely serve document binary by ID.
     */
    public function serveFileById(string|int $id): void
    {
        $id = (int) $id;
        if ($id <= 0) {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid document ID.'], 404);
                return;
            }
            abort(404, 'Invalid document ID.');
        }

        $doc = GuideDocument::find($id);
        if ($doc === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'Document record not found.'], 404);
                return;
            }
            abort(404, 'The requested document record does not exist.');
        }

        if (!$this->ensureAuthorizedForDoc($doc)) {
            return;
        }

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $rel = ltrim((string) ($doc['file_path'] ?? ''), '/');
        $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

        if ($rel === '' || !file_exists($abs) || !is_file($abs)) {
            if ($this->isJson()) {
                $this->json(['error' => 'The document file is missing from the server.'], 404);
                return;
            }
            abort(404, 'The document file is missing or has been removed from the server.');
        }

        $displayName = !empty($doc['label']) ? (string) $doc['label'] : basename($rel);
        self::outputFile($abs, $displayName, false);
    }

    /**
     * Download document file attachment by ID.
     */
    public function download(string|int $id): void
    {
        $id = (int) $id;
        if ($id <= 0) {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid document ID.'], 404);
                return;
            }
            abort(404, 'Invalid document ID.');
        }

        $doc = GuideDocument::find($id);
        if ($doc === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'Document record not found.'], 404);
                return;
            }
            abort(404, 'The requested document record does not exist.');
        }

        if (!$this->ensureAuthorizedForDoc($doc)) {
            return;
        }

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $rel = ltrim((string) ($doc['file_path'] ?? ''), '/');
        $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

        if ($rel === '' || !file_exists($abs) || !is_file($abs)) {
            if ($this->isJson()) {
                $this->json(['error' => 'The document file is missing from the server.'], 404);
                return;
            }
            abort(404, 'The document file is missing or has been removed from the server.');
        }

        $displayName = !empty($doc['label']) ? (string) $doc['label'] : basename($rel);
        self::outputFile($abs, $displayName, true);
    }

    /**
     * Admin view of all documents submitted by a specific user.
     */
    public function adminShow(string|int $userId): void
    {
        if (!AdminAuth::check() && !Auth::isAdmin()) {
            if ($this->isJson()) {
                $this->json(['error' => 'Admin authorization required.'], 403);
                return;
            }
            flash('error', 'Please sign in to the admin portal.');
            redirect('/admin/login');
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid user ID.'], 404);
                return;
            }
            abort(404, 'Invalid user ID.');
        }

        $targetUser = User::find($userId);
        if ($targetUser === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'User not found.'], 404);
                return;
            }
            abort(404, 'User not found.');
        }

        $documents = GuideDocument::forUser($userId);
        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';

        foreach ($documents as &$d) {
            $rel = ltrim((string) ($d['file_path'] ?? ''), '/');
            $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $exists = $rel !== '' && file_exists($abs) && is_file($abs);
            $d['file_exists'] = $exists;
            $d['file_size'] = $exists ? filesize($abs) : 0;
            $d['ext'] = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        }
        unset($d);

        if ($this->isJson()) {
            $this->json([
                'target_user' => $targetUser,
                'documents' => $documents,
            ]);
            return;
        }

        $this->view('documents/admin_show', [
            'title' => 'User Documents · ' . $targetUser['name'],
            'targetUser' => $targetUser,
            'documents' => $documents,
        ], 'admin');
    }

    /**
     * Securely serve rental identification documents.
     */
    public function serveRentalId(string|int $userId, string $filename): void
    {
        $userId = (int) $userId;
        $cleanFilename = basename((string) $filename);

        if ($userId <= 0 || $cleanFilename === '') {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid document request.'], 404);
                return;
            }
            abort(404, 'Invalid document request.');
        }

        $publicDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $rel = "uploads/rentals/ids/{$userId}/{$cleanFilename}";
        $abs = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

        if (!file_exists($abs) || !is_file($abs)) {
            if ($this->isJson()) {
                $this->json(['error' => "The rental identification document '{$cleanFilename}' was not found on the server."], 404);
                return;
            }
            abort(404, "The rental identification document '{$cleanFilename}' could not be found or has been removed from the server.");
        }

        // Authorization: Admin, tourist who uploaded it, or provider who owns the booking
        $me = Auth::user();
        $isAdmin = Auth::isAdmin() || AdminAuth::check();
        $isOwner = $me !== null && (int) $me['id'] === $userId;

        if (!$isAdmin && !$isOwner) {
            $isAuthorized = false;
            if ($me !== null) {
                $req = Database::first(
                    'SELECT rr.id FROM rental_requests rr LEFT JOIN vehicles v ON rr.vehicle_id = v.id WHERE (rr.id_document = ? OR rr.id_document = ?) AND (rr.user_id = ? OR v.user_id = ?)',
                    [$rel, '/' . $rel, (int) $me['id'], (int) $me['id']]
                );
                if ($req !== null) {
                    $isAuthorized = true;
                }
            }
            if (!$isAuthorized) {
                if ($this->isJson()) {
                    $this->json(['error' => 'You do not have permission to view this identification document.'], 403);
                    return;
                }
                if (!Auth::check() && !AdminAuth::check()) {
                    flash('error', 'Please sign in to view this document.');
                    redirect('/login');
                }
                abort(403, 'You do not have permission to view this identification document.');
            }
        }

        self::outputFile($abs, $cleanFilename, false);
    }

    /**
     * Check authorization for document access.
     *
     * @param array<string, mixed> $doc
     */
    private function ensureAuthorizedForDoc(array $doc): bool
    {
        $me = Auth::user();
        $isAdmin = Auth::isAdmin() || AdminAuth::check();
        $isOwner = $me !== null && (int) $me['id'] === (int) ($doc['user_id'] ?? 0);

        if (!$isAdmin && !$isOwner) {
            if ($this->isJson()) {
                $this->json(['error' => 'You do not have permission to access this document.'], 403);
                return false;
            }
            if (!Auth::check() && !AdminAuth::check()) {
                flash('error', 'Please sign in to access this document.');
                redirect('/login');
            }
            abort(403, 'You do not have permission to access this document.');
        }
        return true;
    }

    /**
     * Check authorization for a specific user ID's documents.
     */
    private function ensureAuthorizedForUser(int $targetUserId): bool
    {
        $me = Auth::user();
        $isAdmin = Auth::isAdmin() || AdminAuth::check();
        $isOwner = $me !== null && (int) $me['id'] === $targetUserId;

        if (!$isAdmin && !$isOwner) {
            if ($this->isJson()) {
                $this->json(['error' => 'You do not have permission to access this document.'], 403);
                return false;
            }
            if (!Auth::check() && !AdminAuth::check()) {
                flash('error', 'Please sign in to access this document.');
                redirect('/login');
            }
            abort(403, 'You do not have permission to access this document.');
        }
        return true;
    }

    /**
     * Accurately detect MIME type using magic bytes inspection, finfo, and extension fallback.
     */
    public static function detectMimeType(string $absPath, string $ext = ''): string
    {
        if (!file_exists($absPath) || !is_readable($absPath)) {
            return 'application/octet-stream';
        }

        // 1. Inspect first bytes (magic numbers)
        $fh = @fopen($absPath, 'rb');
        $head = '';
        if ($fh !== false) {
            $head = (string) fread($fh, 1024);
            fclose($fh);
        }

        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($head, '%PDF-')) {
            return 'application/pdf';
        }
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            return 'image/gif';
        }
        if (strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (stripos($head, '<svg') !== false || (stripos($head, '<?xml') !== false && stripos($head, '<svg') !== false)) {
            return 'image/svg+xml';
        }

        // 2. finfo check
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $absPath);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream' && $detected !== 'text/plain') {
                    return $detected;
                }
            }
        }

        // 3. Fallback based on extension
        $cleanExt = strtolower($ext !== '' ? $ext : pathinfo($absPath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
        ];

        return $mimeTypes[$cleanExt] ?? 'application/octet-stream';
    }

    /**
     * Stream a file to the browser with appropriate headers.
     */
    private static function outputFile(string $absPath, string $displayName, bool $asDownload): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $mime = self::detectMimeType($absPath, $ext);

        http_response_code(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absPath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');

        $disposition = $asDownload ? 'attachment' : 'inline';
        $safeName = str_replace(['"', "\r", "\n"], '', $displayName);
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');

        readfile($absPath);
        exit;
    }
}
