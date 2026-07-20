<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Section 34: SHA-256 hash, tamper detection, report locking after
 * approval, version numbering, superseded-report reference. This class is
 * the only place a report_versions row is ever written -- a version is
 * NEVER updated after creation, only superseded by a new row, and a
 * locked report can still gain new versions (a correction after approval
 * is legitimate) but never has an existing version's file silently swapped.
 */
class ReportIntegrityService
{
    public function createVersion(
        Report $report,
        string $fileContents,
        string $format,
        ?string $generatedByUserId,
        ?string $supersedeReason = null,
    ): ReportVersion {
        if ($report->is_locked && empty($supersedeReason)) {
            throw new RuntimeException(
                'This report is locked (already approved/issued). A supersedeReason is required to record a new version after this point.'
            );
        }

        $disk = config('npvams.documents.disk');
        $nextVersionNumber = ($report->versions()->max('version_number') ?? 0) + 1;
        $path = sprintf('tenants/%s/reports/%s/v%d.%s', $report->tenant_id, $report->id, $nextVersionNumber, $format === 'docx' ? 'docx' : 'pdf');

        Storage::disk($disk)->put($path, $fileContents);
        $hash = hash('sha256', $fileContents);

        return DB::transaction(function () use ($report, $format, $disk, $path, $hash, $generatedByUserId, $nextVersionNumber, $supersedeReason) {
            $previousVersion = $report->currentVersion;

            $version = ReportVersion::create([
                'tenant_id' => $report->tenant_id,
                'report_id' => $report->id,
                'version_number' => $nextVersionNumber,
                'format' => $format,
                'storage_disk' => $disk,
                'file_path' => $path,
                'file_hash_sha256' => $hash,
                'generated_by_user_id' => $generatedByUserId,
                'generated_at' => now(),
                'supersede_reason' => $supersedeReason,
            ]);

            if ($previousVersion !== null && $supersedeReason !== null) {
                $previousVersion->forceFill(['superseded_by_id' => $version->id])->save();
            }

            $report->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    public function lock(Report $report): Report
    {
        $report->forceFill(['is_locked' => true])->save();

        return $report;
    }

    /**
     * Recomputes the hash of the file currently on disk and compares it to
     * the hash recorded at generation time. A mismatch means the stored
     * file was altered outside of createVersion() -- exactly the tamper
     * scenario Section 34 requires detecting.
     */
    public function verifyIntegrity(ReportVersion $version): bool
    {
        $contents = Storage::disk($version->storage_disk)->get($version->file_path);

        if ($contents === null) {
            return false;
        }

        return hash('sha256', $contents) === $version->file_hash_sha256;
    }
}
