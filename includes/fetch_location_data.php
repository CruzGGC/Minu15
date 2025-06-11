<?php

class LocationFetcher {
    private $baseUrl = "http://json.localhost:8080";

    private function callApi($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        // Debug log the URL
        error_log("Calling API URL: " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']); // Request JSON response

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("cURL error: " . $curlError . " for URL: " . $url);
            return ['success' => false, 'message' => 'cURL Error: ' . $curlError];
        }

        // Debug log the response
        error_log("API Response: " . substr($response, 0, 200) . "...");
        error_log("HTTP Code: " . $httpCode);

        $decodedResponse = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
        }

        if ($httpCode === 200 && $jsonError === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $decodedResponse];
        } else {
            // Handle API errors or invalid JSON response
            $errorMessage = 'API call failed';
            if ($jsonError !== JSON_ERROR_NONE) {
                $errorMessage .= ': Invalid JSON response - ' . json_last_error_msg();
            } else if (isset($decodedResponse['message'])) {
                $errorMessage .= ': ' . $decodedResponse['message'];
            } else {
                $errorMessage .= ' - HTTP Code: ' . $httpCode;
            }
            error_log("API call failed: " . $url . " - HTTP Code: " . $httpCode . " - Response: " . ($response === false ? 'false' : $response));
            return ['success' => false, 'message' => $errorMessage, 'http_code' => $httpCode, 'response' => $response];
        }
    }

    public function fetchByGps($latitude, $longitude) {
        error_log("fetchByGps called with lat: $latitude, lng: $longitude");
        $endpoint = "/gps/{$latitude},{$longitude}/base/detalhes";
        $params = ['json' => 'true']; // Removed ext-apis parameter as it might be causing issues
        $result = $this->callApi($endpoint, $params);
        error_log("fetchByGps result: " . print_r($result, true));
        return $result;
    }

    public function fetchByFreguesiaAndMunicipio($freguesia, $municipio) {
        $endpoint = "/freguesia/" . urlencode($freguesia);
        $params = ['municipio' => $municipio, 'json' => 'true'];
        return $this->callApi($endpoint, $params);
    }

    public function fetchByMunicipio($municipio) {
        $endpoint = "/municipio/" . urlencode($municipio);
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }

    public function fetchByDistrito($distrito) {
        $endpoint = "/distrito/" . urlencode($distrito);
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }

    public function fetchAllDistritos() {
        $endpoint = "/distritos/base";
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }

    public function fetchMunicipiosByDistrito($distrito) {
        $endpoint = "/distritos/" . urlencode($distrito) . "/municipios";
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }

    public function fetchFreguesiasByMunicipio($municipio) {
        $endpoint = "/municipio/" . urlencode($municipio) . "/freguesias";
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }
}

?> 