# Skill 02 — Base de dades

## Objectiu

Gestionar correctament la base de dades MySQL/MariaDB del projecte **Mediterrani**.

La base de dades ha de permetre gestionar:

- usuaris;
- rols;
- classes;
- alumnes;
- professorat;
- projectes;
- assets, softwares i apps associats a projectes;
- idiomes;
- assignacions de projectes;
- futura sincronització amb Google;
- futures rúbriques i notes.

## Estat actual

### Implementat

- connexió amb PDO des de `config/database.php`;
- esquema educatiu base amb usuaris, rols, classes, projectes, idiomes i assignacions;
- `project_academic_years` com a unitat funcional per a dades d'edició;
- seccions configurables de projecte a `project_sections`;
- taula d'analítica de visites `site_visits`;
- objectius d'aprenentatge i indicadors d'assoliment per objectiu;
- avaluació individual a `student_indicador_assoliment`, lligada a usuari, objectiu i edició;
- equips i membres per projecte a `project_teams`, `project_team_members` i `project_team_member_roles`;
- Classroom i membres de Classroom;
- categories, tipus i evidències d'alumnes relacionades amb edicions i objectius.

### Encara previst

- relació d'evidències concretes amb alumnes, edicions i objectius;
- importació manual d'evidències des de CSV o Google Sheets;
- integració real amb Google Workspace;
- documents, assets, fases, tasques i webhooks de l'antic projecte, pendents de redissenyar;
- taules de rúbriques i notes definitives;
- possibles extensions de visibilitat i historial si calen més endavant.

---

## Base de dades local

Nom de la base de dades local:

```text
mediterrani
```

La connexió llegeix el valor efectiu de `DB_NAME` a `.env`. Per a l'esquema i els procediments de reconstrucció, la font canònica és `database/README.md`; la seqüència executable és `database/schema.sql`.

Entorn:

```text
XAMPP + MySQL/MariaDB + phpMyAdmin
```

---

## Connexió

La connexió es fa amb PDO des de:

```text
config/database.php
```

Les dades de connexió es llegeixen des de:

```text
.env
```

Exemple:

```env
DB_HOST=localhost
DB_NAME=mediterrani
DB_USER=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

---

## Taules actuals

L'esquema executable actual inclou exactament 30 taules:

```text
users
user_activation_tokens
web_roles
user_web_roles
project_roles
languages
academic_years
classes
class_members
class_member_history
class_teachers
site_visits
login_attempts

projects
project_translations
project_academic_years
project_class_assignments
project_teams
project_team_members
project_team_member_roles
project_sections

classrooms
classroom_members

objectius_aprenentatge
indicadors_assoliment
project_academic_year_objectius
student_indicador_assoliment

evidencies_categoria
evidencies_tipus
evidencies_alumnes
```

La font canònica és `database/schema.sql`. Qualsevol nom de taula que aparegui més endavant però no sigui en aquesta llista és una proposta heretada o futura, encara que el text històric descrigui el comportament en present.

`project_groups` és un nom legacy. El model actual utilitza `project_class_assignments`.

## Reconstrucció i migracions

Per reconstruir una base neta, cal executar:

```text
database/schema.sql
```

La classificació dels ajustos incrementals i els canvis ja absorbits es documenta a `database/README.md`.

Per actualitzar una base existent, no s'han d'executar totes les migracions indiscriminadament. Cal identificar l'estat de partida, fer una còpia de seguretat i aplicar només els ajustos posteriors que corresponguin.

### Manteniment d'esquema

El manteniment d'esquema estable ha de viure als SQL de `database/` i seguir el procediment de `database/README.md`. Els controladors web no poden contenir instruccions DDL com `CREATE TABLE`, `ALTER TABLE` o modificacions d'esquema equivalents.

Les comprovacions dinàmiques d'esquema només s'han d'acceptar de manera excepcional i temporal, sempre fora dels controladors i amb un pla per consolidar-les a migracions. En l'estat actual, el manteniment que abans calia per `project_class_assignments`, `projects.display_order` i `project_team_member_roles` ja està cobert per `database/schema.sql` i les migracions corresponents.

---

## Normes generals

- Fer servir InnoDB.
- Fer servir `utf8mb4`.
- Fer servir `utf8mb4_unicode_ci`.
- Fer servir claus primàries.
- Fer servir claus úniques quan calgui evitar duplicats.
- Fer servir PDO.
- Fer servir consultes preparades.
- No concatenar dades d’usuari dins SQL.
- No fer `DROP TABLE` sense confirmació explícita.
- No fer `DROP DATABASE` sense confirmació explícita.
- No fer `DELETE` sense `WHERE`.
- No perdre dades existents.

---

## Usuaris

Taula principal:

```text
users
```

Camps importants:

```text
id
name
surname
email
google_id
password_hash
avatar_url
is_active
last_login_at
created_at
updated_at
```

Criteris:

- `email` ha de ser únic.
- `google_id` pot ser `NULL`.
- `password_hash` pot ser `NULL` si l’usuari només accedeix amb Google.
- No guardar contrasenyes en text pla.

---

## Rols

Taules:

```text
web_roles
user_web_roles
project_roles
project_team_member_roles
```

Funció:

```text
web_roles       → permisos generals d'accés a la web
user_web_roles  → assignació de rols web als usuaris
project_roles   → funcions dels membres dins d'un projecte
project_team_member_roles → assignació d'un o més rols de projecte a cada pertinença d'equip
```

Rols previstos:

```text
student
teacher
coordinator
admin
```

Regla conceptual:

```text
admin       = admin + coordinator + teacher
coordinator = coordinator + teacher
teacher     = teacher
student     = student
```

És preferible assignar diversos rols web explícits a `user_web_roles`. Els rols de projecte no substitueixen els permisos generals de la web.

Un membre d'equip pot tenir més d'un rol de projecte. Per mostrar, filtrar, comptar o importar rols múltiples, s'ha d'utilitzar `project_team_member_roles`. `project_team_members.project_role_id` és només el rol principal de compatibilitat i no s'ha d'usar com a única font funcional.

---

## Classes

Taules:

```text
academic_years
classes
class_members
class_teachers
```

Funció:

```text
academic_years  → cursos acadèmics
classes         → grups classe
class_members   → alumnes dins de classes
class_member_history → historial de canvis de classe
class_teachers  → professorat assignat a classes
```

Classes inicials:

```text
4ESOA
4ESOB
```

Assignació inicial:

```text
Aiman  → 4ESOA
Sílvia → 4ESOB
```

Professorat inicial assignat a les dues classes:

```text
Àlex Martí
Oriol Rovira
Oriol Torrents
```

---

## Projectes

Taules:

```text
projects
project_translations
project_class_assignments
```

Funció:

```text
projects              → dades bàsiques del projecte
project_translations  → títol i descripció per idioma
project_class_assignments → assignació de cada edició a classes
```

Projectes inicials:

```text
projecte-rius
mat-penedes
agroparc
projecte-orenetes
liquencity
vespa-velutina
```

Assignacions inicials:

```text
Projecte Rius      → 4ESOA i 4ESOB
MAT Penedès        → 4ESOA
Agroparc           → 4ESOB
Projecte Orenetes  → 4ESOA
Liquencity         → 4ESOB
```

## Evidències actuals

Taules:

```text
evidencies_categoria
evidencies_tipus
evidencies_alumnes
```

Relació:

```text
evidencies_categoria 1 → N evidencies_tipus
evidencies_tipus 1 → N evidencies_alumnes
```

`evidencies_categoria` defineix les agrupacions visuals amb nom, descripció i color. Les categories inicials són procés, producte i metacognitiva. `evidencies_tipus` defineix el catàleg de formes concretes d'evidència que mostra el dashboard d'administració.

`evidencies_alumnes` representa evidències concretes i les relaciona amb `users`, `project_academic_years`, `objectius_aprenentatge`, categoria i tipus. Encara falta dissenyar la font d'importació i, si correspon, una relació amb tasques.

## Documents (model heretat, no implementat)

Les taules d'aquest apartat no existeixen a l'esquema actual. El contingut següent es conserva únicament com a referència per a un possible redisseny.

Taules:

```text
documents
document_sources
document_fragments
document_visibility_rules
```

Funció:

```text
documents                → documents d'una edició concreta de projecte
document_sources         → fonts d'origen del document
document_fragments       → fragments o blocs reutilitzables
document_visibility_rules → regles de visibilitat per rol, grup o fragment
```

Norma clau:

- `projects` és el catàleg base del projecte;
- `project_academic_years` és la unitat funcional quan una dada depèn del curs concret;
- la clau recomanada és `project_academic_year_id + slug`;
- evita dependre del projecte base per determinar la unitat del document.

Quan usar cada una:

- `projects`: informació estable i compartida del projecte;
- `project_academic_years`: dades que canvien per curs o edició;
- si la consulta necessita saber quin curs és actiu o quin context d'aula hi ha, parteix de `project_academic_years`.

## Avaluació avançada (model heretat, no implementat)

De les taules descrites en aquest apartat, actualment només existeixen `classrooms` i `classroom_members`. Les taules `assessment_*`, les taules pont de fases o tasques i les taules de webhooks són propostes heretades i no es poden consultar ni importar encara.

Taules:

```text
assessment_sources
assessment_import_runs
assessment_records
assessment_import_errors
assessment_phases
assessment_tasks
project_academic_year_phases
project_academic_year_phase_tasks
classrooms
classroom_project_academic_years
classroom_members
assessment_task_classroom_links
classroom_webhook_runs
classroom_webhook_rows
```

Norma clau:

- `assessment_records` depèn de `assessment_sources`;
- la font i el run han de portar `project_academic_year_id`;
- repetir només `project_id` per filtrar notes és massa ampli si el projecte té diverses edicions;
- el filtratge de notes i imports s'ha de fer per edició, a través de `assessment_sources`;
- les fases i tasques es defineixen una sola vegada i després s'assignen a cada edició de projecte amb les taules pont;
- així no copies la mateixa estructura cada curs.
- `classrooms` guarda els Google Classrooms vinculats a un curs acadèmic, no a una tasca base.
- `classroom_project_academic_years` permet que un mateix Classroom estigui vinculat a diverses edicions de projecte.
- `classroom_members` guarda quins usuaris de la web pertanyen a cada Classroom.
- `assessment_task_classroom_links` guarda la URL concreta d'una tasca dins un Classroom.
- `classroom_webhook_runs` i `classroom_webhook_rows` guarden payloads rebuts per webhook abans de qualsevol transformació cap a taules finals.

### Classrooms actuals i evolució prevista

La taula `classrooms` representa els Google Classrooms associats a un curs acadèmic i directament a una edició de projecte.

```text
classrooms.academic_year_id -> academic_years.id
classrooms.project_academic_year_id -> project_academic_years.id
```

Camps principals:

```text
academic_year_id
project_academic_year_id
classroom_key
classroom_name
classroom_url
google_classroom_id
is_active
```

Regles:

- `classroom_key` és una clau pròpia estable generada pel procés d'importació;
- `project_academic_year_id` és obligatori en el model actual;
- `google_classroom_id` és la referència externa de Google Classroom i pot ser nul si encara no està disponible;
- `classroom_url` és la URL general del Classroom;
- `task_url` no s'ha de guardar a `classrooms`, perquè és l'enllaç d'una tasca concreta dins aquell Classroom;
- la unicitat funcional nova és `academic_year_id + classroom_key`;
- cada Classroom queda vinculat directament a una edició de projecte.

Evolució futura per a Classrooms amb més d'un projecte:

La taula pont `classroom_project_academic_years` descrita a continuació no existeix encara. Només s'hauria de crear si es confirma que un mateix Classroom ha de vincular-se a diverses edicions.

- si un Classroom correspon a un sol projecte, el CSV de membres pot portar `project_slug` i el vincle queda creat automàticament;
- si un Classroom agrupa alumnes que treballen en dos o més projectes, el CSV de membres ha de deixar `project_slug` buit;
- en aquest cas, després s'ha d'importar el CSV de vincles `academic_year,classroom_key,project_slug,is_active`;
- no s'ha de crear un projecte fals per representar el tema comú del Classroom;
- exemple: `25-26_4esoab_estudi-impacte-ambiental` pot estar vinculat alhora a `mat-penedes` i `agroparc`.

El CSV unificat de fases, tasques i Classroom podrà alimentar `classrooms`, `assessment_phases`, `assessment_tasks` i les taules pont d'edició. L'importador és responsable de separar el CSV en el model normalitzat de base de dades.

### Enllaços de tasques per Classroom (proposta heretada)

La taula `assessment_task_classroom_links` relaciona una tasca assignada a una edició amb un Classroom concret.

```text
assessment_task_classroom_links.project_academic_year_phase_task_id -> project_academic_year_phase_tasks.id
assessment_task_classroom_links.classroom_id                         -> classrooms.id
```

Camps principals:

```text
project_academic_year_phase_task_id
classroom_id
task_url
google_course_work_id
google_rubric_id
is_visible
```

Regles:

- `task_url` és la URL directa a la tasca de Google Classroom on l'alumnat ha de lliurar la feina;
- `google_course_work_id` és l'identificador de la tasca a Google Classroom;
- `google_rubric_id` és l'identificador de la rúbrica associada a Google Classroom, si existeix;
- una mateixa tasca conceptual pot tenir URLs diferents segons el Classroom;
- la unicitat funcional és `project_academic_year_phase_task_id + classroom_id`;
- aquesta taula no substitueix `assessment_tasks`, perquè `assessment_tasks` continua representant la tasca base.

### Webhook staging de Classroom (proposta heretada)

Les taules `classroom_webhook_runs` i `classroom_webhook_rows` són una capa d'ingesta genèrica per a payloads enviats des de Google Apps Script.

```text
classroom_webhook_rows.webhook_run_id -> classroom_webhook_runs.id
```

`classroom_webhook_runs` guarda la capçalera de cada petició rebuda:

```text
source
event_type
extracted_at
received_at
status
total_rows
request_ip
user_agent
raw_payload_json
```

`classroom_webhook_rows` guarda cada fila del payload:

```text
webhook_run_id
row_number
event_type
academic_year
classroom_key
project_slug
task_key
student_email
google_course_work_id
raw_row_json
process_status
process_error
```

Regles:

- són taules staging, no taules finals de notes, rúbriques ni tasques;
- `raw_payload_json` i `raw_row_json` conserven exactament la dada rebuda per auditoria i reprocessament;
- `process_status` comença a `pending` i només ha de canviar quan un processador posterior transformi la fila;
- el primer `event_type` implementat és `classroom_extraction_test`;
- `classroom_structure_snapshot` es pot processar manualment des de l'admin per crear o actualitzar fases, tasques i enllaços Classroom;
- nous `event_type` com `classroom_task_snapshot` o `classroom_grade_snapshot` s'han d'afegir explícitament i amb validació pròpia.

### Membres de Classrooms

La taula `classroom_members` relaciona l'alumnat existent a `users` amb un Classroom concret.

```text
classroom_members.classroom_id -> classrooms.id
classroom_members.user_id      -> users.id
```

Camps principals:

```text
classroom_id
user_id
student_email
google_user_id
google_photo_url
classroom_group
external_group_id
is_active
```

Format CSV d'importació de membres:

```text
academic_year,classroom_key,email
```

Opcionalment el CSV pot incloure `project_slug`, `google_classroom_id`, `name`, `surname`, `google_user_id`, `google_photo_url`, `classroom_name` i `classroom_url`. Si el Classroom no existeix, l'importador el crea amb `classroom_name` o, si aquest camp ve buit, amb `classroom_key` com a nom visible.

Mapeig:

```text
academic_year                -> classrooms.academic_year_id
project_slug opcional        -> classroom_project_academic_years.project_academic_year_id
classroom_key                -> classrooms.id dins el curs
email                        -> users.email i classroom_members.student_email
google_user_id               -> classroom_members.google_user_id
google_photo_url             -> classroom_members.google_photo_url
```

Regles:

- `student_email` és obligatori i serveix per auditar l'import de Google Classroom;
- l'importador ha de resoldre `user_id` a partir de `users.email`;
- l'importador pot crear o reactivar el Classroom quan existeix el curs `academic_year` però encara no hi ha cap fila a `classrooms` per aquell `classroom_key`;
- si `project_slug` ve informat, l'importador crea o reactiva el vincle `classroom_project_academic_years`;
- no s'han de crear usuaris nous automàticament des de l'import de membres de Classroom;
- si `email` no existeix a `users.email`, la fila s'ha de rebutjar;
- si l'usuari no té rol `student`, l'importador pot assignar-lo igualment però ha de generar un avís;
- la unicitat funcional és `classroom_id + user_id`;
- `google_user_id`, `google_photo_url`, `classroom_group` i `external_group_id` són metadades de sincronització o agrupació.

Format CSV d'importació de vincles Classroom-projecte:

```text
academic_year,classroom_key,project_slug,is_active
```

`is_active` és opcional. Si ve buit, el vincle queda actiu.

Format CSV d'importació de tasques de Classroom:

```text
academic_year,classroom_key,classroom_name,classroom_url,google_classroom_id,project_slug,phase_key,phase_title,task_key,task_title,task_url,role_filter
```

Mapeig:

```text
academic_year + classroom_key -> classrooms.id
project_slug + academic_year  -> project_academic_years.id
phase_key + project_slug      -> assessment_phases.phase_key dins el projecte base
task_key                      -> assessment_tasks.source_column dins la fase
task_url                      -> assessment_task_classroom_links.task_url
role_filter                   -> assessment_tasks.role_filter
```

Regles:

- l'importador pot crear o actualitzar el Classroom;
- l'importador vincula el Classroom amb el projecte a `classroom_project_academic_years`;
- l'importador crea o actualitza fases i tasques base a partir de `phase_key` i `task_key`;
- l'importador crea o actualitza els vincles d'edició a `project_academic_year_phases` i `project_academic_year_phase_tasks`;
- `task_url` ha de ser una URL `http` o `https` vàlida;
- `role_filter` és opcional: buit o `"General"` vol dir tasca comuna visible per a tots els alumnes del Classroom;
- si una tasca és específica d'un rol de projecte, `role_filter` ha de contenir el nom del rol; si n'hi ha més d'un, cal separar-los amb comes;
- una mateixa tasca pot tenir una URL diferent per cada Classroom.

Fases i tasques compartides entre Classrooms:

- les fases i tasques són estructura del projecte i del curs, no del Classroom concret;
- `phase_key` i `task_key` són les claus que fan que una fase o tasca comuna es reutilitzi;
- si tres Classrooms fan les mateixes tasques, el CSV ha de repetir les files per cada `classroom_key`, però mantenint iguals `phase_key` i `task_key`;
- el que canvia entre Classrooms és normalment `classroom_key`, `classroom_name`, `classroom_url`, `google_classroom_id` i `task_url`;
- si `phase_key` o `task_key` canvien encara que el títol sembli igual, l'importador crearà fases o tasques diferents;
- és millor importar primer només les fases i tasques imprescindibles i afegir-ne més endavant si cal, perquè l'importador és incremental.

Exemple de tasca compartida:

```text
2025-2026,25-26_4esoab_vespa-velutina,...,vespa-velutina,fase-1,Preparació,observacio-inicial,Observació inicial,https://classroom.google.com/...,científic/a
2025-2026,25-26_4esocd_vespa-velutina,...,vespa-velutina,fase-1,Preparació,observacio-inicial,Observació inicial,https://classroom.google.com/...,científic/a
2025-2026,25-26_4esoef_vespa-velutina,...,vespa-velutina,fase-1,Preparació,observacio-inicial,Observació inicial,https://classroom.google.com/...,científic/a
```

Aquest exemple crea o reutilitza una sola fase `fase-1` i una sola tasca `observacio-inicial` per al projecte `vespa-velutina`, però guarda tres `task_url`, una per Classroom.

Exemple de tasca comuna per a tots els rols:

```text
2025-2026,25-26_4esoab_vespa-velutina,...,vespa-velutina,fase-1,Preparació,observacio-inicial,Observació inicial,https://classroom.google.com/...,
```

En aquest cas l'última columna queda buida expressament: `role_filter` buit fa que la tasca sigui visible per a tots els alumnes del Classroom.

### Importació de fases (format heretat, no executable)

L'antic projecte preveia importar fases des de CSV exportat de Google Sheets amb aquests headers:

```text
academic_year,project,phase_key,phase_num,phase_name,phase_complet_name,phase_description,phase_comment,display_order,is_active
```

Mapeig:

```text
academic_year       -> academic_years.name, per exemple 2024-2025
project             -> projects.slug, per exemple projecte-rius
phase_key           -> assessment_phases.phase_key
phase_complet_name  -> assessment_phases.title, titol visible a la web
phase_description   -> assessment_phases.description
display_order       -> assessment_phases.display_order i project_academic_year_phases.display_order
is_active           -> assessment_phases.is_active i project_academic_year_phases.is_active
```

`phase_num` i `phase_name` poden servir a Sheets per construir `phase_complet_name` amb formules. L'importador usa `phase_complet_name` com a titol principal, `phase_name` com a fallback si el titol complet ve buit, i `phase_num` com a fallback de `display_order` si `display_order` ve buit.

`phase_comment` queda ignorat de moment per evitar publicar comentaris interns a la web.

Regla important: l'assignació de la fase es fa nomes per l'edició concreta resolta amb `academic_year + project`. No s'han d'actualitzar totes les edicions històriques del mateix projecte.

### Visibilitat de fases per estat d'edició (proposta heretada)

La gestió d'administració i la consulta de l'alumnat no tenen la mateixa regla:

- l'admin gestiona fases i tasques només per edicions del curs actual amb estat `pendent` o `actiu`;
- les edicions `realitzat` no apareixen al panell admin de gestió de fases, perquè ja no s'han d'obrir progressivament;
- mentre una edició està `pendent` o `actiu`, l'alumnat només veu fases amb `project_academic_year_phases.is_active = 1` i tasques amb `project_academic_year_phase_tasks.is_visible = 1`;
- quan una edició passa a `realitzat`, l'alumnat pot consultar totes les fases i totes les tasques de l'edició, encara que els flags `is_active` o `is_visible` estiguin desactivats.

### Importació de tasques (format heretat, no executable)

L'antic projecte preveia importar tasques amb aquests headers:

```text
id,academic_year,project_slug,phase_key,task_name,title,description,weight_label,role_filter,display_order,is_visible
```

Mapeig:

```text
academic_year  -> academic_years.name, per exemple 2024-2025
project_slug   -> projects.slug, per exemple projecte-rius
phase_key      -> assessment_phases.phase_key
task_name      -> assessment_tasks.source_column, clau tecnica de la tasca
title          -> assessment_tasks.title, titol visible a la web
description    -> assessment_tasks.description
weight_label   -> assessment_tasks.weight_label
role_filter    -> assessment_tasks.role_filter
display_order  -> assessment_tasks.display_order i project_academic_year_phase_tasks.display_order
is_visible     -> assessment_tasks.is_visible i project_academic_year_phase_tasks.is_visible
```

`id` queda ignorat perquè els identificadors interns els gestiona MySQL. `task_name` no es mostra com a titol principal: serveix com a clau estable per relacionar imports d'avaluació i evitar duplicats dins la mateixa fase. El titol visible és `title`.

Igual que amb les fases, l'assignació de la tasca es fa nomes per l'edició concreta resolta amb `academic_year + project_slug`.

### Regla de model

- `projects` és el catàleg base del projecte;
- `project_academic_years` és la unitat funcional quan una dada depèn del curs concret;
- si una entitat canvia per edició, no s'ha de resoldre només amb `projects`.

### Estats d'edició

- `project_academic_years.status` controla l'estat global d'una edició i pot ser `pendent`, `actiu`, `realitzat` o `arxivat`;
- `project_class_assignments.status` controla l'estat d'aquesta edició per classe i pot ser `pendent`, `actiu` o `realitzat`;
- l'alumnat veu només edicions de l'any acadèmic actual amb estat d'edició i assignació `actiu` o `realitzat`;
- professorat, coordinació i administració veuen als dashboards edicions de l'any actual amb estat `pendent`, `actiu` o `realitzat`;
- `arxivat` queda fora dels dashboards normals i serveix per conservar històric sense mostrar-lo en el treball diari.

### Equips de projecte

- `project_teams` agrupa els equips per projecte i curs;
- `project_team_members` lliga usuari, equip i classe contextual dins l'edició;
- `project_team_member_roles` lliga cada pertinença amb un o més rols de projecte;
- un alumne pot tenir un equip diferent a cada projecte.

### Quan `project_id` és correcte

- `project_id` és correcte quan la relació apunta al catàleg base del projecte, com `project_translations`, `project_asset_links` o altres relacions compartides per totes les edicions;
- si la dada varia per curs, edició o context d'aula, cal usar `project_academic_year_id` o una taula pont equivalent;
- en documents, imports i avaluació, `project_id` només s'ha de conservar com a dada d'origen o de migració, no com a clau funcional.

---

## Idiomes

Taules:

```text
languages
project_translations
```

Idiomes previstos:

```text
ca
es
en
```

Idioma principal:
```text
ca
```

---

## Assets de projectes (model heretat, no implementat)

`project_assets`, `project_asset_links`, `assessment_supports` i `assessment_task_resources` no formen part de l'esquema actual.

Taules:

```text
project_assets
project_asset_links
```

Funció:

```text
project_assets      → catàleg reutilitzable de logos, softwares, apps i altres recursos
project_asset_links → relació entre projectes i els seus assets visuals o tecnològics
```

Criteris:

- un asset es pot reutilitzar en més d’un projecte;
- un projecte pot tenir diversos assets;
- `logo_path` ha de guardar una ruta relativa dins `public/assets/logos/` o un subdirectori equivalent;
- `website_url` és opcional i permet enllaçar el logo a la seva web oficial;
- `asset_type` pot servir per separar `partner`, `software`, `app` o `tool`;
- `display_order` controla l’ordre de sortida a la portada o a la fitxa del projecte.

Veure també:

- `docs/skills/07-assets-projectes.md`

### Recursos de tasques

En un futur, la relació entre eines, apps o softwares i una tasca es podria modelar amb `assessment_task_resources`, i les bastides reutilitzables amb `assessment_supports`.

Camps principals de la relació:

```text
assessment_task_id
project_asset_id
support_id
display_order
is_visible
notes
```

Això permet reutilitzar `project_assets` com a catàleg, vincular una bastida a cada recurs i mostrar els recursos exactes que es fan servir a cada tasca sense duplicar dades.

---

## Visibilitat i context (model heretat, no implementat)

Actualment només existeix `project_sections`; `project_section_roles` i `document_visibility_rules` encara no existeixen.

El model futur podria ampliar `project_sections` amb relacions de rol i regles de visibilitat, però aquestes taules encara no existeixen.

Criteris:

- mantenir una sola font de dades per projecte;
- evitar guardar la mateixa informació duplicada per rol;
- preferir flags o nivells de visibilitat abans que rutes o taules separades per perfil.

Les ampliacions futures de visibilitat han d'estendre aquestes estructures quan sigui possible, en lloc de crear fitxers o còpies de contingut per perfil.

---

## Consultes útils

### Veure taules

```sql
SHOW TABLES;
```

### Veure estructura completa

```sql
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'mediterrani'
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

### Veure usuaris i rols

```sql
SELECT
    users.id,
    users.name,
    users.surname,
    users.email,
    GROUP_CONCAT(web_roles.name ORDER BY web_roles.name SEPARATOR ', ') AS roles
FROM users
LEFT JOIN user_web_roles ON user_web_roles.user_id = users.id
LEFT JOIN web_roles ON web_roles.id = user_web_roles.role_id
GROUP BY users.id, users.name, users.surname, users.email
ORDER BY users.id;
```

### Veure alumnes per classe

```sql
SELECT
    classes.class_name AS classe,
    users.name,
    users.surname,
    users.email
FROM class_members
JOIN users ON users.id = class_members.user_id
JOIN classes ON classes.id = class_members.class_id
ORDER BY classes.class_name, users.surname, users.name;
```

### Veure professorat per classe

```sql
SELECT
    classes.class_name AS classe,
    users.name,
    users.surname,
    users.email
FROM class_teachers
JOIN users ON users.id = class_teachers.user_id
JOIN classes ON classes.id = class_teachers.class_id
ORDER BY classes.class_name, users.surname, users.name;
```

### Veure projectes per classe

```sql
SELECT 
    classes.class_name AS classe,
    projects.name AS projecte,
    projects.slug,
    project_class_assignments.status
FROM project_class_assignments
JOIN classes ON classes.id = project_class_assignments.class_id
JOIN project_academic_years ON project_academic_years.id = project_class_assignments.project_academic_year_id
JOIN projects ON projects.id = project_academic_years.project_id
ORDER BY classes.class_name, projects.name;
```

### Documents per edició de projecte (consulta futura, no executable)

```sql
SELECT
    projects.name AS projecte,
    academic_years.name AS curs,
    documents.slug,
    documents.title
FROM documents
JOIN project_academic_years ON project_academic_years.id = documents.project_academic_year_id
JOIN projects ON projects.id = project_academic_years.project_id
JOIN academic_years ON academic_years.id = project_academic_years.academic_year_id
ORDER BY projects.name, academic_years.name, documents.display_order;
```

Norma:

- cada document pertany a una edició concreta del projecte;
- evita basar la lògica de documents en el projecte base;
- usa `project_academic_years` per saber quin curs i quin projecte defineixen el document.

### Veure assets per projecte (consulta futura, no executable)

```sql
SELECT
    projects.name AS projecte,
    project_assets.name AS asset,
    project_assets.asset_type,
    project_assets.logo_path,
    project_assets.website_url,
    project_asset_links.display_order
FROM project_asset_links
JOIN projects ON projects.id = project_asset_links.project_id
JOIN project_assets ON project_assets.id = project_asset_links.asset_id
WHERE project_asset_links.is_visible = 1
  AND project_assets.is_active = 1
ORDER BY projects.name, project_asset_links.display_order, project_assets.name;
```

---

## Insercions segures

Quan hi pugui haver duplicats, fer servir:

```sql
INSERT IGNORE
```

o:

```sql
ON DUPLICATE KEY UPDATE
```

Això és especialment útil per:

- rols;
- assignacions d’usuaris a rols;
- assignacions d’alumnes a classes;
- assignacions de professorat;
- assignacions de projectes a classes.

---

## Google Workspace (model futur, no implementat)

Les taules `google_*` d'aquest apartat no existeixen a l'esquema actual. Abans de crear-les cal prioritzar el cas d'ús d'importació d'evidències, definir el format del full i validar el model de relacions.

Taules:

```text
google_sources
google_documents
google_document_blocks
google_sheet_rows
google_sync_runs
google_sync_errors
```

Proposta de disseny que s'haurà de validar abans d'implementar-la:

- la unitat funcional és `project_academic_years` quan el contingut és contextual;
- les futures fonts i els resultats sincronitzats haurien d'anar lligats a `project_academic_year_id`;
- no publicar dades directament des de Google sense validació i sense passar per la BD.
- si es recupera aquest disseny, `google_*` seria la capa d'origen i sincronització;
- per a Sheets, el primer flux que s'ha de dissenyar és la importació validada cap a les futures relacions d'evidències.

---

## Taules previstes per rúbriques i notes

Encara no implementades:

```text
rubrics
rubric_criteria
rubric_levels
assessments
assessment_students
assessment_scores
teacher_observations
```

---

## Criteri principal

La base de dades ha de guardar dades estructurades, segures i relacionades.

No s’ha de copiar literalment l’estructura d’un Google Sheet si no encaixa amb el model de l’aplicació.

Els Google Sheets poden ser font d’importació, però la web ha de treballar amb dades validades dins MySQL/MariaDB.

## Validació de coherència

Després de canvis d'esquema, executa `scripts/check-schema-coherence.php` per detectar:

- camps legacy que encara no s'han eliminat;
- relacions mal situades;
- uniques i índexs que han de seguir existint.

Per a la verificació completa de qualitat del projecte, incloent coherència d'esquema i controladors sense SQL/DDL, executa:

```text
php scripts/check-code-quality.php
```
