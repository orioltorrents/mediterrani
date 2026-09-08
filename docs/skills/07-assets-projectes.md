# Skill 07 - Assets de projectes

## Objectiu

Gestionar logos, softwares, apps i altres assets visuals associats als projectes de **Mediterrani**.

Aquesta skill s'ha d'utilitzar quan calgui:

- afegir un logo nou a un projecte;
- associar un asset existent a diversos projectes;
- canviar l'ordre de sortida dels logos;
- activar o desactivar un asset sense perdre la relació;
- mostrar els mateixos assets a la web publica, alumnat, professorat i administracio.

## Estat actual

### Implementat

- carpeta real d'assets a `public/assets/logos/`;
- logos de projectes, col·laboradors i eines ja presents al repositori;
- `project_assets` i `project_asset_links` com a catàleg i relació;
- assets recuperats als llistats públics, als dashboards i a la fitxa de projecte.

### Encara previst

- ampliar l'ús dels assets a més contextos interns;
- afinar el criteri de selecció del logo principal quan un projecte tingui diversos assets;
- reutilitzar aquest catàleg també per a recursos de tasques quan toqui.

Quan un projecte tingui logo, la targeta l'ha de mostrar dins la caixa, alineat a l'esquerra, amb el contingut textual a la dreta. En pantalles petites, la targeta pot apilar-se en columna i reduir el logo de forma automàtica.

---

## Model de dades

Taules principals:

```text
project_assets
project_asset_links
```

Funció:

```text
project_assets      -> catàleg reutilitzable de logos i recursos
project_asset_links -> relació entre projectes i assets
```

Nota:

- aquesta relació és de catàleg de projecte, no d'edició per curs;
- aquí `project_id` és la clau correcta perquè els assets pertanyen al projecte base.

Camps clau:

```text
project_assets.slug
project_assets.name
project_assets.asset_type
project_assets.logo_path
project_assets.website_url
project_assets.display_order
project_assets.is_active

project_asset_links.project_id
project_asset_links.asset_id
project_asset_links.display_order
project_asset_links.is_visible
```

---

## On guardar els fitxers

Els logos han de viure dins de:

```text
public/assets/logos/
```

Es recomana usar subcarpetes si ajuda a ordenar-los:

```text
public/assets/logos/projectes/
public/assets/logos/collaboradors/
public/assets/logos/intermunicipal/
```

No posar assets visuals importants fora de `public/assets/`.

---

## Flux recomanat

1. Col·locar el fitxer al directori correcte.
2. Crear o reutilitzar una fila a `project_assets`.
3. Crear l'enllaç a `project_asset_links`.
4. Verificar que el servei PHP el recupera.
5. Confirmar que apareix a:
   - web pública;
   - fitxa de projecte;
   - dashboard d'alumnes;
   - dashboard de professorat;
   - dashboard d'administració.

---

## Regles

- No mapar logos manualment a les vistes si ja existeix la relació a la base de dades.
- Reutilitzar el mateix asset en diversos projectes sempre que calgui.
- Guardar la ruta dins `logo_path` com a ruta relativa dins de `public/`.
- Si el logo ha d'enllaçar a una web externa, omplir `website_url`.
- Fer servir `display_order` per controlar l'ordre de sortida.
- Fer servir `is_active` i `is_visible` per ocultar sense esborrar.

---

## Exemple

```sql
INSERT INTO project_assets (slug, name, asset_type, logo_path, website_url, display_order, is_active)
VALUES (
    'projecte-rius-logo',
    'Projecte Rius',
    'project',
    'assets/logos/projectes/projecte-rius.png',
    NULL,
    10,
    1
);

INSERT INTO project_assets (slug, name, asset_type, logo_path, website_url, display_order, is_active)
VALUES (
    'mat-penedes-logo',
    'MAT Penedès',
    'project',
    'assets/logos/projectes/mat-penedes.png',
    NULL,
    20,
    1
);

INSERT INTO project_assets (slug, name, asset_type, logo_path, website_url, display_order, is_active)
VALUES (
    'agroparc-logo',
    'Agroparc',
    'project',
    'assets/logos/projectes/agroparc.png',
    NULL,
    30,
    1
);

INSERT INTO project_asset_links (project_id, asset_id, display_order, is_visible)
SELECT p.id, a.id, 10, 1
FROM projects p
JOIN project_assets a
WHERE p.slug = 'projecte-rius'
  AND a.slug = 'projecte-rius-logo';

INSERT INTO project_asset_links (project_id, asset_id, display_order, is_visible)
SELECT p.id, a.id, 20, 1
FROM projects p
JOIN project_assets a
WHERE p.slug = 'mat-penedes'
  AND a.slug = 'mat-penedes-logo';

INSERT INTO project_asset_links (project_id, asset_id, display_order, is_visible)
SELECT p.id, a.id, 30, 1
FROM projects p
JOIN project_assets a
WHERE p.slug = 'agroparc'
  AND a.slug = 'agroparc-logo';
```

---

## Verificació

Després d'afegir o canviar assets:

1. Comprovar que el fitxer existeix a `public/assets/logos/`.
2. Revisar que `project_assets` i `project_asset_links` tenen les files correctes.
3. Verificar que `ProjectService` i `ProjectAssignmentService` retornen `assets`.
4. Revisar la portada, la fitxa de projecte i els dashboards.
5. Fer refresh fort del navegador si cal.

Si la targeta de projecte es veu en columna, revisar que el bloc de mitjans i el bloc de contingut facin servir el patró horitzontal BEM.

---

## Relació amb altres skills

- `docs/skills/02-base-de-dades.md` per l'esquema i les consultes SQL.
- `docs/skills/06-css-i-ui.md` per la presentació visual dels logos.

## Extensió prevista per a tasques

Si més endavant cal mostrar apps, softwares o eines dins de les tasques, la recomanació és no crear un catàleg nou, sinó reutilitzar `project_assets` i afegir una taula de relació específica, `assessment_task_resources`.

Per a les bastides o ajudes associades, el catàleg recomanat és `assessment_supports`.

La idea és que un mateix asset pugui aparèixer:

- en un projecte com a logo o recurs;
- en una tasca com a eina recomanada o recurs associat.
- amb una bastida o ajuda concreta vinculada al recurs.
