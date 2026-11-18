# Biblioteca Joomla y perfiles de comunidad

## Preparar la importación

1. Activa **Goldensite Storage & Downloads** para habilitar muestras, descargas y aportes.
2. Si tienes un archivo Akeeba completo, extráelo en una carpeta privada con la herramienta de Akeeba. Conserva todas las partes. El asistente de Goldensite trabaja con los archivos ya extraídos.
3. Importa artículos y cuentas en **Configuración → Importar desde WordPress o Joomla**.
4. En `storage/joomla-media-inbox`, coloca los SQL de Joomla y jDownloads con los nombres `site.sql` y `jdownloads.sql`. Coloca en esa misma bandeja los archivos originales y las muestras, respetando los nombres de archivo.
5. En **Import / Export → Abrir asistente Joomla** analiza los SQL con su prefijo. Confirma la carga con tu contraseña. Se crea un respaldo SQL privado con el servicio de copias de Goldensite.
6. Analiza los archivos y revisa los candidatos por rango de ID. Vincula hasta 25 por operación. Repite el rango si quedan candidatos y luego avanza con el último ID mostrado. Las coincidencias ambiguas se excluyen. Se aceptan originales MIDI/KAR y muestras MP3, WAV u Ogg, de hasta 20 MiB por archivo.
7. Configura por ficha quién puede verla, escuchar la muestra y descargar el original. La visibilidad de Joomla sigue siendo un límite superior. Las muestras y los originales importados quedan cerrados hasta habilitar sus permisos.

La biblioteca se abre en `/biblioteca`. La importación deja la portada normal del sitio seleccionada. Los archivos vinculados se copian al almacén privado de Goldensite. El original de Joomla y la copia local quedan separados. El asistente coteja nombres únicos y verifica la integridad de la copia con SHA-256. Revisa la procedencia de cada candidato antes de confirmarlo.

## Perfiles y aportes

**Configuración → Community Profiles** define los campos. Cada cuenta edita su perfil en `/comunidad/mi-perfil`. El perfil, los campos y el historial requieren una elección explícita de privacidad. El historial muestra aportes aprobados en Goldensite.

Los miembros envían MIDI o KAR desde una categoría pública. El administrador revisa los aportes en **Storage & Downloads → Gestionar catálogo y aportes**. Una vez aprobado, el original requiere una cuenta activa.

## Rutas y sitemap

Las rutas conocidas de jDownloads responden con 301 hacia `/biblioteca/ficha/ID` cuando la ficha es visible. El sitemap de la biblioteca enumera exclusivamente las fichas públicas y utiliza la URL principal. El sitemap general conserva las páginas de Goldensite.

## Referencias

- https://www.akeeba.com/documentation/akeeba-backup-joomla/restoring-backups.html
- https://jdownloads.net/documentation?catid=32&id=211%3Acontrolled-access-to-categories-and-downloads-permissions-access-levels&view=article
