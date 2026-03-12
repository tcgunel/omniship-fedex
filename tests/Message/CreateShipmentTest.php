<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Enum\LabelFormat;
use Omniship\Common\Package;
use Omniship\FedEx\Message\CreateShipmentRequest;
use Omniship\FedEx\Message\CreateShipmentResponse;

use function Omniship\FedEx\Tests\createMockHttpClient;
use function Omniship\FedEx\Tests\createMockRequestFactory;
use function Omniship\FedEx\Tests\createMockStreamFactory;

function createShipmentSuccessJson(): string
{
    return (string) file_get_contents(__DIR__ . '/../Mock/CreateShipmentSuccess.json');
}

function createShipmentErrorJson(): string
{
    return (string) file_get_contents(__DIR__ . '/../Mock/CreateShipmentError.json');
}

function createFedExShipmentRequest(string $responseJson): CreateShipmentRequest
{
    return new CreateShipmentRequest(
        createMockHttpClient($responseJson, 200),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('builds correct shipment data', function () {
    $request = createFedExShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(
            name: 'Ahmet Yilmaz',
            company: 'Sender Company',
            street1: 'Ataturk Cad. No:42',
            city: 'Istanbul',
            postalCode: '34710',
            country: 'TR',
            state: 'IST',
            phone: '+905551234567',
            email: 'sender@example.com',
        ),
        'shipTo' => new Address(
            name: 'John Smith',
            company: 'Receiver Inc',
            street1: '123 Main Street',
            street2: 'Suite 100',
            city: 'Memphis',
            state: 'TN',
            postalCode: '38118',
            country: 'US',
            phone: '+12125551234',
            email: 'receiver@example.com',
        ),
        'packages' => [
            new Package(weight: 2.5, length: 30, width: 20, height: 15, description: 'Electronics'),
        ],
        'serviceCode' => 'FEDEX_GROUND',
    ]);

    $data = $request->getData();

    expect($data['accountNumber']['value'])->toBe('123456789')
        ->and($data['requestedShipment']['shipper']['address']['city'])->toBe('Istanbul')
        ->and($data['requestedShipment']['shipper']['address']['countryCode'])->toBe('TR')
        ->and($data['requestedShipment']['shipper']['contact']['personName'])->toBe('Ahmet Yilmaz')
        ->and($data['requestedShipment']['shipper']['contact']['companyName'])->toBe('Sender Company')
        ->and($data['requestedShipment']['recipients'][0]['address']['city'])->toBe('Memphis')
        ->and($data['requestedShipment']['recipients'][0]['address']['stateOrProvinceCode'])->toBe('TN')
        ->and($data['requestedShipment']['recipients'][0]['address']['postalCode'])->toBe('38118')
        ->and($data['requestedShipment']['recipients'][0]['contact']['personName'])->toBe('John Smith')
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'])->toBe(2.5)
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['weight']['units'])->toBe('KG')
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['length'])->toBe(30)
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['width'])->toBe(20)
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['height'])->toBe(15)
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['units'])->toBe('CM')
        ->and($data['requestedShipment']['serviceType'])->toBe('FEDEX_GROUND');
});

it('handles multiple packages with quantity', function () {
    $request = createFedExShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Memphis', country: 'US', phone: '555'),
        'packages' => [
            new Package(weight: 1.0, length: 10, width: 10, height: 10, quantity: 2),
        ],
    ]);

    $data = $request->getData();

    expect($data['requestedShipment']['requestedPackageLineItems'])->toHaveCount(2)
        ->and($data['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'])->toBe(1.0)
        ->and($data['requestedShipment']['requestedPackageLineItems'][1]['weight']['value'])->toBe(1.0);
});

it('sends request and returns successful response', function () {
    $request = createFedExShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Memphis', country: 'US', phone: '555'),
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CreateShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getTrackingNumber())->toBe('794644790138');
});

it('sends request and returns error response', function () {
    $request = createFedExShipmentRequest(createShipmentErrorJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'shipFrom' => new Address(name: 'Sender', city: 'Istanbul', country: 'TR', phone: '555'),
        'shipTo' => new Address(name: 'Receiver', city: 'Memphis', country: 'US', phone: '555'),
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Invalid account number')
        ->and($response->getCode())->toBe('INVALID.INPUT.EXCEPTION');
});

it('throws exception when required fields are missing', function () {
    $request = createFedExShipmentRequest(createShipmentSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

// Response tests

function createFedExCreateResponseWith(array $data): CreateShipmentResponse
{
    $request = new CreateShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'clientId' => 'key',
        'clientSecret' => 'secret',
        'accountNumber' => '123',
    ]);

    return new CreateShipmentResponse($request, $data);
}

it('parses successful shipment creation response', function () {
    $data = json_decode(createShipmentSuccessJson(), true);
    $response = createFedExCreateResponseWith($data);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getTrackingNumber())->toBe('794644790138')
        ->and($response->getShipmentId())->toBe('794644790138')
        ->and($response->getBarcode())->toBe('794644790138')
        ->and($response->getTotalCharge())->toBe(15.75)
        ->and($response->getCurrency())->toBe('USD');
});

it('extracts label from shipment response', function () {
    $data = json_decode(createShipmentSuccessJson(), true);
    $response = createFedExCreateResponseWith($data);

    $label = $response->getLabel();

    expect($label)->not->toBeNull()
        ->and($label->trackingNumber)->toBe('794644790138')
        ->and($label->content)->toBe('JVBERi0xLjQKMSAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgMiAwIFI+PgplbmRvYmoK')
        ->and($label->format)->toBe(LabelFormat::PDF);
});

it('returns null label when no package documents', function () {
    $response = createFedExCreateResponseWith([
        'output' => [
            'transactionShipments' => [
                [
                    'masterTrackingNumber' => '794644790138',
                    'pieceResponses' => [],
                ],
            ],
        ],
    ]);

    expect($response->getLabel())->toBeNull();
});

it('parses error response', function () {
    $data = json_decode(createShipmentErrorJson(), true);
    $response = createFedExCreateResponseWith($data);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Invalid account number')
        ->and($response->getCode())->toBe('INVALID.INPUT.EXCEPTION')
        ->and($response->getTrackingNumber())->toBeNull()
        ->and($response->getLabel())->toBeNull();
});

it('returns null for charges on error', function () {
    $data = json_decode(createShipmentErrorJson(), true);
    $response = createFedExCreateResponseWith($data);

    expect($response->getTotalCharge())->toBeNull()
        ->and($response->getCurrency())->toBeNull();
});

it('provides access to raw data', function () {
    $data = ['output' => ['transactionShipments' => [['masterTrackingNumber' => '794644790138']]]];
    $response = createFedExCreateResponseWith($data);

    expect($response->getData())->toBe($data);
});
