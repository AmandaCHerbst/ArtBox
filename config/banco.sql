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


create table if not exists categorias (
    idCATEGORIA INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomeCATEGORIA VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS produto_categorias (
  idProdutoCategoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_produto          INT UNSIGNED NOT NULL,
  id_categoria        INT UNSIGNED NOT NULL,
  FOREIGN KEY (id_produto)   REFERENCES produtos(idPRODUTO)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_categoria) REFERENCES categorias(idCATEGORIA)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos (
    idPRODUTO INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomePRODUTO VARCHAR(150) NOT NULL,
    descricaoPRODUTO TEXT NOT NULL,
    tamanhos_disponiveis VARCHAR(255) DEFAULT NULL,
    cores_disponiveis VARCHAR(255) DEFAULT NULL,
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
