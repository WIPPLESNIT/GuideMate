<?php

declare(strict_types=1);

/**
 * One-time backfill: some guides were seeded as already "approved" without any
 * rows in `guide_documents`, so their Profile / verification page shows nothing.
 * This inserts realistic verification documents (viewable SVG files) for every
 * approved guide that has none, so the documents they were verified on are shown.
 *
 * Safe to re-run: it only touches approved guides that currently have 0 docs.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$publicDir = __DIR__ . '/../public';

/** Build a realistic, self-contained guide document SVG. */
$makeSvg = static function (string $type, string $title, string $ownerName): string {
    $t = htmlspecialchars($title, ENT_QUOTES);
    $n = htmlspecialchars(strtoupper($ownerName), ENT_QUOTES);
    $year = (int) date('Y');
    $expireYear = $year + 3;
    $date = date('F j, Y');

    if ($type === 'guide_license') {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="850" height="600" viewBox="0 0 850 600">
  <defs>
    <linearGradient id="parchment" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fdfbf7"/>
      <stop offset="50%" stop-color="#faf5ed"/>
      <stop offset="100%" stop-color="#f4ebe1"/>
    </linearGradient>
    <linearGradient id="goldBorder" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#b45309"/>
      <stop offset="25%" stop-color="#f59e0b"/>
      <stop offset="50%" stop-color="#fef08a"/>
      <stop offset="75%" stop-color="#d97706"/>
      <stop offset="100%" stop-color="#78350f"/>
    </linearGradient>
  </defs>
  <rect x="20" y="20" width="810" height="560" rx="8" fill="url(#parchment)"/>
  <rect x="36" y="36" width="778" height="528" rx="6" fill="none" stroke="url(#goldBorder)" stroke-width="4"/>
  <rect x="44" y="44" width="762" height="512" rx="4" fill="none" stroke="#0f766e" stroke-width="1.5"/>
  <g transform="translate(425, 75)" text-anchor="middle">
    <circle cx="0" cy="18" r="28" fill="#0f766e"/>
    <circle cx="0" cy="18" r="22" fill="#042f2e"/>
    <text x="0" y="65" font-family="'Plus Jakarta Sans', Segoe UI, serif" font-size="12" font-weight="700" fill="#475569" letter-spacing="2">REPUBLIC OF THE PHILIPPINES</text>
    <text x="0" y="84" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="16" font-weight="800" fill="#0f766e" letter-spacing="1.5">DEPARTMENT OF TOURISM</text>
    <text x="0" y="100" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="600" fill="#64748b">REGION VII · CENTRAL VISAYAS REGIONAL OFFICE</text>
    <text x="0" y="142" font-family="'Georgia', serif" font-size="28" font-weight="700" fill="#0f172a" letter-spacing="1">Certificate of Accreditation</text>
    <line x1="-160" y1="154" x2="160" y2="154" stroke="url(#goldBorder)" stroke-width="2"/>
    <text x="0" y="172" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#b45309" letter-spacing="1">ACCREDITATION NO. DOT-R07-TG-{$year}-0842</text>
    <text x="0" y="205" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="13" font-style="italic" fill="#475569">This is to officially certify that</text>
    <text x="0" y="246" font-family="'Georgia', serif" font-size="30" font-weight="700" fill="#0f766e" letter-spacing="1">{$n}</text>
    <line x1="-180" y1="258" x2="180" y2="258" stroke="#cbd5e1" stroke-width="1"/>
    <text x="0" y="290" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="13" fill="#334155">has complied with all requirements set forth for Accredited Tour Guides in Region VII.</text>
    <text x="0" y="336" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="16" font-weight="800" fill="#0f172a">OFFICIAL REGIONAL TOUR GUIDE</text>
  </g>
  <g transform="translate(80, 470)">
    <text x="0" y="0" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#64748b">DATE ISSUED:</text>
    <text x="80" y="0" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="12" font-weight="700" fill="#1e293b">{$date}</text>
    <text x="0" y="22" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#64748b">VALID UNTIL:</text>
    <text x="80" y="22" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="12" font-weight="700" fill="#0f766e">December 31, {$expireYear}</text>
  </g>
</svg>
SVG;
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="850" height="540" viewBox="0 0 850 540">
  <defs>
    <linearGradient id="cardGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0f766e"/>
      <stop offset="100%" stop-color="#042f2e"/>
    </linearGradient>
  </defs>
  <rect x="20" y="20" width="810" height="500" rx="20" fill="url(#cardGrad)"/>
  <rect x="20" y="20" width="810" height="96" rx="20" fill="#042f2e" fill-opacity="0.6"/>
  <text x="60" y="52" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="13" font-weight="700" fill="#ccfbf1" letter-spacing="1.5">REPUBLIC OF THE PHILIPPINES · DEPARTMENT OF TOURISM</text>
  <text x="60" y="76" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="18" font-weight="800" fill="#ffffff">OFFICIAL TOURIST GUIDE IDENTIFICATION CARD</text>
  <rect x="42" y="132" width="766" height="364" rx="14" fill="#ffffff"/>
  <g transform="translate(70, 160)">
    <rect x="0" y="0" width="160" height="200" rx="10" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2"/>
    <circle cx="80" cy="70" r="34" fill="#94a3b8"/>
    <path d="M 28 170 C 28 120, 132 120, 132 170 Z" fill="#0d9488"/>
  </g>
  <g transform="translate(260, 165)">
    <text x="0" y="20" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#64748b" letter-spacing="1">FULL NAME</text>
    <text x="0" y="50" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="24" font-weight="800" fill="#0f172a">{$n}</text>
    <text x="0" y="90" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#64748b" letter-spacing="1">ACCREDITATION NUMBER</text>
    <text x="0" y="114" font-family="Courier, monospace" font-size="17" font-weight="800" fill="#0f766e">DOT-R07-TG-{$year}-0842</text>
    <text x="0" y="150" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#64748b" letter-spacing="1">DOCUMENT TYPE</text>
    <text x="0" y="174" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="16" font-weight="700" fill="#1e293b">{$t}</text>
    <text x="0" y="210" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="11" font-weight="700" fill="#64748b" letter-spacing="1">VALIDITY</text>
    <text x="0" y="234" font-family="'Plus Jakarta Sans', Segoe UI, sans-serif" font-size="14" font-weight="700" fill="#0f766e">Valid until December 31, {$expireYear}</text>
  </g>
</svg>
SVG;
};

// Documents to create for a verified guide.
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
        file_put_contents($absPath, $makeSvg($tpl['type'], $tpl['label'], $name));
        Database::insert(
            'INSERT INTO guide_documents (user_id, doc_type, label, file_path) VALUES (?, ?, ?, ?)',
            [$uid, $tpl['type'], $tpl['label'], $relPath]
        );
        echo "Added {$tpl['label']} for guide #{$uid} ({$name})\n";
    }
}

echo "Backfill complete.\n";
