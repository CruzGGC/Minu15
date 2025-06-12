<?php
require_once __DIR__ . '/api_cache.php';

class LocationFetcher {
    private $baseUrl = "http://json.localhost:8080";
    private $cache;
    private $cacheEnabled = true;
    private $cacheExpiry = 604800; // 7 days in seconds

    public function __construct($cacheEnabled = true, $cacheExpiry = null) {
        $this->cacheEnabled = $cacheEnabled;
        if ($cacheExpiry !== null) {
            $this->cacheExpiry = $cacheExpiry;
        }
        $this->cache = new ApiCache(__DIR__ . '/../cache/location_data/', $this->cacheExpiry);
    }

    private function callApi($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        // Debug log the URL
        error_log("Calling API URL: " . $url);

        // Check if we have a valid cache for this request
        if ($this->cacheEnabled) {
            $cacheKey = $this->cache->generateCacheKey($endpoint, $params);
            if ($this->cache->hasValidCache($cacheKey)) {
                error_log("Using cached data for: " . $url);
                return $this->cache->get($cacheKey);
            }
        }

        // For URLs with spaces or special characters, first try directly
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']); // Request JSON response
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout to 30 seconds

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // If the direct call fails (likely due to spaces or special chars), try with proper URL encoding
        if ($httpCode === 404 && (strpos($url, ' ') !== false || strpos($url, '+') !== false)) {
            error_log("URL with spaces or special chars failed, trying with encoded URL");
            curl_close($ch);
            
            // Properly encode the URL, maintaining the query string structure
            $urlParts = parse_url($url);
            
            // Split the path by / and encode each segment separately
            $pathSegments = explode('/', $urlParts['path']);
            $encodedSegments = [];
            
            foreach ($pathSegments as $segment) {
                if (!empty($segment)) {
                    // Replace + with space before encoding to avoid double encoding
                    $segment = str_replace('+', ' ', $segment);
                    $encodedSegments[] = rawurlencode($segment);
                } else {
                    $encodedSegments[] = '';
                }
            }
            
            $encodedPath = implode('/', $encodedSegments);
            
            // Ensure we maintain the leading slash
            if (substr($urlParts['path'], 0, 1) === '/') {
                $encodedPath = '/' . ltrim($encodedPath, '/');
            }
            
            $encodedUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $encodedPath;
            if (isset($urlParts['query'])) {
                $encodedUrl .= '?' . $urlParts['query'];
            }
            
            error_log("Encoded URL: " . $encodedUrl);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $encodedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
        }
        
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
            $result = null;
            if (is_array($decodedResponse)) {
                $result = ['success' => true, 'data' => $decodedResponse];
            } else {
                $result = ['success' => true, 'data' => ['response' => $decodedResponse]];
            }
            
            // Cache the successful response if caching is enabled
            if ($this->cacheEnabled) {
                $cacheKey = $this->cache->generateCacheKey($endpoint, $params);
                $this->cache->set($cacheKey, $result);
                error_log("Cached API response for: " . $url);
            }
            
            return $result;
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
    
    /**
     * Clear the cache for a specific endpoint
     * 
     * @param string $endpoint The API endpoint
     * @param array $params Additional parameters
     * @return bool True if cache was cleared, false otherwise
     */
    public function clearCache($endpoint, $params = []) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheKey = $this->cache->generateCacheKey($endpoint, $params);
        return $this->cache->delete($cacheKey);
    }
    
    /**
     * Clear all cached data
     * 
     * @return bool True if cache was cleared, false otherwise
     */
    public function clearAllCache() {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        return $this->cache->clearAll();
    }
    
    /**
     * Enable or disable caching
     * 
     * @param bool $enabled True to enable caching, false to disable
     */
    public function setCacheEnabled($enabled) {
        $this->cacheEnabled = $enabled;
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
        error_log("fetchByFreguesiaAndMunicipio called with: " . $freguesia . ", " . $municipio);
        
        // Use rawurlencode for special characters in freguesia names
        $rawFreguesia = rawurlencode($freguesia);
        
        // Try the freguesia endpoint first with the raw encoded freguesia
        $endpoint = "/freguesia/" . $rawFreguesia;
        $params = ['municipio' => $municipio, 'json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // If that fails, try with standard URL encoding
        if (!$result['success']) {
            error_log("Raw URL encoding failed, trying standard encoding");
            // Use standard urlencode
            $encodedFreguesia = urlencode($freguesia);
            $endpoint = "/freguesia/" . $encodedFreguesia;
            $params = ['municipio' => $municipio, 'json' => 'true'];
            $result = $this->callApi($endpoint, $params);
        }
        
        // Try the municipality/freguesia endpoint as a last resort
        if (!$result['success']) {
            error_log("Standard URL encoding failed, trying municipality/freguesia endpoint");
            // Use rawurlencode instead of urlencode
            $encodedMunicipio = rawurlencode($municipio);
            $encodedFreguesia = rawurlencode($freguesia);
            $endpoint = "/municipio/" . $encodedMunicipio . "/freguesia/" . $encodedFreguesia;
            $params = ['json' => 'true'];
            $result = $this->callApi($endpoint, $params);
        }
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos da freguesia - censos2021 presente: " . 
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") . 
                     ", censos2011 presente: " . 
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Check if geojsons object exists in the response
            if (isset($result['data']['geojsons'])) {
                error_log("Found geojsons object in API response with keys: " . 
                         (isset($result['data']['geojsons']) ? implode(", ", array_keys($result['data']['geojsons'])) : "none"));
                
                // Keep the original geojsons structure but also set geojson for backward compatibility
                if (isset($result['data']['geojsons']['freguesia'])) {
                    $result['data']['geojson'] = $result['data']['geojsons']['freguesia'];
                    error_log("Set geojson from geojsons.freguesia for backward compatibility");
                }
                // If there's a freguesias array, find the matching freguesia for backward compatibility
                else if (isset($result['data']['geojsons']['freguesias']) && is_array($result['data']['geojsons']['freguesias'])) {
                    foreach ($result['data']['geojsons']['freguesias'] as $freguesiaGeoJson) {
                        if (isset($freguesiaGeoJson['properties']) && 
                            isset($freguesiaGeoJson['properties']['Freguesia']) && 
                            $freguesiaGeoJson['properties']['Freguesia'] === $freguesia) {
                            $result['data']['geojson'] = $freguesiaGeoJson;
                            error_log("Set geojson from matching freguesia in geojsons.freguesias array");
                            break;
                        }
                    }
                }
                
                // Log if we have freguesias in the geojsons
                if (isset($result['data']['geojsons']['freguesias'])) {
                    error_log("Found " . count($result['data']['geojsons']['freguesias']) . " freguesias in geojsons.freguesias");
                }
            }
            // If still no geojson, try to fetch it
            else if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para a freguesia: " . $freguesia);
                
                // Use raw encoding for geometry endpoint
                $geometryEndpoint = "/freguesia/" . $rawFreguesia . "/geometria";
                $geometryParams = ['municipio' => $municipio, 'json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                // Try standard encoding if raw fails
                if (!$geometryResult['success']) {
                    $geometryEndpoint = "/freguesia/" . rawurlencode($freguesia) . "/geometria";
                    $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                }
                
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
                
                // Use raw encoding for area endpoint
                $areaEndpoint = "/freguesia/" . $rawFreguesia . "/area";
                $areaParams = ['municipio' => $municipio, 'json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                // Try standard encoding if raw fails
                if (!$areaResult['success']) {
                    $areaEndpoint = "/freguesia/" . rawurlencode($freguesia) . "/area";
                    $areaResult = $this->callApi($areaEndpoint, $areaParams);
                }
                
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
            
            // Check if geojsons exists in the response
            if (isset($result['data']['geojsons'])) {
                error_log("Found geojsons object in API response with keys: " . 
                         (isset($result['data']['geojsons']) ? implode(", ", array_keys($result['data']['geojsons'])) : "none"));
                
                // Keep the original geojsons structure but also set geojson for backward compatibility
                if (isset($result['data']['geojsons']['municipio'])) {
                    $result['data']['geojson'] = $result['data']['geojsons']['municipio'];
                    error_log("Set geojson from geojsons.municipio for backward compatibility");
                }
                
                // Log if we have freguesias in the geojsons
                if (isset($result['data']['geojsons']['freguesias'])) {
                    error_log("Found " . count($result['data']['geojsons']['freguesias']) . " freguesias in geojsons.freguesias");
                }
            }
            // If no geojsons, try to fetch geometry if geojson is not present
            else if (!isset($result['data']['geojson'])) {
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