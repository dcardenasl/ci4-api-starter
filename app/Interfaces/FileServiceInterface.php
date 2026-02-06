<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * File Service Interface
 *
 * Contract for file upload, download, and deletion operations
 */
interface FileServiceInterface
{
    /**
     * Upload a file
     *
     * @param array $data Request data with 'file' and 'user_id'
     * @return array
     */
    public function upload(array $data): array;

    /**
     * List user's files
     *
     * @param array $data Request data with 'user_id'
     * @return array
     */
    public function index(array $data): array;

    /**
     * Download a file
     *
     * @param array $data Request data with 'id' and 'user_id'
     * @return array
     */
    public function download(array $data): array;

    /**
     * Delete a file
     *
     * @param array $data Request data with 'id' and 'user_id'
     * @return array
     */
    public function delete(array $data): array;
}
