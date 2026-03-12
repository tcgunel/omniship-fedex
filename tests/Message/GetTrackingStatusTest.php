<?php

declare(strict_types=1);

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\FedEx\Message\GetTrackingStatusRequest;
use Omniship\FedEx\Message\GetTrackingStatusResponse;

use function Omniship\FedEx\Tests\createMockHttpClient;
use function Omniship\FedEx\Tests\createMockRequestFactory;
use function Omniship\FedEx\Tests\createMockStreamFactory;

function getTrackingSuccessJson(): string
{
    return (string) file_get_contents(__DIR__ . '/../Mock/GetTrackingStatusSuccess.json');
}

function createFedExTrackingRequest(string $responseJson): GetTrackingStatusRequest
{
    return new GetTrackingStatusRequest(
        createMockHttpClient($responseJson, 200),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function createFedExTrackingResponseWith(array $data): GetTrackingStatusResponse
{
    $request = new GetTrackingStatusRequest(
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

    return new GetTrackingStatusResponse($request, $data);
}

it('builds correct tracking data', function () {
    $request = createFedExTrackingRequest(getTrackingSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'trackingNumber' => '794644790138',
    ]);

    $data = $request->getData();

    expect($data['trackingInfo'][0]['trackingNumberInfo']['trackingNumber'])->toBe('794644790138');
});

it('sends tracking request and returns successful response', function () {
    $request = createFedExTrackingRequest(getTrackingSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
        'trackingNumber' => '794644790138',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetTrackingStatusResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});

it('throws exception when tracking number is missing', function () {
    $request = createFedExTrackingRequest(getTrackingSuccessJson());
    $request->initialize([
        'clientId' => 'testClientId',
        'clientSecret' => 'testClientSecret',
        'accountNumber' => '123456789',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

// Response tests

it('parses successful tracking response with delivered status', function () {
    $data = json_decode(getTrackingSuccessJson(), true);
    $response = createFedExTrackingResponseWith($data);

    expect($response->isSuccessful())->toBeTrue();

    $trackingInfo = $response->getTrackingInfo();

    expect($trackingInfo->trackingNumber)->toBe('794644790138')
        ->and($trackingInfo->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($trackingInfo->carrier)->toBe('FedEx')
        ->and($trackingInfo->signedBy)->toBe('John Smith')
        ->and($trackingInfo->events)->not->toBeEmpty();
});

it('parses tracking events in order', function () {
    $data = json_decode(getTrackingSuccessJson(), true);
    $response = createFedExTrackingResponseWith($data);

    $events = $response->getTrackingInfo()->events;

    expect($events)->toHaveCount(5)
        ->and($events[0]->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($events[0]->description)->toBe('Delivered')
        ->and($events[1]->status)->toBe(ShipmentStatus::OUT_FOR_DELIVERY)
        ->and($events[4]->status)->toBe(ShipmentStatus::PICKED_UP);
});

it('parses tracking event locations', function () {
    $data = json_decode(getTrackingSuccessJson(), true);
    $response = createFedExTrackingResponseWith($data);

    $events = $response->getTrackingInfo()->events;

    expect($events[0]->city)->toBe('MEMPHIS')
        ->and($events[0]->country)->toBe('US')
        ->and($events[0]->location)->toBe('MEMPHIS, TN');
});

it('maps FedEx status codes correctly', function () {
    $data = json_decode(getTrackingSuccessJson(), true);
    $response = createFedExTrackingResponseWith($data);

    $events = $response->getTrackingInfo()->events;

    // DL = Delivered
    expect($events[0]->status)->toBe(ShipmentStatus::DELIVERED);
    // OD = Out for Delivery
    expect($events[1]->status)->toBe(ShipmentStatus::OUT_FOR_DELIVERY);
    // IT = In Transit
    expect($events[2]->status)->toBe(ShipmentStatus::IN_TRANSIT);
    // PU = Picked Up
    expect($events[4]->status)->toBe(ShipmentStatus::PICKED_UP);
});

it('returns unknown status when no tracking results', function () {
    $response = createFedExTrackingResponseWith([]);

    $trackingInfo = $response->getTrackingInfo();

    expect($trackingInfo->status)->toBe(ShipmentStatus::UNKNOWN)
        ->and($trackingInfo->events)->toBe([]);
});

it('handles error response', function () {
    $response = createFedExTrackingResponseWith([
        'errors' => [
            [
                'code' => 'TRACKING.TRACKINGNUMBER.NOTFOUND',
                'message' => 'Tracking number not found',
            ],
        ],
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Tracking number not found')
        ->and($response->getCode())->toBe('TRACKING.TRACKINGNUMBER.NOTFOUND');
});

it('extracts service name from tracking response', function () {
    $data = json_decode(getTrackingSuccessJson(), true);
    $response = createFedExTrackingResponseWith($data);

    $trackingInfo = $response->getTrackingInfo();

    expect($trackingInfo->serviceName)->toBe('FedEx Ground');
});
