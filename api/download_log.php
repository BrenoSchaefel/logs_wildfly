<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter parâmetros da requisição
$server = $_GET['server'] ?? '';
$file = $_GET['file'] ?? '';

if (empty($server) || empty($file)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Servidor ou arquivo não especificado']);
    exit;
}

// Configuração do proxy (habilitado apenas em ambiente local)
$useProxy = false; // Altere para false em produção

// Configurações dos servidores
$servers = [
    // Servidor01
    'Servidor01-a' => [
        'url' => 'http://servidor01-a.com.br:9990/management',
        'username' => 'usuario',
        'password' => 'senha'
    ],
    'Servidor01-b' => [
        'url' => 'http://Servidor01-b.com.br:9990/management',
        'username' => 'usuario',
        'password' => 'senha'
    ],

    // Servidor04
    'Servidor04-a' => [
        'url' => 'http://Servidor04-a.com.br:9990/management',
        'username' => 'usuario',
        'password' => 'senha'
    ]
];

// Verificar se o servidor existe
if (!isset($servers[$server])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Servidor não encontrado']);
    exit;
}

$serverConfig = $servers[$server];

try {
    // Construir URL de download
    $downloadUrl = $serverConfig['url'] . '/subsystem/logging/log-file/' . urlencode($file) . '?operation=attribute&name=stream&useStreamAsResponse';
    
    // Configurar cURL para download
    $ch = curl_init();
    
    $curlOptions = [
        CURLOPT_URL => $downloadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/octet-stream'
        ],
        CURLOPT_TIMEOUT => 300, // 5 minutos para arquivos grandes
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $serverConfig['username'] . ':' . $serverConfig['password'],
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        // Configurações para download de arquivo
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5
    ];
    
    // Adicionar configuração do proxy apenas se habilitado
    if ($useProxy) {
        $curlOptions[CURLOPT_PROXY] = 'proxy.com.br';
        $curlOptions[CURLOPT_PROXYPORT] = 3128;
        $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
    }
    
    curl_setopt_array($ch, $curlOptions);

    // Executar requisição
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);

    // Verificar erros do cURL
    if ($error) {
        throw new Exception("Erro de conexão: " . $error);
    }

    // Verificar código HTTP
    if ($httpCode !== 200) {
        throw new Exception("Erro HTTP: " . $httpCode);
    }

    // Verificar se recebeu dados
    if (empty($response)) {
        throw new Exception("Arquivo vazio ou não encontrado");
    }

    // Configurar headers para download
    $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file);
    $safeFilename = $server . '_' . $safeFilename;
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($response));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Enviar arquivo
    echo $response;
    exit;

} catch (Exception $e) {
    error_log("Erro ao baixar arquivo $file do servidor $server: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao baixar arquivo: ' . $e->getMessage(),
        'server' => $server,
        'file' => $file
    ]);
}
?>