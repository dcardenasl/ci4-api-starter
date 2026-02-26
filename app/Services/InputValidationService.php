<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Interfaces\InputValidationServiceInterface;
use App\Validations\ApiKeyValidation;
use App\Validations\AuditValidation;
use App\Validations\AuthValidation;
use App\Validations\BaseValidation;
use App\Validations\FileValidation;
use App\Validations\TokenValidation;
use App\Validations\UserValidation;
use CodeIgniter\Validation\ValidationInterface;

/**
 * Input Validation Service
 *
 * Centralized service for input validation across the application.
 * Uses domain-specific validation classes to retrieve rules and messages.
 */
class InputValidationService implements InputValidationServiceInterface
{
    /**
     * @var array<string, BaseValidation> Registered validation classes
     */
    protected array $validators = [];

    /**
     * @var ValidationInterface CodeIgniter validation instance
     */
    protected ValidationInterface $validation;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->validation = \Config\Services::validation();
        $this->registerValidators();
    }

    /**
     * Register all domain validation classes
     */
    protected function registerValidators(): void
    {
        $this->validators = [
            'auth'    => new AuthValidation(),
            'user'    => new UserValidation(),
            'file'    => new FileValidation(),
            'token'   => new TokenValidation(),
            'audit'   => new AuditValidation(),
            'api_key' => new ApiKeyValidation(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $data, array $rules, array $messages = []): array
    {
        $this->validation->reset();

        // Convert flat messages format (field.rule => message)
        // to nested format (field => [rule => message])
        $nestedMessages = $this->convertMessagesToNestedFormat($messages);

        $this->validation->setRules($rules, $nestedMessages);

        if (!$this->validation->run($data)) {
            return $this->validation->getErrors();
        }

        return [];
    }

    /**
     * Convert flat message format to nested format for CI4 validation
     *
     * Input:  ['field.rule' => 'message', 'field.rule2' => 'message2']
     * Output: ['field' => ['rule' => 'message', 'rule2' => 'message2']]
     *
     * @param array $messages Flat format messages
     * @return array Nested format messages
     */
    protected function convertMessagesToNestedFormat(array $messages): array
    {
        $nested = [];

        foreach ($messages as $key => $message) {
            // Check if key contains a dot (field.rule format)
            if (strpos($key, '.') !== false) {
                [$field, $rule] = explode('.', $key, 2);
                $nested[$field][$rule] = $message;
            } else {
                // Already in nested format or single key - keep as is
                $nested[$key] = $message;
            }
        }

        return $nested;
    }

    /**
     * {@inheritDoc}
     */
    public function getRules(string $domain, string $action): array
    {
        $validator = $this->getValidator($domain);

        if ($validator === null) {
            return [];
        }

        return $validator->getRules($action);
    }

    /**
     * {@inheritDoc}
     */
    public function getMessages(string $domain, string $action): array
    {
        $validator = $this->getValidator($domain);

        if ($validator === null) {
            return [];
        }

        return $validator->getMessages($action);
    }

    /**
     * {@inheritDoc}
     */
    public function validateOrFail(array $data, string $domain, string $action): void
    {
        $validator = $this->getValidator($domain);
        if ($validator === null) {
            throw new \InvalidArgumentException(
                lang('InputValidation.common.unknownValidationDomain', [$domain])
            );
        }

        $rules = $validator->getRules($action);

        if (empty($rules)) {
            throw new \InvalidArgumentException(
                lang('InputValidation.common.unknownValidationAction', [$action, $domain])
            );
        }

        $messages = $validator->getMessages($action);
        $errors = $this->validate($data, $rules, $messages);

        if (!empty($errors)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $errors
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $domain, string $action): array
    {
        return [
            'rules'    => $this->getRules($domain, $action),
            'messages' => $this->getMessages($domain, $action),
        ];
    }

    /**
     * Get validator instance for a domain
     *
     * @param string $domain Domain name
     * @return BaseValidation|null
     */
    protected function getValidator(string $domain): ?BaseValidation
    {
        return $this->validators[$domain] ?? null;
    }

    /**
     * Register a custom validator
     *
     * @param string         $domain    Domain name
     * @param BaseValidation $validator Validator instance
     * @return self
     */
    public function registerValidator(string $domain, BaseValidation $validator): self
    {
        $this->validators[$domain] = $validator;
        return $this;
    }

    /**
     * Check if a domain is registered
     *
     * @param string $domain Domain name
     * @return bool
     */
    public function hasDomain(string $domain): bool
    {
        return isset($this->validators[$domain]);
    }

    /**
     * Get list of registered domains
     *
     * @return array<string>
     */
    public function getDomains(): array
    {
        return array_keys($this->validators);
    }
}
