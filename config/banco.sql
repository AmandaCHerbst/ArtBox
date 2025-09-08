create schema if not exists ArtBoxBanco
  default character set utf8mb4
  default collate utf8mb4_unicode_ci;
use ArtBoxBanco;

create table if not exists usuarios (
    idUSUARIO INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomeUSUARIO VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    tipo_usuario ENUM('normal', 'artesao') NOT NULL DEFAULT 'normal',
    usuario VARCHAR(100) NOT NULL, 
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NOT NULL DEFAULT 'default.png';
ALTER TABLE usuarios ADD COLUMN cpfUSUARIO VARCHAR(14) AFTER email;

create table if not exists categorias (
    idCATEGORIA INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomeCATEGORIA VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS produtos (
    idPRODUTO INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomePRODUTO VARCHAR(150) NOT NULL,
    descricaoPRODUTO TEXT NOT NULL,
    nome_tipologia VARCHAR(50) DEFAULT NULL,
    nome_especificacao VARCHAR(50) DEFAULT NULL,
    precoPRODUTO DECIMAL(10,2) NOT NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 0,
    imagemPRODUTO VARCHAR(255),
    id_categoria INT UNSIGNED,
    id_artesao INT UNSIGNED NOT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES categorias(idCATEGORIA)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_artesao) REFERENCES usuarios(idUSUARIO)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produto_categorias (
  idProdutoCategoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_produto          INT UNSIGNED NOT NULL,
  id_categoria        INT UNSIGNED NOT NULL,
  FOREIGN KEY (id_produto)   REFERENCES produtos(idPRODUTO)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_categoria) REFERENCES categorias(idCATEGORIA)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produto_imagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_produto INT UNSIGNED NOT NULL,
  caminho VARCHAR(255) NOT NULL,
  FOREIGN KEY (id_produto) REFERENCES produtos(idPRODUTO)
);

CREATE TABLE variantes (
  idVARIANTE INT AUTO_INCREMENT PRIMARY KEY,
  id_produto INT UNSIGNED NOT NULL,
  valor_tipologia VARCHAR(50) NOT NULL,
  valor_especificacao VARCHAR(50) NOT NULL,
  estoque INT NOT NULL,
  FOREIGN KEY (id_produto) REFERENCES produtos(idPRODUTO)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE favoritos (
    idFAVORITO INT AUTO_INCREMENT PRIMARY KEY,
    idUSUARIO INT UNSIGNED NOT NULL,
    idPRODUTO INT UNSIGNED NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY ux_favorito_usuario_produto (idUSUARIO, idPRODUTO),
    FOREIGN KEY (idUSUARIO) REFERENCES usuarios(idUSUARIO) ON DELETE CASCADE,
    FOREIGN KEY (idPRODUTO) REFERENCES produtos(idPRODUTO) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

create table if not exists pedidos (
    idPEDIDO INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'pago', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(idUSUARIO)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

create table if not exists itens_pedido (
    idintens_pedido INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT UNSIGNED NOT NULL,
    id_produto INT UNSIGNED NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(idPEDIDO)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(idPRODUTO)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
ALTER TABLE itens_pedido ADD COLUMN id_variante INT UNSIGNED AFTER id_produto;

CREATE TABLE pedidos_artesao (
  idPEDIDOS_ARTESAO INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido INT UNSIGNED NOT NULL,
  id_artesao INT UNSIGNED NOT NULL,
  status ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
  data_atualizacao  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (id_pedido)  REFERENCES pedidos(idPEDIDO)  ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_artesao) REFERENCES usuarios(idUSUARIO) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comentarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  usuario_id INT NULL,
  nota TINYINT NOT NULL,
  comentario TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (produto_id)
);

