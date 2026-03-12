<?php

declare(strict_types=1);

use Omniship\FedEx\Carrier;
use Omniship\FedEx\Message\CancelShipmentRequest;
use Omniship\FedEx\Message\CreateShipmentRequest;
use Omniship\FedEx\Message\GetRatesRequest;
use Omniship\FedEx\Message\GetTrackingStatusRequest;

use function Omniship\FedEx\Tests\createMockHttpClient;
use function Omniship\FedEx\Tests\createMockRequestFactory;
use function Omniship\FedEx\Tests\createMockStreamFactory;

function createCarrier(): Carrier
{
    $carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );

    $carrier->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'testMode' => true,
    ]);

    return $carrier;
}

it('returns correct name and short name', function () {
    $carrier = createCarrier();

    expect($carrier->getName())->toBe('FedEx')
        ->and($carrier->getShortName())->toBe('FedEx');
});

it('initializes with default parameters', function () {
    $carrier = createCarrier();

    expect($carrier->getClientId())->toBe('testClientId')
        ->and($carrier->getClientSecret())->toBe('testClientSecret')
        ->and($carrier->getAccountNumber())->toBe('123456789')
        ->and($carrier->getTestMode())->toBeTrue();
});

it('returns sandbox base URL when test mode is on', function () {
    $carrier = createCarrier();

    expect($carrier->getBaseUrl())->toBe('https://apis-sandbox.fedex.com');
});

it('returns production base URL when test mode is off', function () {
    $carrier = createCarrier();
    $carrier->setTestMode(false);

    expect($carrier->getBaseUrl())->toBe('https://apis.fedex.com');
});

it('returns sandbox OAuth token URL when test mode is on', function () {
    $carrier = createCarrier();

    expect($carrier->getOAuthTokenUrl())->toBe('https://apis-sandbox.fedex.com/oauth/token');
});

it('returns production OAuth token URL when test mode is off', function () {
    $carrier = createCarrier();
    $carrier->setTestMode(false);

    expect($carrier->getOAuthTokenUrl())->toBe('https://apis.fedex.com/oauth/token');
});

it('creates shipment request', function () {
    $carrier = createCarrier();
    $request = $carrier->createShipment();

    expect($request)->toBeInstanceOf(CreateShipmentRequest::class);
});

it('creates tracking request', function () {
    $carrier = createCarrier();
    $request = $carrier->getTrackingStatus(['trackingNumber' => '794644790138']);

    expect($request)->toBeInstanceOf(GetTrackingStatusRequest::class);
});

it('creates cancel request', function () {
    $carrier = createCarrier();
    $request = $carrier->cancelShipment([
        'trackingNumber' => '794644790138',
    ]);

    expect($request)->toBeInstanceOf(CancelShipmentRequest::class);
});

it('creates rates request', function () {
    $carrier = createCarrier();
    $request = $carrier->getRates();

    expect($request)->toBeInstanceOf(GetRatesRequest::class);
});
