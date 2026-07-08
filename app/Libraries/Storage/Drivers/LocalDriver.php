<?php

declare(strict_types=1);

namespace App\Libraries\Storage\Drivers;

use App\Libraries\Storage\StorageDriverInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Local File Storage Driver
 *
 * Stores files on the local filesystem using Flysystem
 */
class LocalDriver implements StorageDriverInterface
{
    protected Filesystem $filesystem;
    protected string $basePath;

    public function __construct()
    {
        $uploadPath = config('Api')->fileUploadPath;

        // Ensure path is absolute (relative to project root, not public).
        // Resolving against FCPATH (the public/ web root) would place uploads
        // inside the document root, making them directly fetchable by anyone
        // who can guess the path — bypassing the app's own auth/visibility
        // checks. Resolving against dirname(FCPATH) keeps the storage root
        // outside public/; the only files served raw are the ones explicitly
        // exposed via the public/uploads symlink (see url() below).
        if (!str_starts_with($uploadPath, '/')) {
            $projectRoot = dirname(FCPATH);
            $this->basePath = rtrim($projectRoot, '/') . '/' . ltrim($uploadPath, '/');
        } else {
            $this->basePath = $uploadPath;
        }

        // Create directory if it doesn't exist
        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }

        // Ensure directory is writable
        if (!is_writable($this->basePath)) {
            @chmod($this->basePath, 0775);
        }

        $adapter = new LocalFilesystemAdapter($this->basePath);
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Resolve the absolute filesystem path for a storage-relative path.
     *
     * This is the single source of truth for "where does the local driver
     * actually keep its files" — callers that need to touch the filesystem
     * directly (e.g. FileController's direct-download fast path, or storage
     * diagnostics) must go through this instead of re-deriving basePath from
     * config('Api')->fileUploadPath themselves, which is what caused the
     * write path (this class) and the read path (FileController) to drift
     * apart before this fix.
     *
     * @param string $path Path relative to the storage root
     * @return string Absolute filesystem path
     */
    public function getAbsolutePath(string $path): string
    {
        return rtrim($this->basePath, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Store a file
     *
     * @param string $path Path where to store the file
     * @param mixed $contents File contents (string or resource)
     * @return bool Success status
     */
    public function store(string $path, $contents): bool
    {
        try {
            if (is_resource($contents)) {
                $this->filesystem->writeStream($path, $contents);
            } else {
                $this->filesystem->write($path, $contents);
            }
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Local storage error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve a file
     *
     * @param string $path Path to the file
     * @return string|false File contents or false on failure
     */
    public function retrieve(string $path): string|false
    {
        try {
            return $this->filesystem->read($path);
        } catch (\Exception $e) {
            log_message('error', 'Local retrieval error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file
     *
     * @param string $path Path to the file
     * @return bool Success status
     */
    public function delete(string $path): bool
    {
        try {
            $this->filesystem->delete($path);
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Local deletion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a file exists
     *
     * @param string $path Path to the file
     * @return bool True if exists, false otherwise
     */
    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file URL
     *
     * @param string $path Path to the file
     * @return string URL to access the file
     */
    public function url(string $path): string
    {
        // Now that the storage root lives outside public/ (see __construct),
        // files are reachable through the public/uploads symlink rather than
        // by reconstructing a filesystem path — see install/README for how
        // that symlink is created.
        return base_url('uploads/' . $path);
    }

    /**
     * Get file size
     *
     * @param string $path Path to the file
     * @return int|false File size in bytes or false on failure
     */
    public function size(string $path): int|false
    {
        try {
            return $this->filesystem->fileSize($path);
        } catch (\Exception $e) {
            return false;
        }
    }
}
