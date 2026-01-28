<?php

namespace App\Services;

use App\Models\UserModel;
use App\Entities\UserEntity;

class UserService
{
    protected UserModel $userModel;

    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * Obtener todos los usuarios
     */
    public function index(array $data): array
    {
        $users = $this->userModel->findAll();

        return [
            'status' => 'success',
            'data' => array_map(fn($user) => $user->toArray(), $users),
        ];
    }

    /**
     * Obtener un usuario por ID
     */
    public function show(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => 'El ID del usuario es obligatorio']];
        }

        $user = $this->userModel->find($data['id']);

        if (!$user) {
            throw new \InvalidArgumentException('Usuario no encontrado');
        }

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(array $data): array
    {
        // Validaciones de reglas de negocio (más allá de integridad de datos)
        // Ejemplo: verificar dominio de email permitido, consultar API externa, etc.
        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            return ['errors' => $businessErrors];
        }

        // Model maneja validación y timestamps automáticamente
        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ]);

        if (!$userId) {
            // Obtener errores de validación del Model
            return ['errors' => $this->userModel->errors()];
        }

        $user = $this->userModel->find($userId);

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => 'El ID del usuario es obligatorio']];
        }

        $id = (int) $data['id'];

        // Verificar si el usuario existe
        if (!$this->userModel->find($id)) {
            throw new \InvalidArgumentException('Usuario no encontrado');
        }

        // Regla de negocio: al menos un campo requerido
        if (empty($data['email']) && empty($data['username'])) {
            return ['errors' => ['fields' => 'Se requiere al menos un campo (email o username)']];
        }

        // Preparar datos de actualización
        $updateData = array_filter([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ], fn($value) => $value !== null);

        // Model maneja validación y updated_at automáticamente
        $success = $this->userModel->update($id, $updateData);

        if (!$success) {
            return ['errors' => $this->userModel->errors()];
        }

        $user = $this->userModel->find($id);

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Eliminar un usuario (soft delete)
     */
    public function destroy(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => 'El ID del usuario es obligatorio']];
        }

        $id = (int) $data['id'];

        // Verificar si el usuario existe
        if (!$this->userModel->find($id)) {
            throw new \InvalidArgumentException('Usuario no encontrado');
        }

        // Realiza soft delete (gracias a useSoftDeletes = true)
        if (!$this->userModel->delete($id)) {
            throw new \RuntimeException('Error al eliminar el usuario');
        }

        return [
            'status' => 'success',
            'message' => 'Usuario eliminado correctamente',
        ];
    }

    /**
     * Validaciones de reglas de negocio específicas
     * Separadas de las reglas de integridad del Model
     */
    protected function validateBusinessRules(array $data): array
    {
        $errors = [];

        // Ejemplo: validar dominio de email permitido
        // if (isset($data['email']) && !$this->isAllowedEmailDomain($data['email'])) {
        //     $errors['email'] = 'Dominio de email no permitido';
        // }

        return $errors;
    }
}
