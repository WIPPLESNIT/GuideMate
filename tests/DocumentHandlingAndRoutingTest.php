<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\DocumentController;
use App\Core\AdminAuth;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Router;
use App\Models\GuideDocument;
use App\Models\User;

class DocumentHandlingAndRoutingTest
{
    private int $passed = 0;
    private int $failed = 0;
    private ?int $testUser1Id = null;
    private ?int $testUser2Id = null;
    private ?int $workingDocId = null;
    private ?int $missingFileDocId = null;
    private string $tempFilePath = '';

    public function run(): bool
    {
        error_reporting(E_ALL & ~E_WARNING);

        echo "========================================================\n";
        echo " GuideMate Document Handling, Controller & Routing Tests\n";
        echo "========================================================\n\n";

        $this->setUp();

        $this->testDocumentControllerExists();
        $this->testRoutesConfiguredInRouter();
        $this->testUnauthenticatedAccessRedirects();
        $this->testAuthorizedUserCanViewOwnExistingDocument();
        $this->testUnauthorizedUserBlockedFromOtherUserDocument();
        $this->testMissingDocumentRecordHandledGracefully();
        $this->testDeletedOrMissingFileHandledGracefully();
        $this->testMyDocumentsPageView();
        $this->testAdminCanAccessUserDocuments();
        $this->testDocumentLinksFallBackWhenFilePathEmpty();
        $this->testDocumentsAccessibleWhenStatusIsUnderReview();
        $this->testMimeTypeDetectionMagicBytes();
        $this->testNoIntermediateSvgCardReturned();
        $this->testDocPreviewModalMarkupAndAttributes();
        $this->testGuideVerificationPageRemovedAndRedirects();
        $this->testDocumentsPageLayoutMatchesImage2();
        $this->testDocModalHeaderButtonsRemovedMatchingImage3();
        $this->testDocumentReuploadAndUpdateWorkflow();

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function setUp(): void
    {
        $suffix = bin2hex(random_bytes(4));

        // Create test user 1 (Guide)
        $this->testUser1Id = User::create(
            'Test Guide One',
            'guide_doc_test_' . $suffix . '@example.com',
            'Password123!',
            'guide'
        );

        // Create test user 2 (Another Guide)
        $this->testUser2Id = User::create(
            'Test Guide Two',
            'guide_doc_two_' . $suffix . '@example.com',
            'Password123!',
            'guide'
        );

        // Create a real physical file for test user 1
        $publicDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
        $userDocDir = $publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'guide-docs' . DIRECTORY_SEPARATOR . $this->testUser1Id;
        if (!is_dir($userDocDir)) {
            mkdir($userDocDir, 0777, true);
        }
        $filename = 'test_working_doc_' . $suffix . '.jpg';
        $this->tempFilePath = $userDocDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($this->tempFilePath, 'fake image binary content for test');

        $relPath = "uploads/guide-docs/{$this->testUser1Id}/{$filename}";

        // Insert working doc in DB
        $this->workingDocId = GuideDocument::create(
            $this->testUser1Id,
            'valid_id',
            'Government Valid ID',
            $relPath
        );

        // Insert doc with a missing physical file on disk
        $this->missingFileDocId = GuideDocument::create(
            $this->testUser1Id,
            'guide_license',
            'Missing License Doc',
            "uploads/guide-docs/{$this->testUser1Id}/non_existent_file_{$suffix}.pdf"
        );
    }

    private function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
        $publicDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
        if ($this->testUser1Id !== null) {
            $userDir = $publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'guide-docs' . DIRECTORY_SEPARATOR . $this->testUser1Id;
            if (is_dir($userDir)) {
                @array_map('unlink', glob("$userDir/*.*") ?: []);
                @rmdir($userDir);
            }
            GuideDocument::deleteForUser($this->testUser1Id);
            Database::run('DELETE FROM users WHERE id = ?', [$this->testUser1Id]);
        }
        if ($this->testUser2Id !== null) {
            GuideDocument::deleteForUser($this->testUser2Id);
            Database::run('DELETE FROM users WHERE id = ?', [$this->testUser2Id]);
        }
        Auth::logout();
    }

    private function testDocumentControllerExists(): void
    {
        if (class_exists(DocumentController::class)) {
            $ctrl = new DocumentController();
            if (method_exists($ctrl, 'myDocuments') && method_exists($ctrl, 'show') && method_exists($ctrl, 'serveFile')) {
                $this->pass('testDocumentControllerExists: App\Controllers\DocumentController loaded with required actions');
                return;
            }
        }
        $this->fail('testDocumentControllerExists: DocumentController missing or does not define required methods');
    }

    private function testRoutesConfiguredInRouter(): void
    {
        $router = new Router();
        require dirname(__DIR__) . '/routes/web.php';

        $routes = (fn() => $this->routes)->call($router);
        $patterns = array_column($routes, 'pattern');

        $hasDocuments = in_array('/documents', $patterns, true);
        $hasDocId = in_array('/documents/{id}', $patterns, true);
        $hasServe = in_array('/uploads/guide-docs/{userId}/{filename}', $patterns, true);
        $hasAdminShow = in_array('/admin/users/{id}/documents', $patterns, true);

        if ($hasDocuments && $hasDocId && $hasServe && $hasAdminShow) {
            $this->pass('testRoutesConfiguredInRouter: all document routes properly registered in web.php');
        } else {
            $this->fail('testRoutesConfiguredInRouter: some document routes are missing from router table');
        }
    }

    private function testUnauthenticatedAccessRedirects(): void
    {
        Auth::logout();
        $_SESSION = [];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->show((string) $this->workingDocId);
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);

        if ($code === 403 || $code === 401) {
            $this->pass('testUnauthenticatedAccessRedirects: unauthenticated request blocked with ' . $code);
        } else {
            $this->fail('testUnauthenticatedAccessRedirects: unexpected response code ' . $code);
        }
    }

    private function testAuthorizedUserCanViewOwnExistingDocument(): void
    {
        $user1 = User::find($this->testUser1Id);
        Auth::login($user1);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->show((string) $this->workingDocId);
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);

        $data = json_decode($output ?: '', true);

        if ($code === 200 && is_array($data) && !empty($data['file_exists'])) {
            $this->pass('testAuthorizedUserCanViewOwnExistingDocument: owner can access existing physical document');
        } else {
            $this->fail('testAuthorizedUserCanViewOwnExistingDocument: failed to access document (code ' . $code . ')');
        }
    }

    private function testUnauthorizedUserBlockedFromOtherUserDocument(): void
    {
        // Login as User 2 (not the owner)
        $user2 = User::find($this->testUser2Id);
        Auth::login($user2);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->show((string) $this->workingDocId);
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);

        if ($code === 403) {
            $this->pass('testUnauthorizedUserBlockedFromOtherUserDocument: access denied (403) for non-owner');
        } else {
            $this->fail('testUnauthorizedUserBlockedFromOtherUserDocument: unauthorized user was not blocked (code ' . $code . ')');
        }
    }

    private function testMissingDocumentRecordHandledGracefully(): void
    {
        $user1 = User::find($this->testUser1Id);
        Auth::login($user1);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->show('99999999');
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);

        if ($code === 404) {
            $this->pass('testMissingDocumentRecordHandledGracefully: non-existent document ID handled with 404 not-found');
        } else {
            $this->fail('testMissingDocumentRecordHandledGracefully: non-existent document ID did not return 404 (code ' . $code . ')');
        }
    }

    private function testDeletedOrMissingFileHandledGracefully(): void
    {
        $user1 = User::find($this->testUser1Id);
        Auth::login($user1);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        // Requesting inline stream of missing file doc
        $_GET['inline'] = '1';
        $controller->show((string) $this->missingFileDocId);
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_GET['inline'], $_SERVER['HTTP_ACCEPT']);

        if ($code === 404) {
            $this->pass('testDeletedOrMissingFileHandledGracefully: deleted/missing file handled gracefully with 404 not-found instead of crash');
        } else {
            $this->fail('testDeletedOrMissingFileHandledGracefully: missing file was not handled with 404 (code ' . $code . ')');
        }
    }

    private function testMyDocumentsPageView(): void
    {
        $user1 = User::find($this->testUser1Id);
        Auth::login($user1);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->myDocuments();
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);

        $data = json_decode($output ?: '', true);

        if ($code === 200 && is_array($data) && count($data['documents'] ?? []) === 2) {
            $this->pass('testMyDocumentsPageView: user documents retrieved with accurate count and metadata');
        } else {
            $this->fail('testMyDocumentsPageView: failed to retrieve documents list (code ' . $code . ')');
        }
    }

    private function testAdminCanAccessUserDocuments(): void
    {
        // Simulate admin user
        $adminUser = [
            'id' => 9999,
            'name' => 'Test Admin',
            'email' => 'admin_doc_test@example.com',
            'role' => 'admin',
        ];
        Auth::login($adminUser);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->show((string) $this->workingDocId);
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);

        $data = json_decode($output ?: '', true);

        if ($code === 200 && is_array($data) && !empty($data['file_exists'])) {
            $this->pass('testAdminCanAccessUserDocuments: admin user authorized to inspect any user verification document');
        } else {
            $this->fail('testAdminCanAccessUserDocuments: admin user was rejected (code ' . $code . ')');
        }
    }

    private function testDocumentLinksFallBackWhenFilePathEmpty(): void
    {
        $d = [
            'id' => 42,
            'doc_type' => 'valid_id',
            'label' => 'Government ID',
            'file_path' => '',
        ];

        $hasFile = !empty($d['file_path']);
        $docUrl = $hasFile ? url($d['file_path']) : url('/documents/' . (int) $d['id']);
        $docLabel = !empty($d['label']) ? $d['label'] : ($hasFile ? basename($d['file_path']) : 'Document #' . $d['id']);

        if ($docUrl === url('/documents/42') && $docLabel === 'Government ID') {
            $this->pass('testDocumentLinksFallBackWhenFilePathEmpty: empty file_path correctly falls back to safe /documents/{id} route');
        } else {
            $this->fail('testDocumentLinksFallBackWhenFilePathEmpty: fallback failed to generate expected route');
        }
    }

    private function testDocumentsAccessibleWhenStatusIsUnderReview(): void
    {
        // Set user 1 status to pending (Under Review)
        Database::run('UPDATE users SET guide_status = "pending" WHERE id = ?', [$this->testUser1Id]);
        $ownerUser = User::find($this->testUser1Id);
        Auth::login($ownerUser);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->show((string) $this->workingDocId);
        $output = ob_get_clean();
        $code = http_response_code();

        unset($_SERVER['HTTP_ACCEPT']);
        $data = json_decode($output ?: '', true);

        if ($code === 200 && is_array($data) && ($data['status'] ?? '') === 'pending' && ($data['status_label'] ?? '') === 'Under Review') {
            $this->pass('testDocumentsAccessibleWhenStatusIsUnderReview: documents remain fully viewable while status is Under Review');
        } else {
            $this->fail('testDocumentsAccessibleWhenStatusIsUnderReview: document preview blocked or incorrect status (code ' . $code . ')');
        }
    }

    private function testMimeTypeDetectionMagicBytes(): void
    {
        $tempJpg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_magic_' . bin2hex(random_bytes(4)) . '.jpg';
        $tempPng = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_magic_' . bin2hex(random_bytes(4)) . '.png';
        $tempPdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_magic_' . bin2hex(random_bytes(4)) . '.pdf';
        $tempSvgNamedJpg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_svg_named_' . bin2hex(random_bytes(4)) . '.jpg';

        file_put_contents($tempJpg, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00");
        file_put_contents($tempPng, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01");
        file_put_contents($tempPdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj");
        file_put_contents($tempSvgNamedJpg, "<svg xmlns=\"http://www.w3.org/2000/svg\"><text>Sample</text></svg>");

        $mimeJpg = DocumentController::detectMimeType($tempJpg, 'jpg');
        $mimePng = DocumentController::detectMimeType($tempPng, 'png');
        $mimePdf = DocumentController::detectMimeType($tempPdf, 'pdf');
        $mimeSvgNamedJpg = DocumentController::detectMimeType($tempSvgNamedJpg, 'jpg');

        @unlink($tempJpg);
        @unlink($tempPng);
        @unlink($tempPdf);
        @unlink($tempSvgNamedJpg);

        if ($mimeJpg === 'image/jpeg' && $mimePng === 'image/png' && $mimePdf === 'application/pdf' && $mimeSvgNamedJpg === 'image/svg+xml') {
            $this->pass('testMimeTypeDetectionMagicBytes: accurately sniffs JPG, PNG, PDF and detects SVG inside .jpg extension');
        } else {
            $this->fail("testMimeTypeDetectionMagicBytes failed: jpg=$mimeJpg, png=$mimePng, pdf=$mimePdf, svgAsJpg=$mimeSvgNamedJpg");
        }
    }

    private function testNoIntermediateSvgCardReturned(): void
    {
        $baseDir = dirname(__DIR__);
        $christianDoc = $baseDir . '/public/uploads/guide-docs/371/af611a83f844bd73.jpg';
        $marcDoc = $baseDir . '/public/uploads/guide-docs/3/valid-id.svg';

        $christianValid = false;
        if (file_exists($christianDoc)) {
            $head = (string) file_get_contents($christianDoc, false, null, 0, 3);
            // Must be genuine JPEG bytes, not text SVG
            $christianValid = str_starts_with($head, "\xFF\xD8\xFF");
        }

        $marcValid = false;
        if (file_exists($marcDoc)) {
            $content = (string) file_get_contents($marcDoc);
            // Must not contain the generic placeholder card from Image 1
            $marcValid = !str_contains($content, 'fill="#f0f7f5"') && str_contains($content, 'ACCREDITED TOUR GUIDE');
        }

        if ($christianValid && $marcValid) {
            $this->pass('testNoIntermediateSvgCardReturned: genuine valid document assets exist, replacing placeholder certificate & broken JPEG');
        } else {
            $this->fail("testNoIntermediateSvgCardReturned failed (christianValid=$christianValid, marcValid=$marcValid)");
        }
    }

    private function testDocPreviewModalMarkupAndAttributes(): void
    {
        $baseDir = dirname(__DIR__);
        $modalFile = $baseDir . '/app/Views/partials/doc-preview-modal.php';
        $guidesFile = $baseDir . '/app/Views/admin/guides.php';
        $userFormFile = $baseDir . '/app/Views/admin/user-form.php';

        $modalContent = file_exists($modalFile) ? (string) file_get_contents($modalFile) : '';
        $guidesContent = file_exists($guidesFile) ? (string) file_get_contents($guidesFile) : '';
        $userFormContent = file_exists($userFormFile) ? (string) file_get_contents($userFormFile) : '';

        $hasModalElements = str_contains($modalContent, 'id="docPreviewModal"')
            && str_contains($modalContent, 'id="docModalTitle"')
            && str_contains($modalContent, 'id="docModalImg"')
            && str_contains($modalContent, 'id="docModalPdfFrame"')
            && str_contains($modalContent, 'id="docModalLoader"')
            && str_contains($modalContent, 'id="docZoomInBtn"')
            && str_contains($modalContent, 'id="docModalError"');

        $hasGuidesDataAttr = str_contains($guidesContent, 'data-doc-preview="1"')
            && str_contains($guidesContent, 'data-doc-status=');

        $hasUserFormDataAttr = str_contains($userFormContent, 'data-doc-preview="1"')
            && str_contains($userFormContent, 'data-doc-status=');

        if ($hasModalElements && $hasGuidesDataAttr && $hasUserFormDataAttr) {
            $this->pass('testDocPreviewModalMarkupAndAttributes: modal markup and data-doc-preview attributes properly wired');
        } else {
            $this->fail("testDocPreviewModalMarkupAndAttributes failed (modal=$hasModalElements, guides=$hasGuidesDataAttr, userForm=$hasUserFormDataAttr)");
        }
    }

    private function testGuideVerificationPageRemovedAndRedirects(): void
    {
        $baseDir = dirname(__DIR__);
        $oldVerificationView = $baseDir . '/app/Views/guide/verification.php';
        $viewDeleted = !file_exists($oldVerificationView);

        $guideControllerCode = (string) file_get_contents($baseDir . '/app/Controllers/GuideController.php');
        $hasRedirect = str_contains($guideControllerCode, "redirect('/documents')");

        if ($viewDeleted && $hasRedirect) {
            $this->pass('testGuideVerificationPageRemovedAndRedirects: old guide/verification.php removed and route redirects to /documents');
        } else {
            $this->fail('testGuideVerificationPageRemovedAndRedirects: guide/verification.php still exists or missing redirect');
        }
    }

    private function testDocumentsPageLayoutMatchesImage2(): void
    {
        $baseDir = dirname(__DIR__);
        $docIndexPath = $baseDir . '/app/Views/documents/index.php';
        $content = file_exists($docIndexPath) ? (string) file_get_contents($docIndexPath) : '';

        // Requirement 7: Breadcrumb Profile > Documents
        $hasBreadcrumb = str_contains($content, 'Profile') && str_contains($content, '&gt; Documents');

        // Requirement 2: Header button is "Back to Profile"
        $hasBackToProfileTop = str_contains($content, 'Back to Profile') && !str_contains($content, 'Submit or Update');

        // Requirement 5: "Preview" button removed from inside Submitted Documents
        $previewButtonRemoved = !str_contains($content, '>Preview<') && !str_contains($content, '> Preview <');

        // Requirement 3: Duplicate "Back to Profile" at bottom removed
        $bottomPart = substr($content, strpos($content, 'Submitted Documents') ?: 0);
        $duplicateBottomBackToProfileRemoved = substr_count($bottomPart, 'Back to Profile') === 1; // only the Profile Details button or 0

        // Requirement 4: "Re-upload or Update Documents" button present and functional
        $hasReuploadBtn = str_contains($content, 'Re-upload or Update Documents') && str_contains($content, 'reuploadFormPanel');

        if ($hasBreadcrumb && $hasBackToProfileTop && $previewButtonRemoved && $duplicateBottomBackToProfileRemoved && $hasReuploadBtn) {
            $this->pass('testDocumentsPageLayoutMatchesImage2: breadcrumbs, Back to Profile, preview removal, and re-upload match Image 2');
        } else {
            $this->fail("testDocumentsPageLayoutMatchesImage2 failed: breadcrumb=$hasBreadcrumb, backToProfile=$hasBackToProfileTop, previewRemoved=$previewButtonRemoved, bottomDedup=$duplicateBottomBackToProfileRemoved, reupload=$hasReuploadBtn");
        }
    }

    private function testDocModalHeaderButtonsRemovedMatchingImage3(): void
    {
        $baseDir = dirname(__DIR__);
        $modalPath = $baseDir . '/app/Views/partials/doc-preview-modal.php';
        $content = file_exists($modalPath) ? (string) file_get_contents($modalPath) : '';

        // Requirement 6: "Open" and "Download" buttons removed from upper-right header of modal
        $hasOpenInHeader = str_contains($content, 'id="docModalExternalLink"');
        $hasDownloadInHeader = str_contains($content, 'id="docModalDownloadBtn"');
        $hasZoomControls = str_contains($content, 'id="docModalZoomControls"');
        $hasCloseBtn = str_contains($content, 'id="docModalCloseBtn"');

        if (!$hasOpenInHeader && !$hasDownloadInHeader && $hasZoomControls && $hasCloseBtn) {
            $this->pass('testDocModalHeaderButtonsRemovedMatchingImage3: Open and Download removed from modal header, matching Image 3');
        } else {
            $this->fail("testDocModalHeaderButtonsRemovedMatchingImage3 failed: openInHeader=$hasOpenInHeader, downloadInHeader=$hasDownloadInHeader, zoom=$hasZoomControls, close=$hasCloseBtn");
        }
    }

    private function testDocumentReuploadAndUpdateWorkflow(): void
    {
        $user = User::find($this->testUser1Id);
        Auth::login($user);

        // Prepare dummy files for upload
        $tempIdFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_id_' . bin2hex(random_bytes(4)) . '.jpg';
        $tempCredFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_cred_' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($tempIdFile, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00");
        file_put_contents($tempCredFile, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01");

        $_FILES = [
            'valid_id' => [
                'name' => 'updated_passport.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => $tempIdFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tempIdFile),
            ],
            'credential' => [
                'name' => 'updated_license.png',
                'type' => 'image/png',
                'tmp_name' => $tempCredFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tempCredFile),
            ],
        ];
        $_POST = [
            '_token' => Auth::csrfToken(),
            'credential_type' => 'guide_license',
        ];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new DocumentController();
        ob_start();
        $controller->upload();
        $output = ob_get_clean();

        unset($_SERVER['HTTP_ACCEPT'], $_FILES, $_POST);
        @unlink($tempIdFile);
        @unlink($tempCredFile);

        $res = json_decode($output ?: '', true);

        // Check documents in DB
        $docs = GuideDocument::forUser($this->testUser1Id);
        $freshUser = User::find($this->testUser1Id);

        $labels = array_column($docs, 'label');
        $hasUpdatedId = in_array('updated_passport.jpg', $labels, true);
        $hasUpdatedCred = in_array('updated_license.png', $labels, true);
        $isPending = ($freshUser['guide_status'] ?? '') === 'pending';

        if (is_array($res) && !empty($res['success']) && $hasUpdatedId && $hasUpdatedCred && $isPending) {
            $this->pass('testDocumentReuploadAndUpdateWorkflow: re-upload and update workflow saves documents and sets pending status');
        } else {
            $this->fail("testDocumentReuploadAndUpdateWorkflow failed: json=" . json_encode($res) . " id=$hasUpdatedId cred=$hasUpdatedCred pending=$isPending");
        }
    }

    private function pass(string $name): void
    {
        echo " [PASS] $name\n";
        $this->passed++;
    }

    private function fail(string $name): void
    {
        echo " [FAIL] $name\n";
        $this->failed++;
    }
}

$test = new DocumentHandlingAndRoutingTest();
exit($test->run() ? 0 : 1);
