<?php

namespace App\Repositories;

use App\Entities\UserEntity;
use CodeIgniter\Database\BaseConnection;

class UserRepository
{
    protected BaseConnection $db;
    protected string $table = 'users';

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $builder = $this->db->table($this->table);
        $query = $builder->get();

        return array_map(function ($row) {
            return new UserEntity((array) $row);
        }, $query->getResult());
    }

    public function findById(int $id): ?UserEntity
    {
        $builder = $this->db->table($this->table);
        $query = $builder->where('id', $id)->get();

        $row = $query->getRow();
        return $row ? new UserEntity((array) $row) : null;
    }

    public function create(array $data): ?UserEntity
    {
        $builder = $this->db->table($this->table);

        if ($builder->insert($data)) {
            $id = $this->db->insertID();
            return $this->findById($id);
        }

        return null;
    }

    public function update(int $id, array $data): ?UserEntity
    {
        $builder = $this->db->table($this->table);

        if ($builder->where('id', $id)->update($data)) {
            return $this->findById($id);
        }

        return null;
    }

    public function delete(int $id): bool
    {
        $builder = $this->db->table($this->table);
        return $builder->where('id', $id)->delete();
    }
}
