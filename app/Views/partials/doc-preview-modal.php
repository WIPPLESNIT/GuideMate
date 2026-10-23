<?php
/**
 * Global Document Preview Modal
 * Provides an interactive lightbox viewer for verification documents (images, PDFs)
 * with loading spinner, zoom controls, status badges, and error handling.
 */
?>
<div id="docPreviewModal" class="doc-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="docModalTitle" style="display:none;">
    <div class="doc-modal-overlay" data-doc-close="true"></div>
    <div class="doc-modal-dialog">
        <div class="doc-modal-header">
            <div class="doc-modal-header-info">
                <div class="doc-modal-title-row">
                    <h3 id="docModalTitle" class="doc-modal-title">Document Preview</h3>
                    <div id="docModalBadges" class="doc-modal-badges">
                        <span id="docModalTypeBadge" class="doc-type-pill">Document</span>
                        <span id="docModalStatusBadge" class="doc-status-pill pill-pending">Under Review</span>
                    </div>
                </div>
                <div id="docModalMeta" class="doc-modal-meta">
                    <span id="docModalOwner"></span>
                    <span class="meta-sep">·</span>
                    <span id="docModalFileInfo"></span>
                    <span class="meta-sep">·</span>
                    <span id="docModalDate"></span>
                </div>
            </div>

            <div class="doc-modal-actions">
                <!-- Zoom controls for images -->
                <div id="docModalZoomControls" class="doc-zoom-toolbar" style="display:none;">
                    <button type="button" class="doc-tool-btn" id="docZoomOutBtn" title="Zoom out (–)" aria-label="Zoom out">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    </button>
                    <span id="docZoomLevel" class="doc-zoom-level">100%</span>
                    <button type="button" class="doc-tool-btn" id="docZoomInBtn" title="Zoom in (+)" aria-label="Zoom in">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    </button>
                    <button type="button" class="doc-tool-btn" id="docZoomResetBtn" title="Reset zoom / Fit to screen" aria-label="Reset zoom">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                    </button>
                </div>

                <!-- Close modal button (Open and Download buttons removed per requirement 6 / Image 3) -->
                <button type="button" class="doc-close-btn" id="docModalCloseBtn" data-doc-close="true" title="Close preview (Esc)" aria-label="Close preview">
                    &times;
                </button>
            </div>
        </div>

        <div class="doc-modal-body">
            <!-- Loading Indicator -->
            <div id="docModalLoader" class="doc-preview-state doc-preview-loading">
                <div class="doc-spinner"></div>
                <p>Loading document preview...</p>
            </div>

            <!-- Image Viewer Container -->
            <div id="docModalImageWrap" class="doc-image-wrap" style="display:none;">
                <div id="docModalImageStage" class="doc-image-stage">
                    <img id="docModalImg" src="" alt="Document Preview" class="doc-preview-img" draggable="false">
                </div>
            </div>

            <!-- PDF Viewer Container -->
            <div id="docModalPdfWrap" class="doc-pdf-wrap" style="display:none;">
                <iframe id="docModalPdfFrame" src="" title="PDF Document Viewer" class="doc-preview-frame"></iframe>
            </div>

            <!-- Unsupported Format State -->
            <div id="docModalUnsupported" class="doc-preview-state doc-preview-unsupported" style="display:none;">
                <div class="state-icon">📁</div>
                <h4 id="docUnsupportedTitle">File Format Preview Unavailable</h4>
                <p class="hint">This document type cannot be rendered inline in the browser previewer. You can download the full file directly.</p>
                <a id="docUnsupportedDownload" href="#" class="btn btn-primary" download>
                    Download File
                </a>
            </div>

            <!-- Error State -->
            <div id="docModalError" class="doc-preview-state doc-preview-error" style="display:none;">
                <div class="state-icon">⚠️</div>
                <h4 id="docErrorTitle">Unable to Load Document</h4>
                <p id="docErrorMessage" class="hint">The document file could not be loaded or is temporarily unavailable.</p>
                <div class="state-actions">
                    <button type="button" id="docErrorRetryBtn" class="btn btn-ghost btn-sm">Retry</button>
                    <a id="docErrorDownloadBtn" href="#" class="btn btn-primary btn-sm" download>Download File</a>
                </div>
            </div>
        </div>
    </div>
</div>
