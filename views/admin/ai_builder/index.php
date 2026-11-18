<?php 
use App\Core\Csrf; 
use App\Core\Settings;

$csrfToken = Csrf::generate(); 

$activeTheme   = Settings::get('active_theme', 'dark-cyber');
$customPrimary = Settings::get('theme_primary_color', '');
$customBg      = Settings::get('theme_bg_color', '');
$customCardBg  = Settings::get('theme_card_bg', '');
$customText    = Settings::get('theme_text_color', '');
$customFont    = Settings::get('theme_font_family', 'Segoe UI, sans-serif');
$customRadius  = Settings::get('theme_border_radius', '8px');
$customCss     = Settings::get('theme_custom_css', '');

$themePalettes = [
    'dark-cyber'      => ['bg' => '#070913', 'accent' => '#38bdf8', 'card' => '#1e293b', 'text' => '#ffffff'],
    'agency-creative' => ['bg' => '#0f172a', 'accent' => '#6366f1', 'card' => '#1e1b4b', 'text' => '#ffffff'],
    'cyber-sec'       => ['bg' => '#020617', 'accent' => '#22c55e', 'card' => '#022c22', 'text' => '#ffffff'],
    'e-store'         => ['bg' => '#1e1b4b', 'accent' => '#f59e0b', 'card' => '#312e81', 'text' => '#ffffff']
];

$palette = $themePalettes[$activeTheme] ?? $themePalettes['dark-cyber'];

$finalBg     = !empty($customBg) ? $customBg : $palette['bg'];
$finalAccent = !empty($customPrimary) ? $customPrimary : $palette['accent'];
$finalCard   = !empty($customCardBg) ? $customCardBg : $palette['card'];
$finalText   = !empty($customText) ? $customText : $palette['text'];
?>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.8rem; font-weight: 800; color: #f8fafc; margin-bottom: 0.25rem;">🤖 AI Site & App Generator PRO</h1>
        <p style="color: #94a3b8; font-size: 0.9rem;">Escribe en lenguaje natural o selecciona un modelo para generar el sitio web, la base de datos y la App Android.</p>
    </div>

    <div>
        <?php if (!empty($isAiActive)): ?>
            <form action="/admin/ai-builder" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                
                <input type="hidden" name="action" value="disable_ai_frontend">
                <button type="submit" style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">
                    🔴 Restaurar Diseño Base
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($status)): ?>
    <div style="background: <?= $status['type'] === 'success' ? 'rgba(34, 197, 94, 0.15)' : ($status['type'] === 'warning' ? 'rgba(245, 158, 11, 0.15)' : 'rgba(239, 68, 68, 0.15)') ?>; border: 1px solid <?= $status['type'] === 'success' ? '#22c55e' : ($status['type'] === 'warning' ? '#f59e0b' : '#ef4444') ?>; color: <?= $status['type'] === 'success' ? '#4ade80' : ($status['type'] === 'warning' ? '#fde047' : '#fca5a5') ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold;">
        <?= htmlspecialchars($status['msg']) ?>
    </div>
<?php endif; ?>

<!-- Layout Studio Principal -->
<div style="display: grid; grid-template-columns: 420px 1fr; gap: 1.5rem; align-items: start;">
    
    <!-- Columna 1: Asistente IA y Lenguaje Natural -->
    <div style="background: #1e293b; border: 1px solid #38bdf8; border-radius: 14px; padding: 1.5rem; box-shadow: 0 10px 30px rgba(56, 189, 248, 0.15);">
        
        <h2 style="font-size: 1.1rem; color: #38bdf8; font-weight: bold; margin-bottom: 0.5rem;">✨ 1. Prompt en Lenguaje Natural</h2>
        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 1rem;">Describe tu proyecto de negocio con tus propias palabras:</p>

        <!-- Descripción en Lenguaje Natural -->
        <textarea id="promptInput" rows="4" placeholder="Ej: Tengo una peluquería llamada Bella Hair. Quiero una página moderna donde aparezcan mis servicios, precios, horarios, ubicación, galería de fotos, contacto y reservas." style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.8rem; border-radius: 8px; font-size: 0.85rem; outline: none; margin-bottom: 1rem; resize: vertical;"></textarea>

        <button type="button" onclick="analyzeNaturalLanguagePrompt()" style="width: 100%; background: #38bdf8; color: #0f172a; border: none; padding: 0.75rem; border-radius: 8px; font-weight: bold; font-size: 0.88rem; cursor: pointer; margin-bottom: 1.25rem;">
            🧠 Analizar Intención y Módulos
        </button>

        <!-- Selección de Modelo Predeterminado -->
        <label style="display: block; font-size: 0.8rem; font-weight: bold; color: #cbd5e1; margin-bottom: 0.4rem;">O Selecciona un Modelo de Negocio:</label>
        <select id="presetSelect" onchange="onPresetChange()" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.65rem; border-radius: 8px; font-size: 0.85rem; outline: none; margin-bottom: 1.25rem;">
            <option value="salon">✂️ Peluquería & Barbería (Bella Hair / Salon VIP)</option>
            <option value="restaurant">🍕 Restaurante Gourmet & Gastronomía PRO</option>
            <option value="dealer">🚘 Dealer & Catálogo de Autos Deportivos</option>
            <option value="workshop">🔧 Taller Mecánico Automotriz & Servicios</option>
            <option value="realestate">🏢 Inmobiliaria & Venta de Propiedades</option>
            <option value="generic">⚙️ Generic Mode (Módulos Manuales)</option>
        </select>

        <!-- Módulos Detectados por IA -->
        <div id="detectedModulesBox" style="background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 0.85rem; margin-bottom: 1.25rem;">
            <div style="font-size: 0.75rem; color: #94a3b8; font-weight: bold; text-transform: uppercase; margin-bottom: 0.5rem;">🧩 Módulos & Tablas a Generar:</div>
            <div id="moduleTags" style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                <span style="background: rgba(56,189,248,0.2); color:#38bdf8; font-size:0.7rem; padding:0.2rem 0.5rem; border-radius:4px;">Servicios</span>
                <span style="background: rgba(56,189,248,0.2); color:#38bdf8; font-size:0.7rem; padding:0.2rem 0.5rem; border-radius:4px;">Precios</span>
                <span style="background: rgba(56,189,248,0.2); color:#38bdf8; font-size:0.7rem; padding:0.2rem 0.5rem; border-radius:4px;">Reservas</span>
                <span style="background: rgba(56,189,248,0.2); color:#38bdf8; font-size:0.7rem; padding:0.2rem 0.5rem; border-radius:4px;">Galería</span>
                <span style="background: rgba(56,189,248,0.2); color:#38bdf8; font-size:0.7rem; padding:0.2rem 0.5rem; border-radius:4px;">Contacto</span>
            </div>
        </div>

        <form action="/admin/ai-builder" method="POST" id="publishForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
            
            <input type="hidden" name="action" value="publish_layout">
            <input type="hidden" name="business_type" id="hiddenBusinessType" value="salon">
            <input type="hidden" name="app_name" id="hiddenAppName" value="BellaHair">
            <textarea name="html_code" id="hiddenHtmlCode" style="display: none;"></textarea>

            <button type="submit" id="btnPublish" style="width: 100%; background: #22c55e; color: #0f172a; border: none; padding: 0.85rem; border-radius: 8px; font-weight: 900; font-size: 0.95rem; cursor: pointer;">
                🚀 Generar Sitio Web, DB & App Android
            </button>
        </form>
    </div>

    <!-- Columna 2: Previsualizador en Vivo y Vista Dispositivos -->
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 1.25rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h2 style="font-size: 1.1rem; color: #4ade80; font-weight: bold;">👁️ Vista Previa en Tiempo Real</h2>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" onclick="setDeviceView('100%')" style="background:#0f172a; color:#fff; border:1px solid #475569; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.75rem; cursor:pointer;">🖥️ Desktop</button>
                <button type="button" onclick="setDeviceView('768px')" style="background:#0f172a; color:#fff; border:1px solid #475569; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.75rem; cursor:pointer;">📱 Tablet</button>
                <button type="button" onclick="setDeviceView('375px')" style="background:#0f172a; color:#fff; border:1px solid #475569; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.75rem; cursor:pointer;">📱 Móvil</button>
            </div>
        </div>

        <div style="border: 1px solid #334155; border-radius: 8px; overflow: hidden; background: #ffffff; height: 640px; display: flex; justify-content: center; background:#070913;">
            <iframe id="previewFrame" style="width: 100%; height: 100%; border: none; transition: width 0.3s ease;"></iframe>
        </div>
    </div>
</div>

<script>
const currentTheme = {
    bg: "<?= $finalBg ?>",
    accent: "<?= $finalAccent ?>",
    cardBg: "<?= $finalCard ?>",
    text: "<?= $finalText ?>",
    font: "<?= $customFont ?>",
    radius: "<?= $customRadius ?>",
    css: `<?= addslashes($customCss) ?>`
};

const businessTemplates = {
    salon: { logo: "✂️ BELLA HAIR SALON & SPA", heroTitle: "Estilo, Corte & Cuidado Capilar Profesional", links: ["Inicio", "Servicios", "Precios", "Reservas", "Contacto"], c1Title: "✂️ Corte & Styling Premium", c1Desc: "Asesoría de imagen y lavado nutritivo incluido.", c1Price: "$25.00", c2Title: "🎨 Tinte & Mechas Balayage", c2Desc: "Tratamiento hidratante protector del color.", c2Price: "$65.00", c3Title: "💅 Manicura & Pedicura Spa", c3Desc: "Esmaltado de larga duración e hidratación.", c3Price: "$30.00", btnText: "Reservar Turno", formTitle: "📅 Agendar Cita en Bella Hair" },
    restaurant: { logo: "🍕 RESTAURANTE GOURMET VIP", heroTitle: "Gastronomía Exclusiva & Alta Cocina", links: ["Menú", "Vinos", "Reservas", "Ubicación"], c1Title: "🥩 Corte Tomahawk Premium", c1Desc: "Carne madurada con guarnición especial.", c1Price: "$34.99", c2Title: "🍝 Pasta Trufada del Mare", c2Desc: "Salsa artesanal de trufas y mariscos.", c2Price: "$22.50", c3Title: "🍷 Vinos Reserva", c3Desc: "Cata exclusiva de tintos y espumosos.", c3Price: "$45.00", btnText: "Reservar Mesa", formTitle: "📅 Reserva tu Mesa Gourmet" },
    dealer: { logo: "🚘 AUTO SPORT DEALER", heroTitle: "Catálogo de Vehículos Deportivos & Alta Gama", links: ["Deportivos", "SUVs", "Eléctricos", "Contacto"], c1Title: "🏎️ Porsche 911 GT3 RS", c1Desc: "Motor 4.0L 525 HP, PDK 7 vel.", c1Price: "$225,000 USD", c2Title: "⚡ Nammi Box 01 Electric", c2Desc: "Autonomía 400km, carga rápida.", c2Price: "$24,900 USD", c3Title: "🚙 BMW X6 M Competition", c3Desc: "V8 TwinPower Turbo 625 HP.", c3Price: "$138,000 USD", btnText: "Solicitar Prueba", formTitle: "📝 Cotiza tu Vehículo" },
    generic: { logo: "⚡ CMS PROYECTO GENÉRICO", heroTitle: "Bienvenido a Tu Proyecto GoldenSiteCMS", links: ["Inicio", "Módulos", "Servicios", "Contacto"], c1Title: "⭐ Servicio o Producto 1", c1Desc: "Descripción del primer elemento destacado.", c1Price: "$19.99", c2Title: "⭐ Servicio o Producto 2", c2Desc: "Descripción de la opción secundaria.", c2Price: "$29.99", c3Title: "⭐ Servicio o Producto 3", c3Desc: "Descripción de la opción avanzada.", c3Price: "$49.99", btnText: "Solicitar Información", formTitle: "👤 Formulario de Contacto" }
};

function analyzeNaturalLanguagePrompt() {
    const text = document.getElementById('promptInput').value.toLowerCase();
    let type = 'generic';

    if (text.includes('peluquería') || text.includes('hair') || text.includes('barbería') || text.includes('corte') || text.includes('turno')) {
        type = 'salon';
    } else if (text.includes('restaurante') || text.includes('comida') || text.includes('menú') || text.includes('plato')) {
        type = 'restaurant';
    } else if (text.includes('auto') || text.includes('coche') || text.includes('dealer') || text.includes('vehículo')) {
        type = 'dealer';
    }

    document.getElementById('presetSelect').value = type;
    document.getElementById('hiddenBusinessType').value = type;
    generateAndPreview();
}

function onPresetChange() {
    const key = document.getElementById('presetSelect').value;
    document.getElementById('hiddenBusinessType').value = key;
    generateAndPreview();
}

function setDeviceView(width) {
    document.getElementById('previewFrame').style.width = width;
}

function generateAndPreview() {
    const presetKey = document.getElementById('presetSelect').value;
    const iframe = document.getElementById('previewFrame');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

    const tmpl = businessTemplates[presetKey] || businessTemplates.generic;

    const generatedHtml = `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${tmpl.logo}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: ${currentTheme.font}; }
        body { background: ${currentTheme.bg}; color: ${currentTheme.text}; line-height: 1.6; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 1.2rem 2rem; background: ${currentTheme.cardBg}; border-bottom: 2px solid ${currentTheme.accent}; }
        .logo { font-size: 1.3rem; font-weight: 900; color: ${currentTheme.accent}; }
        .nav-links { display: flex; gap: 1rem; list-style: none; }
        .nav-links a { color: ${currentTheme.text}; text-decoration: none; font-size: 0.85rem; font-weight: 700; }
        .hero { text-align: center; padding: 3.5rem 1.5rem; background: rgba(0,0,0,0.2); }
        .hero h1 { font-size: 2.2rem; color: ${currentTheme.text}; margin-bottom: 0.75rem; font-weight: 900; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
        .card { background: ${currentTheme.cardBg}; border: 1px solid ${currentTheme.accent}; border-radius: ${currentTheme.radius}; padding: 1.5rem; }
        .card h3 { color: ${currentTheme.accent}; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .price { font-size: 1.3rem; font-weight: 900; color: #22c55e; margin: 0.75rem 0; }
        .btn { background: ${currentTheme.accent}; color: #000; border-radius: ${currentTheme.radius}; padding: 0.65rem 1.2rem; border: none; font-weight: 900; cursor: pointer; text-decoration: none; display: inline-block; }
        @media (max-width: 768px) { .grid-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav>
        <div class="logo">${tmpl.logo}</div>
        <ul class="nav-links">
            ${tmpl.links.map(l => `<li><a href="#contacto">${l}</a></li>`).join('')}
        </ul>
    </nav>

    <div class="hero">
        <h1>${tmpl.heroTitle}</h1>
        <a href="#catalogo" class="btn">${tmpl.btnText}</a>
    </div>

    <div class="container">
        <h2 style="text-align:center; color:${currentTheme.accent}; margin-bottom:2rem;" id="catalogo">⭐ Catálogo & Módulos Activos</h2>
        <div class="grid-3">
            <div class="card">
                <h3>${tmpl.c1Title}</h3>
                <p>${tmpl.c1Desc}</p>
                <div class="price">${tmpl.c1Price}</div>
                <button class="btn" style="width:100%;">${tmpl.btnText}</button>
            </div>
            <div class="card">
                <h3>${tmpl.c2Title}</h3>
                <p>${tmpl.c2Desc}</p>
                <div class="price">${tmpl.c2Price}</div>
                <button class="btn" style="width:100%;">${tmpl.btnText}</button>
            </div>
            <div class="card">
                <h3>${tmpl.c3Title}</h3>
                <p>${tmpl.c3Desc}</p>
                <div class="price">${tmpl.c3Price}</div>
                <button class="btn" style="width:100%;">${tmpl.btnText}</button>
            </div>
        </div>
    </div>
</body>
</html>`;

    iframeDoc.open();
    iframeDoc.write(generatedHtml);
    iframeDoc.close();

    document.getElementById('hiddenHtmlCode').value = generatedHtml;
}

window.onload = function() {
    onPresetChange();
};
</script>