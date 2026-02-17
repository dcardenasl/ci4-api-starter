<?php

declare(strict_types=1);

namespace App\HTTP;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;

class ApiRequest extends IncomingRequest
{
    private ?int $authUserId = null;
    private ?string $authUserRole = null;
    private ?float $requestStartTime = null;
    /** @var array{limit:int,remaining:int,reset:int}|null */
    private ?array $rateLimitInfo = null;
    /** @var array{limit:int,remaining:int,reset:int}|null */
    private ?array $authRateLimitInfo = null;

    public function __construct(App $config, URI $uri, $body = 'php://input', ?UserAgent $userAgent = null)
    {
        parent::__construct($config, $uri, $body, $userAgent);
    }

    public function setAuthContext(?int $userId, ?string $role): void
    {
        $this->authUserId = $userId;
        $this->authUserRole = $role;
    }

    public function getAuthUserId(): ?int
    {
        return $this->authUserId;
    }

    public function getAuthUserRole(): ?string
    {
        return $this->authUserRole;
    }

    public function setRequestStartTime(float $value): void
    {
        $this->requestStartTime = $value;
    }

    public function getRequestStartTime(): ?float
    {
        return $this->requestStartTime;
    }

    /**
     * @param array{limit:int,remaining:int,reset:int} $info
     */
    public function setRateLimitInfo(array $info): void
    {
        $this->rateLimitInfo = $info;
    }

    /**
     * @return array{limit:int,remaining:int,reset:int}|null
     */
    public function getRateLimitInfo(): ?array
    {
        return $this->rateLimitInfo;
    }

    /**
     * @param array{limit:int,remaining:int,reset:int} $info
     */
    public function setAuthRateLimitInfo(array $info): void
    {
        $this->authRateLimitInfo = $info;
    }

    /**
     * @return array{limit:int,remaining:int,reset:int}|null
     */
    public function getAuthRateLimitInfo(): ?array
    {
        return $this->authRateLimitInfo;
    }
}
