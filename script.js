// Variáveis globais
let currentServer = '';
let currentServerName = '';

// Função para abrir o modal de logs
function openLogsModal(server, serverName) {
    currentServer = server;
    currentServerName = serverName;
    
    // Atualizar título do modal
    document.getElementById('modalTitle').textContent = `Logs do Servidor ${serverName}`;
    
    // Mostrar modal
    document.getElementById('logsModal').style.display = 'block';
    
    // Carregar logs do servidor
    loadServerLogs(server);
}

// Função para fechar o modal
function closeLogsModal() {
    document.getElementById('logsModal').style.display = 'none';
    document.getElementById('logsList').innerHTML = '';
    document.getElementById('logsList').style.display = 'none';
}

// Função para carregar logs do servidor
async function loadServerLogs(server) {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const logsList = document.getElementById('logsList');
    
    // Mostrar spinner de carregamento
    loadingSpinner.style.display = 'block';
    logsList.style.display = 'none';
    
    try {
        // Fazer requisição para o backend PHP
        const response = await fetch('api/list_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                server: server
            })
        });
        
        const data = await response.json();
        
        // Esconder spinner
        loadingSpinner.style.display = 'none';
        
        if (data.success) {
            displayLogs(data.logs);
        } else {
            showError(data.message || 'Erro ao carregar logs');
        }
        
    } catch (error) {
        console.error('Erro na requisição:', error);
        loadingSpinner.style.display = 'none';
        showError('Erro de conexão. Verifique sua internet e tente novamente.');
    }
}

// Função para exibir os logs na interface
function displayLogs(logs) {
    const logsList = document.getElementById('logsList');
    
    if (logs.length === 0) {
        logsList.innerHTML = '<p style="text-align: center; color: #666;">Nenhum arquivo de log encontrado.</p>';
        logsList.style.display = 'block';
        return;
    }
    
    let html = '';
    
    logs.forEach(log => {
        const sizeClass = getSizeClass(log.size);
        html += `
            <div class="log-item">
                <div class="log-info">
                    <span class="log-name">${log.name}</span>
                    <span class="log-size ${sizeClass}">${log.formatted_size}</span>
                </div>
                <button class="btn-download" onclick="downloadLog('${currentServer}', '${log.name}')" title="Baixar arquivo">
                    📥 Baixar
                </button>
            </div>
        `;
    });
    
    logsList.innerHTML = html;
    logsList.style.display = 'block';
}

// Função para determinar a classe CSS baseada no tamanho do arquivo
function getSizeClass(size) {
    if (size === 0 || size === 'N/A' || size === 'Erro') {
        return 'size-unknown';
    }
    
    const sizeInMB = size / (1024 * 1024);
    
    if (sizeInMB >= 100) {
        return 'size-large'; // Vermelho para arquivos grandes
    } else if (sizeInMB >= 10) {
        return 'size-medium'; // Amarelo para arquivos médios
    } else {
        return 'size-small'; // Verde para arquivos pequenos
    }
}

// Função para mostrar erro
function showError(message) {
    const logsList = document.getElementById('logsList');
    logsList.innerHTML = `<div class="error-message">${message}</div>`;
    logsList.style.display = 'block';
}

// Função para baixar log
async function downloadLog(server, logFile) {
    try {
        // Mostrar indicador de download
        const downloadButton = event.target;
        const originalText = downloadButton.innerHTML;
        downloadButton.innerHTML = '⏳ Baixando...';
        downloadButton.disabled = true;
        
        // Construir URL de download
        const downloadUrl = `api/download_log.php?server=${encodeURIComponent(server)}&file=${encodeURIComponent(logFile)}`;
        
        // Fazer requisição de download
        const response = await fetch(downloadUrl);
        
        if (!response.ok) {
            // Se não for OK, tentar ler como JSON para ver a mensagem de erro
            try {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Erro ao baixar arquivo');
            } catch (jsonError) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
        }
        
        // Verificar se é um arquivo válido
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            // Se retornou JSON, é um erro
            const errorData = await response.json();
            throw new Error(errorData.message || 'Erro ao baixar arquivo');
        }
        
        // Obter o blob do arquivo
        const blob = await response.blob();
        
        // Criar URL para download
        const url = window.URL.createObjectURL(blob);
        
        // Criar link de download
        const a = document.createElement('a');
        a.href = url;
        a.download = `${server}_${logFile}`;
        document.body.appendChild(a);
        a.click();
        
        // Limpar
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Mostrar sucesso
        showDownloadSuccess(logFile);
        
    } catch (error) {
        console.error('Erro no download:', error);
        showDownloadError(error.message);
    } finally {
        // Restaurar botão
        const downloadButton = event.target;
        downloadButton.innerHTML = '📥 Baixar';
        downloadButton.disabled = false;
    }
}

// Função para mostrar sucesso no download
function showDownloadSuccess(filename) {
    // Criar notificação temporária
    const notification = document.createElement('div');
    notification.className = 'download-notification success';
    notification.innerHTML = `
        <span>✅ Download concluído: ${filename}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Remover após 3 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

// Função para mostrar erro no download
function showDownloadError(message) {
    // Criar notificação temporária
    const notification = document.createElement('div');
    notification.className = 'download-notification error';
    notification.innerHTML = `
        <span>❌ Erro no download: ${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Remover após 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Fechar modal quando clicar fora dele
window.onclick = function(event) {
    const modal = document.getElementById('logsModal');
    if (event.target === modal) {
        closeLogsModal();
    }
}

// Fechar modal com tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLogsModal();
    }
});

// Adicionar efeitos de hover nos cards dos servidores
document.addEventListener('DOMContentLoaded', function() {
    const serverCards = document.querySelectorAll('.server-card');
    
    serverCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});