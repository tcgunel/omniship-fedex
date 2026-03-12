<?php

declare(strict_types=1);

namespace Omniship\FedEx;

use Omniship\Common\AbstractHttpCarrier;
use Omniship\Common\Auth\OAuthTrait;
use Omniship\Common\Message\RequestInterface;
use Omniship\FedEx\Message\CancelShipmentRequest;
use Omniship\FedEx\Message\CreateShipmentRequest;
use Omniship\FedEx\Message\GetRatesRequest;
use Omniship\FedEx\Message\GetTrackingStatusRequest;

class Carrier extends AbstractHttpCarrier
{
    use OAuthTrait;

    private const BASE_URL_PRODUCTION = 'https://apis.fedex.com';
    private const BASE_URL_SANDBOX = 'https://apis-sandbox.fedex.com';

    private const OAUTH_TOKEN_URL_PRODUCTION = 'https://apis.fedex.com/oauth/token';
    private const OAUTH_TOKEN_URL_SANDBOX = 'https://apis-sandbox.fedex.com/oauth/token';

    public function getName(): string
    {
        return 'FedEx';
    }

    public function getShortName(): string
    {
        return 'FedEx';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'accountNumber' => '',
            'testMode' => false,
        ];
    }

    public function getAccountNumber(): string
    {
        return (string) $this->getParameter('accountNumber');
    }

    public function setAccountNumber(string $accountNumber): static
    {
        return $this->setParameter('accountNumber', $accountNumber);
    }

    public function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_SANDBOX : self::BASE_URL_PRODUCTION;
    }

    public function getOAuthTokenUrl(): string
    {
        return $this->getTestMode() ? self::OAUTH_TOKEN_URL_SANDBOX : self::OAUTH_TOKEN_URL_PRODUCTION;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getTrackingStatus(array $options = []): RequestInterface
    {
        return $this->createRequest(GetTrackingStatusRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function cancelShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CancelShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getRates(array $options = []): RequestInterface
    {
        return $this->createRequest(GetRatesRequest::class, $options);
    }
}
