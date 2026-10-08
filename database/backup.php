<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$config = \App\Core\App::config('db');
$dbName = $config['database'] ?? 'guidemate';
$host = $config['host'] ?? '127.0.0.1';
$user = $config['username'] ?? 'root';
$pass = $config['password'] ?? '';

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$stamp = date('Y-m-d_His');
$sqlFile = $backupDir . "/{$dbName}_{$stamp}.sql";
$zipFile = $backupDir . "/guidemate_backup_{$stamp}.zip";

$cmd = sprintf(
    'mysqldump -h%s -u%s %s %s > %s',
    escapeshellarg($host),
    escapeshellarg($user),
    $pass !== '' ? '-p' . escapeshellarg($pass) : '',
    escapeshellarg($dbName),
    escapeshellarg($sqlFile)
);

exec($cmd, $output, $code);
if ($code !== 0 || !is_file($sqlFile)) {
    fwrite(STDERR, "mysqldump failed. Ensure mysqldump is in PATH.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create zip archive.\n");
    exit(1);
}
$zip->addFile($sqlFile, basename($sqlFile));

$uploads = dirname(__DIR__) . '/public/uploads';
if (is_dir($uploads)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $zip->addFile($file->getPathname(), 'uploads/' . substr($file->getPathname(), strlen($uploads) + 1));
        }
    }
}

$zip->close();
unlink($sqlFile);

echo "Backup saved: {$zipFile}\n";
