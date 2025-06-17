<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<head>
  <link rel="stylesheet" href="assets/css/menu.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<nav class="navbar">
  <a href="index.php" class="logo">ARTBOX</a>
  <div class="search">
    <form action="index.php" method="get">
      <input type="text" name="q" placeholder="Pesquisar produtos...">
    </form>
  </div>
  <div class="icons">
    <?php if (isset($_SESSION['idUSUARIO'])): ?>
      <a href="<?php
        echo ($_SESSION['tipo_usuario'] === 'artesao')
             ? 'perfil_artesao.php'
             : 'perfil_normal.php';
      ?>">
       <span class="material-icons" aria-hidden="true">account_circle</span>
      </a>
    <?php else: ?>
      <a href="login.php"><span class="material-icons" aria-hidden="true">account_circle</span></a>
    <?php endif; ?>
    <a href="cart.php"><span class="material-icons" aria-hidden="true">shopping_cart</span></span></a>
  </div>
</nav>

