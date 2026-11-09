<?php

namespace App\Services;

use App\Models\AssessmentNotification;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupService
{
    private const BACKUP_MAGIC = "DVXBACKUP1\n";

    private const PAYLOAD_MAGIC = "DVXPAYLOAD1\n";

    public function run(?User $actor = null, string $trigger = 'scheduled'): BackupRun
    {
        $lock = Cache::lock('divertex-system-backup', 21600);
        throw_if(! $lock->get(), new RuntimeException('Another backup is already running.'));
        $run = null;
        $temporary = [];

        try {
            $run = BackupRun::create([
                'requested_by' => $actor?->id,
                'status' => 'running',
                'trigger' => $trigger,
                'started_at' => now(),
            ]);
            set_time_limit(0);
            $this->ensureKey();
            $database = $this->temporaryPath("database-{$run->id}.dump");
            $payload = $this->temporaryPath("payload-{$run->id}.bin");
            $encrypted = $this->temporaryPath("encrypted-{$run->id}.dvxbak");
            $temporary = [$database, $payload, $encrypted];
            $databaseStats = $this->exportDatabase($database);
            $payloadStats = $this->buildPayload($payload, $database, $databaseStats);
            $this->encrypt($payload, $encrypted, $run);

            $name = sprintf('divertex-%s-%06d.dvxbak', now()->format('Ymd-His'), $run->id);
            $storedPath = trim((string) config('backups.directory'), '/').'/'.$name;
            $stream = fopen($encrypted, 'rb');
            throw_if(! $stream, new RuntimeException('Could not open the encrypted backup for storage.'));
            try {
                $stored = Storage::disk(config('backups.disk'))->writeStream($storedPath, $stream);
            } finally {
                fclose($stream);
            }
            throw_if(! $stored, new RuntimeException('The encrypted backup could not be written to the backup disk.'));

            $run->update([
                'status' => 'completed',
                'file_name' => $storedPath,
                'encrypted_size' => filesize($encrypted),
                'encrypted_checksum' => hash_file('sha256', $encrypted),
                'contents' => $payloadStats['contents'],
                'database_tables' => $databaseStats['tables'],
                'database_rows' => $databaseStats['rows'],
                'file_count' => $payloadStats['file_count'],
                'source_bytes' => $payloadStats['source_bytes'],
                'completed_at' => now(),
            ]);
            $this->prune();

            return $run->fresh();
        } catch (Throwable $exception) {
            if (! $run) {
                throw $exception;
            }
            $run->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            $this->alertFailure($run, 'Automated backup failed', 'The system backup failed. Open Backup & Recovery to review the failure and retry it.');
            report($exception);

            return $run->fresh();
        } finally {
            foreach ($temporary as $path) {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }
            $lock->release();
        }
    }

    public function testRestore(BackupRun $run): array
    {
        throw_if($run->status !== 'completed' || ! $run->file_name, new RuntimeException('Only completed backups can be restore-tested.'));
        $encrypted = $this->temporaryPath("restore-encrypted-{$run->id}.dvxbak");
        $payload = $this->temporaryPath("restore-payload-{$run->id}.bin");
        $stream = Storage::disk(config('backups.disk'))->readStream($run->file_name);
        throw_if(! $stream, new RuntimeException('The encrypted backup file is missing from storage.'));

        try {
            $destination = fopen($encrypted, 'wb');
            throw_if(! $destination, new RuntimeException('Could not prepare the isolated restore test.'));
            stream_copy_to_stream($stream, $destination);
            fclose($destination);
            fclose($stream);
            throw_if(hash_file('sha256', $encrypted) !== $run->encrypted_checksum, new RuntimeException('Encrypted backup checksum mismatch.'));
            $this->decrypt($encrypted, $payload);
            $result = $this->verifyPayload($payload);
            $run->update([
                'restore_tested_at' => now(),
                'restore_test_status' => 'passed',
                'restore_test_message' => "Verified {$result['entries']} entries, {$result['database_rows']} database rows, and all checksums.",
            ]);

            return $result;
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $run->update([
                'restore_tested_at' => now(),
                'restore_test_status' => 'failed',
                'restore_test_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            $this->alertFailure($run, 'Backup restore test failed', 'An encrypted backup failed its isolated restore test. Review Backup & Recovery immediately.');
            throw $exception;
        } finally {
            foreach ([$encrypted, $payload] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    public function prune(): void
    {
        $cutoff = now()->subDays(max(1, (int) config('backups.retention_days')));
        BackupRun::query()->where('status', 'completed')->where('completed_at', '<', $cutoff)->each(function (BackupRun $run): void {
            if ($run->file_name) {
                Storage::disk(config('backups.disk'))->delete($run->file_name);
            }
            $run->update(['status' => 'expired', 'file_name' => null]);
        });
    }

    private function exportDatabase(string $path): array
    {
        $handle = fopen($path, 'wb');
        throw_if(! $handle, new RuntimeException('Could not create the database backup stream.'));
        $tables = collect(Schema::getTableListing())->sort()->values();
        $rows = 0;
        fwrite($handle, json_encode(['format' => 'php-serialized-base64-v1', 'driver' => DB::getDriverName(), 'tables' => $tables->all()], JSON_THROW_ON_ERROR)."\n");

        DB::beginTransaction();
        try {
            foreach ($tables as $table) {
                foreach (DB::table($table)->cursor() as $row) {
                    fwrite($handle, $table."\t".base64_encode(serialize((array) $row))."\n");
                    $rows++;
                }
            }
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            fclose($handle);
            throw $exception;
        }
        fclose($handle);

        return ['tables' => $tables->count(), 'rows' => $rows, 'bytes' => filesize($path)];
    }

    private function buildPayload(string $payloadPath, string $databasePath, array $databaseStats): array
    {
        $output = fopen($payloadPath, 'wb');
        throw_if(! $output, new RuntimeException('Could not create the backup payload.'));
        fwrite($output, self::PAYLOAD_MAGIC);
        $contents = ['database' => ['label' => 'Database', 'files' => 1, 'bytes' => $databaseStats['bytes']]];
        $sourceBytes = $databaseStats['bytes'];
        $fileCount = 0;
        $this->writeLocalEntry($output, 'database/database.dump', 'database', $databasePath);

        foreach ((array) config('backups.sources') as $category => $source) {
            $disk = Storage::disk($source['disk']);
            $files = $disk->allFiles($source['path']);
            $categoryBytes = 0;
            foreach ($files as $file) {
                $size = (int) $disk->size($file);
                $stream = $disk->readStream($file);
                throw_if(! $stream, new RuntimeException("Could not read a {$source['label']} file."));
                $this->writeStreamEntry($output, 'files/'.$category.'/'.$file, $category, $stream, $size);
                fclose($stream);
                $fileCount++;
                $sourceBytes += $size;
                $categoryBytes += $size;
            }
            $contents[$category] = ['label' => $source['label'], 'files' => count($files), 'bytes' => $categoryBytes];
        }
        fwrite($output, "END\n");
        fclose($output);

        return ['contents' => $contents, 'file_count' => $fileCount, 'source_bytes' => $sourceBytes];
    }

    private function writeLocalEntry($output, string $path, string $category, string $source): void
    {
        $stream = fopen($source, 'rb');
        throw_if(! $stream, new RuntimeException('Could not read the database export.'));
        $this->writeStreamEntry($output, $path, $category, $stream, (int) filesize($source));
        fclose($stream);
    }

    private function writeStreamEntry($output, string $path, string $category, $stream, int $size): void
    {
        $temporary = $this->temporaryPath('entry-'.bin2hex(random_bytes(8)).'.bin');
        $copy = fopen($temporary, 'wb');
        $hash = hash_init('sha256');
        $written = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            throw_if($chunk === false, new RuntimeException('A backup source could not be read.'));
            if ($chunk === '') {
                continue;
            }
            hash_update($hash, $chunk);
            $written += strlen($chunk);
            fwrite($copy, $chunk);
        }
        fclose($copy);
        throw_if($written !== $size, new RuntimeException('A backup source changed while it was being copied.'));
        fwrite($output, json_encode(['path' => $path, 'category' => $category, 'size' => $size, 'sha256' => hash_final($hash)], JSON_THROW_ON_ERROR)."\n");
        $copy = fopen($temporary, 'rb');
        stream_copy_to_stream($copy, $output);
        fclose($copy);
        @unlink($temporary);
        fwrite($output, "\n");
    }

    private function encrypt(string $inputPath, string $outputPath, BackupRun $run): void
    {
        $input = fopen($inputPath, 'rb');
        $output = fopen($outputPath, 'wb');
        $salt = random_bytes(16);
        $key = $this->derivedKey($salt);
        $chunkBytes = (int) config('backups.chunk_bytes');
        fwrite($output, self::BACKUP_MAGIC);
        fwrite($output, json_encode(['cipher' => 'aes-256-gcm-chunks-v1', 'chunk_bytes' => $chunkBytes, 'salt' => base64_encode($salt), 'backup_id' => $run->id], JSON_THROW_ON_ERROR)."\n");
        $index = 0;
        while (! feof($input)) {
            $plain = fread($input, $chunkBytes);
            if ($plain === false || $plain === '') {
                continue;
            }
            $nonce = random_bytes(12);
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, pack('N', $index), 16);
            throw_if($cipher === false, new RuntimeException('Backup encryption failed.'));
            fwrite($output, pack('N', strlen($cipher)).$nonce.$tag.$cipher);
            $index++;
        }
        fwrite($output, pack('N', 0));
        fclose($input);
        fclose($output);
    }

    private function decrypt(string $inputPath, string $outputPath): void
    {
        $input = fopen($inputPath, 'rb');
        $output = fopen($outputPath, 'wb');
        throw_if(fgets($input) !== self::BACKUP_MAGIC, new RuntimeException('Invalid Divertex backup header.'));
        $metadata = json_decode((string) fgets($input), true, flags: JSON_THROW_ON_ERROR);
        throw_if(($metadata['cipher'] ?? null) !== 'aes-256-gcm-chunks-v1', new RuntimeException('Unsupported backup encryption format.'));
        $salt = base64_decode((string) ($metadata['salt'] ?? ''), true);
        throw_if($salt === false || strlen($salt) !== 16, new RuntimeException('Invalid backup encryption salt.'));
        $key = $this->derivedKey($salt);
        $index = 0;
        while (true) {
            $lengthBytes = fread($input, 4);
            throw_if(strlen((string) $lengthBytes) !== 4, new RuntimeException('The encrypted backup is truncated.'));
            $length = unpack('Nlength', $lengthBytes)['length'];
            if ($length === 0) {
                break;
            }
            $nonce = fread($input, 12);
            $tag = fread($input, 16);
            $cipher = $this->readExact($input, $length);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, pack('N', $index));
            throw_if($plain === false, new RuntimeException('Backup authentication failed. The file or encryption key is invalid.'));
            fwrite($output, $plain);
            $index++;
        }
        fclose($input);
        fclose($output);
    }

    private function verifyPayload(string $payloadPath): array
    {
        $input = fopen($payloadPath, 'rb');
        throw_if(fgets($input) !== self::PAYLOAD_MAGIC, new RuntimeException('Invalid backup payload.'));
        $entries = 0;
        $databaseRows = 0;
        $seen = [];
        while (($line = fgets($input)) !== false) {
            if ($line === "END\n") {
                break;
            }
            $entry = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
            $path = (string) ($entry['path'] ?? '');
            $size = (int) ($entry['size'] ?? -1);
            throw_if($path === '' || $size < 0 || isset($seen[$path]), new RuntimeException('Backup entry metadata is invalid.'));
            $seen[$path] = true;
            $hash = hash_init('sha256');
            $remaining = $size;
            $database = $path === 'database/database.dump' ? fopen($this->temporaryPath('verify-db-'.bin2hex(random_bytes(6)).'.dump'), 'w+b') : null;
            while ($remaining > 0) {
                $chunk = fread($input, min(1024 * 1024, $remaining));
                throw_if($chunk === false || $chunk === '', new RuntimeException('A backup entry is truncated.'));
                hash_update($hash, $chunk);
                if ($database) {
                    fwrite($database, $chunk);
                }
                $remaining -= strlen($chunk);
            }
            throw_if(fread($input, 1) !== "\n", new RuntimeException('Backup entry delimiter is invalid.'));
            throw_if(! hash_equals((string) ($entry['sha256'] ?? ''), hash_final($hash)), new RuntimeException("Checksum failed for {$path}."));
            if ($database) {
                rewind($database);
                $databaseRows = $this->verifyDatabaseDump($database);
                $meta = stream_get_meta_data($database);
                fclose($database);
                @unlink($meta['uri']);
            }
            $entries++;
        }
        throw_if(! isset($seen['database/database.dump']), new RuntimeException('Database export is missing from the backup.'));

        return ['entries' => $entries, 'database_rows' => $databaseRows];
    }

    private function verifyDatabaseDump($stream): int
    {
        $metadata = json_decode((string) fgets($stream), true, flags: JSON_THROW_ON_ERROR);
        throw_if(($metadata['format'] ?? null) !== 'php-serialized-base64-v1' || ! is_array($metadata['tables'] ?? null), new RuntimeException('Database restore metadata is invalid.'));
        $rows = 0;
        while (($line = fgets($stream)) !== false) {
            [$table, $encoded] = array_pad(explode("\t", rtrim($line, "\r\n"), 2), 2, null);
            $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
            $record = $decoded === false ? false : @unserialize($decoded, ['allowed_classes' => false]);
            throw_if(! in_array($table, $metadata['tables'], true) || ! is_array($record), new RuntimeException('A database restore row is invalid.'));
            $rows++;
        }

        return $rows;
    }

    private function readExact($stream, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($stream, $length - strlen($buffer));
            throw_if($chunk === false || $chunk === '', new RuntimeException('The encrypted backup is truncated.'));
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function derivedKey(string $salt): string
    {
        return hash_hkdf('sha256', (string) config('backups.encryption_key'), 32, 'divertex-system-backup-v1', $salt);
    }

    private function ensureKey(): void
    {
        throw_if(strlen((string) config('backups.encryption_key')) < 32, new RuntimeException('BACKUP_ENCRYPTION_KEY must be configured with at least 32 characters.'));
    }

    private function temporaryPath(string $name): string
    {
        $directory = storage_path('framework/cache/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not prepare private backup workspace.');
        }

        return $directory.'/'.$name;
    }

    private function alertFailure(BackupRun $run, string $title, string $message): void
    {
        User::query()->whereIn('role', ['admin', 'it_admin'])->get()->filter->canAccessAccount()->each(function (User $user) use ($run, $title, $message): void {
            AssessmentNotification::firstOrCreate(
                ['deduplication_key' => "backup:{$run->id}:failure:{$user->id}:".md5($title)],
                ['recipient_id' => $user->id, 'type' => 'backup_failure', 'title' => $title, 'message' => $message, 'action_url' => '/backup-recovery'],
            );
        });
    }
}
