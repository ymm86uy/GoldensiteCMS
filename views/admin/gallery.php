<div class="container-fluid" style="padding: 1rem 0;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; display: flex; align-items: center; gap: 1rem;">
            🖼️ Galería de Imágenes & Motion FX
            <span style="background: #0284c7; color: #fff; font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: bold;">MÓDULO GRATUITO</span>
        </h1>
        <p style="color: #94a3b8; font-size: 0.95rem;">Crea una galería y pega su shortcode en la página donde quieras mostrarla. Con Carrusel 3D puedes publicar tarjetas con título y enlace.</p>
    </div>

    <!-- Formulario de Creación de Galería -->
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: bold; color: #fff; margin-bottom: 1.5rem;">🎨 Configurar Nueva Galería</h2>

        <form action="/admin/gallery/save" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="color: #f8fafc; font-weight: bold; display: block; margin-bottom: 0.5rem;">Título de la Galería:</label>
                    <input type="text" name="title" required placeholder="Ej: Portafolio de Diseños 2026" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.75rem; border-radius: 6px;">
                </div>

                <div>
                    <label style="color: #f8fafc; font-weight: bold; display: block; margin-bottom: 0.5rem;">Tipo de Maquetación (Layout):</label>
                    <select name="layout_type" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.75rem; border-radius: 6px;">
                        <option value="masonry">🧱 Grid Masonry Dinámico</option>
                        <option value="carousel">🎠 Carrusel 3D Interactivo</option>
                        <option value="slider">🎞️ Slider Full-Screen de Presentación</option>
                        <option value="grid">📐 Malla Uniforme (Grid Clean)</option>
                    </select>
                </div>

                <div>
                    <label style="color: #f8fafc; font-weight: bold; display: block; margin-bottom: 0.5rem;">Efectos de Entrada y Salida (FX):</label>
                    <select name="animation_fx" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #38bdf8; font-weight: bold; padding: 0.75rem; border-radius: 6px;">
                        <option value="zoom-fade">🔍 Fade Zoom & Elevation</option>
                        <option value="3d-rotate">🔄 Rotación 3D Flip Speed</option>
                        <option value="slide-parallax">↔️ Slide Parallax Horizontal</option>
                        <option value="bounce-in">⚽ Bounce Dynamic Drop</option>
                    </select>
                </div>
            </div>

            <!-- Entrada de URLs de Imágenes -->
            <div style="margin-bottom: 1.5rem; background: #0f172a; padding: 1.25rem; border-radius: 8px; border: 1px solid #334155;">
                <label style="color: #38bdf8; font-weight: bold; display: block; margin-bottom: 0.5rem;">URLs de Imágenes (Una por fila):</label>
                <textarea name="images[]" rows="4" aria-describedby="gallery-card-format" placeholder="/uploads/imagen1.jpg | Colección de verano | /coleccion&#10;https://ejemplo.com/imagen2.jpg" style="width: 100%; background: #1e293b; border: 1px solid #475569; color: #fff; padding: 0.75rem; border-radius: 6px; font-family: monospace;"></textarea>
                <p id="gallery-card-format">Para Carrusel 3D, escribe <code>imagen | título | enlace</code>. El título y el enlace son opcionales. Para las demás galerías, escribe una URL por línea.</p>
            </div>

            <button type="submit" style="background: #38bdf8; color: #0f172a; border: none; padding: 0.85rem 1.5rem; font-weight: bold; border-radius: 6px; cursor: pointer;">
                ✨ Guardar & Publicar Galería
            </button>
        </form>
    </div>

    <!-- Lista de Galerías Creadas -->
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
        <h3 style="font-size: 1.15rem; font-weight: bold; color: #fff; margin-bottom: 1rem;">📜 Galerías Activas</h3>
        
        <?php if (empty($galleries)): ?>
            <p style="color: #94a3b8;">No se han creado galerías aún.</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                <?php foreach ($galleries as $gal): ?>
                    <div style="background: #0f172a; border: 1px solid #334155; padding: 1rem; border-radius: 8px;">
                        <h4 style="color: #38bdf8; margin: 0 0 0.5rem; font-size: 1rem;"><?= htmlspecialchars($gal['title']) ?></h4>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin: 0 0 0.5rem;">Diseño: <strong><?= htmlspecialchars($gal['layout_type']) ?></strong> | FX: <strong><?= htmlspecialchars($gal['animation_fx']) ?></strong></p>
                        <code style="background: #1e293b; color: #f59e0b; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">[goldensite_gallery id="<?= $gal['id'] ?>"]</code>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
