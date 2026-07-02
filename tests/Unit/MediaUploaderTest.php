<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers MediaUploader's rejection paths — the part of the upload flow that
 * runs before any DB write, so it's testable without a live database. This
 * is the security-sensitive validation that used to be duplicated 4x across
 * FieldController and TeamPortalController (size limits, mime allowlists).
 */
final class MediaUploaderTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'mu_test_');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    public function testStorePhotoRejectsMissingFile(): void
    {
        $result = MediaUploader::storePhoto([], ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
        $this->assertSame('Δεν επιλέχθηκε έγκυρη φωτογραφία.', $result['error']);
    }

    public function testStorePhotoRejectsUploadError(): void
    {
        $file = ['error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => $this->tmpFile, 'size' => 100];
        $result = MediaUploader::storePhoto($file, ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
    }

    public function testStorePhotoRejectsOversizedFile(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $this->tmpFile, 'size' => 13 * 1024 * 1024];
        $result = MediaUploader::storePhoto($file, ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('12MB', $result['error']);
    }

    public function testStorePhotoRejectsNonImageContent(): void
    {
        file_put_contents($this->tmpFile, 'this is not an image');
        $file = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $this->tmpFile, 'size' => 21];
        $result = MediaUploader::storePhoto($file, ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('JPG', $result['error']);
    }

    public function testStoreVideoRejectsMissingFile(): void
    {
        $result = MediaUploader::storeVideo([], ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
        $this->assertSame('Δεν επιλέχθηκε έγκυρο βίντεο.', $result['error']);
    }

    public function testStoreVideoRejectsOversizedFile(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $this->tmpFile, 'size' => 61 * 1024 * 1024];
        $result = MediaUploader::storeVideo($file, ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('60MB', $result['error']);
    }

    public function testStoreVideoRejectsUnsupportedMime(): void
    {
        file_put_contents($this->tmpFile, 'not a real video file');
        $file = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $this->tmpFile, 'size' => 22];
        $result = MediaUploader::storeVideo($file, ['mid' => 1, 'eid' => 1, 'tid' => 1]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('MP4', $result['error']);
    }
}
