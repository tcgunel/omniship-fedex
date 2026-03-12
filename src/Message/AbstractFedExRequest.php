<?php

declare(strict_types=1);

namespace Omniship\FedEx\Message;

use Omniship\Common\Message\AbstractHttpRequest;

abstract class AbstractFedExRequest extends AbstractHttpRequest
{
    private const BASE_URL_PRODUCTION = 'https://apis.fedex.com';
    private const BASE_URL_SANDBOX = 'https://apis-sandbox.fedex.com';

    public function getClientId(): ?string
    {
        return $this->getParameter('clientId');
    }

    public function setClientId(string $clientId): static
    {
        return $this->setParameter('clientId', $clientId);
    }

    public function getClientSecret(): ?string
    {
        return $this->getParameter('clientSecret');
    }

    public function setClientSecret(string $clientSecret): static
    {
        return $this->setParameter('clientSecret', $clientSecret);
    }

    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    public function setAccountNumber(string $accountNumber): static
    {
        return $this->setParameter('accountNumber', $accountNumber);
    }

    protected function getBaseUrl(): string
    {
        return $this->getTestMode()
            ? self::BASE_URL_SANDBOX
            : self::BASE_URL_PRODUCTION;
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-locale' => 'en_US',
        ];
    }

    /**
     * Send a JSON request to the FedEx API.
     *
     * Note: In production usage, the Authorization header with the OAuth Bearer token
     * would be added by the carrier's middleware. For unit tests, we test the request
     * building and response parsing without actual OAuth flow.
     *
     * @param array<string, mixed> $jsonData
     * @return array<string, mixed>
     */
    protected function sendJsonRequest(string $method, string $url, array $jsonData = []): array
    {
        $headers = $this->getDefaultHeaders();
        $body = $jsonData !== [] ? json_encode($jsonData, JSON_THROW_ON_ERROR) : null;

        $response = $this->sendHttpRequest(
            method: $method,
            url: $url,
            headers: $headers,
            body: $body,
        );

        $responseBody = (string) $response->getBody();

        if ($responseBody === '') {
            return [];
        }

        /** @var array<string, mixed> */
        return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    }
}
