<?php

declare(strict_types=1);

use Omniship\FedEx\Message\CancelShipmentRequest;
use Omniship\FedEx\Message\CancelShipmentResponse;

use function Omniship\FedEx\Tests\createMockHttpClient;
use function Omniship\FedEx\Tests\createMockRequestFactory;
use function Omniship\FedEx\Tests\createMockStreamFactory;

function getCancelSuccessJson(): string
{
    return (string) file_get_contents(__DIR__ . '/../Mock/CancelShipmentSuccess.json');
}

function createFedExCancelRequest(string $responseJson): CancelShipmentRequest
{
    return new CancelShipmentRequest(
        createMockHttpClient($responseJson, 200),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function createFedExCancelResponseWith(array $data): CancelShipmentResponse
{
    $request = new CancelShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'clientId' => 'key',
        'clientSecret' => 'secret',
        'accountNumber' => '123',
        'trackingNumber' => '794644790138',
    ]);

    return new CancelShipmentResponse($request, $data);
}

it('builds correct cancel data', function () {
    $request = createFedExCancelRequest(getCancelSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'trackingNumber' => '794644790138',
    ]);

    $data = $request->getData();

    expect($data['accountNumber']['value'])->toBe('123456789')
        ->and($data['trackingNumber'])->toBe('794644790138');
});

it('sends cancel request and returns successful response', function () {
    $request = createFedExCancelRequest(getCancelSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'trackingNumber' => '794644790138',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CancelShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();
});

it('throws exception when tracking number is missing', function () {
    $request = createFedExCancelRequest(getCancelSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

// Response tests

it('parses successful cancel response', function () {
    $data = json_decode(getCancelSuccessJson(), true);
    $response = createFedExCancelResponseWith($data);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();
});

it('handles error cancel response', function () {
    $response = createFedExCancelResponseWith([
        'errors' => [
            [
                'code' => 'SHIPMENT.ALREADY.CANCELLED',
                'message' => 'Shipment has already been cancelled',
            ],
        ],
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getMessage())->toBe('Shipment has already been cancelled')
        ->and($response->getCode())->toBe('SHIPMENT.ALREADY.CANCELLED');
});

it('provides access to raw data', function () {
    $data = json_decode(getCancelSuccessJson(), true);
    $response = createFedExCancelResponseWith($data);

    expect($response->getData())->toBe($data);
});
