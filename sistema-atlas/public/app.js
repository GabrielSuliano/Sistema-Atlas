// URL base da API PHP.
const URL_API = '../api/index.php';

// Elementos principais da interface para uso em toda a aplicação.
const elementoMetricaClientes = document.getElementById('metricaClientes');
const elementoMetricaOportunidades = document.getElementById('metricaOportunidades');
const elementoMetricaPipeline = document.getElementById('metricaPipeline');
const elementoMetricaAtividades = document.getElementById('metricaAtividades');

const corpoTabelaClientes = document.getElementById('corpoTabelaClientes');
const corpoTabelaOportunidades = document.getElementById('corpoTabelaOportunidades');
const corpoTabelaAtividades = document.getElementById('corpoTabelaAtividades');

const formCliente = document.getElementById('formCliente');
const formOportunidade = document.getElementById('formOportunidade');
const formAtividade = document.getElementById('formAtividade');

const seletorClienteOportunidade = document.getElementById('oportunidadeClienteId');
const seletorOportunidadeAtividade = document.getElementById('atividadeOportunidadeId');

// Estados locais para facilitar renderização e edição dos registros.
let listaClientes = [];
let listaOportunidades = [];
let listaAtividades = [];

/**
 * Executa chamadas HTTP para a API e trata erros comuns.
 */
async function requisitarApi(entidade, metodo = 'GET', dados = null, id = null) {
    const url = new URL(URL_API, window.location.href);
    url.searchParams.set('entidade', entidade);

    if (id) {
        url.searchParams.set('id', id);
    }

    const opcoes = {
        method: metodo,
        headers: {
            'Content-Type': 'application/json',
        },
    };

    if (dados) {
        opcoes.body = JSON.stringify(dados);
    }

    const resposta = await fetch(url, opcoes);
    const corpo = await resposta.json();

    if (!resposta.ok || !corpo.sucesso) {
        throw new Error(corpo.mensagem || 'Falha ao processar requisição.');
    }

    return corpo.dados;
}

/**
 * Formata valores numéricos para moeda brasileira.
 */
function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

/**
 * Atualiza os cards do dashboard.
 */
async function carregarDashboard() {
    const dadosDashboard = await requisitarApi('dashboard');

    elementoMetricaClientes.textContent = dadosDashboard.total_clientes;
    elementoMetricaOportunidades.textContent = dadosDashboard.total_oportunidades;
    elementoMetricaPipeline.textContent = formatarMoeda(dadosDashboard.valor_pipeline);
    elementoMetricaAtividades.textContent = dadosDashboard.atividades_pendentes;
}

/**
 * Carrega clientes e renderiza tabela e seletores relacionados.
 */
async function carregarClientes() {
    listaClientes = await requisitarApi('clientes');

    corpoTabelaClientes.innerHTML = listaClientes.map((cliente) => `
        <tr>
            <td>${cliente.id}</td>
            <td>${cliente.nome_empresa}</td>
            <td>${cliente.contato_email}</td>
            <td>${cliente.telefone || '-'}</td>
            <td>${cliente.setor || '-'}</td>
            <td>${cliente.criado_em}</td>
            <td>
                <button class="botao-tabela" onclick="editarCliente(${cliente.id})">Editar</button>
                <button class="botao-tabela botao-excluir" onclick="excluirCliente(${cliente.id})">Excluir</button>
            </td>
        </tr>
    `).join('');

    // Atualiza o select de cliente usado no cadastro de oportunidade.
    seletorClienteOportunidade.innerHTML = '<option value="">Selecione</option>' +
        listaClientes.map((cliente) => `<option value="${cliente.id}">${cliente.nome_empresa}</option>`).join('');
}

/**
 * Carrega oportunidades e renderiza tabela e seletores relacionados.
 */
async function carregarOportunidades() {
    listaOportunidades = await requisitarApi('oportunidades');

    corpoTabelaOportunidades.innerHTML = listaOportunidades.map((oportunidade) => `
        <tr>
            <td>${oportunidade.id}</td>
            <td>${oportunidade.nome_empresa}</td>
            <td>${oportunidade.titulo}</td>
            <td>${formatarMoeda(oportunidade.valor_estimado)}</td>
            <td>${oportunidade.status}</td>
            <td>${oportunidade.probabilidade}%</td>
            <td>${oportunidade.data_fechamento_prevista}</td>
            <td>
                <button class="botao-tabela" onclick="editarOportunidade(${oportunidade.id})">Editar</button>
                <button class="botao-tabela botao-excluir" onclick="excluirOportunidade(${oportunidade.id})">Excluir</button>
            </td>
        </tr>
    `).join('');

    // Atualiza o select de oportunidades usado no cadastro de atividades.
    seletorOportunidadeAtividade.innerHTML = '<option value="">Selecione</option>' +
        listaOportunidades.map((oportunidade) => `<option value="${oportunidade.id}">${oportunidade.titulo}</option>`).join('');
}

/**
 * Carrega atividades e renderiza tabela.
 */
async function carregarAtividades() {
    listaAtividades = await requisitarApi('atividades');

    corpoTabelaAtividades.innerHTML = listaAtividades.map((atividade) => `
        <tr>
            <td>${atividade.id}</td>
            <td>${atividade.oportunidade_titulo}</td>
            <td>${atividade.tipo}</td>
            <td>${atividade.descricao}</td>
            <td>${atividade.data_atividade}</td>
            <td>${atividade.responsavel}</td>
            <td>${Number(atividade.concluida) === 1 ? 'Sim' : 'Não'}</td>
            <td>
                <button class="botao-tabela" onclick="editarAtividade(${atividade.id})">Editar</button>
                <button class="botao-tabela botao-excluir" onclick="excluirAtividade(${atividade.id})">Excluir</button>
            </td>
        </tr>
    `).join('');
}

/**
 * Recarrega toda a tela após operações de CRUD.
 */
async function recarregarTudo() {
    await carregarDashboard();
    await carregarClientes();
    await carregarOportunidades();
    await carregarAtividades();
}

formCliente.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const clienteId = document.getElementById('clienteId').value;
    const dadosCliente = {
        nome_empresa: document.getElementById('clienteNomeEmpresa').value,
        contato_email: document.getElementById('clienteContatoEmail').value,
        telefone: document.getElementById('clienteTelefone').value,
        setor: document.getElementById('clienteSetor').value,
    };

    try {
        if (clienteId) {
            await requisitarApi('clientes', 'PUT', dadosCliente, clienteId);
        } else {
            await requisitarApi('clientes', 'POST', dadosCliente);
        }

        formCliente.reset();
        document.getElementById('clienteId').value = '';
        await recarregarTudo();
    } catch (erro) {
        alert(erro.message);
    }
});

formOportunidade.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const oportunidadeId = document.getElementById('oportunidadeId').value;
    const dadosOportunidade = {
        cliente_id: document.getElementById('oportunidadeClienteId').value,
        titulo: document.getElementById('oportunidadeTitulo').value,
        valor_estimado: document.getElementById('oportunidadeValorEstimado').value,
        status: document.getElementById('oportunidadeStatus').value,
        probabilidade: document.getElementById('oportunidadeProbabilidade').value,
        data_fechamento_prevista: document.getElementById('oportunidadeDataFechamento').value,
    };

    try {
        if (oportunidadeId) {
            await requisitarApi('oportunidades', 'PUT', dadosOportunidade, oportunidadeId);
        } else {
            await requisitarApi('oportunidades', 'POST', dadosOportunidade);
        }

        formOportunidade.reset();
        document.getElementById('oportunidadeId').value = '';
        await recarregarTudo();
    } catch (erro) {
        alert(erro.message);
    }
});

formAtividade.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const atividadeId = document.getElementById('atividadeId').value;
    const dadosAtividade = {
        oportunidade_id: document.getElementById('atividadeOportunidadeId').value,
        tipo: document.getElementById('atividadeTipo').value,
        descricao: document.getElementById('atividadeDescricao').value,
        data_atividade: document.getElementById('atividadeData').value,
        responsavel: document.getElementById('atividadeResponsavel').value,
        concluida: document.getElementById('atividadeConcluida').value,
    };

    try {
        if (atividadeId) {
            await requisitarApi('atividades', 'PUT', dadosAtividade, atividadeId);
        } else {
            await requisitarApi('atividades', 'POST', dadosAtividade);
        }

        formAtividade.reset();
        document.getElementById('atividadeId').value = '';
        await recarregarTudo();
    } catch (erro) {
        alert(erro.message);
    }
});

// Botões de cancelar edição para limpar o estado dos formulários.
document.getElementById('cancelarEdicaoCliente').addEventListener('click', () => {
    formCliente.reset();
    document.getElementById('clienteId').value = '';
});

document.getElementById('cancelarEdicaoOportunidade').addEventListener('click', () => {
    formOportunidade.reset();
    document.getElementById('oportunidadeId').value = '';
});

document.getElementById('cancelarEdicaoAtividade').addEventListener('click', () => {
    formAtividade.reset();
    document.getElementById('atividadeId').value = '';
});

// Funções expostas no escopo global para uso dos botões em linha da tabela.
window.editarCliente = (idCliente) => {
    const cliente = listaClientes.find((item) => Number(item.id) === Number(idCliente));
    if (!cliente) return;

    document.getElementById('clienteId').value = cliente.id;
    document.getElementById('clienteNomeEmpresa').value = cliente.nome_empresa;
    document.getElementById('clienteContatoEmail').value = cliente.contato_email;
    document.getElementById('clienteTelefone').value = cliente.telefone || '';
    document.getElementById('clienteSetor').value = cliente.setor || '';
};

window.excluirCliente = async (idCliente) => {
    if (!confirm('Deseja realmente excluir este cliente?')) return;

    try {
        await requisitarApi('clientes', 'DELETE', null, idCliente);
        await recarregarTudo();
    } catch (erro) {
        alert(erro.message);
    }
};

window.editarOportunidade = (idOportunidade) => {
    const oportunidade = listaOportunidades.find((item) => Number(item.id) === Number(idOportunidade));
    if (!oportunidade) return;

    document.getElementById('oportunidadeId').value = oportunidade.id;
    document.getElementById('oportunidadeClienteId').value = oportunidade.cliente_id;
    document.getElementById('oportunidadeTitulo').value = oportunidade.titulo;
    document.getElementById('oportunidadeValorEstimado').value = oportunidade.valor_estimado;
    document.getElementById('oportunidadeStatus').value = oportunidade.status;
    document.getElementById('oportunidadeProbabilidade').value = oportunidade.probabilidade;

    // Converte data no formato brasileiro para formato esperado no input date.
    const partesData = String(oportunidade.data_fechamento_prevista).split('/');
    if (partesData.length === 3) {
        document.getElementById('oportunidadeDataFechamento').value = `${partesData[2]}-${partesData[1]}-${partesData[0]}`;
    }
};

window.excluirOportunidade = async (idOportunidade) => {
    if (!confirm('Deseja realmente excluir esta oportunidade?')) return;

    try {
        await requisitarApi('oportunidades', 'DELETE', null, idOportunidade);
        await recarregarTudo();
    } catch (erro) {
        alert(erro.message);
    }
};

window.editarAtividade = (idAtividade) => {
    const atividade = listaAtividades.find((item) => Number(item.id) === Number(idAtividade));
    if (!atividade) return;

    document.getElementById('atividadeId').value = atividade.id;
    document.getElementById('atividadeOportunidadeId').value = atividade.oportunidade_id;
    document.getElementById('atividadeTipo').value = atividade.tipo;
    document.getElementById('atividadeDescricao').value = atividade.descricao;
    document.getElementById('atividadeResponsavel').value = atividade.responsavel;
    document.getElementById('atividadeConcluida').value = atividade.concluida;

    // Converte data no formato brasileiro para formato esperado no input date.
    const partesData = String(atividade.data_atividade).split('/');
    if (partesData.length === 3) {
        document.getElementById('atividadeData').value = `${partesData[2]}-${partesData[1]}-${partesData[0]}`;
    }
};

window.excluirAtividade = async (idAtividade) => {
    if (!confirm('Deseja realmente excluir esta atividade?')) return;

    try {
        await requisitarApi('atividades', 'DELETE', null, idAtividade);
        await recarregarTudo();
    } catch (erro) {
        alert(erro.message);
    }
};

// Inicialização principal da aplicação.
(async function iniciarSistemaAtlas() {
    try {
        await recarregarTudo();
    } catch (erro) {
        alert(`Erro ao inicializar o sistema: ${erro.message}`);
    }
})();
