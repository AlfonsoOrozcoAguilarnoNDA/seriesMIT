![Logo de Vibe Coding México](seriesimagegrok.jpg)

# 📺 Prueba de Vibecoding: control de series en streaming

[![PHP Version](https://img.shields.io/badge/php-8.x-8892bf.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

**Experimento de revisar el estado de streaming de series y el avance de visionado, con el control hecho por un LLM.**

Prueba realizada en **octubre de 2026** como parte de las pruebas de [vibecodingmexico.com](https://vibecodingmexico.com).

Este repositorio reúne el código que generó cada modelo de lenguaje (servicios en línea y modelos locales) a partir de **un mismo prompt**: una aplicación PHP de un solo archivo para llevar el control de las series que se están viendo, con su progreso de episodios, fechas de inicio y fin, enlace y comentarios.

**Desglose completo y contexto de la prueba:** [vibecodingmexico.com/pruebalo-ya-visor-de-series/](https://vibecodingmexico.com/pruebalo-ya-visor-de-series/)

> ⚠️ El código de este repositorio fue generado por LLMs y **no debe usarse en producción sin revisión**. Varios archivos tienen errores, y eso es justamente lo que se documenta.

---

## 📋 El Prompt (idéntico para todos los modelos)

El prompt completo está en el post. Resumen de requerimientos:

- Un **único archivo** `series.php`, PHP 8.x procedural, sin clases ni frameworks.
- MySQLi con **prepared statements** para toda consulta con datos del usuario; la conexión `$link` viene de `config.php`.
- Bootstrap 4.6.2 y Font Awesome 5.15.4 desde jsDelivr; evitar JavaScript si no es indispensable.
- Tabla `series` creada con `CREATE TABLE IF NOT EXISTS`: título, temporada (1 a 5), total de episodios, episodio actual, fecha de inicio (la asigna el servidor), fecha de fin, enlace y comentarios.
- Cinco funciones: **agregar, listar con barra de progreso, editar, marcar "Terminada" y eliminar** (con una sola confirmación).
- Validación en servidor, escape de todo dato mostrado, patrón POST/Redirect/GET con mensaje de confirmación.
- Navbar y footer fijos con la identificación del modelo ("Generado por [modelo] [versión] el [fecha]"; si no lo sabe, debe escribir "no verificable").

---

## 🧪 Cómo se probó

- **Un solo prompt, una sola pasada**, sin iterar ni corregir errores.
- Servicios **en línea** (chats públicos) y modelos **locales**, evaluados en grupos separados porque no compiten en las mismas condiciones. El hardware y la configuración de los modelos locales están documentados en otros posts del blog.
- Servidor con **cPanel y PHP 8.x**, con MySQL **sin modo SQL estricto**. Esto importa: un código que guarda aquí podría fallar en un servidor con modo estricto.
- El autor ejecutó los archivos; las calificaciones son suyas.
- Los resultados son una foto de **octubre de 2026**. Los modelos cambian sin avisar.

---

## 🏆 Resultados

### ✅ Servicios en línea que funcionaron

| Modelo | Archivo | Nota | Observaciones |
|--------|---------|------|---------------|
| **Kimi** (instant) | `serieskiminstant.php` | **10/10** 🥇 | Edición en línea que funciona y pregunta antes de borrar. El formulario de edición no tiene botón para cerrarlo. |
| **ChatGPT** | `sereschatgpt.php` | 2.º lugar | Funciona; gráficamente discreto. La redirección pasa la URL por `htmlspecialchars()` dentro del header `Location`. |
| **Claude** (Sonnet 5.5) | `seriesclaudesonnet.php` | 9/10 | Botones demasiado largos. Editar no permite quitar el estado "terminada" (el prompt no permitía editar las fechas). |
| **Grok** (4.5 fast) | `seriesgrok4_5fast.php` | Empate con Claude en el 3.er lugar | Validación poco exacta, diseño distinto; hay cosas por mejorar, pero cumple. |
| **Mistral** (instant) | `seriesmistral.php` | 9/10 | Funciona, pero el código dice "Generado por **GLM**". |
| **DeepSeek** | `seriesdeepseek.php` | 9/10 | Se identifica sin versión. Trunca los comentarios a 40 caracteres y la URL se ve amontonada. |
| **Qwen 3.7 Plus** (modo chat) | `seriesQwen37Plus.php` | Funciona y guarda | Usa `gap-1` (clase de Bootstrap 5), no verifica el resultado del UPDATE y tiene tipos incorrectos en el `bind_param` del UPDATE (`siissii` en lugar de `siiissi`). |

### 💻 Modelos locales

| Modelo | Archivo | Nota | Observaciones |
|--------|---------|------|---------------|
| **qwen/qwen3-30b-a3b-2507** | `seriesqwen30bnocoder.php` | **9/10** 🥇 (locales) | Se ve decente y guarda. El INSERT omite `fecha_inicio`: no guarda la fecha y, en modo SQL estricto, el alta fallaría. Guarda la temporada pero no la muestra en la tabla. |
| **qwen/qwen3-coder-30b** | `seriesqwen30bcoder.php` | 7/10 | Se ve bien, pero no guarda: el número de parámetros del `bind_param` no coincide con las variables. |
| **google/gemma-4-e4b** | `seriesgemma4.php` | No funciona | Dos `foreach (...):` sin `endforeach`, y sobrescribe la conexión (`$link = sanitize_input(...)`). Aun así entregó un archivo completo. |
| **openai/gpt-oss-20b** | `seriegptosslocal.php` | 0 | Lee `action` de `$_GET` cuando los formularios lo envían por POST: ninguna acción hace nada. |

### ❌ No funcionaron o no entregaron

| Modelo | Archivo | Resultado |
|--------|---------|-----------|
| **Gemini** Flash 3.6 (plan de pago) | `seriesgemini36flash.php` | Descalificado: dos fragmentos incompletos, sin lógica de servidor, y la interfaz no deja copiar el código con facilidad. |
| **Meta.ai** | `seriesmeta.php` | 0. Todo el código en una sola línea, ilegible; al ejecutarlo no guarda. |
| **Dolphin 24b** (chat.dphn.ai) | `seriesdolphin.php` | 0. No tiene editar y agregar falla siempre. Gráficamente pobre. |
| **Cohere** | — | Se quedó en un bucle y no entregó nada. |
| **duck.ai** | — | Salida inservible e imposible de copiar. |
| **OLMo** (allen.ai) | — | Descartado: error de sintaxis desde la primera línea (`mysqli query(` con espacio). |
| **MiniMax** y **GLM 5.3** (chat.together.ai) | — | No entregaron nada: la plataforma respondió que la respuesta era demasiado larga. |

---

## 🔍 Hallazgos

1. **Gemini (plan de pago):** en esta prueba y en dos intentos, no entregó un archivo completo. Gemma, un modelo local pequeño, sí lo entregó, aunque con errores de sintaxis.
2. **MiniMax y GLM 5.3** no entregaron por el mensaje de "respuesta demasiado larga", que puede ser un límite de la plataforma y no del modelo. Según la experiencia del autor, MiniMax antes sí lograba este tipo de tarea.
3. **ChatGPT** tuvo un resultado mejor de lo que el autor esperaba, dado su historial previo en programación.
4. **Mistral se identificó como GLM** en su propio código. No se pudo verificar por qué.
5. **qwen3-30b-a3b se ve mejor y sí funciona, mientras que qwen3-coder-30b no guarda.** Conviene conservar los dos para futuras pruebas.
6. **Lo más relevante: los servicios evitan mostrar el número de versión** y a veces es imposible saber qué modelo exacto responde, lo que dificulta usarlos en proyectos serios.

### 🏷️ Identificación del modelo

| Modelo | Lo que escribió el código | Lo que mostraba la interfaz |
|--------|---------------------------|-----------------------------|
| Kimi | "Kimi Chat (versión no verificable)" | "instant" |
| DeepSeek | "DeepSeek no verificable" | Sin versión |
| ChatGPT | "GPT-5.6 Luna" | No muestra el modelo |
| Grok | "Grok 4.5 (xAI)" | "fast" |
| Mistral | "GLM" | "Mistral instant" |
| Qwen 3.7 Plus | "Qwen 3.7" | Modo chat |
| Claude | "Claude Sonnet 5.5" | — |

### 🎯 Conclusión del autor

- **Servicios en línea:** 1.º Kimi, 2.º ChatGPT, 3.º empate entre Claude y Grok.
- **Modelos locales:** 1.º `qwen/qwen3-30b-a3b-2507`.
- Es un proyecto simple y una sola prueba, de un solo prompt y una sola pasada, así que el alcance es limitado. Para algo real, Kimi o Claude; pero en modo local **sí se puede hacer algo**.

---

## 📂 Archivos del Repositorio

| Archivo | Descripción |
|---------|-------------|
| `review.php` | Visor para listar y ver el código de todos los `.php` del repositorio |
| `serieskiminstant.php` | Kimi (instant) |
| `sereschatgpt.php` | ChatGPT |
| `seriesclaudesonnet.php` | Claude Sonnet 5.5 |
| `seriesgrok4_5fast.php` | Grok 4.5 fast |
| `seriesmistral.php` | Mistral (instant) |
| `seriesdeepseek.php` | DeepSeek |
| `seriesQwen37Plus.php` | Qwen 3.7 Plus (modo chat) |
| `seriesqwen30bnocoder.php` | qwen/qwen3-30b-a3b-2507 (local) |
| `seriesqwen30bcoder.php` | qwen/qwen3-coder-30b (local) |
| `seriesgemma4.php` | google/gemma-4-e4b (local) |
| `seriegptosslocal.php` | openai/gpt-oss-20b (local) |
| `seriesgemini36flash.php` | Gemini Flash 3.6 (fragmentos) |
| `seriesmeta.php` | Meta.ai (ilegible) |
| `seriesdolphin.php` | Dolphin 24b |

---

## 🛠️ Especificaciones Técnicas

* **Lenguaje:** PHP 8.x procedural
* **Frontend:** Bootstrap 4.6.2 + Font Awesome 5.15.4 vía jsDelivr
* **Base de datos:** MySQLi (objeto `$link` definido en `config.php`)
* **Hosting de prueba:** cPanel
* **Arquitectura:** archivo único, auto-referente

---

## ⚙️ Uso

1. Crea tu `config.php` a partir del ejemplo del repositorio config-sample.php (debe definir la conexión mysqli en `$link`).
2. Sube al servidor el archivo `.php` que quieras probar.
3. Ábrelo en el navegador. Los archivos que corren crean la tabla `series` si no existe.
4. Usa `review.php` para ver el código de cada archivo.

> Todos los archivos comparten la misma tabla `series`. Prueba uno a la vez o limpia la tabla entre pruebas.

---

## 🧾 Transparencia

Para leer y comparar el código de cada archivo se usó a **Claude (Anthropic)** como apoyo. El código del propio Claude también participó en la prueba y fue calificado por el autor como los demás. La lectura de Claude fue estática; las calificaciones son del autor, tras ejecutar los archivos.

---

## 🧪 Notas del Autor

Este repositorio es parte de los experimentos documentados en **[vibecodingmexico.com](https://vibecodingmexico.com)**.

Los modelos de lenguaje cambian. Los resultados de hoy no garantizan los de mañana. Por eso se fecha todo y se documenta el modelo exacto que generó cada archivo.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991.

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

El código es tuyo para usar, copiar, modificar y distribuir.
La única condición es mantener el aviso de copyright en las copias sustanciales.

---

## ✍️ Acerca del Autor
* **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
* **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)
