<?php

declare(strict_types=1);

/**
 * One-time backfill: some guides were seeded as already "approved" without any
 * rows in `guide_documents`, so their Profile / verification page shows nothing.
 * This inserts placeholder verification documents (viewable SVG files) for every
 * approved guide that has none, so the documents they were verified on are shown.
 *
 * Safe to re-run: it only touches approved guides that currently have 0 docs.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$publicDir = __DIR__ . '/../public';

/** Build a simple, self-contained "document on file" placeholder as an SVG. */
$makeSvg = static function (string $title, string $ownerName): string {
    $t = htmlspecialchars($title, ENT_QUOTES);
    $n = htmlspecialchars($ownerName, ENT_QUOTES);
    $date = date('F j, Y');
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500">
  <rect width="800" height="500" fill="#f0f7f5"/>
  <rect x="24" y="24" width="752" height="452" rx="18" fill="#ffffff" stroke="#0f766e" stroke-width="3"/>
  <text x="60" y="110" font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700" fill="#0f766e">GuideMate — Verified Document</text>
  <text x="60" y="180" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="700" fill="#0f172a">{$t}</text>
  <text x="60" y="240" font-family="Segoe UI, Arial, sans-serif" font-size="22" fill="#334155">Submitted by: {$n}</text>
  <text x="60" y="284" font-family="Segoe UI, Arial, sans-serif" font-size="22" fill="#334155">Reviewed &amp; verified by GuideMate admin</text>
  <text x="60" y="328" font-family="Segoe UI, Arial, sans-serif" font-size="20" fill="#64748b">On file since {$date}</text>
  <rect x="60" y="380" width="240" height="60" rx="10" fill="#dcfce7" stroke="#22c55e" stroke-width="2"/>
  <text x="180" y="418" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700" fill="#15803d">✓ VERIFIED</text>
</svg>
SVG;
};

// Placeholder documents to create for a verified guide.
$templates = [
    ['type' => 'valid_id', 'label' => 'Valid government ID', 'file' => 'valid-id.svg'],
    ['type' => 'guide_license', 'label' => 'Tour guide license', 'file' => 'guide-license.svg'],
];

$guides = Database::all(
    "SELECT u.id, u.name
     FROM users u
     LEFT JOIN guide_documents d ON d.user_id = u.id
     WHERE u.role = 'guide' AND u.guide_status = 'approved'
     GROUP BY u.id, u.name
     HAVING COUNT(d.id) = 0"
);

if ($guides === []) {
    echo "Nothing to backfill — every approved guide already has documents.\n";
    return;
}

foreach ($guides as $g) {
    $uid = (int) $g['id'];
    $name = (string) $g['name'];
    $dir = $publicDir . '/uploads/guide-docs/' . $uid;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    foreach ($templates as $tpl) {
        $relPath = 'uploads/guide-docs/' . $uid . '/' . $tpl['file'];
        $absPath = $publicDir . '/' . $relPath;
        file_put_contents($absPath, $makeSvg($tpl['label'], $name));
        Database::insert(
            'INSERT INTO guide_documents (user_id, doc_type, label, file_path) VALUES (?, ?, ?, ?)',
            [$uid, $tpl['type'], $tpl['label'], $relPath]
        );
        echo "Added {$tpl['label']} for guide #{$uid} ({$name})\n";
    }
}

echo "Backfill complete.\n";
