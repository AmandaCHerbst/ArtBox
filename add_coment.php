<?php
// add_coment.php (versão aprimorada)
// Autor: adaptado para o projeto da Anabel
session_start();
require __DIR__ . '/config/config.inc.php'; // deve definir $pdo (ou DSN/USUARIO/SENHA constants)

if (empty($_SESSION['idUSUARIO']) && empty($_SESSION['user_id'])) {
    // se quiser permitir anônimo, comente linhas abaixo
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['idUSUARIO'] ?? $_SESSION['user_id'] ?? null;

// Garante instância PDO caso config não tenha setado $pdo
if (!isset($pdo)) {
    if (defined('DSN') && defined('USUARIO') && defined('SENHA')) {
        try {
            $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            die('Erro no BD: ' . htmlspecialchars($e->getMessage()));
        }
    } else {
        die('Conexão ao banco não encontrada. Verifique config.inc.php');
    }
}

$errors = [];
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : (int)($_POST['produto_id'] ?? 0);
$comentario_val = $_POST['comentario'] ?? '';
$nota_val = isset($_POST['nota']) ? (int)$_POST['nota'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $nota = isset($_POST['nota']) ? (int)$_POST['nota'] : 0;
    $comentario = trim($_POST['comentario'] ?? '');

    if ($produto_id <= 0) $errors[] = 'Produto inválido.';
    if ($nota < 1 || $nota > 5) $errors[] = 'Por favor selecione uma nota entre 1 e 5 estrelas.';
    if (mb_strlen($comentario) > 1000) $errors[] = 'Comentário muito longo (máx 1000 caracteres).';

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO comentarios (produto_id, usuario_id, nota, comentario) VALUES (:produto_id, :usuario_id, :nota, :comentario)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $usuario_id ?: null, PDO::PARAM_INT);
            $stmt->bindValue(':nota', $nota, PDO::PARAM_INT);
            $stmt->bindValue(':comentario', $comentario, PDO::PARAM_STR);
            $stmt->execute();

            // redireciona, se quiser outro local mude a URL
            header('Location: produto_ampliado.php?id=' . $produto_id . '#comentarios');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao salvar avaliação: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        // manter valores para repopular form
        $comentario_val = htmlspecialchars($comentario);
        $nota_val = $nota;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deixe sua avaliação</title>
<style>
/* Layout limpo e responsivo */
:root{
  --accent:#5C3A21;
  --accent-2:#A95C38;
  --muted:#6b7280;
  --star-size:2.4rem;
}
*{box-sizing:border-box}
body{
  font-family:Inter, "Segoe UI", Arial, sans-serif;
  background:#f7f7f8;
  color:#222;
  margin:0;
  padding:24px;
  display:flex;
  justify-content:center;
}
.container{
  width:100%;
  max-width:760px;
  background:#fff;
  border-radius:12px;
  padding:22px;
  box-shadow:0 6px 24px rgba(15,15,15,0.06);
}
h1{margin:0 0 12px; color:var(--accent)}
p.lead{margin:0 0 18px; color:var(--muted)}

/* Erros */
.errors{background:#fff0f0;border:1px solid #f2c6c6;color:#9a1f1f;padding:10px;border-radius:8px;margin-bottom:12px}

/* Estrelas */
.star-row{
  display:flex;
  gap:8px;
  align-items:center;
}
.stars{
  display:inline-flex;
  gap:6px;
  user-select:none;
}
.star{
  font-size:var(--star-size);
  width:var(--star-size);
  height:var(--star-size);
  display:inline-grid;
  place-items:center;
  color:#ddd;
  cursor:pointer;
  transition: transform .08s ease, color .12s ease;
  border-radius:6px;
}
.star:hover{ transform:translateY(-3px); }
.star.filled{ color:#f5b301; text-shadow:0 1px 0 rgba(0,0,0,0.06); }

/* hidden radios for accessibility (screen readers) */
.star-inputs{ position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden; }

/* Comentário */
label{display:block;margin-bottom:6px;font-weight:600;color:#333}
textarea{width:100%;min-height:110px;padding:12px;border-radius:8px;border:1px solid #e6e6e9;font-size:0.95rem;resize:vertical}
.form-row{margin:12px 0}

/* Botões */
.btn{
  display:inline-block;
  background:var(--accent);
  color:#fff;
  border:none;
  padding:10px 16px;
  border-radius:10px;
  text-decoration:none;
  font-weight:700;
  cursor:pointer;
}
.btn.secondary{ background:transparent;color:var(--accent);border:1px solid #e6e6e9; }
.help{font-size:0.88rem;color:var(--muted);margin-top:8px}

/* footer link */
.back-link{display:block;margin-top:18px;color:var(--accent-2);text-decoration:none;font-weight:600}
.back-link:hover{text-decoration:underline}
@media (max-width:520px){
  .star{font-size:1.9rem;width:1.9rem;height:1.9rem}
}
</style>
</head>
<body>
  <div class="container" role="main">
    <h1>Deixe sua avaliação</h1>
    <p class="lead">Avalie o produto e conte como foi sua experiência — sua opinião ajuda outros compradores e o artesão :</p>

    <?php if (!empty($errors)): ?>
      <div class="errors" role="alert">
        <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="add_coment.php" autocomplete="off" aria-labelledby="titulo-form">
      <input type="hidden" name="produto_id" value="<?= (int)$produto_id ?>">

      <div class="form-row" aria-hidden="false">
        <label for="nota">Nota</label>
        <div class="star-row">
          <div class="stars" id="stars" role="radiogroup" aria-label="Nota do produto">
            <!-- visual stars (javascript controlará preenchimento e também atualizará os radios abaixo) -->
            <span class="star" data-value="1" tabindex="0" aria-label="1 estrela" role="radio" aria-checked="<?= $nota_val==1 ? 'true':'false' ?>">★</span>
            <span class="star" data-value="2" tabindex="0" aria-label="2 estrelas" role="radio" aria-checked="<?= $nota_val==2 ? 'true':'false' ?>">★</span>
            <span class="star" data-value="3" tabindex="0" aria-label="3 estrelas" role="radio" aria-checked="<?= $nota_val==3 ? 'true':'false' ?>">★</span>
            <span class="star" data-value="4" tabindex="0" aria-label="4 estrelas" role="radio" aria-checked="<?= $nota_val==4 ? 'true':'false' ?>">★</span>
            <span class="star" data-value="5" tabindex="0" aria-label="5 estrelas" role="radio" aria-checked="<?= $nota_val==5 ? 'true':'false' ?>">★</span>
          </div>

          <!-- radios escondidos (para submissão / acessibilidade) -->
          <div class="star-inputs" aria-hidden="true">
            <input type="radio" id="r1" name="nota" value="1" <?= $nota_val==1 ? 'checked' : '' ?>>
            <input type="radio" id="r2" name="nota" value="2" <?= $nota_val==2 ? 'checked' : '' ?>>
            <input type="radio" id="r3" name="nota" value="3" <?= $nota_val==3 ? 'checked' : '' ?>>
            <input type="radio" id="r4" name="nota" value="4" <?= $nota_val==4 ? 'checked' : '' ?>>
            <input type="radio" id="r5" name="nota" value="5" <?= $nota_val==5 ? 'checked' : '' ?>>
          </div>
        </div>
      </div>

      <div class="form-row">
        <label for="comentario">Comentário (opcional)</label>
        <textarea id="comentario" name="comentario" maxlength="1000" placeholder="Escreva aqui sua opinião..."><?= htmlspecialchars($comentario_val) ?></textarea>
        <div class="help"><small><span id="charcount"><?= mb_strlen(trim($comentario_val)) ?></span>/1000</small></div>
      </div>

      <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
        <button type="submit" class="btn">Enviar avaliação</button>
        <a class="btn secondary" href="avaliar.php">Voltar sem avaliar</a>
      </div>
    </form>

    <a class="back-link" href="perfil_normal.php">&larr; Voltar ao Perfil</a>
  </div>

<script>
// Script que faz a interação das estrelas (hover, clique, teclado)
// Não depende de bibliotecas
(function(){
  const stars = Array.from(document.querySelectorAll('.star'));
  const radios = Array.from(document.querySelectorAll('.star-inputs input[name="nota"]'));
  const charcount = document.getElementById('charcount');
  const comentario = document.getElementById('comentario');

  // preenchimento visual até index (1..n)
  function paint(to) {
    stars.forEach(s => {
      const v = Number(s.dataset.value);
      if (v <= to) s.classList.add('filled'); else s.classList.remove('filled');
      s.setAttribute('aria-checked', v === to ? 'true' : 'false');
    });
  }

  // recupera nota atual (radio checked) ou 0
  function current() {
    const r = radios.find(r => r.checked);
    return r ? Number(r.value) : 0;
  }

  // inicial: se houver nota do POST, pinta
  paint(current());

  stars.forEach(st => {
    const val = Number(st.dataset.value);

    // hover
    st.addEventListener('mouseenter', ()=> paint(val));
    st.addEventListener('focus', ()=> paint(val));

    // hover out -> retorna para seleção atual
    st.addEventListener('mouseleave', ()=> paint(current()));
    st.addEventListener('blur', ()=> paint(current()));

    // clique -> marca radio real
    st.addEventListener('click', ()=> {
      const r = radios.find(r => Number(r.value) === val);
      if (r) {
        r.checked = true;
        // dispara evento change (se precisar)
        r.dispatchEvent(new Event('change', {bubbles:true}));
      }
      paint(val);
    });

    // teclado: Enter/Space seleciona, left/right altera
    st.addEventListener('keydown', (ev)=>{
      if (ev.key === 'Enter' || ev.key === ' '){
        ev.preventDefault();
        st.click();
        st.focus();
      } else if (ev.key === 'ArrowLeft' || ev.key === 'ArrowDown') {
        ev.preventDefault();
        const prev = stars[Math.max(0, stars.indexOf(st)-1)];
        prev && prev.focus();
      } else if (ev.key === 'ArrowRight' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        const next = stars[Math.min(stars.length-1, stars.indexOf(st)+1)];
        next && next.focus();
      }
    });
  });

  // atualiza contagem de caracteres
  if (comentario && charcount) {
    comentario.addEventListener('input', ()=>{
      charcount.textContent = comentario.value.length;
    });
  }
})();
</script>
</body>
</html>

