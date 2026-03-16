<?php

// Configurações principais de acesso ao MySQL no XAMPP.
$host_banco = '127.0.0.1';
$porta_banco = '3306';
$nome_banco = 'sistema_atlas';
$usuario_banco = 'root';
$senha_banco = '';

/**
 * Retorna uma conexão PDO única para toda a aplicação.
 *
 * @return PDO
 */
function obter_conexao_banco(): PDO
{
    static $conexao = null;

    // Se a conexão já foi criada anteriormente, reutiliza.
    if ($conexao instanceof PDO) {
        return $conexao;
    }

    global $host_banco, $porta_banco, $nome_banco, $usuario_banco, $senha_banco;

    $dsn = "mysql:host={$host_banco};port={$porta_banco};dbname={$nome_banco};charset=utf8mb4";

    try {
        $conexao = new PDO($dsn, $usuario_banco, $senha_banco, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $erro_conexao) {
        error_log(sprintf(
            '[%s] Falha de conexão com banco: %s em %s:%d',
            date('Y-m-d H:i:s'),
            $erro_conexao->getMessage(),
            $erro_conexao->getFile(),
            $erro_conexao->getLine()
        ));

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Falha ao conectar no banco de dados.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $conexao;
}
