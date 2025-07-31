# Gerenciador de Logs WildFly

Sistema web para listar e baixar arquivos de log do WildFly de múltiplos servidores.

## Funcionalidades

- Interface moderna e responsiva
- Listagem de logs de múltiplos servidores WildFly
- **Exibição do tamanho dos arquivos** (KB, MB, GB)
- **Indicadores visuais de tamanho** (cores diferentes)
- **Download de arquivos de log** com notificações
- **Organização em grupos** por ambiente
- **Configuração de proxy condicional** (habilitado apenas em ambiente local)
- Autenticação digest com o WildFly
- Modal interativo para visualização de logs
- Configuração de proxy para acesso aos servidores

## Servidores Configurados

### Servidor01 (2 servidores)
- **Servidor01-A**: http://servidor01-a.com.br:9990/management
- **Servidor01-B**: http://servidor01-b.com.br:9990/management

### Servidor04 (1 servidor)
- **Servidor04-A**: http://servidor04-a.com.br:9990/management

**Total: 3 servidores** organizados em 2 grupos

## Configuração de Rede

### Proxy Condicional
O sistema possui uma variável `$useProxy` que controla se o proxy será usado:

```php
$useProxy = true; // Altere para false em produção
```

- **Desenvolvimento Local**: `$useProxy = true` (usa proxy)
- **Produção**: `$useProxy = false` (acesso direto)

### Configuração do Proxy
- **Servidor**: proxy.com.br
- **Porta**: 3128
- **Tipo**: HTTP

## Estrutura do Projeto

```
wildfly/
├── index.html          # Página principal
├── styles.css          # Estilos CSS
├── script.js           # JavaScript da interface
├── favicon.svg         # Favicon personalizado
├── api/
│   ├── list_logs.php   # Backend PHP para listar logs
│   └── download_log.php # Backend PHP para download
└── README.md           # Documentação
```

## Como Usar

1. Acesse `index.html` no seu navegador
2. Navegue pelos grupos de servidores
3. Clique em "Ver Logs" em qualquer servidor
4. Aguarde o carregamento dos logs
5. Visualize a lista de arquivos com seus tamanhos
6. Clique em "📥 Baixar" para download do arquivo

## Indicadores de Tamanho

- 🟢 **Verde**: Arquivos pequenos (< 10 MB)
- 🟡 **Amarelo**: Arquivos médios (10-100 MB)
- 🔴 **Vermelho**: Arquivos grandes (> 100 MB)
- ⚪ **Cinza**: Tamanho não disponível

## Funcionalidade de Download

- ✅ **Download direto** dos arquivos de log
- ✅ **Notificações visuais** de sucesso/erro
- ✅ **Indicador de progresso** durante download
- ✅ **Nomes de arquivo seguros** (prefixo do servidor)
- ✅ **Timeout de 5 minutos** para arquivos grandes

## Requisitos

- Servidor web com PHP (XAMPP, WAMP, etc.)
- Extensão cURL habilitada no PHP
- Acesso aos servidores WildFly configurados
- Acesso ao proxy corporativo (apenas em desenvolvimento)

## Configuração

### Credenciais
- **Usuário**: usuario
- **Senha**: senha

### Configuração de Ambiente
Para alterar entre desenvolvimento e produção, edite a variável `$useProxy` nos arquivos:
- `api/list_logs.php`
- `api/download_log.php`

### Endpoints WildFly

#### 1. Listagem de Arquivos
```json
{
  "operation": "read-children-names",
  "child-type": "log-file",
  "address": [
    "subsystem",
    "logging"
  ]
}
```

#### 2. Informações de Tamanho
```json
{
  "operation": "read-resource",
  "address": [
    "subsystem",
    "logging",
    "log-file",
    "nome_do_arquivo.log"
  ],
  "include-runtime": true
}
```

#### 3. Download de Arquivo
```
GET /management/subsystem/logging/log-file/nome_do_arquivo.log?operation=attribute&name=stream&useStreamAsResponse
```

## Próximos Passos

- [x] ✅ Implementar funcionalidade de download de logs
- [ ] Adicionar filtros por data/tipo de log
- [ ] Implementar cache de resultados
- [ ] Adicionar mais servidores
- [ ] Implementar autenticação de usuários
- [ ] Adicionar ordenação por tamanho
- [ ] Implementar download em lote
- [ ] Adicionar busca por servidor

## Tecnologias Utilizadas

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 7.4+
- **Comunicação**: cURL com autenticação digest
- **Proxy**: HTTP Proxy para acesso corporativo (condicional)
- **Interface**: Design responsivo com CSS Grid e Flexbox
- **Download**: Blob API para download de arquivos

## Desenvolvimento

Para desenvolvimento local:

1. Clone o repositório
2. Configure um servidor web local (XAMPP recomendado)
3. Mantenha `$useProxy = true` para usar o proxy corporativo
4. Acesse via `http://localhost/wildfly/`

## Produção

Para ambiente de produção:

1. Altere `$useProxy = false` nos arquivos PHP
2. Configure o servidor web
3. Acesse diretamente os servidores WildFly