<?php
/**
 * Endpoint para Download de Documentos (Faturas/Comprovantes)
 * Exemplo de uso: /download.php?id=10
 */

// Inicia a sessão ou validação de token (ajuste conforme seu sistema de autenticação)
session_start();

// Simula a obtenção do ID do usuário logado (substitua pela sua lógica real)
$usuarioLogadoId = $_SESSION['usuario_id'] ?? null;

if (!$usuarioLogadoId) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["erro" => "Acesso não autorizado. Faça login."]);
    exit;
}

// Recebe e valida o ID do documento enviado via parâmetro GET
$documentoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$documentoId) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["erro" => "ID do documento inválido."]);
    exit;
}

try {
    // Conexão com o banco de dados (Exemplo usando SQLite compatível com a sua tabela)
    // Se utilizar MySQL, altere o DSN para: 'mysql:host=localhost;dbname=seu_banco;charset=utf8mb4', 'usuario', 'senha'
    $pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta o documento garantindo que ele pertence ao usuário logado (Segurança IDOR)
    $sql = "SELECT * FROM documentos WHERE id = :id AND usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id' => $documentoId,
        'usuario_id' => $usuarioLogadoId
    ]);
    
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o documento não existir ou não pertencer ao usuário
    if (!$documento) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["erro" => "Documento não encontrado ou permissão negada."]);
        exit;
    }

    // Diretório onde os arquivos PDF estão salvos no servidor
    $diretorioUploads = __DIR__ . '/uploads/documentos/';
    
    // basename() previne ataques de Path Traversal (ex: ../../etc/passwd)
    $nomeArquivoSeguro = basename($documento['nome_arquivo']);
    $caminhoCompleto = $diretorioUploads . $nomeArquivoSeguro;

    // Verifica se o arquivo físico realmente existe no servidor
    if (!file_exists($caminhoCompleto)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["erro" => "O arquivo físico não foi encontrado no servidor."]);
        exit;
    }

    // Limpa qualquer buffer de saída anterior para evitar corromper o PDF
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Define os cabeçalhos HTTP para download do PDF
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nomeArquivoSeguro . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    // Evita que faturas com dados pessoais fiquem armazenadas em caches compartilhados.
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Length: ' . filesize($caminhoCompleto));

    // Lê e envia o arquivo para o navegador do cliente de forma eficiente
    readfile($caminhoCompleto);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    // Em produção, registre o erro em log ao invés de exibi-lo na tela
    echo json_encode(["erro" => "Erro interno no servidor ao processar o download."]);
    exit;
}