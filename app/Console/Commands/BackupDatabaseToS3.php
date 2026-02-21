<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BackupDatabaseToS3 extends Command
{
    protected $signature = 'app:backup-database-to-s3
        {--retention-days=14}
        {--disk=s3}
        {--path-prefix=respaldos}';

    protected $description = 'Dump MySQL (gz) y sube a S3';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $retentionDays = (int) $this->option('retention-days');
        $prefix = trim((string) $this->option('path-prefix'), '/');

        $env = app()->environment();
        $app = Str::slug(config('app.name', 'Nenis'));
        $timestamp = now('UTC')->format('Ymd_His');
        $datePath = now('UTC')->format('Y/m/d');

        $remotePath = "{$prefix}/{$app}/{$datePath}/db_{$timestamp}_utc.sql.gz";

        $conn = config('database.connections.mysql');
        $host = $conn['host'] ?? '127.0.0.1';
        $port = (int) ($conn['port'] ?? 3306);
        $database = $conn['database'] ?? null;
        $username = $conn['username'] ?? null;
        $password = $conn['password'] ?? '';

        if (!$database || !$username) {
            $this->error('Config de BD incompleta.');
            return self::FAILURE;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0775, true);

        $localFile = "{$tmpDir}/db_{$timestamp}_utc.sql.gz";

        $cmd = sprintf(
            'MYSQL_PWD=%s mysqldump --single-transaction --quick --routines --triggers --events -h%s -P%d -u%s %s | gzip > %s',
            escapeshellarg($password),
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($localFile),
        );

        $this->info('Generando dump...');
        $exitCode = null;
        system($cmd, $exitCode);

        if ($exitCode !== 0 || !file_exists($localFile) || filesize($localFile) === 0) {
            @unlink($localFile);
            $this->error("Falló mysqldump/gzip (exit={$exitCode}).");
            return self::FAILURE;
        }

        $this->info("Subiendo a {$disk}:{$remotePath} ...");

        $stream = fopen($localFile, 'r');
        Storage::disk($disk)->put($remotePath, $stream, [
            'visibility' => 'private',
            'ContentType' => 'application/gzip',
        ]);
        fclose($stream);

        @unlink($localFile);

        $this->info('Backup OK');

        $this->applyRetention($disk, "{$prefix}/{$app}/{$env}", $retentionDays);

        return self::SUCCESS;
    }

    private function applyRetention(string $disk, string $basePath, int $days): void
    {
        if ($days <= 0) return;

        $cutoff = now('UTC')->subDays($days);
        $files = Storage::disk($disk)->allFiles($basePath);

        $deleted = 0;
        foreach ($files as $file) {
            if (!preg_match('/db_(\d{8}_\d{6})_utc\.sql\.gz$/', $file, $m)) continue;

            $dt = Carbon::createFromFormat('Ymd_His', $m[1], 'UTC');
            if ($dt->lt($cutoff)) {
                Storage::disk($disk)->delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) $this->info("Retención: eliminados {$deleted} backups.");
    }
}
