<?php $status = $_SESSION['migrator_status'] ?? null; unset($_SESSION['migrator_status']); ?>
<h1>Importación de artículos JSON</h1>
<p style="margin:1rem 0">Importa de 1 a 1000 artículos, hasta 5 MB. Se guardan como borradores de texto sin código ejecutable. No se importan medios ni se ejecutan archivos SQL/XML. Las URLs deben revisarse antes de publicar.</p>
<?php if ($status): ?><p role="status"><?= htmlspecialchars($status['msg'], ENT_QUOTES, 'UTF-8') ?></p><?php endif ?>
<p>Formato de entrada:</p><pre style="white-space:pre-wrap;margin:1rem 0">{"posts":[{"title":"Mi artículo","slug":"mi-articulo","content":"Texto del artículo"}]}</pre>
<form action="/admin/migrator/import" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>"><label>Archivo JSON<input type="file" name="export_file" accept=".json,application/json" required></label><button style="padding:12px;background:#38bdf8;color:#0f172a;margin:1rem 0">Importar borradores</button></form>
