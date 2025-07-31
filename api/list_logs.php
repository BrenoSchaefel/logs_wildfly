<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);
$server = $input['server'] ?? '';

if (empty($server)) {
    echo json_encode(['success' => false, 'message' => 'Servidor não especificado']);
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
    echo json_encode(['success' => false, 'message' => 'Servidor não encontrado']);
    exit;
}

$serverConfig = $servers[$server];

// Função para formatar tamanho de arquivo
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Função para fazer requisição cURL
function makeCurlRequest($url, $jsonData, $serverConfig, $useProxy) {
    $ch = curl_init();
    
    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($jsonData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $serverConfig['username'] . ':' . $serverConfig['password'],
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ];
    
    // Adicionar configuração do proxy apenas se habilitado
    if ($useProxy) {
        $curlOptions[CURLOPT_PROXY] = 'proxy.com.br';
        $curlOptions[CURLOPT_PROXYPORT] = 3128;
        $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
    }
    
    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);

    if ($error) {
        throw new Exception("Erro de conexão: " . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception("Erro HTTP: " . $httpCode);
    }

    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta JSON");
    }

    return $responseData;
}

try {
    // 1. Primeiro, listar todos os arquivos de log
    $listJsonData = [
        'operation' => 'read-children-names',
        'child-type' => 'log-file',
        'address' => [
            'subsystem',
            'logging'
        ]
    ];

    $listResponse = makeCurlRequest($serverConfig['url'], $listJsonData, $serverConfig, $useProxy);

    if (!isset($listResponse['outcome']) || $listResponse['outcome'] !== 'success') {
        throw new Exception("Operação de listagem falhou no WildFly");
    }

    $logFiles = $listResponse['result'] ?? [];
    
    // 2. Para cada arquivo, buscar informações de tamanho
    $logsWithSize = [];
    
    foreach ($logFiles as $logFile) {
        try {
            $sizeJsonData = [
                'operation' => 'read-resource',
                'address' => [
                    'subsystem',
                    'logging',
                    'log-file',
                    $logFile
                ],
                'include-runtime' => true
            ];

            $sizeResponse = makeCurlRequest($serverConfig['url'], $sizeJsonData, $serverConfig, $useProxy);

            if (isset($sizeResponse['outcome']) && $sizeResponse['outcome'] === 'success') {
                $result = $sizeResponse['result'] ?? [];
                $fileSize = $result['file-size'] ?? 0;
                
                $logsWithSize[] = [
                    'name' => $logFile,
                    'size' => $fileSize,
                    'formatted_size' => formatFileSize($fileSize)
                ];
            } else {
                // Se não conseguir obter o tamanho, ainda inclui o arquivo
                $logsWithSize[] = [
                    'name' => $logFile,
                    'size' => 0,
                    'formatted_size' => 'N/A'
                ];
            }
        } catch (Exception $e) {
            // Se houver erro ao buscar tamanho de um arquivo específico, ainda inclui o arquivo
            $logsWithSize[] = [
                'name' => $logFile,
                'size' => 0,
                'formatted_size' => 'Erro'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logsWithSize,
        'server' => $server
    ]);

} catch (Exception $e) {
    error_log("Erro ao listar logs do servidor $server: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao conectar com o servidor: ' . $e->getMessage(),
        'server' => $server
    ]);
}
?>