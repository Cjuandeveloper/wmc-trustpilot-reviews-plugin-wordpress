<h1 align="center">WMC Trustpilot Reviews – Opiniones reales, confianza visible.</h1>

<p align="center">
  <strong>Desarrollado por <a href="https://webmastercol.com" target="_blank">Webmastercol®</a></strong><br>
  Extrae, organiza y muestra reseñas verificadas desde Trustpilot directamente en tu sitio WordPress.<br>
  Convierte la experiencia y confianza de tus clientes en tu mejor argumento de venta.
</p>

---

## 🧭 Descripción general

**WMC Trustpilot Reviews** es un plugin corporativo para WordPress que permite **integrar automáticamente reseñas verificadas de Trustpilot** en tu sitio web. Analiza el perfil público de tu empresa, extrae reseñas, las cachea inteligentemente y las presenta en formatos dinámicos: **lista, resumen o carrusel**. Este proyecto forma parte del portafolio de soluciones digitales de **Webmastercol®**, orientado a fortalecer la **confianza online**, **la reputación de marca** y **la optimización de conversión** de negocios en línea.

---

## ⚙️ Características principales

- 🔍 **Extracción avanzada** desde perfiles públicos de Trustpilot con múltiples fuentes (JSON-LD, NEXT_DATA, HTML fallback).  
- ⚡ **Caché inteligente** con `transients` para optimizar rendimiento y evitar bloqueos.  
- 💬 **Shortcodes universales** para mostrar listas, resúmenes o carruseles de reseñas:  
  - `[wmc_trustpilot_reviews_list]`  
  - `[wmc_trustpilot_summary]`  
  - `[wmc_trustpilot_reviews_carousel]`  
- 🎨 **Diseño responsivo y personalizable**, con microdatos `schema.org` (SEO ready).  
- 🔒 **Código seguro y verificado**: validación, sanitización y uso de nonces en AJAX.  
- 🧩 **Integración nativa** con el panel de administración de WordPress.  
- 🌐 **Multilenguaje listo** (i18n con dominio `wmc-trustpilot-reviews`).

---

## 🚀 Instalación y configuración

1. Sube la carpeta del plugin a `/wp-content/plugins/` o instálalo desde el ZIP.  
2. Actívalo desde **Plugins → WMC Trustpilot Reviews**.  
3. En el panel **Ajustes → Trustpilot**, ingresa tu perfil público (ej. `https://es.trustpilot.com/review/tu-dominio.com`).  
4. Ajusta el diseño, habilita el carrusel y configura los límites de reseñas.  
5. Inserta los shortcodes en tus páginas o widgets.

> Compatible con **WordPress 6.x+**. Probado en entornos corporativos y temas personalizados.

---

## 🧠 Funcionamiento interno

El plugin utiliza un sistema de **crawler inteligente** que:
1. Analiza la estructura pública de Trustpilot.  
2. Extrae reseñas, metadatos del negocio y perfiles de consumidores.  
3. Limpia y normaliza los datos.  
4. Los cachea localmente por 12 horas para maximizar rendimiento.  
5. Genera vistas dinámicas con microdatos SEO y compatibilidad total con Gutenberg.

> Ninguna información privada o sensible es almacenada.  
> Cumple con políticas de privacidad y buenas prácticas de protección de datos.

---

## 🧩 Shortcodes disponibles

| Shortcode | Descripción |
|------------|--------------|
| `[wmc_trustpilot_reviews_list]` | Lista clásica de reseñas con nombre, estrellas y texto. |
| `[wmc_trustpilot_summary]` | Promedio de puntuación y resumen de opiniones. |
| `[wmc_trustpilot_reviews_carousel]` | Carrusel dinámico con autoplay y diseño corporativo. |

---

## 🔒 Seguridad y buenas prácticas

- Sanitización y escape en todas las salidas (`esc_html`, `esc_attr`, `esc_url`).  
- Control de permisos (`current_user_can`, `manage_options`).  
- Protección AJAX con `check_ajax_referer`.  
- No almacena datos personales de usuarios.  
- Cumple con el estándar de calidad de código WordPress.

---

## 🧭 Roadmap

- Panel de estadísticas de reputación.  
- Sincronización programada vía WP-Cron.  
- Compatibilidad avanzada con bloques Gutenberg.  
- Filtros por idioma y valoración mínima.  

---

## 🤝 Soporte y contribución

Un desarrollo de Webmastercol®, parte de nuestro portafolio de soluciones digitales para empresas orientadas a resultados.

---

## 📄 Licencia

**Licencia:** GPL-2.0-or-later  
**© 2025 Webmastercol® – Todos los derechos reservados.**
