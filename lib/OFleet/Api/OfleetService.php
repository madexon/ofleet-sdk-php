<?php

namespace OFleet\Api;

use CommerceGuys\Guzzle\Oauth2\GrantType\ClientCredentials;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\Post\PostFile;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

class OfleetService
{

    private $client;
    private $serializer;

    const API_VERSION = '/api/v1';

    public function __construct($baseUrl, $clientId, $clientSecret)
    {
        $this->baseUrl = $baseUrl;
        $oauth2Client = new Client(['base_url' => $this->baseUrl]);
        $config = [
            'token_url' => 'oauth/token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'auth_location' => 'header'
        ];

        $token = new ClientCredentials($oauth2Client, $config);
        $refreshToken = new RefreshToken($oauth2Client, $config);

        $oauth2 = new Oauth2Subscriber($token, $refreshToken);

        $this->client = new Client([
            'defaults' => [
                'auth' => 'oauth2',
                'subscribers' => [$oauth2],
            ],
        ]);
        $this->client->setDefaultOption('verify', false);
        $this->client->setDefaultOption('version', '1.0');

        $this->serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
    }

    public function listAgencies()
    {
        return $this->sendRequest('/agencies/list');
    }

    public function listLocations($filter)
    {
        return $this->sendRequest('/locations/list?filter=' . $filter);
    }

    public function listCountries($filter)
    {
        return $this->sendRequest('/countries/list?filter=' . $filter);
    }

    public function listVehicles($keepSameModel = true)
    {
        return $this->sendRequest("/vehicles/list?keepSameModel=" . ($keepSameModel ? 'true' : 'false'));
    }

    public function featuredVehicles()
    {
        return $this->sendRequest('/vehicles/featured');
    }

    public function idCardTypes()
    {
        return $this->sendRequest('/clients/id-card-types');
    }

    public function getVehicle($id)
    {
        return $this->sendRequest('/vehicles/vehicle/' . $id);
    }

    public function getVehicleWithPricing($id, $fromDate, $toDate)
    {
        return $this->sendRequest('/vehicles/vehicle/' . $id . "?fromDate=$fromDate&toDate=$toDate");
    }

    public function availableVehicles($fromDate, $toDate, $rentalContractId = null)
    {
        return $this->sendRequest('/vehicles/vehiclesByState/available?showOutsourcedAsAvailable=false&fromDate=' . $fromDate . '&toDate=' . $toDate . '&keepSameModelDuplicates=false' .
            ($rentalContractId ? '&rentalContractId=' . $rentalContractId : ''));
    }

    public function isVehicleAvailable($vehicleId, $fromDate, $toDate, $contractId = null)
    {
        return $this->sendRequest("/vehicles/vehicle/$vehicleId/available?showOutsourcedAsAvailable=false&fromDate=$fromDate&toDate=$toDate" . ($contractId ? "&rentalContractId=$contractId" : ''));
    }

    public function getVehicleDayRate($vehicleId, $fromDate, $toDate)
    {
        return $this->sendRequest("/vehicles/vehicle/$vehicleId/rate?fromDate=$fromDate&toDate=$toDate");
    }

    public function calculateNumberOfDaysForContract($contractId, $fromDate, $toDate)
    {
        return $this->sendRequest("/contracts/count-days/contract/$contractId?fromDate=$fromDate&toDate=$toDate");
    }

    public function listOptionsForVehicle($vehicleId)
    {
        return $this->sendRequest('/vehicles/vehicle/' . $vehicleId . '/rates/equipments');
    }

    public function listInsurancesForVehicle($vehicleId)
    {
        return $this->sendRequest('/vehicles/vehicle/' . $vehicleId . '/rates/insurances');
    }

    public function computeContractAmount($contract)
    {
        return $this->postData('/contracts/compute', $contract);
    }

    public function listBookingsForClient($id)
    {
        return $this->sendRequest('/contracts/client/' . $id . '?size=50');
    }

    public function getBookingsAttributeValues($clientId, $attributeName)
    {
        return $this->sendRequest("/contracts/attribute-values?clientId=$clientId&attributeName=$attributeName");
    }

    public function getBooking($bookingId)
    {
        return $this->sendRequest('/contracts/contract/' . $bookingId);
    }

    public function getBookingByContractNumber($contractNumber)
    {
        return $this->sendRequest("/contracts/contract/number/$contractNumber");
    }


    public function getTaxPercent()
    {
        return floatval($this->sendRequest('/settings/setting/taxPercent'));
    }

    public function printContract($bookingId)
    {
        return $this->sendPdfRequest("/print/contract/" . $bookingId);
    }

    public function printInvoice($bookingId)
    {
        return $this->sendPdfRequest("/print/invoices/" . $bookingId);
    }

    public function checkUser($username)
    {
        return $this->sendRequest('/auth/user?username=' . $username);
    }

    public function checkUserById($userId)
    {
        return $this->sendRequest('/auth/user?id=' . $userId);
    }

    public function saveBooking($booking)
    {
        return $this->postData('/contracts/contract/create', $booking);
    }

    public function updateBooking($booking)
    {
        return $this->postData('/contracts/contract/update', $booking);
    }

    public function validateReservation($bookingId)
    {
        return $this->sendRequest('/contracts/contract/' . $bookingId . '/validateReservation');
    }

    public function changeBookingVehicle($bookingId, $vehicleId, $fromDate, $toDate, $deliveryFee, $dropoffFee, $save)
    {
        return $this->sendRequest("/contracts/contract/$bookingId/modify?vehicleId=$vehicleId&fromDate=$fromDate&toDate=$toDate&deliveryFee=$deliveryFee&dropoffFee=$dropoffFee&save=$save");
    }

    public function createClient($client)
    {
        return $this->postData('/clients/client/create', $client);
    }

    public function updateClient($authenticatedUser)
    {
        return $this->postData('/clients/client/update', $authenticatedUser);
    }

    public function updateClientDrivingLicense($clientId, $path, $fileName)
    {
        return $this->uploadFile("/clients/client/" . $clientId . "/update/driving-license", "drivingLicense", $path, $fileName);
    }

    public function updateClientIdCard($clientId, $path, $fileName)
    {
        return $this->uploadFile("/clients/client/" . $clientId . "/update/id-card", "idCard", $path, $fileName);
    }

    public function changePassword($clientId, $passwordUpdate)
    {
        return $this->postData('/auth/change-password/' . $clientId, $passwordUpdate);
    }

    private function sendRequest($endpoint)
    {
        $response = $this->client->get($this->baseUrl . self::API_VERSION . $endpoint);
        return $response->json();
    }

    private function sendPdfRequest($endpoint)
    {
        $response = $this->client->get($this->baseUrl . self::API_VERSION . $endpoint);
        return $response;
    }

    private function postData($endpoint, $data)
    {
        $json = $this->serializer->normalize($data);

        $response = $this->client->post($this->baseUrl . self::API_VERSION . $endpoint, [
            'json' => $json
        ]);
        return $response->json();
    }

    private function uploadFile($endpoint, $name, $path, $fileName)
    {
        $response = $this->client->post($this->baseUrl . self::API_VERSION . $endpoint, [
            'body' => [
                "$name" => new PostFile($name, fopen($path, 'r'), $fileName)
            ]
        ]);
        return $response->json();
    }
}
