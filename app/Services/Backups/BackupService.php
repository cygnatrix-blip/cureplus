<?php

namespace App\Services\Backups;

use App\Models\BackupRun;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Written in pure PHP, because mysqldump needs proc_open and the host disables it.
 * The file is SQL, then gzip, then encrypted in chunks, so a stolen copy reveals nothing.
 */
class BackupService
{
    private const DISK_FOLDER = 'backups';

    // Rebuilt by the app, or useless after a restore.
    private const SKIP_TABLES = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches'];

    private const CHUNK_BYTES = 1048576;

    public function run(string $trigger = 'schedule'): BackupRun
    {
        $run = BackupRun::query()->create(['status' => 'running', 'trigger' => $trigger]);
        $sqlPath = (string) tempnam(sys_get_temp_dir(), 'bak');
        $encryptedPath = $sqlPath.'.enc';

        try {
            $tables = $this->dump($sqlPath);
            $this->encryptFile($sqlPath, $encryptedPath);

            $filename = 'cureplus-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.sql.gz.enc';
            $disk = $this->disk();
            $stream = fopen($encryptedPath, 'r');
            $disk->writeStream(self::DISK_FOLDER.'/'.$filename, $stream);
            is_resource($stream) && fclose($stream);

            $run->fill([
                'filename' => $filename,
                'size_bytes' => (int) filesize($encryptedPath),
                'checksum' => (string) hash_file('sha256', $encryptedPath),
                'tables_count' => $tables,
                'status' => 'completed',
                'finished_at' => now(),
                'copied_offsite' => $this->copyOffsite($filename, $encryptedPath),
            ])->save();

            $this->prune();
        } catch (Throwable $exception) {
            // Only the type: database errors can quote the data they failed on.
            $run->fill(['status' => 'failed', 'error' => class_basename($exception), 'finished_at' => now()])->save();

            throw $exception;
        } finally {
            @unlink($sqlPath);
            @unlink($encryptedPath);
        }

        return $run;
    }

    /**
     * Proves a backup can be read without touching live data: checks the file's checksum,
     * decrypts it, and parses the SQL.
     *
     * @return array{tables: int, rows: int}
     */
    public function verify(BackupRun $run): array
    {
        $path = $this->localCopy($run);
        $sqlPath = $path.'.sql';

        try {
            if (hash_file('sha256', $path) !== $run->checksum) {
                throw new RuntimeException('The backup file does not match its checksum.');
            }

            $this->decryptFile($path, $sqlPath);
            $tables = 0;
            $rows = 0;

            foreach ($this->statements($sqlPath) as $statement) {
                $tables += str_starts_with($statement, 'CREATE TABLE') ? 1 : 0;
                $rows += str_starts_with($statement, 'INSERT INTO') ? 1 : 0;
            }

            if ($tables === 0) {
                throw new RuntimeException('The backup contains no tables.');
            }

            return ['tables' => $tables, 'rows' => $rows];
        } finally {
            @unlink($path);
            @unlink($sqlPath);
        }
    }

    /**
     * Replaces the current database with the backup. Only reachable from the command
     * line, on purpose: it destroys what is there now.
     */
    public function restore(BackupRun $run): void
    {
        $path = $this->localCopy($run);
        $sqlPath = $path.'.sql';

        try {
            $this->decryptFile($path, $sqlPath);
            Schema::disableForeignKeyConstraints();

            foreach ($this->statements($sqlPath) as $statement) {
                DB::unprepared($statement);
            }

            Schema::enableForeignKeyConstraints();
        } finally {
            @unlink($path);
            @unlink($sqlPath);
        }
    }

    public function path(BackupRun $run): string
    {
        return self::DISK_FOLDER.'/'.$run->filename;
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('cureplus.backups.disk'));
    }

    private function dump(string $path): int
    {
        $gz = gzopen($path, 'wb6');
        $pdo = DB::connection()->getPdo();
        $count = 0;

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (in_array($name, self::SKIP_TABLES, true) || str_starts_with($name, 'sqlite_')) {
                continue;
            }

            gzwrite($gz, 'DROP TABLE IF EXISTS `'.$name."`;\n");
            gzwrite($gz, str_replace("\n", ' ', $this->createStatement($name)).";\n");

            // cursor() streams row by row, so a large table does not fill memory.
            foreach (DB::table($name)->cursor() as $row) {
                $values = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), (array) $row);
                $line = 'INSERT INTO `'.$name.'` (`'.implode('`,`', array_keys((array) $row)).'`) VALUES ('.implode(',', $values).');';
                // One statement per line, with newlines escaped, so a restore can read line by line.
                gzwrite($gz, base64_encode($line)."\n");
            }

            $count++;
        }

        gzclose($gz);

        return $count;
    }

    private function createStatement(string $table): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return (string) DB::scalar('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);
        }

        $row = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');

        return (string) array_values($row)[1];
    }

    /**
     * @return \Generator<int, string>
     */
    private function statements(string $path): \Generator
    {
        $gz = gzopen($path, 'rb');

        while (! gzeof($gz)) {
            $line = rtrim((string) gzgets($gz), "\n");

            if ($line === '') {
                continue;
            }

            yield str_starts_with($line, 'DROP TABLE') || str_starts_with($line, 'CREATE TABLE')
                ? rtrim($line, ';')
                : rtrim((string) base64_decode($line), ';');
        }

        gzclose($gz);
    }

    private function encryptFile(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = fopen($to, 'wb');
        $key = $this->key();

        while (! feof($in)) {
            $chunk = (string) fread($in, self::CHUNK_BYTES);

            if ($chunk === '') {
                break;
            }

            $iv = random_bytes(12);
            $tag = '';
            $cipher = (string) openssl_encrypt($chunk, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            fwrite($out, pack('N', strlen($cipher)).$iv.$tag.$cipher);
        }

        fclose($in);
        fclose($out);
    }

    private function decryptFile(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = fopen($to, 'wb');
        $key = $this->key();

        while (! feof($in)) {
            $header = fread($in, 4);

            if ($header === false || strlen($header) < 4) {
                break;
            }

            $length = (int) unpack('N', $header)[1];
            $iv = (string) fread($in, 12);
            $tag = (string) fread($in, 16);
            $plain = openssl_decrypt((string) fread($in, $length), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

            if ($plain === false) {
                throw new RuntimeException('This backup cannot be decrypted with the current key.');
            }

            fwrite($out, $plain);
        }

        fclose($in);
        fclose($out);
    }

    /**
     * BACKUP_KEY is optional. Without it, backups are tied to APP_KEY, which
     * has to be kept safe anyway because it also protects encrypted columns.
     */
    private function key(): string
    {
        return hash('sha256', (string) (config('cureplus.backups.key') ?: config('app.key')).'|backup', true);
    }

    private function localCopy(BackupRun $run): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'res');
        $stream = $this->disk()->readStream($this->path($run)) ?? throw new RuntimeException('The backup file is missing.');
        $out = fopen($path, 'wb');
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);

        return $path;
    }

    private function copyOffsite(string $filename, string $path): bool
    {
        $disk = config('cureplus.backups.offsite_disk');

        if (blank($disk)) {
            return false;
        }

        $stream = fopen($path, 'r');
        $copied = Storage::disk((string) $disk)->writeStream(self::DISK_FOLDER.'/'.$filename, $stream);
        is_resource($stream) && fclose($stream);

        return (bool) $copied;
    }

    private function prune(): void
    {
        $old = BackupRun::query()
            ->where('status', 'completed')
            ->where('created_at', '<', now()->subDays((int) config('cureplus.backups.retention_days')))
            ->get();

        foreach ($old as $run) {
            if ($run->filename) {
                $this->disk()->delete($this->path($run));
            }

            $run->fill(['status' => 'expired'])->save();
        }
    }
}
