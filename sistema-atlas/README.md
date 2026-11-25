# Sistema Atlas (CRM Comercial)

Projeto full stack em **PHP + MySQL + HTML/CSS/JS puro**, pensado para portfólio profissional.

## O que o sistema entrega

- Cadastro, edição e exclusão de **clientes**
- Cadastro, edição e exclusão de **oportunidades comerciais**
- Cadastro, edição e exclusão de **atividades de follow-up**
- **Dashboard** com métricas reais (clientes, oportunidades, pipeline e pendências)
- Modelagem relacional de banco de dados com integridade referencial
- Hardening de backend para mitigar vazamento de dados

## Estrutura de pastas

- `public/`: Front-end (interface)
- `api/`: Endpoints PHP (backend)
- `config/`: Configuração de banco
- `database/`: Script SQL

## Como rodar no XAMPP

1. Copie a pasta `sistema-atlas` para:
   - `C:\xampp\htdocs\sistema-atlas`
2. Inicie no XAMPP:
   - `Apache`
   - `MySQL`
3. Abra o phpMyAdmin:
   - `http://localhost/phpmyadmin`
4. Importe o arquivo:
   - `database/schema.sql`
5. Acesse no navegador:
   - `http://localhost/sistema-atlas/public/`

## Configuração de banco

Arquivo: `config/banco.php`

Padrão XAMPP local:
- Host: `127.0.0.1`
- Porta: `3306`
- Banco: `sistema_atlas`
- Usuário: `root`
- Senha: (vazia)

Se seu ambiente for diferente, ajuste nesse arquivo.

## Segurança aplicada no backend

- Tratamento de erros sem exposição de stack trace ou mensagem interna para o cliente
- Validação e normalização de entradas (tamanho, formato de e-mail, data, listas permitidas)
- Bloqueio de `Content-Type` indevido e JSON malformado
- Limite de tamanho da requisição (proteção contra abuso de payload)
- Rate limit por IP em janela curta
- CORS restrito para origens locais permitidas
- Cabeçalhos de segurança (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, etc.)
- Pendência: proteção do servidor para `config/` e `database/`; não há `.htaccess` versionado.

## Observação importante

Nenhum sistema é 100% invulnerável. O projeto está endurecido para ambiente real de portfólio, mas em produção recomenda-se:

- HTTPS obrigatório
- Usuário de banco exclusivo (não usar `root`)
- Senha forte e rotação periódica
- Autenticação com sessão/token e controle por perfil
- Monitoramento de logs e backup automatizado

## Sugestões para evoluir no portfólio

- Adicionar autenticação de usuários com níveis de acesso
- Criar logs de auditoria (quem alterou o quê)
- Exportar dados para CSV/Excel
- Criar filtros por período, status e responsável
