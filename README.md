# Sistema Atlas

CRM comercial full stack em PHP, MySQL, HTML, CSS e JavaScript puro. O projeto oferece uma interface para gerenciar clientes, oportunidades comerciais, atividades de follow-up e indicadores de pipeline.

O codigo principal esta na pasta:

```text
sistema-atlas/
```

## Recursos

- Cadastro, edicao, listagem e exclusao de clientes.
- Cadastro e gestao de oportunidades comerciais.
- Controle de atividades de follow-up.
- Dashboard com metricas de clientes, oportunidades, pipeline e pendencias.
- API PHP com respostas JSON padronizadas.
- Banco relacional MySQL com script SQL.
- Validacoes de entrada, headers de seguranca e rate limit simples.

## Stack

- PHP 8+
- MySQL ou MariaDB
- PDO
- HTML5
- CSS3
- JavaScript
- XAMPP para ambiente local

## Estrutura

```text
sistema-atlas/
├── api/          # Endpoints PHP
├── config/       # Conexao com banco
├── database/     # Script SQL
└── public/       # Interface web
```

## Como rodar no XAMPP

1. Copie a pasta `sistema-atlas` para `C:\xampp\htdocs\sistema-atlas`.
2. Inicie Apache e MySQL no XAMPP.
3. Abra `http://localhost/phpmyadmin`.
4. Importe o arquivo `sistema-atlas/database/schema.sql`.
5. Acesse:

```text
http://localhost/sistema-atlas/public/
```

## Configuracao do banco

Arquivo:

```text
sistema-atlas/config/banco.php
```

Padrao local:

- Host: `127.0.0.1`
- Porta: `3306`
- Banco: `sistema_atlas`
- Usuario: `root`
- Senha: vazia

## API

Endpoint principal:

```text
sistema-atlas/api/index.php
```

Entidades suportadas:

- `clientes`
- `oportunidades`
- `atividades`
- `dashboard`

Exemplo:

```text
GET /sistema-atlas/api/index.php?entidade=dashboard
```

## Status

Projeto de portfolio com foco em CRUD, dashboard comercial, organizacao full stack e boas praticas basicas de backend.
