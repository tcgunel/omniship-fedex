<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Package;
use Omniship\Common\Rate;
use Omniship\FedEx\Message\GetRatesRequest;
use Omniship\FedEx\Message\GetRatesResponse;

use function Omniship\FedEx\Tests\createMockHttpClient;
use function Omniship\FedEx\Tests\createMockRequestFactory;
use function Omniship\FedEx\Tests\createMockStreamFactory;

function getRatesSuccessJson(): string
{
    return (string) file_get_contents(__DIR__ . '/../Mock/GetRatesSuccess.json');
}

function createFedExRatesRequest(string $responseJson): GetRatesRequest
{
    return new GetRatesRequest(
        createMockHttpClient($responseJson, 200),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function createFedExRatesResponseWith(array $data): GetRatesResponse
{
    $request = new GetRatesRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'clientId' => 'key',
        'clientSecret' => 'secret',
        'accountNumber' => '123',
        'shipFrom' => new Address(city: 'Indianapolis', country: 'US', postalCode: '46201', state: 'IN'),
        'shipTo' => new Address(city: 'Memphis', country: 'US', postalCode: '38118', state: 'TN'),
    ]);

    return new GetRatesResponse($request, $data);
}

it('builds correct rates data', function () {
    $request = createFedExRatesRequest(getRatesSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(
            name: 'Sender',
            city: 'Indianapolis',
            state: 'IN',
            postalCode: '46201',
            country: 'US',
            phone: '555',
        ),
        'shipTo' => new Address(
            name: 'Receiver',
            city: 'Memphis',
            state: 'TN',
            postalCode: '38118',
            country: 'US',
            phone: '555',
        ),
        'packages' => [
            new Package(weight: 2.5, length: 30, width: 20, height: 15),
        ],
    ]);

    $data = $request->getData();

    expect($data['accountNumber']['value'])->toBe('123456789')
        ->and($data['requestedShipment']['shipper']['address']['city'])->toBe('Indianapolis')
        ->and($data['requestedShipment']['shipper']['address']['countryCode'])->toBe('US')
        ->and($data['requestedShipment']['recipient']['address']['city'])->toBe('Memphis')
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'])->toBe(2.5)
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['weight']['units'])->toBe('KG');
});

it('sends rates request and returns successful response', function () {
    $request = createFedExRatesRequest(getRatesSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(name: 'Sender', city: 'Indianapolis', country: 'US', postalCode: '46201', state: 'IN', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Memphis', country: 'US', postalCode: '38118', state: 'TN', phone: '555'),
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetRatesResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});

it('throws exception when required fields are missing', function () {
    $request = createFedExRatesRequest(getRatesSuccessJson());
    $request->initialize([
        'clientId' => 'key',
        'clientSecret' => 'secret',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

// Response tests

it('parses successful rates response', function () {
    $data = json_decode(getRatesSuccessJson(), true);
    $response = createFedExRatesResponseWith($data);

    expect($response->isSuccessful())->toBeTrue();

    $rates = $response->getRates();

    expect($rates)->toHaveCount(3);

    // FedEx Ground
    expect($rates[0])->toBeInstanceOf(Rate::class)
        ->and($rates[0]->carrier)->toBe('FedEx')
        ->and($rates[0]->serviceCode)->toBe('FEDEX_GROUND')
        ->and($rates[0]->serviceName)->toBe('FedEx Ground')
        ->and($rates[0]->totalPrice)->toBe(15.75)
        ->and($rates[0]->currency)->toBe('USD')
        ->and($rates[0]->transitDays)->toBe(3)
        ->and($rates[0]->estimatedDelivery)->not->toBeNull();

    // FedEx Express Saver
    expect($rates[1]->serviceCode)->toBe('FEDEX_EXPRESS_SAVER')
        ->and($rates[1]->serviceName)->toBe('FedEx Express Saver')
        ->and($rates[1]->totalPrice)->toBe(25.50)
        ->and($rates[1]->transitDays)->toBe(1);
});

it('handles empty rate reply details', function () {
    $response = createFedExRatesResponseWith([
        'output' => [
            'rateReplyDetails' => [],
        ],
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getRates())->toBe([]);
});

it('handles error response', function () {
    $response = createFedExRatesResponseWith([
        'errors' => [
            [
                'code' => 'INVALID.INPUT.EXCEPTION',
                'message' => 'Invalid postal code',
            ],
        ],
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Invalid postal code')
        ->and($response->getCode())->toBe('INVALID.INPUT.EXCEPTION')
        ->and($response->getRates())->toBe([]);
});

it('handles missing commit data', function () {
    $response = createFedExRatesResponseWith([
        'output' => [
            'rateReplyDetails' => [
                [
                    'serviceType' => 'FEDEX_GROUND',
                    'serviceName' => 'FedEx Ground',
                    'ratedShipmentDetails' => [
                        [
                            'rateType' => 'ACCOUNT',
                            'totalNetCharge' => 15.75,
                            'currency' => 'USD',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $rates = $response->getRates();

    expect($rates[0]->transitDays)->toBeNull()
        ->and($rates[0]->estimatedDelivery)->toBeNull();
});

it('provides access to raw data', function () {
    $data = ['output' => ['rateReplyDetails' => []]];
    $response = createFedExRatesResponseWith($data);

    expect($response->getData())->toBe($data);
});
