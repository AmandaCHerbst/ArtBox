<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<head>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    .navbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(0, 0, 0, 0.7);
      color: #fff;
      padding: 10px 20px;
      flex-wrap: wrap;
      position: sticky;
      top: 0;
      z-index: 1000;
      backdrop-filter: blur(5px);
    }
    .navbar .logo {
      font-size: 1.8rem;
      font-family: 'Georgia', serif;
      letter-spacing: 1px;
      font-weight: bold;
      text-decoration: none;
      color: #ffffff;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.4);
    }
    .navbar .search {
      flex: 1;
      display: flex;
      justify-content: center;
      position: relative;
      max-width: 600px;
      margin: 10px auto;
    }
    .navbar .search form {
      width: 100%;
      position: relative;
    }
    .navbar .search input {
      width: 100%;
      padding: 10px 45px 10px 15px;
      border-radius: 25px;
      border: none;
      outline: none;
      font-size: 1rem;
    }
    .navbar .search button {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #fff;
      font-size: 1.8rem;
    }
    .navbar .icons a {
      margin-left: 15px;
      text-decoration: none;
      color: #fff;
      font-size: 2.4rem;
    }
  </style>
</head>
<nav class="navbar">
  <a href="index.php" class="logo">ARTBOX</a>
  <div class="search">
    <form action="index.php" method="get">
      <input type="text" name="q" placeholder="Pesquisar produtos...">
      <button type="submit"><span class="material-icons">search</span></button>
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
    <a href="cart.php"><span class="material-icons" aria-hidden="true">shopping_cart</span></a>
  </div>
</nav>
