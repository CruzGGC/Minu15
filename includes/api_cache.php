<?php
/**
 * Sistema de Cache da API
 * Fornece um mecanismo de cache de propósito geral para chamadas de API
 * 
 * @version 1.0
 */

class ApiCache {
    private $cacheDir;
    private $defaultExpiry = 31536000; // 365 days in seconds
    
    /**
     * Construtor
     * 
     * @param string $cacheDir O diretório para armazenar ficheiros de cache (sem barra final)
     * @param int $defaultExpiry Tempo de expiração de cache padrão em segundos
     */
    public function __construct($cacheDir = null, $defaultExpiry = null) {
        // Define o diretório de cache, predefinido para ../cache/api/
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../cache/api/';
        
        // Garante que o diretório de cache existe
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Define a expiração padrão se fornecida
        if ($defaultExpiry !== null) {
            $this->defaultExpiry = $defaultExpiry;
        }
    }
    
    /**
     * Gera uma chave de cache a partir de vários parâmetros
     * 
     * @param string $baseKey Chave base (normalmente o endpoint)
     * @param array $params Parâmetros adicionais a incluir na chave
     * @return string A chave de cache
     */
    public function generateCacheKey($baseKey, $params = []) {
        $key = $baseKey;
        
        if (!empty($params)) {
            // Sort params to ensure consistent keys regardless of parameter order
            ksort($params);
            $key .= '_' . http_build_query($params);
        }
        
        return md5($key);
    }
    
    /**
     * Obtém o caminho completo para um ficheiro de cache
     * 
     * @param string $cacheKey A chave de cache
     * @return string O caminho completo para o ficheiro de cache
     */
    public function getCacheFilePath($cacheKey) {
        return $this->cacheDir . $cacheKey . '.json';
    }
    
    /**
     * Verifica se um item em cache existe e ainda é válido
     * 
     * @param string $cacheKey A chave de cache
     * @param int $expiry Ignora o tempo de expiração padrão (em segundos)
     * @return bool Verdadeiro se o cache existe e é válido, falso caso contrário
     */
    public function hasValidCache($cacheKey, $expiry = null) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            $fileAge = time() - filemtime($cacheFile);
            $expiryTime = $expiry !== null ? $expiry : $this->defaultExpiry;
            
            return $fileAge < $expiryTime;
        }
        
        return false;
    }
    
    /**
     * Obtém dados do cache
     * 
     * @param string $cacheKey A chave de cache
     * @return mixed Os dados em cache (descodificados de JSON), ou nulo se não encontrado
     */
    public function get($cacheKey) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            return json_decode($content, true);
        }
        
        return null;
    }
    
    /**
     * Armazena dados no cache
     * 
     * @param string $cacheKey A chave de cache
     * @param mixed $data Os dados a armazenar (serão codificados em JSON)
     * @return bool Verdadeiro em caso de sucesso, falso em caso de falha
     */
    public function set($cacheKey, $data) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        // Ensure we have a valid JSON string
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            error_log("API Cache: Failed to encode data to JSON - " . json_last_error_msg());
            return false;
        }
        
        // Write to cache file
        $result = file_put_contents($cacheFile, $jsonData);
        return $result !== false;
    }
    
    /**
     * Apaga um item em cache
     * 
     * @param string $cacheKey A chave de cache
     * @return bool Verdadeiro se o cache foi apagado, falso caso contrário
     */
    public function delete($cacheKey) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return false;
    }
    
    /**
     * Limpa todos os itens em cache
     * 
     * @return bool Verdadeiro em caso de sucesso, falso em caso de falha
     */
    public function clearAll() {
        $files = glob($this->cacheDir . '*.json');
        
        if ($files === false) {
            return false;
        }
        
        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Obtém ou define dados em cache com uma função de callback
     * 
     * @param string $cacheKey A chave de cache
     * @param callable $callback Função a chamar se o cache não for válido
     * @param int $expiry Ignora o tempo de expiração padrão (em segundos)
     * @return mixed Os dados em cache ou o resultado da callback
     */
    public function remember($cacheKey, $callback, $expiry = null) {
        // Verifica se temos dados em cache válidos
        if ($this->hasValidCache($cacheKey, $expiry)) {
            return $this->get($cacheKey);
        }
        
        // Chama a callback para obter dados atualizados
        $data = $callback();
        
        // Armazena os dados atualizados no cache
        $this->set($cacheKey, $data);
        
        return $data;
    }
} 