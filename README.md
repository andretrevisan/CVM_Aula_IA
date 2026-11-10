- Organizar dois pares de equipes.
- Etapa 1:
    - Cada equipe escolherá um cenário
    - Cada equipe escolherá uma IA (diferente da outra equipe)
    - Cada equipe deve, com auxílio da IA, escrever um código PHP para um dos cenários

- Etapa 2:
    - Enviar o código para a outra equipe para a revisão do código
    - AGUARDAR NOVAS INSTRUÇÕES


Cenário A — Perfil de Usuário (Atualização de Cadastro)
Objetivo de Negócio: Endpoint/Backend para atualizar o e-mail ou a senha do usuário logado na aplicação.


SQL de referência

CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT, -- ou INT AUTO_INCREMENT
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    tipo_usuario VARCHAR(20) DEFAULT 'cliente' -- 'cliente' ou 'admin'
);





Cenário B — Gestão de Documentos (Download de Faturas/PDFs)
Objetivo de Negócio: Endpoint/Backend responsável por disponibilizar o download de comprovantes ou faturas em PDF de clientes.

SQL de referência

CREATE TABLE documentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- 'fatura', 'recibo', 'contrato'
    nome_arquivo VARCHAR(255) NOT NULL, -- ex: 'fatura_1042.pdf'
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
