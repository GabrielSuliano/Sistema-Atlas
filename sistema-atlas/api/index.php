<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/banco.php';

// Lista branca de origens permitidas para chamadas CORS no ambiente local.
$origens_permitidas = [
    'http://localhost',
    'http://localhost:80',
    'http://127.0.0.1',
    'http://127.0.0.1:80',
];

/**
 * Aplica cabeçalhos de segurança e CORS de forma restrita.
 */
function aplicar_cabecalhos_http(array $origens_permitidas): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    $origem_requisicao = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origem_requisicao !== '' && in_array($origem_requisicao, $origens_permitidas, true)) {
        header('Access-Control-Allow-Origin: ' . $origem_requisicao);
        header('Vary: Origin');
    }
}

/**
 * Envia resposta JSON padrão e interrompe a execução.
 */
function enviar_resposta_json(int $codigo_http, bool $sucesso, string $mensagem, $dados = null): void
{
    http_response_code($codigo_http);
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Registra erro interno sem vazar detalhes para o cliente.
 */
function registrar_erro_interno(string $referencia, Throwable $erro): void
{
    error_log(sprintf(
        '[%s] [%s] %s em %s:%d',
        date('Y-m-d H:i:s'),
        $referencia,
        $erro->getMessage(),
        $erro->getFile(),
        $erro->getLine()
    ));
}

/**
 * Retorna string segura, removendo caracteres de controle e limitando tamanho.
 */
function normalizar_texto(string $texto, int $tamanho_maximo): string
{
    $texto_limpo = trim($texto);
    $texto_limpo = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto_limpo) ?? '';
    if (mb_strlen($texto_limpo) > $tamanho_maximo) {
        $texto_limpo = mb_substr($texto_limpo, 0, $tamanho_maximo);
    }
    return $texto_limpo;
}

/**
 * Valida formato de data no padrão YYYY-MM-DD.
 */
function validar_data_yyyy_mm_dd(string $data): bool
{
    $data_objeto = DateTime::createFromFormat('Y-m-d', $data);
    return $data_objeto instanceof DateTime && $data_objeto->format('Y-m-d') === $data;
}

/**
 * Impede payloads grandes para reduzir risco de abuso e DoS.
 */
function validar_tamanho_requisicao(int $limite_bytes): void
{
    $tamanho_informado = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($tamanho_informado > $limite_bytes) {
        enviar_resposta_json(413, false, 'Requisição excede o tamanho permitido.');
    }
}

/**
 * Limite simples de requisições por IP em janela curta.
 */
function aplicar_rate_limit(int $maximo_requisicoes, int $janela_segundos): void
{
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    $chave_arquivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atlas_rate_' . md5($ip_cliente) . '.json';
    $instante_atual = time();

    $registro = [
        'inicio_janela' => $instante_atual,
        'quantidade' => 0,
    ];

    if (is_file($chave_arquivo)) {
        $conteudo = file_get_contents($chave_arquivo);
        $dados = json_decode((string) $conteudo, true);
        if (is_array($dados) && isset($dados['inicio_janela'], $dados['quantidade'])) {
            $registro = $dados;
        }
    }

    $inicio_janela = (int) $registro['inicio_janela'];
    $quantidade = (int) $registro['quantidade'];

    if (($instante_atual - $inicio_janela) >= $janela_segundos) {
        $inicio_janela = $instante_atual;
        $quantidade = 0;
    }

    $quantidade++;

    file_put_contents($chave_arquivo, json_encode([
        'inicio_janela' => $inicio_janela,
        'quantidade' => $quantidade,
    ]), LOCK_EX);

    if ($quantidade > $maximo_requisicoes) {
        enviar_resposta_json(429, false, 'Muitas requisições. Tente novamente em instantes.');
    }
}

/**
 * Lê e valida o corpo JSON.
 */
function ler_corpo_json(): array
{
    $metodo_http = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $metodos_com_corpo = ['POST', 'PUT', 'PATCH'];

    if (!in_array($metodo_http, $metodos_com_corpo, true)) {
        return [];
    }

    $content_type = strtolower(trim($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($content_type !== '' && strpos($content_type, 'application/json') !== 0) {
        enviar_resposta_json(415, false, 'Content-Type inválido. Use application/json.');
    }

    $conteudo_bruto = file_get_contents('php://input');
    if ($conteudo_bruto === false || trim($conteudo_bruto) === '') {
        return [];
    }

    try {
        $dados = json_decode($conteudo_bruto, true, 512, JSON_THROW_ON_ERROR);
        return is_array($dados) ? $dados : [];
    } catch (JsonException $erro_json) {
        enviar_resposta_json(400, false, 'JSON inválido na requisição.');
    }
}

aplicar_cabecalhos_http($origens_permitidas);
validar_tamanho_requisicao(1024 * 1024);
aplicar_rate_limit(120, 60);

$metodo_http = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($metodo_http === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$entidade = normalizar_texto((string) ($_GET['entidade'] ?? ''), 30);
$id_registro = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$entidades_permitidas = ['clientes', 'oportunidades', 'atividades', 'dashboard'];
if ($entidade === '') {
    enviar_resposta_json(400, false, 'Parâmetro "entidade" é obrigatório. Use: clientes, oportunidades, atividades ou dashboard.');
}

if (!in_array($entidade, $entidades_permitidas, true)) {
    enviar_resposta_json(404, false, 'Rota não encontrada.');
}

$conexao = obter_conexao_banco();

try {
    if ($entidade === 'clientes') {
        if ($metodo_http === 'GET') {
            $sql = 'SELECT id, nome_empresa, contato_email, telefone, setor, DATE_FORMAT(criado_em, "%d/%m/%Y %H:%i") AS criado_em FROM clientes ORDER BY id DESC';
            $lista_clientes = $conexao->query($sql)->fetchAll();
            enviar_resposta_json(200, true, 'Clientes carregados com sucesso.', $lista_clientes);
        }

        if ($metodo_http === 'POST') {
            $dados_cliente = ler_corpo_json();

            $nome_empresa = normalizar_texto((string) ($dados_cliente['nome_empresa'] ?? ''), 150);
            $contato_email = normalizar_texto((string) ($dados_cliente['contato_email'] ?? ''), 120);
            $telefone = normalizar_texto((string) ($dados_cliente['telefone'] ?? ''), 30);
            $setor = normalizar_texto((string) ($dados_cliente['setor'] ?? ''), 80);

            if ($nome_empresa === '' || $contato_email === '') {
                enviar_resposta_json(422, false, 'Nome da empresa e e-mail são obrigatórios.');
            }

            if (!filter_var($contato_email, FILTER_VALIDATE_EMAIL)) {
                enviar_resposta_json(422, false, 'E-mail de contato inválido.');
            }

            $comando = $conexao->prepare('INSERT INTO clientes (nome_empresa, contato_email, telefone, setor) VALUES (?, ?, ?, ?)');
            $comando->execute([$nome_empresa, $contato_email, $telefone, $setor]);

            enviar_resposta_json(201, true, 'Cliente cadastrado com sucesso.', [
                'id' => (int) $conexao->lastInsertId(),
            ]);
        }

        if ($metodo_http === 'PUT' && $id_registro > 0) {
            $dados_cliente = ler_corpo_json();

            $nome_empresa = normalizar_texto((string) ($dados_cliente['nome_empresa'] ?? ''), 150);
            $contato_email = normalizar_texto((string) ($dados_cliente['contato_email'] ?? ''), 120);
            $telefone = normalizar_texto((string) ($dados_cliente['telefone'] ?? ''), 30);
            $setor = normalizar_texto((string) ($dados_cliente['setor'] ?? ''), 80);

            if ($nome_empresa === '' || $contato_email === '') {
                enviar_resposta_json(422, false, 'Nome da empresa e e-mail são obrigatórios.');
            }

            if (!filter_var($contato_email, FILTER_VALIDATE_EMAIL)) {
                enviar_resposta_json(422, false, 'E-mail de contato inválido.');
            }

            $comando = $conexao->prepare('UPDATE clientes SET nome_empresa = ?, contato_email = ?, telefone = ?, setor = ? WHERE id = ?');
            $comando->execute([$nome_empresa, $contato_email, $telefone, $setor, $id_registro]);

            enviar_resposta_json(200, true, 'Cliente atualizado com sucesso.');
        }

        if ($metodo_http === 'DELETE' && $id_registro > 0) {
            $comando = $conexao->prepare('DELETE FROM clientes WHERE id = ?');
            $comando->execute([$id_registro]);

            enviar_resposta_json(200, true, 'Cliente removido com sucesso.');
        }
    }

    if ($entidade === 'oportunidades') {
        if ($metodo_http === 'GET') {
            $sql = 'SELECT o.id, o.cliente_id, c.nome_empresa, o.titulo, o.valor_estimado, o.status, o.probabilidade, DATE_FORMAT(o.data_fechamento_prevista, "%d/%m/%Y") AS data_fechamento_prevista
                    FROM oportunidades o
                    INNER JOIN clientes c ON c.id = o.cliente_id
                    ORDER BY o.id DESC';
            $lista_oportunidades = $conexao->query($sql)->fetchAll();
            enviar_resposta_json(200, true, 'Oportunidades carregadas com sucesso.', $lista_oportunidades);
        }

        if ($metodo_http === 'POST') {
            $dados_oportunidade = ler_corpo_json();

            $cliente_id = (int) ($dados_oportunidade['cliente_id'] ?? 0);
            $titulo = normalizar_texto((string) ($dados_oportunidade['titulo'] ?? ''), 150);
            $valor_estimado = (float) ($dados_oportunidade['valor_estimado'] ?? 0);
            $status = normalizar_texto((string) ($dados_oportunidade['status'] ?? 'Novo'), 40);
            $probabilidade = (int) ($dados_oportunidade['probabilidade'] ?? 0);
            $data_fechamento_prevista = normalizar_texto((string) ($dados_oportunidade['data_fechamento_prevista'] ?? ''), 10);

            $status_permitidos = ['Novo', 'Qualificação', 'Proposta', 'Negociação', 'Fechada ganha', 'Fechada perdida'];

            if ($cliente_id <= 0 || $titulo === '' || $valor_estimado <= 0) {
                enviar_resposta_json(422, false, 'Cliente, título e valor estimado são obrigatórios.');
            }

            if (!in_array($status, $status_permitidos, true)) {
                enviar_resposta_json(422, false, 'Status da oportunidade inválido.');
            }

            if ($probabilidade < 0 || $probabilidade > 100) {
                enviar_resposta_json(422, false, 'Probabilidade deve estar entre 0 e 100.');
            }

            if (!validar_data_yyyy_mm_dd($data_fechamento_prevista)) {
                enviar_resposta_json(422, false, 'Data de fechamento prevista inválida.');
            }

            $comando = $conexao->prepare('INSERT INTO oportunidades (cliente_id, titulo, valor_estimado, status, probabilidade, data_fechamento_prevista) VALUES (?, ?, ?, ?, ?, ?)');
            $comando->execute([$cliente_id, $titulo, $valor_estimado, $status, $probabilidade, $data_fechamento_prevista]);

            enviar_resposta_json(201, true, 'Oportunidade cadastrada com sucesso.', [
                'id' => (int) $conexao->lastInsertId(),
            ]);
        }

        if ($metodo_http === 'PUT' && $id_registro > 0) {
            $dados_oportunidade = ler_corpo_json();

            $cliente_id = (int) ($dados_oportunidade['cliente_id'] ?? 0);
            $titulo = normalizar_texto((string) ($dados_oportunidade['titulo'] ?? ''), 150);
            $valor_estimado = (float) ($dados_oportunidade['valor_estimado'] ?? 0);
            $status = normalizar_texto((string) ($dados_oportunidade['status'] ?? 'Novo'), 40);
            $probabilidade = (int) ($dados_oportunidade['probabilidade'] ?? 0);
            $data_fechamento_prevista = normalizar_texto((string) ($dados_oportunidade['data_fechamento_prevista'] ?? ''), 10);

            $status_permitidos = ['Novo', 'Qualificação', 'Proposta', 'Negociação', 'Fechada ganha', 'Fechada perdida'];

            if ($cliente_id <= 0 || $titulo === '' || $valor_estimado <= 0) {
                enviar_resposta_json(422, false, 'Cliente, título e valor estimado são obrigatórios.');
            }

            if (!in_array($status, $status_permitidos, true)) {
                enviar_resposta_json(422, false, 'Status da oportunidade inválido.');
            }

            if ($probabilidade < 0 || $probabilidade > 100) {
                enviar_resposta_json(422, false, 'Probabilidade deve estar entre 0 e 100.');
            }

            if (!validar_data_yyyy_mm_dd($data_fechamento_prevista)) {
                enviar_resposta_json(422, false, 'Data de fechamento prevista inválida.');
            }

            $comando = $conexao->prepare('UPDATE oportunidades SET cliente_id = ?, titulo = ?, valor_estimado = ?, status = ?, probabilidade = ?, data_fechamento_prevista = ? WHERE id = ?');
            $comando->execute([$cliente_id, $titulo, $valor_estimado, $status, $probabilidade, $data_fechamento_prevista, $id_registro]);

            enviar_resposta_json(200, true, 'Oportunidade atualizada com sucesso.');
        }

        if ($metodo_http === 'DELETE' && $id_registro > 0) {
            $comando = $conexao->prepare('DELETE FROM oportunidades WHERE id = ?');
            $comando->execute([$id_registro]);

            enviar_resposta_json(200, true, 'Oportunidade removida com sucesso.');
        }
    }

    if ($entidade === 'atividades') {
        if ($metodo_http === 'GET') {
            $sql = 'SELECT a.id, a.oportunidade_id, o.titulo AS oportunidade_titulo, a.tipo, a.descricao, DATE_FORMAT(a.data_atividade, "%d/%m/%Y") AS data_atividade, a.responsavel, a.concluida
                    FROM atividades a
                    INNER JOIN oportunidades o ON o.id = a.oportunidade_id
                    ORDER BY a.id DESC';
            $lista_atividades = $conexao->query($sql)->fetchAll();
            enviar_resposta_json(200, true, 'Atividades carregadas com sucesso.', $lista_atividades);
        }

        if ($metodo_http === 'POST') {
            $dados_atividade = ler_corpo_json();

            $oportunidade_id = (int) ($dados_atividade['oportunidade_id'] ?? 0);
            $tipo = normalizar_texto((string) ($dados_atividade['tipo'] ?? 'Ligação'), 40);
            $descricao = normalizar_texto((string) ($dados_atividade['descricao'] ?? ''), 255);
            $data_atividade = normalizar_texto((string) ($dados_atividade['data_atividade'] ?? ''), 10);
            $responsavel = normalizar_texto((string) ($dados_atividade['responsavel'] ?? ''), 120);
            $concluida = (int) ($dados_atividade['concluida'] ?? 0);

            $tipos_permitidos = ['Ligação', 'Reunião', 'E-mail', 'Visita'];

            if ($oportunidade_id <= 0 || $descricao === '' || $responsavel === '') {
                enviar_resposta_json(422, false, 'Oportunidade, descrição e responsável são obrigatórios.');
            }

            if (!in_array($tipo, $tipos_permitidos, true)) {
                enviar_resposta_json(422, false, 'Tipo de atividade inválido.');
            }

            if (!validar_data_yyyy_mm_dd($data_atividade)) {
                enviar_resposta_json(422, false, 'Data da atividade inválida.');
            }

            if (!in_array($concluida, [0, 1], true)) {
                enviar_resposta_json(422, false, 'Valor de conclusão inválido.');
            }

            $comando = $conexao->prepare('INSERT INTO atividades (oportunidade_id, tipo, descricao, data_atividade, responsavel, concluida) VALUES (?, ?, ?, ?, ?, ?)');
            $comando->execute([$oportunidade_id, $tipo, $descricao, $data_atividade, $responsavel, $concluida]);

            enviar_resposta_json(201, true, 'Atividade cadastrada com sucesso.', [
                'id' => (int) $conexao->lastInsertId(),
            ]);
        }

        if ($metodo_http === 'PUT' && $id_registro > 0) {
            $dados_atividade = ler_corpo_json();

            $oportunidade_id = (int) ($dados_atividade['oportunidade_id'] ?? 0);
            $tipo = normalizar_texto((string) ($dados_atividade['tipo'] ?? 'Ligação'), 40);
            $descricao = normalizar_texto((string) ($dados_atividade['descricao'] ?? ''), 255);
            $data_atividade = normalizar_texto((string) ($dados_atividade['data_atividade'] ?? ''), 10);
            $responsavel = normalizar_texto((string) ($dados_atividade['responsavel'] ?? ''), 120);
            $concluida = (int) ($dados_atividade['concluida'] ?? 0);

            $tipos_permitidos = ['Ligação', 'Reunião', 'E-mail', 'Visita'];

            if ($oportunidade_id <= 0 || $descricao === '' || $responsavel === '') {
                enviar_resposta_json(422, false, 'Oportunidade, descrição e responsável são obrigatórios.');
            }

            if (!in_array($tipo, $tipos_permitidos, true)) {
                enviar_resposta_json(422, false, 'Tipo de atividade inválido.');
            }

            if (!validar_data_yyyy_mm_dd($data_atividade)) {
                enviar_resposta_json(422, false, 'Data da atividade inválida.');
            }

            if (!in_array($concluida, [0, 1], true)) {
                enviar_resposta_json(422, false, 'Valor de conclusão inválido.');
            }

            $comando = $conexao->prepare('UPDATE atividades SET oportunidade_id = ?, tipo = ?, descricao = ?, data_atividade = ?, responsavel = ?, concluida = ? WHERE id = ?');
            $comando->execute([$oportunidade_id, $tipo, $descricao, $data_atividade, $responsavel, $concluida, $id_registro]);

            enviar_resposta_json(200, true, 'Atividade atualizada com sucesso.');
        }

        if ($metodo_http === 'DELETE' && $id_registro > 0) {
            $comando = $conexao->prepare('DELETE FROM atividades WHERE id = ?');
            $comando->execute([$id_registro]);

            enviar_resposta_json(200, true, 'Atividade removida com sucesso.');
        }
    }

    if ($entidade === 'dashboard' && $metodo_http === 'GET') {
        $total_clientes = (int) $conexao->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
        $total_oportunidades = (int) $conexao->query('SELECT COUNT(*) FROM oportunidades')->fetchColumn();
        $valor_pipeline = (float) $conexao->query("SELECT COALESCE(SUM(valor_estimado), 0) FROM oportunidades WHERE status != 'Fechada ganha' AND status != 'Fechada perdida'")->fetchColumn();
        $atividades_pendentes = (int) $conexao->query('SELECT COUNT(*) FROM atividades WHERE concluida = 0')->fetchColumn();

        enviar_resposta_json(200, true, 'Dashboard carregado com sucesso.', [
            'total_clientes' => $total_clientes,
            'total_oportunidades' => $total_oportunidades,
            'valor_pipeline' => $valor_pipeline,
            'atividades_pendentes' => $atividades_pendentes,
        ]);
    }

    enviar_resposta_json(404, false, 'Rota não encontrada.');
} catch (Throwable $erro_aplicacao) {
    $referencia_erro = bin2hex(random_bytes(8));
    registrar_erro_interno($referencia_erro, $erro_aplicacao);

    enviar_resposta_json(500, false, 'Erro interno da aplicação.', [
        'referencia' => $referencia_erro,
    ]);
}
