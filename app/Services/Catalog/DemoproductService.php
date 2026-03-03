<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTO\SecurityContext;
use App\Entities\DemoproductEntity;
use App\Exceptions\BadRequestException;
use App\Interfaces\Catalog\DemoproductServiceInterface;
use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;

class DemoproductService extends BaseCrudService implements DemoproductServiceInterface
{
    public function __construct(
        protected RepositoryInterface $demoproductRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($responseMapper);
        $this->repository = $demoproductRepository;
    }

    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($request) {
            $id = $this->repository->insert($request->toArray());
            if ($id === false || $id === true) {
                throw new \App\Exceptions\ValidationException(lang('Api.validationFailed'), $this->repository->errors());
            }

            /** @var DemoproductEntity|null $entity */
            $entity = $this->repository->find($id);
            if (! $entity instanceof DemoproductEntity) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }

            return $this->mapToResponse($entity);
        });
    }

    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($id, $request) {
            if (!$this->repository->find($id)) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }

            $data = $request->toArray();
            if (empty($data)) {
                throw new BadRequestException(lang('Api.noFieldsToUpdate'));
            }

            $this->repository->update($id, $data);

            /** @var DemoproductEntity|null $entity */
            $entity = $this->repository->find($id);
            if (! $entity instanceof DemoproductEntity) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }

            return $this->mapToResponse($entity);
        });
    }
}
