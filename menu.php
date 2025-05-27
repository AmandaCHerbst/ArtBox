<?php
session_start();
?>
<style>
  .navbar { display: flex; align-items: center; justify-content: space-between; background: #333; color: #fff; padding: 10px 20px; }
  .navbar .logo { font-size: 1.5rem; font-weight: bold; text-decoration: none; color: #fff; }
  .navbar .search { flex: 1; margin: 0 20px; }
  .navbar .search input { width: 100%; padding: 5px 10px; border-radius: 4px; border: none; }
  .navbar .icons a { margin-left: 15px; text-decoration: none; color: #fff; font-size: 1.2rem; }
</style>
<nav class="navbar">
  <a href="index.php" class="logo">ARTBOX</a>
  <div class="search">
    <form action="index.php" method="get">
      <input type="text" name="q" placeholder="Pesquisar produtos...">
    </form>
  </div>
  <div class="icons">
    <a href="<?= isset($_SESSION['user_id']) ? 'profile.php' : 'login.php' ?>">
      <span>&#128100;</span>
    </a>
    <a href="cart.php">
      <span>&#128722;</span>
    </a>
  </div>
</nav>
