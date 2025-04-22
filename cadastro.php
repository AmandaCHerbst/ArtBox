<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Produto - ARTBOX</title>
    <link rel="stylesheet" href="assets/css/cad-img.css">
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="assets/img/favicon.png" alt="Logo">
            <a href="home.php">ARTBOX</a>
        </div>
        <div class="search-bar">
            <input type="text" placeholder="Buscar...">
            <button>🔍</button>
        </div>
        <div class="profile">
            <img src="assets/img/user.svg" alt="Perfil">
        </div>
    </div>

    <div class="main-content">
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="image-upload">
                <label for="upload-image" class="image-placeholder">
                    <span>Adicionar Imagem +</span>
                    <input type="file" id="upload-image" name="product-image" class="upload-image-input" required>
                </label>
            </div>

            <div class="product-info">
                <div class="form-group">
                    <label for="product-name">Nome do Produto</label>
                    <input type="text" id="product-name" name="product-name" placeholder="" required>
                </div>

                <div class="form-group sizes">
                    <label>Tamanhos Disponíveis</label>
                    <div class="sizes-options">
                        <label><input type="checkbox" name="sizes[]" value="Único"> Único</label>
                        <label><input type="checkbox" name="sizes[]" value="PP"> PP</label>
                        <label><input type="checkbox" name="sizes[]" value="P"> P</label>
                        <label><input type="checkbox" name="sizes[]" value="M"> M</label>
                        <label><input type="checkbox" name="sizes[]" value="G"> G</label>
                        <label><input type="checkbox" name="sizes[]" value="GG"> GG</label>
                        <label><input type="checkbox" name="sizes[]" value="XG"> XG</label>
                        <label><input type="checkbox" name="sizes[]" value="XGG"> XGG</label>
                    </div>
                </div>

                <div class="form-group colors">
                    <label>Cores Disponíveis</label>
                    <div>
                        <input type="color" name="color" id="color">
                    </div>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantidade Disponível</label>
                    <select id="quantity" name="quantity" required>
                        <option value="">Selecione...</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>

                <div class="form-group price">
                    <label>Preço</label>
                    <div class="price-input">
                        <span>R$</span>
                        <input type="number" name="price" placeholder="00,00" step="0.01" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Cadastrar Produto</button>
            </div>
        </form>
    </div>
</body>
</html>
