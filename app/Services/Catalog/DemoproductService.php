<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTO\SecurityContext;
use App\Entities\DemoproductEntity;
use App\Exceptions\BadRequestException;
use App\Interfaces\Catalog\DemoproductServiceInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Models\DemoproductModel;
use App\Services\Core\BaseCrudService;
use App\Traits\AppliesQueryOptions;

class DemoproductService extends BaseCrudService implements DemoproductServiceInterface
{
    use AppliesQueryOptions;

    public function __construct(
        protected DemoproductModel $demoproductModel,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($responseMapper);
        $this->model = $demoproductModel;
    }

    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($request) {
            $id = $this->model->insert($request->toArray());
            if (!$id) {
                throw new \App\Exceptions\ValidationException(lang('Api.validationFailed'), $this->model->errors());
            }

            /** @var DemoproductEntity|null $entity */
            $entity = $this->model->find($id);
            if (! $entity instanceof DemoproductEntity) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }

            return $this->mapToResponse($entity);
        });
    }

    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($id, $request) {
            if (!$this->model->find($id)) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }

            $data = $request->toArray();
            if (empty($data)) {
                throw new BadRequestException(lang('Api.noFieldsToUpdate'));
            }

            $this->model->update($id, $data);

            /** @var DemoproductEntity|null $entity */
            $entity = $this->model->find($id);
            if (! $entity instanceof DemoproductEntity) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }

            return $this->mapToResponse($entity);
        });
    }
}
