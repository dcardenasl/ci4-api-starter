<?php

declare(strict_types=1);

namespace App\Interfaces\System;

use App\DTO\Response\Common\PayloadResponseDTO;
use App\Interfaces\DataTransferObjectInterface;

interface CatalogServiceInterface
{
    public function index(): PayloadResponseDTO;

    public function auditFacets(DataTransferObjectInterface $request): PayloadResponseDTO;
}
