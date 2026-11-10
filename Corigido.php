<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

session_start();

function responder(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    header('Allow: PATCH');
    responder(405, ['erro' => 'Método não permitido. Use PATCH.']);
}

$usuarioId = $_SESSION['usuario_id'] ?? null;
if ((!is_int($usuarioId) && !(is_string($usuarioId) && ctype_digit($usuarioId))) ||
    (int) $usuarioId < 1) {
    responder(401, ['erro' => 'É necessário estar autenticado.']);
}
$usuarioId = (int) $usuarioId;

$tokenSessao = $_SESSION['csrf_token'] ?? null;
$tokenRequisicao = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($tokenSessao) || $tokenSessao === '' ||
    !is_string($tokenRequisicao) || !hash_equals($tokenSessao, $tokenRequisicao)) {
    responder(403, ['erro' => 'Token CSRF inválido ou ausente.']);
}

try {
    $entrada = json_decode(
        file_get_contents('php://input'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $erro) {
    responder(400, ['erro' => 'O corpo deve conter um JSON válido.']);
}

if (!is_array($entrada)) {
    responder(400, ['erro' => 'O corpo deve ser um objeto JSON.']);
}

// Adicionamos 'senha_atual' como um campo permitido obrigatório para validação de segurança
$camposPermitidos = ['email', 'senha_atual', 'nova_senha'];
if (array_diff(array_keys($entrada), $camposPermitidos) !== []) {
    responder(400, ['erro' => 'O corpo contém campos não permitidos.']);
}

$alterarEmail = array_key_exists('email', $entrada);
$alterarSenha = array_key_exists('nova_senha', $entrada);

if (!$alterarEmail && !$alterarSenha) {
    responder(400, ['erro' => 'Informe email e/ou nova_senha.']);
}

// CORREÇÃO DE SEGURANÇA: A senha atual passa a ser OBRIGATÓRIA para qualquer alteração sensível (email ou senha)
if (!isset($entrada['senha_atual']) || !is_string($entrada['senha_atual'])) {
    responder(400, ['erro' => 'A senha atual é obrigatória para realizar alterações na conta.']);
}
$senhaAtual = $entrada['senha_atual'];

if ($alterarEmail) {
    if (!is_string($entrada['email'])) {
        responder(400, ['erro' => 'O e-mail deve ser um texto.']);
    }

    $email = trim($entrada['email']);
    if (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        responder(422, ['erro' => 'Informe um e-mail válido com até 120 bytes.']);
    }
}

if ($alterarSenha) {
    if (!is_string($entrada['nova_senha'])) {
        responder(400, ['erro' => 'A nova senha deve ser um texto.']);
    }

    if (strlen($entrada['nova_senha']) < 8 || strlen($entrada['nova_senha']) > 72) {
        responder(422, ['erro' => 'A nova senha deve ter entre 8 e 72 bytes.']);
    }
}

$dsn = getenv('DB_DSN') ?: 'sqlite:' . __DIR__ . '/usuarios.sqlite';
$usuarioBanco = getenv('DB_USER') ?: null;
$senhaBanco = getenv('DB_PASSWORD') ?: null;

try {
    $pdo = new PDO($dsn, $usuarioBanco, $senhaBanco, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $consulta = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
    $consulta->execute(['id' => $usuarioId]);
    $usuario = $consulta->fetch();

    if ($usuario === false) {
        responder(401, ['erro' => 'A sessão não corresponde a um usuário válido.']);
    }

    // Validação centralizada da senha atual (protege tanto a troca de senha quanto a troca de email)
    if (!password_verify($senhaAtual, $usuario['senha_hash'])) {
        responder(403, ['erro' => 'A senha atual está incorreta.']);
    }

    $campos = [];
    $parametros = ['id' => $usuarioId];

    if ($alterarEmail) {
        $campos[] = 'email = :email';
        $parametros['email'] = $email;
    }

    if ($alterarSenha) {
        $campos[] = 'senha_hash = :senha_hash';
        $parametros['senha_hash'] = password_hash($entrada['nova_senha'], PASSWORD_DEFAULT);
    }

    $atualizacao = $pdo->prepare(
        'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id'
    );
    $atualizacao->execute($parametros);

    responder(200, ['mensagem' => 'Perfil atualizado com sucesso.']);
} catch (PDOException $erro) {
    $codigoBanco = $erro->errorInfo[1] ?? null;
    if ($erro->getCode() === '23000' || $codigoBanco === 19 || $codigoBanco === 1062) {
        responder(409, ['erro' => 'Este e-mail já está cadastrado.']);
    }

    error_log('Falha ao atualizar perfil: ' . $erro->getMessage());
    responder(500, ['erro' => 'Não foi possível atualizar o perfil.']);
}