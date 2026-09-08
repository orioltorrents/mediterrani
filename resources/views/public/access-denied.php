<?php
ob_start();
?>
<section class="page-header">
    <h1>Accés restringit</h1>
    <p>No tens permís per veure aquesta edició del projecte.</p>
    <a class="button" href="<?= url('dashboard') ?>">Torna al teu espai</a>
</section>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
