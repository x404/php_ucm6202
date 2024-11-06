<?php

/*
 * Copyright by Oleksii Sedliarov (kronos2003@gmail.com)
 * 
 *  Establish connection with Grandstream UCM6202 by API and receive CDR list
 */


// Include the config file
require_once 'config.php';

class CdrApiClient {
    private string $apiUrl;
    private string $user;
    private string $password;

    /**
     * Constructor to initialize API URL, user, and password
     */
    public function __construct() {
        $this->apiUrl = API_URL . ':' . (defined('API_PORT') ? API_PORT : '8089');
        $this->user = API_USER;
        $this->password = API_PASSWORD;
    }

    /**
     * Sends the challenge request to obtain a challenge key
     * 
     * @return array|string The decoded response or '403' if forbidden
     */
    public function sendChallengeAction() {
        $body = [
            "request" => [
                "action" => "challenge",
                "user" => $this->user,
                "version" => "1.2"
            ]
        ];

        $response = $this->makeRequest("/api/v1", $body);

        // Check for forbidden access in the response
        if (str_contains($response, '403 Forbidden')) {
            return '403';
        }

        return json_decode($response, true);
    }

    /**
     * Logs in to the API using the challenge key and returns the session cookie
     *
     * @param string $challengeKey The challenge key received from sendChallengeAction
     * @return array The decoded login response including the cookie
     */
    private function setUcmConnection(string $challengeKey) {
        $md5Hash = md5($challengeKey . $this->password);

        $body = [
            "request" => [
                "action" => "login",
                "token" => $md5Hash,
                "url" => $this->apiUrl,
                "user" => $this->user
            ]
        ];

        $response = $this->makeRequest("/api/v1", $body);
        return json_decode($response, true);
    }

    /**
     * Retrieves the CDR list from the API using the session cookie
     *
     * @param string $cookieKey The session cookie from setUcmConnection
     * @return array|null The decoded CDR list in JSON format or null on failure
     */
    private function getCdrList(string $cookieKey) {
        $body = [
            "request" => [
                "action" => "cdrapi",
                "cookie" => $cookieKey,
                "format" => "json",
                "startTime" => date("Y-m-d", strtotime("-30 days")),
                "endTime" => date("Y-m-d\TH:i")
            ]
        ];

        $response = $this->makeRequest("/api/v1", $body);
        return json_decode($response, true);
    }

    /**
     * Makes a cURL request to the API endpoint with the provided body
     *
     * @param string $endpoint The endpoint to send the request to
     * @param array $body The request payload as an associative array
     * @return string The raw response from the API
     */
    private function makeRequest(string $endpoint, array $body): string {
        $headers = [
            "Content-Type: application/json;charset=UTF-8"
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);

        // Handle cURL errors if any
        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }

    public function fetchCdrList() {
        $response = $this->sendChallengeAction();
    
        if ($response === '403') {
            return [
                "status" => "403",
                "statusText" => "Forbidden",
                "name" => "HttpErrorResponse"
            ];
        }
    
        $challengeKey = $response['response']['challenge'];
        $loginResponse = $this->setUcmConnection($challengeKey);
        $cookie = $loginResponse['response']['cookie'];
    
        return $this->getCdrList($cookie);
    }
}




// Initialize API client
$client = new CdrApiClient();

// Send challenge request
$response = $client->sendChallengeAction();

if ($response !== '403') {
    $cdrList = $client->fetchCdrList();
    echo json_encode($cdrList);
} else {
    // Error response if access is forbidden
    $error = json_encode([
        "status" => "403",
        "statusText" => "Forbidden",
        "name" => "HttpErrorResponse"
    ]);
    echo $error;
}

?>
