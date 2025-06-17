<?php
/**
 * Script de Gestão de Cache
 * 
 * Este script fornece funcionalidades para limpar caches da API.
 * Pode ser executado a partir da linha de comandos ou através de uma interface web.
 */

// Determinar se o script está a ser executado a partir da linha de comandos
$isCli = (php_sapi_name() === 'cli');

// Configurar o ambiente
if (!$isCli) {
    // Se estiver a ser executado num servidor web, definir cabeçalhos e verificar acesso de administrador
    header('Content-Type: text/html; charset=utf-8');
    
    // Verificação de autenticação simples - substitua pelo seu método de autenticação real
    $isAuthenticated = false;
    
    // Verificar cookie ou sessão de administrador
    if (isset($_COOKIE['admin_authenticated']) || (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true)) {
        $isAuthenticated = true;
    }
    
    // Se não autenticado, verificar autenticação básica
    if (!$isAuthenticated && isset($_SERVER['PHP_AUTH_USER'])) {
        // Substitua pela sua verificação de credenciais de administrador real
        if ($_SERVER['PHP_AUTH_USER'] === 'admin' && $_SERVER['PHP_AUTH_PW'] === 'adminpassword') {
            $isAuthenticated = true;
        }
    }
    
    // Se ainda não autenticado, exigir autenticação básica
    if (!$isAuthenticated) {
        header('WWW-Authenticate: Basic realm="Gestão de Cache"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<h1>Autenticação Necessária</h1>';
        exit;
    }
}

// Incluir a classe de cache da API
require_once __DIR__ . '/../../includes/api_cache.php';

// Definir diretórios de cache
$cacheDirs = [
    'location_data' => __DIR__ . '/../../cache/location_data/',
    'geoapi' => __DIR__ . '/../../cache/geoapi/',
    'api' => __DIR__ . '/../../cache/api/'
];

// Função para limpar uma cache específica
function clearCache($cacheDir, $name) {
    if (!is_dir($cacheDir)) {
        return ['success' => false, 'message' => "O diretório de cache '$name' não existe"];
    }
    
    $cache = new ApiCache($cacheDir);
    $result = $cache->clearAll();
    
    if ($result) {
        return ['success' => true, 'message' => "Cache '$name' limpa com sucesso"];
    } else {
        return ['success' => false, 'message' => "Falha ao limpar a cache '$name'"];
    }
}

// Função para obter estatísticas da cache
function getCacheStats($cacheDir, $name) {
    if (!is_dir($cacheDir)) {
        return ['name' => $name, 'exists' => false, 'count' => 0, 'size' => 0];
    }
    
    $files = glob($cacheDir . '*.json');
    $count = count($files);
    $size = 0;
    
    foreach ($files as $file) {
        $size += filesize($file);
    }
    
    return [
        'name' => $name,
        'exists' => true,
        'count' => $count,
        'size' => formatSize($size),
        'oldest' => $count > 0 ? date('Y-m-d H:i:s', min(array_map('filemtime', $files))) : 'N/A',
        'newest' => $count > 0 ? date('Y-m-d H:i:s', max(array_map('filemtime', $files))) : 'N/A'
    ];
}

// Função para formatar o tamanho do ficheiro
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Lidar com ações
$action = $isCli ? ($argv[1] ?? '') : ($_GET['action'] ?? '');
$target = $isCli ? ($argv[2] ?? '') : ($_GET['target'] ?? '');
$result = null;

if ($action === 'clear') {
    if ($target === 'all') {
        $results = [];
        foreach ($cacheDirs as $name => $dir) {
            $results[$name] = clearCache($dir, $name);
        }
        $result = ['success' => true, 'message' => 'Todas as caches limpas', 'details' => $results];
    } else if (isset($cacheDirs[$target])) {
        $result = clearCache($cacheDirs[$target], $target);
    } else {
        $result = ['success' => false, 'message' => "Alvo de cache desconhecido: $target"];
    }
}

// Obter estatísticas da cache
$stats = [];
foreach ($cacheDirs as $name => $dir) {
    $stats[$name] = getCacheStats($dir, $name);
}

// Saída do resultado
if ($isCli) {
    // Saída da linha de comandos
    if ($result) {
        echo $result['message'] . PHP_EOL;
        if (isset($result['details'])) {
            foreach ($result['details'] as $name => $detail) {
                echo "  $name: " . $detail['message'] . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "Estatísticas da Cache:" . PHP_EOL;
    foreach ($stats as $name => $stat) {
        echo "  $name: " . ($stat['exists'] ? "{$stat['count']} ficheiros ({$stat['size']})" : "não encontrado") . PHP_EOL;
    }
} else {
    // Saída da interface web
    ?>
    <!DOCTYPE html>
    <html lang="pt-PT">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestão de Cache da API</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
                color: #333;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }
            h1 {
                color: #2c3e50;
                margin-top: 0;
            }
            .message {
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .success {
                background-color: #d4edda;
                color: #155724;
            }
            .error {
                background-color: #f8d7da;
                color: #721c24;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background-color: #f2f2f2;
            }
            .actions {
                margin-top: 20px;
            }
            .btn {
                display: inline-block;
                padding: 8px 16px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin-right: 10px;
                margin-bottom: 10px;
            }
            .btn:hover {
                background-color: #2980b9;
            }
            .btn-danger {
                background-color: #e74c3c;
            }
            .btn-danger:hover {
                background-color: #c0392b;
            }
            .btn-warning {
                background-color: #f39c12;
            }
            .btn-warning:hover {
                background-color: #d35400;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Gestão de Cache da API</h1>
            
            <?php if ($result): ?>
                <div class="message <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                    <?php if (isset($result['details'])): ?>
                        <ul>
                            <?php foreach ($result['details'] as $name => $detail): ?>
                                <li><?php echo htmlspecialchars("$name: {$detail['message']}"); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h2>Estatísticas da Cache</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cache</th>
                        <th>Ficheiros</th>
                        <th>Tamanho</th>
                        <th>Entrada Mais Antiga</th>
                        <th>Entrada Mais Recente</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $name => $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($name); ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['count']) : 'N/A'; ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['size']) : 'N/A'; ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['oldest']) : 'N/A'; ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['newest']) : 'N/A'; ?></td>
                            <td>
                                <?php if ($stat['exists'] && $stat['count'] > 0): ?>
                                    <a href="?action=clear&target=<?php echo urlencode($name); ?>" class="btn btn-warning" onclick="return confirm('Tem a certeza que deseja limpar a cache <?php echo htmlspecialchars($name); ?>?');">Limpar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="actions">
                <a href="?action=clear&target=all" class="btn btn-danger" onclick="return confirm('Tem a certeza que deseja limpar TODAS as caches?');">Limpar Todas as Caches</a>
                <a href="?" class="btn">Atualizar</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?> 