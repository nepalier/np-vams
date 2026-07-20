<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Document\Models\DocumentVersion;
use App\Domain\Document\Models\PropertyDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Centralizes the two non-negotiable rules from Step 1 Section 49:
 * "Do not store uploaded documents in public directories" and every
 * document mutation is versioned rather than overwritten in place.
 */
class DocumentService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MAX_SIZE_BYTES = 25 * 1024 * 1024; // 25MB

    public function upload(Model $documentable, UploadedFile $file, array $attributes, string $uploadedByUserId): PropertyDocument
    {
        $this->assertFileIsSafe($file);

        $tenantId = $documentable->tenant_id;
        $disk = config('npvams.documents.disk'); // never the public disk -- see config/filesystems.php

        $path = sprintf(
            'tenants/%s/documents/%s/%s',
            $tenantId,
            Str::uuid(),
            $file->getClientOriginalName()
        );

        $storedPath = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($documentable, $attributes, $disk, $storedPath, $hash, $uploadedByUserId, $tenantId) {
            $document = PropertyDocument::create(array_merge($attributes, [
                'tenant_id' => $tenantId,
                'documentable_type' => $documentable::class,
                'documentable_id' => $documentable->id,
                'storage_disk' => $disk,
                'file_path' => $storedPath,
                'file_hash_sha256' => $hash,
                'current_version' => 1,
                'uploaded_by' => $uploadedByUserId,
                'uploaded_at' => now(),
            ]));

            DocumentVersion::create([
                'tenant_id' => $tenantId,
                'property_document_id' => $document->id,
                'version_number' => 1,
                'storage_disk' => $disk,
                'file_path' => $storedPath,
                'file_hash_sha256' => $hash,
                'uploaded_by' => $uploadedByUserId,
                'uploaded_at' => now(),
                'change_remarks' => 'Initial upload.',
            ]);

            return $document;
        });
    }

    public function replaceWithNewVersion(PropertyDocument $document, UploadedFile $file, string $uploadedByUserId, ?string $remarks = null): PropertyDocument
    {
        $this->assertFileIsSafe($file);

        $nextVersion = $document->current_version + 1;
        $path = sprintf('tenants/%s/documents/%s/v%d_%s', $document->tenant_id, $document->id, $nextVersion, $file->getClientOriginalName());
        $storedPath = Storage::disk($document->storage_disk)->putFileAs(dirname($path), $file, basename($path));
        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($document, $storedPath, $hash, $nextVersion, $uploadedByUserId, $remarks) {
            DocumentVersion::create([
                'tenant_id' => $document->tenant_id,
                'property_document_id' => $document->id,
                'version_number' => $nextVersion,
                'storage_disk' => $document->storage_disk,
                'file_path' => $storedPath,
                'file_hash_sha256' => $hash,
                'uploaded_by' => $uploadedByUserId,
                'uploaded_at' => now(),
                'change_remarks' => $remarks,
            ]);

            $document->forceFill([
                'file_path' => $storedPath,
                'file_hash_sha256' => $hash,
                'current_version' => $nextVersion,
            ])->save();

            return $document->fresh();
        });
    }

    /** Duplicate-photo/document detection (Section 16/18) via hash within the same documentable. */
    public function findDuplicateByHash(Model $documentable, string $hash): ?PropertyDocument
    {
        return PropertyDocument::where('documentable_type', $documentable::class)
            ->where('documentable_id', $documentable->id)
            ->where('file_hash_sha256', $hash)
            ->first();
    }

    private function assertFileIsSafe(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('File exceeds the maximum allowed size of 25MB.');
        }

        // MIME check via the file's actual content (finfo-backed), not the
        // client-supplied extension/Content-Type -- extension/type headers
        // are trivially spoofable.
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('File type not permitted.');
        }

        // Malware scan hook: wire to a ClamAV daemon (clamd) via a socket
        // client here in the production hardening pass. Left as a single
        // choke point so nothing downstream needs to change when it lands.
    }
}
