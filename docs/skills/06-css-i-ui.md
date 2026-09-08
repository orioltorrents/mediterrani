# Skill 06 — CSS i UI

## Objectiu

Refactoritzar i ampliar el CSS de **Mediterrani** de manera sostenible, mantenint el resultat visual actual quan convingui, però permetent millores visuals petites i segures quan aportin claredat o qualitat.

Aquest skill s’ha de consultar abans de:

- reorganitzar `public/assets/css/styles.css`;
- crear o modificar components visuals;
- reduir duplicació d’estils;
- afegir variables CSS;
- canviar classes, noms de components o estructura visual;
- revisar responsive, focus, hover o accessibilitat.

## Estat actual

### Implementat

- el CSS actiu és `public/assets/css/styles.css`;
- el layout ja fa cache-busting de CSS i JS;
- les vistes ja utilitzen una nomenclatura BEM força coherent;
- hi ha estils reals per a portada, projectes, dashboards i fitxa de projecte.
- les targetes de projecte utilitzen blocs BEM compartits (`project-card__*`) amb modificadors per alumnat i professorat;
- el semàfor d'assoliment de l'alumnat utilitza el component `student-achievement-*`, amb colors atenuats i estat actiu il·luminat.

### Encara previst

- seguir reduint duplicació d'estils;
- consolidar millor les variables globals;
- refinar responsive i accessibilitat on calgui;
- evitar regressions quan es toquin vistes o components nous.

---

## Fitxer principal

El CSS que carrega l’aplicació és:

```text
public/assets/css/styles.css
```

No posar CSS necessari per l’aplicació PHP dins:

```text
assets/css/
css/
```

Aquests directoris poden existir com a referència històrica o per a la maqueta estàtica `index.html`, però el navegador no els carrega des de l’aplicació PHP modular.

---

## Principis

Prioritats:

1. Mantenir funcionalitat.
2. Evitar canvis visuals grans no demanats.
3. Reduir duplicació.
4. Fer el CSS més llegible.
5. Preparar components reutilitzables.
6. Millorar accessibilitat.

Regla pràctica actual:

- en àrees que es refan, preferir classes BEM noves i netes;
- no conservar compatibilitat antiga si ja s’ha migrat tota la vista;
- només mantenir selectors antics si hi ha una vista o un script que encara depèn d’ells;
- si el canvi és visual però petit i clar, es pot aplicar directament.

No fer una reescriptura completa si una refactorització incremental resol el problema.

### Components de semàfor

El semàfor d'objectius mostra els colors configurats pels indicadors i només exposa el descriptor en el tooltip de cada llum. La vista de l'alumne no ha de mostrar la llista completa de descriptors sota l'objectiu, perquè aquest espai queda reservat per a les evidències.

Classes actuals:

```text
student-achievement-lights
student-achievement-light
student-achievement-light--vermell
student-achievement-light--groc
student-achievement-light--verd_clar
student-achievement-light--verd_fosc
student-achievement-light.is-active
```

---

## Flux de treball recomanat

Abans de tocar CSS:

1. Buscar on s’utilitzen les classes.
2. Revisar la vista PHP relacionada.
3. Identificar si el canvi afecta web pública, alumne, professor o admin.
4. Separar canvis visuals de canvis estructurals.
5. Fer canvis petits i verificables.

Comandes útils:

```bash
rg -n "nom-classe|component" resources public app
rg -n "#[0-9a-fA-F]{3,6}|rgba|border-radius|box-shadow|padding|margin" public/assets/css/styles.css
```

---

## Organització del CSS

Ordenar el CSS per seccions clares:

```css
/* Variables globals */
/* Base */
/* Layout */
/* Components */
/* Formularis */
/* Taules */
/* Admin */
/* Estats */
/* Responsive */
```

Els comentaris han d’ajudar a localitzar blocs, no explicar cada propietat.

---

## Variables CSS

Centralitzar valors repetits a `:root`.

Variables actuals del projecte (`:root` a `styles.css`):

```css
:root {
    --bg: #f4f8f2;
    --paper: #ffffff;
    --ink: #1f2d1f;
    --muted: #6d7a6b;
    --leaf: #2f5d3a;
    --leaf-2: #6b8f5e;
    --leaf-soft: #e7efdf;
    --leaf-soft-2: #eef6e9;
    --border: #dfe9d8;
    --border-soft: #cbd7ca;
    --line: #e6ece2;
    --text-secondary: #47624a;
    --text-body: #34433b;
    --shadow: 0 2px 10px rgba(0,0,0,.08);
    --shadow-2: 0 14px 40px rgba(47,93,58,.12);
    --radius: 10px;
}
```

Afegir variables quan hi hagi repetició real. No crear variables per valors que apareixen una sola vegada sense motiu clar.

---

## Reduir duplicació

Buscar i agrupar:

- colors repetits;
- `border-radius`;
- ombres;
- paddings de targetes;
- estils de botons;
- estils de formularis;
- estats `hover`, `focus`, `disabled`;
- patrons de graella.

Exemple de millora:

```css
.card,
.auth-card,
.project-detail {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}
```

No agrupar selectors si això crea dependències confuses o si els components poden evolucionar de manera diferent.

### Utilitats CSS disponibles

El projecte disposa de classes d'utilitat ja definides a `styles.css` que cobreixen els patrons més repetits. En vistes noves o refactoritzacions, preferir composar amb aquestes abans de declarar manualment les mateixes propietats:

```css
.flex-row { display: flex; }
.flex-col { display: flex; flex-direction: column; }
.flex-center { align-items: center; display: flex; }
.flex-wrap { flex-wrap: wrap; }
.grid { display: grid; }
.gap-xs { gap: .35rem; }
.gap-sm { gap: .5rem; }
.gap-md { gap: .75rem; }
.gap-lg { gap: 1rem; }
.space-between { justify-content: space-between; }
.fw-700 { font-weight: 700; }
.fw-800 { font-weight: 800; }
.text-uppercase { text-transform: uppercase; }
.pill { background: var(--leaf-soft); border-radius: 999px; color: var(--leaf); display: inline-block; font-size: .8rem; padding: .25rem .6rem; }
```

Exemple d'ús en una vista PHP:

```html
<div class="flex-row flex-center gap-lg space-between">
    <span class="fw-700">Títol</span>
    <span class="pill">actiu</span>
</div>
```

---

## Nomenclatura

Preferir classes descriptives i consistents.

Patró recomanat inspirat en BEM:

```css
.project-card {}
.project-card__header {}
.project-card__logo {}
.project-card__actions {}
.project-card--inactive {}
```

Per components existents, no canviar noms de classe només per “fer-ho més BEM” si això obliga a tocar moltes vistes sense benefici clar.

Quan es refa una vista concreta, sí que es pot retirar la compatibilitat antiga d’aquella vista i deixar només la nomenclatura nova.

Quan es canviï una classe:

1. Actualitzar totes les vistes que la fan servir.
2. Revisar JavaScript si depèn d’aquella classe.
3. Verificar amb `rg`.

---

## Selectors

Estrictament prohibit encadenar selectors profunds:

```css
/* ⛔ PROHIBIT */
main div section article div span {}
#projectes-lista .card .foo {}
```

Utilitzar sempre classes netes i planes:

```css
.project-card__status {}
.admin-subsection {}
```

Excepcions documentades i rares (p. ex. contextualitzar un element dins un bloc BEM concret).

No dependre exclusivament del color per a estats `hover` o `active`: combinat amb `text-decoration`, `background`, `opacity` o un canvi d'icona.

Evitar estils acoblats a IDs excepte per ancoratges o casos molt concrets.

---

## Accessibilitat

Mantenir o afegir:

- contrast suficient entre text i fons;
- `:focus-visible` en botons, enllaços i camps;
- estats `hover` que no siguin l’única pista visual;
- mides de clic còmodes;
- formularis llegibles en mòbil.

Exemple:

```css
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid rgba(47,93,58,.35);
    outline-offset: 2px;
}
```

No eliminar estils que ajudin a entendre focus, errors o estats inactius.

### Preferències de moviment reduït

El projecte ja inclou `@media (prefers-reduced-motion: reduce)` al final del CSS per aturar animacions i transicions quan l'usuari ho té configurat al sistema. No afegir animacions noves sense verificar que siguin compatibles.

### Estils d'impressió

El projecte ja inclou una `@media print` bàsica. En components nous que puguin ser impresos (rúbriques, informes, targetes de projecte), assegurar que el disseny sigui funcional en blanc i negre i sense interactivitat.

---

## Responsive

Agrupar media queries quan sigui raonable.

Evitar duplicar moltes regles petites si poden conviure en una sola secció responsive.

Criteris:

- el panell admin ha de ser escanejable en desktop;
- les taules poden tenir `overflow-x: auto`;
- targetes i formularis han de passar a una columna en mòbil;
- text i botons no s’han de solapar.

---

## Canvis visuals

Mantenir el look actual si l’usuari demana refactor tècnic.

Si es detecta una incoherència visual important:

- aplicar-la directament només si és petita i segura;
- proposar-la abans si canvia jerarquia, colors dominants, layout principal o comportament.

Exemples de canvis segurs:

- substituir colors repetits per variables equivalents;
- unificar radi de targetes semblants;
- afegir `focus-visible`;
- compactar CSS duplicat sense canviar HTML;
- millorar jerarquia visual amb espai, contrast i ombres més coherents;
- fer un dashboard més llegible sense canviar la funcionalitat.

Exemples que cal proposar abans:

- canviar tota la paleta;
- redissenyar el dashboard;
- substituir taules per targetes;
- canviar l’estructura HTML de moltes vistes.

---

## Verificació

Després de tocar CSS:

1. Fer `rg` per assegurar que les classes canviades existeixen.
2. Validar que el JavaScript no depèn de classes eliminades.
3. Si s’han tocat vistes PHP, executar lint:

```bash
php -l resources/views/admin/dashboard.php
```

4. Si s’ha tocat JS relacionat:

```bash
node --check public/assets/js/scripts.js
```

5. Revisar visualment les pantalles afectades quan sigui possible.

6. Si el CSS sembla no aplicar-se, revisar el cache-busting del layout i fer un refresh fort al navegador.

---

## Resultat esperat

En acabar una refactorització CSS, explicar breument:

- quins fitxers s’han canviat;
- quines duplicacions s’han reduït;
- quines variables s’han creat o reutilitzat;
- quin impacte visual hi ha;
- quines recomanacions queden pendents.

Resposta curta i concreta. No fer un informe llarg si el canvi és petit.
