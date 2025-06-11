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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout to 30 seconds

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("cURL error: " . $curlError . " for URL: " . $url);
            return ['success' => false, 'message' => 'cURL Error: ' . $curlError];
        }

        // Debug log the response
        error_log("API Response: " . substr($response, 0, 200) . (strlen($response) > 200 ? "..." : ""));
        error_log("HTTP Code: " . $httpCode);

        $decodedResponse = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
        }

        if ($httpCode === 200 && $jsonError === JSON_ERROR_NONE) {
            // Ensure consistent response format
            if (is_array($decodedResponse)) {
                return ['success' => true, 'data' => $decodedResponse];
            } else {
                return ['success' => true, 'data' => ['response' => $decodedResponse]];
            }
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
        
        // Buscar localização completa pelo GPS (já deve incluir censos2021 e censos2011)
        $endpoint = "/gps/{$latitude},{$longitude}/base/detalhes";
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos do GPS - censos2021 presente: " . 
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") . 
                     ", censos2011 presente: " . 
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Extrair informações da freguesia e município
            $freguesia = null;
            $municipio = null;
            
            if (isset($result['data']['freguesia'])) {
                $freguesia = $result['data']['freguesia'];
            } else if (isset($result['data']['detalhesFreguesia']) && isset($result['data']['detalhesFreguesia']['nome'])) {
                $freguesia = $result['data']['detalhesFreguesia']['nome'];
                // Adicionar ao resultado principal para consistência
                $result['data']['freguesia'] = $freguesia;
            }
            
            if (isset($result['data']['municipio'])) {
                $municipio = $result['data']['municipio'];
            } else if (isset($result['data']['concelho'])) {
                $municipio = $result['data']['concelho'];
            } else if (isset($result['data']['detalhesMunicipio']) && isset($result['data']['detalhesMunicipio']['nome'])) {
                $municipio = $result['data']['detalhesMunicipio']['nome'];
                // Adicionar ao resultado principal para consistência
                $result['data']['municipio'] = $municipio;
            }
            
            // Buscar geometria (GeoJSON) se não estiver presente
            if (!isset($result['data']['geojson']) && $freguesia && $municipio) {
                error_log("Buscando geometria para a freguesia: " . $freguesia);
                
                $geometryEndpoint = "/freguesia/" . urlencode($freguesia) . "/geometria";
                $geometryParams = ['municipio' => $municipio, 'json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha']) && $freguesia && $municipio) {
                error_log("Buscando dados de área para a freguesia: " . $freguesia);
                
                $areaEndpoint = "/freguesia/" . urlencode($freguesia) . "/area";
                $areaParams = ['municipio' => $municipio, 'json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByGps result: " . print_r($result, true));
        return $result;
    }

    public function fetchByFreguesiaAndMunicipio($freguesia, $municipio) {
        error_log("fetchByFreguesiaAndMunicipio called with freguesia: " . $freguesia . ", municipio: " . $municipio);
        
        // Primeiro, busca os dados completos da freguesia (já deve incluir censos2021 e censos2011)
        $endpoint = "/municipio/" . urlencode($municipio) . "/freguesia/" . urlencode($freguesia);
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Tenta a outra variante da URL se a primeira falhar
        if (!$result['success']) {
            error_log("Primeira tentativa falhou, tentando endpoint alternativo");
            $endpoint = "/freguesia/" . urlencode($freguesia);
            $params = ['municipio' => $municipio, 'json' => 'true'];
            $result = $this->callApi($endpoint, $params);
        }
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos da freguesia - censos2021 presente: " . 
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") . 
                     ", censos2011 presente: " . 
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Buscar geometria (GeoJSON) se não estiver presente
            if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para a freguesia: " . $freguesia);
                
                $geometryEndpoint = "/freguesia/" . urlencode($freguesia) . "/geometria";
                $geometryParams = ['municipio' => $municipio, 'json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Garantir que os nomes da freguesia e município estão incluídos na resposta
            if (!isset($result['data']['freguesia'])) {
                $result['data']['freguesia'] = $freguesia;
            }
            if (!isset($result['data']['municipio']) && !isset($result['data']['concelho'])) {
                $result['data']['municipio'] = $municipio;
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha'])) {
                error_log("Buscando dados de área para a freguesia: " . $freguesia);
                
                $areaEndpoint = "/freguesia/" . urlencode($freguesia) . "/area";
                $areaParams = ['municipio' => $municipio, 'json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByFreguesiaAndMunicipio result: " . print_r($result, true));
        return $result;
    }

    public function fetchByMunicipio($municipio) {
        error_log("fetchByMunicipio called with: " . $municipio);
        
        // Buscar dados completos do município (já deve incluir censos2021 e censos2011)
        $endpoint = "/municipio/" . urlencode($municipio);
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos do município - censos2021 presente: " . 
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") . 
                     ", censos2011 presente: " . 
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Buscar geometria (GeoJSON) se não estiver presente
            if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para o município: " . $municipio);
                
                $geometryEndpoint = "/municipio/" . urlencode($municipio) . "/geometria";
                $geometryParams = ['json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Garantir que o nome do município está incluído na resposta
            if (!isset($result['data']['municipio']) && !isset($result['data']['concelho'])) {
                $result['data']['municipio'] = $municipio;
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha'])) {
                error_log("Buscando dados de área para o município: " . $municipio);
                
                $areaEndpoint = "/municipio/" . urlencode($municipio) . "/area";
                $areaParams = ['json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByMunicipio result: " . print_r($result, true));
        return $result;
    }

    public function fetchByDistrito($distrito) {
        error_log("fetchByDistrito called with: " . $distrito);
        
        // Buscar dados completos do distrito (já inclui censos2021 e censos2011)
        $endpoint = "/distrito/" . urlencode($distrito);
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos do distrito - censos2021 presente: " . 
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") . 
                     ", censos2011 presente: " . 
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Garantir que a geometria (GeoJSON) está presente
            if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para o distrito: " . $distrito);
                
                $geometryEndpoint = "/distrito/" . urlencode($distrito) . "/geometria";
                $geometryParams = ['json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Garantir que o nome do distrito está incluído na resposta
            if (!isset($result['data']['distrito'])) {
                $result['data']['distrito'] = $distrito;
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha'])) {
                error_log("Buscando dados de área para o distrito: " . $distrito);
                
                $areaEndpoint = "/distrito/" . urlencode($distrito) . "/area";
                $areaParams = ['json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByDistrito result: " . print_r($result, true));
        return $result;
    }

    public function fetchAllDistritos() {
        $endpoint = "/distritos";
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        return $result;
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