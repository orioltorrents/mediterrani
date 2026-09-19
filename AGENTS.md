# AGENTS.md — Mediterrani

## Context del projecte

**Mediterrani** és una aplicació web PHP modular per a projectes educatius de 1ESO.

La plataforma inclou i amplia progressivament:

- web pública;
- espai d’alumnes;
- espai de professorat;
- panell d’administració;
- gestió d’usuaris, rols, classes i projectes;
- preparació d'una futura importació d'evidències des de Google Sheets;
- objectius d'aprenentatge, indicadors d'assoliment i un catàleg inicial d'evidències.

El projecte ja disposa d'una aplicació modular funcional. Té rutes públiques i privades, autenticació bàsica, dashboards per perfil, projectes provinents de la base de dades, seccions, equips, objectius d'aprenentatge, indicadors d'assoliment i un catàleg inicial d'evidències.

## Estat actual

### Implementat

- front controller a `public/index.php` i wrapper arrel a `index.php`;
- router declaratiu propi a `app/Support/Router.php`;
- controladors per a web pública, autenticació, alumnat, professorat i administració;
- serveis d'autenticació, projectes, assignacions, seccions, analítica, objectius, assoliment, evidències i Classroom;
- layout compartit i vistes públiques, privades i d'administració;
- login bàsic amb sessió, CSRF al formulari de login, CSRF a accions sensibles d'admin i control de rols web;
- projectes, edicions per curs, assignacions a classes i equips de projecte;
- seccions configurables de projecte;
- objectius d'aprenentatge, indicadors d'assoliment i assoliment individual per alumne;
- catàleg de categories i tipus d'evidències, encara sense relació amb alumnes ni objectius;
- Classroom i membres de Classroom dins el model actual;
- vista `access-denied.php` per a controls d'accés a contingut no autoritzat.

### Pendent o parcial

- model i importació d'evidències des de Google Sheets;
- integració real amb les API de Google Docs i Google Sheets;
- documents, assets, fases, tasques, webhooks i taules pròpies de sincronització Google de l'antic projecte, pendents de redissenyar abans de recuperar-los;
- sistema complet de rúbriques, criteris, puntuacions i observacions;
- permisos més fins segons el context i l'assignació del professorat.

---

## Entorn de desenvolupament

El projecte s’executa en local amb:

- Windows;
- XAMPP;
- Apache;
- MySQL/MariaDB;
- PHP;
- Visual Studio Code.

Ruta local del projecte:

```text
C:\xampp\htdocs\mediterrani
```

URL local:

```text
http://localhost/mediterrani/public/
```

Base de dades local:

```text
mediterrani
```

El nom es configura amb `DB_NAME` a `.env`; `mediterrani` és el valor canònic per a l'entorn local.

---

## Tecnologies del projecte

Fer servir:

- PHP sense frameworks grans;
- HTML;
- CSS;
- JavaScript;
- MySQL/MariaDB;
- PDO;
- arquitectura pròpia modular.

No fer servir:

- WordPress;
- Drupal;
- Joomla;
- Laravel;
- Symfony;
- frameworks grans;
- CMS externs;
- credencials escrites directament al codi.

---

## Estructura general del projecte

L’estructura principal del projecte és:

```text
mediterrani/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   └── Helpers/
│
├── config/
│   ├── app.php
│   └── database.php
│
├── database/
│   ├── migrations/
│   ├── seeds/
│   ├── schema.sql
│   └── 02_education_tables.sql
│
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       ├── js/
│       ├── logos/
│       └── img/
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── public/
│   │   ├── auth/
│   │   ├── students/
│   │   ├── teachers/
│   │   └── admin/
│   │
│   └── lang/
│       ├── ca.php
│       ├── es.php
│       └── en.php
│
├── storage/
│   ├── logs/
│   ├── cache/
│   └── synced/
│
├── docs/
│   └── skills/
│
├── index.php
├── .env
├── .env.example
├── .gitignore
├── composer.json
├── README.md
└── AGENTS.md
```

---

## Normes d’estructura

- `public/index.php` és el front controller real de l’aplicació.
- `index.php` a l'arrel és un wrapper que delega a `public/index.php`; no conté rutes ni lògica pròpies.
- `public/assets/` és l'única ubicació activa per al CSS, JavaScript, logos i imatges de l'aplicació PHP.
- Els fitxers JavaScript i CSS utilitzats per la web pública han de viure a `public/assets/`.
- Les vistes han d’estar dins `resources/views/`.
- La lògica PHP ha d’estar dins `app/`.
- La configuració ha d’estar dins `config/`.
- Els SQL, migracions i seeds han d’estar dins `database/`.
- Els logs, cache i dades sincronitzades han d’estar dins `storage/`.
- La documentació i els skills han d’estar dins `docs/`.
- No crear pàgines PHP soltes per cada projecte.
- No barrejar HTML, SQL i lògica de negoci dins un mateix fitxer quan es pugui evitar.

## Components PHP actuals

Controladors:

```text
AdminController
AuthController
DocumentSyncController
PublicController
StudentController
TeacherController
```

Serveis:

```text
AdminActionService
AdminAssessmentStructureService
AdminClassService
AdminClassroomService
AdminDashboardAssessmentService
AdminDashboardClassroomService
AdminDashboardProjectService
AdminDashboardService
AdminDashboardUserService
AdminProjectService
AdminStudentImportService
AdminUserService
AnalyticsService
AssessmentService
AssessmentStructureImportService
AuthService
ClassroomCsvImportService
ClassroomLookupService
ClassroomMemberImportService
ClassroomStructureSnapshotProcessingService
ClassroomTaskImportService
ClassroomWebhookService
DocumentImportService
DocumentService
GoogleSyncService
LogService
ProjectAccessService
ProjectAssetService
ProjectAssignmentService
ProjectSectionService
ProjectService
```

Helpers:

```text
AppHelper
env
lang
route
session
view
```

Aquest inventari descriu l'estat actual, però no substitueix la separació de responsabilitats: els controladors han de coordinar, els serveis han de contenir la lògica complexa i les vistes només han de presentar dades.

---

## Configuració d’entorn

El projecte utilitza un fitxer `.env`.

Exemple de configuració local:

```env
APP_NAME="Mediterrani"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/mediterrani/public

DB_HOST=localhost
DB_NAME=mediterrani
DB_USER=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

Normes:

- No publicar mai `.env`.
- No escriure credencials directament al codi.
- Crear i mantenir `.env.example` sense credencials sensibles.
- La configuració sensible ha d’estar fora del repositori.

---

## Base de dades actual

El nom canònic de la base de dades local és:

```text
mediterrani
```

La connexió sempre ha de llegir el valor efectiu des de `DB_NAME` a `.env`.

Taules existents:

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
evidencies
```

Aquest inventari conté 29 taules i coincideix amb `database/schema.sql`. Qualsevol altra taula mencionada en documentació històrica s'ha de considerar heretada o prevista, no implementada.

Per reconstruir una base neta, cal executar des de l'arrel:

```text
database/schema.sql
```

`database/schema.sql` és l'autoritat executable per a una reconstrucció neta. No s'han d'executar després totes les migracions històriques. Per actualitzar una base existent, cal seguir `database/README.md`, identificar l'estat de partida i aplicar només els ajustos incrementals corresponents.

`project_groups` és el nom legacy anterior a `project_class_assignments`; no forma part del model final d'una reconstrucció neta.

Equips de projecte:

- `project_teams` agrupa els equips per projecte i curs;
- `project_team_members` lliga usuari, equip i classe contextual dins l'edició;
- `project_team_member_roles` lliga cada pertinença amb un o més rols de projecte;
- un alumne pot tenir un equip diferent a cada projecte.

`project_team_members.project_role_id` es manté només com a rol principal de compatibilitat. Quan cal mostrar, filtrar, comptar o importar rols múltiples, la font correcta és `project_team_member_roles`. Això permet que un mateix membre compti com a `científic/a` i `cartògraf/a` dins la mateixa pertinença.

`scripts/check-schema-coherence.php` s'ha d'executar després de canvis d'esquema per detectar camps legacy, relacions mal situades i índexs o uniques esperats.

Les seccions de projecte es modelen amb `project_sections`. Els permisos detallats per secció, els assets i els recursos de tasques no formen part encara de l'esquema nou.

Regla del model:

- `projects` és el catàleg base del projecte;
- `project_academic_years` és la unitat funcional quan una dada depèn del curs concret;
- si una entitat canvia per edició, no s'ha de resoldre només amb `projects`;

Quan usar cada una:

- `projects`: nom, slug, ordre, activació i relacions comunes a totes les edicions;
- `project_academic_years`: assignacions, equips, objectius i qualsevol dada que pugui variar per curs;
- si una dada pot canviar l'any següent sense canviar el projecte base, ha d'anar a `project_academic_years`.

Estats d'edició:

- `project_academic_years.status` controla l'estat global de l'edició i pot ser `pendent`, `actiu`, `realitzat` o `arxivat`;
- `project_class_assignments.status` controla l'estat d'una edició per classe i pot ser `pendent`, `actiu` o `realitzat`;
- l'alumnat veu només edicions de l'any acadèmic actual amb estat `actiu` o `realitzat` i assignació de classe `actiu` o `realitzat`;
- professorat, coordinació i administració veuen al dashboard edicions de l'any actual amb estat `pendent`, `actiu` o `realitzat`; `arxivat` queda fora dels dashboards normals.
- `ProjectAccessService` reforça l'accés directe per URL: alumnat i professorat només poden obrir edicions assignades al seu context; admin i coordinació poden obrir edicions de projecte.

- els futurs imports d'evidències han d'anar lligats a `project_academic_year_id`;
- les dades d'evidències i assoliment són privades i no s'han de mostrar públicament;
- les taules de documents, avaluació avançada, webhooks i sincronització Google de l'antic projecte no formen part de l'esquema actual.

Objectius i assoliment:

- `objectius_aprenentatge` defineix els objectius base;
- `indicadors_assoliment` defineix els descriptors i colors del semàfor per objectiu;
- `project_academic_year_objectius` assigna objectius a una edició concreta;
- `student_indicador_assoliment` guarda el nivell seleccionat per alumne, objectiu i edició, inclòs el professor que l'ha avaluat;
- `evidencies_categoria` i `evidencies` formen un catàleg inicial d'evidències;
- encara falta modelar la relació d'una evidència concreta amb alumne, edició, objectiu i font d'importació.

---

## Connexió a base de dades

La connexió es fa amb PDO des de:

```text
config/database.php
```

Normes:

- Fer servir PDO.
- Fer servir consultes preparades.
- No concatenar dades d’usuari directament en consultes SQL.
- Fer servir `utf8mb4`.
- Fer servir `utf8mb4_unicode_ci`.
- Fer servir InnoDB.
- Gestionar errors amb excepcions.
- No mostrar errors sensibles en producció.

---

## Usuaris i rols

Taules relacionades:

```text
users
web_roles
user_web_roles
project_roles
project_team_member_roles
```

Rols web disponibles:

```text
student
teacher
guest_teacher
coordinator
admin
```

`guest_teacher` permet representar professorat visitant quan cal diferenciar-lo del professorat assignat. Els permisos contextuals fins continuen pendents de reforç.

Regla conceptual de permisos:

```text
admin       → també pot actuar com a coordinator i teacher
coordinator → també pot actuar com a teacher
teacher     → professor
student     → alumne
```

És preferible assignar diversos rols web explícits a la taula `user_web_roles`. `project_roles` descriu funcions dins dels equips i no substitueix els permisos generals de la web. Les assignacions de rols de projecte als membres d'equip s'han de consultar a `project_team_member_roles`, no només a `project_team_members.project_role_id`.

Exemples:

```text
admin       = admin + coordinator + teacher
coordinator = coordinator + teacher
teacher     = teacher
student     = student
```

Usuaris inicials previstos o creats:

```text
Oriol Torrents → admin + coordinator + teacher
Oriol Rovira   → coordinator + teacher
Àlex Martí     → teacher
Aiman          → student
Sílvia         → student
```

---

## Classes i professorat

Taules relacionades:

```text
academic_years
classes
class_members
class_teachers
```

Classes inicials (`class_name` / `class_code`):

```text
4ESO A / 4ESO-A
4ESO B / 4ESO-B
```

Assignacions inicials d’alumnes:

```text
Aiman  → 4ESO A
Sílvia → 4ESO B
```

Professorat assignat a 4ESO A i 4ESO B:

```text
Àlex Martí
Oriol Rovira
Oriol Torrents
```

`class_members` relaciona alumnes amb classes.

`class_member_history` guarda els canvis de classe per alumne.

`class_teachers` relaciona professorat amb classes.

---

## Projectes educatius

Taules relacionades:

```text
projects
project_translations
project_academic_years
project_class_assignments
project_teams
project_team_members
project_team_member_roles
```

Projectes inicials:

```text
projecte-rius
mat-penedes
agroparc
projecte-orenetes
liquencity
vespa-velutina
ratpenats
```

Noms visibles:

```text
Projecte Rius
MAT Penedès
Agroparc
Projecte Orenetes
Liquencity
Vespa velutina
Ratpenats
```

Assignacions inicials de projectes:

```text
Projecte Rius      → 4ESOA i 4ESOB
MAT Penedès        → 4ESOA
Agroparc           → 4ESOB
Projecte Orenetes  → 4ESOA
Liquencity         → 4ESOB
```

`project_class_assignments` relaciona una edició de projecte amb les classes. `project_teams`, `project_team_members` i `project_team_member_roles` gestionen els equips, les pertinences i els rols de projecte de cada edició.

---

## Norma sobre projectes

No crear fitxers PHP separats per projecte, com:

```text
agroparc.php
liquencity.php
projecte-rius.php
projecte-orenetes.php
```

Els projectes han de venir de la base de dades i mostrar-se amb una plantilla genèrica.

Vista recomanada:

```text
resources/views/public/project-detail.php
```

Ruta recomanada:

```text
/ca/projectes/{slug}
```

Exemples:

```text
/ca/projectes/projecte-rius
/ca/projectes/agroparc
/ca/projectes/liquencity
```

---

## Idiomes

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

Taules relacionades:

```text
languages
project_translations
```

Criteris:

- La interfície ha d’estar preparada per traduccions.
- Els continguts llargs poden venir més endavant de base de dades o Google Docs.
- Les rutes públiques poden incloure idioma.

---

## Rutes públiques previstes

```text
/
/ca
/ca/projectes
/ca/projectes/projecte-rius
/ca/projectes/mat-penedes
/ca/projectes/agroparc
/ca/projectes/projecte-orenetes
/ca/projectes/liquencity
/ca/projectes/vespa-velutina
```

---

## Rutes privades previstes

```text
/login
/logout

/alumne
/alumne/projectes
/alumne/materials
/alumne/rubriques

/professor
/professor/grups
/professor/alumnes
/professor/projectes
/professor/rubriques
/professor/notes

/admin
/admin/usuaris
/admin/projectes
/admin/google-sources
/admin/sincronitzacio
/admin/logs
/admin/configuracio
```

---

## Normes de codi PHP

- Fer servir `declare(strict_types=1);` en fitxers PHP nous.
- Fer servir PDO per accedir a la base de dades.
- Fer servir consultes preparades.
- No posar consultes SQL ni operacions DDL (`CREATE TABLE`, `ALTER TABLE`, etc.) dins controladors.
- No concatenar dades d’usuari directament en SQL.
- Escapar sortides HTML amb `htmlspecialchars`.
- Separar controladors, models, serveis i vistes.
- Evitar fitxers massa grans.
- Evitar repetir codi.
- No barrejar lògica de negoci dins les vistes.
- Mantenir els controladors simples.
- Posar la lògica complexa dins serveis.
- Delegar la lògica de dades, accions de negoci i manteniment d'esquema a serveis dins `app/Services/`.
- Fer que el codi sigui llegible abans que excessivament abstracte.
- Abans de tancar canvis de codi, executar `php scripts/check-code-quality.php` quan sigui possible.

---

## Normes de seguretat

- No publicar `.env`.
- No exposar dades personals a la part pública.
- No deixar fitxers de prova accessibles en producció.
- No guardar contrasenyes en text pla.
- Fer servir `password_hash()` i `password_verify()` quan hi hagi contrasenyes pròpies.
- Els usuaris que només accedeixin amb Google poden tenir `password_hash = NULL`.
- No mostrar errors sensibles en producció.
- Validar dades d’entrada.
- Escapar sortides HTML.
- Protegir les zones privades per sessió i rol.
- No posar claus de Google al JavaScript.
- No posar credencials dins fitxers versionats.

---

## Autenticació

El login bàsic amb email i contrasenya, sessió, comprovació d'usuari actiu, rols web, CSRF al formulari de login i CSRF a accions sensibles d'admin ja està implementat.

El login amb Google i l'extensió progressiva de CSRF a qualsevol nova operació sensible continuen pendents.

Camps importants a `users`:

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
- `google_id` pot ser `NULL` fins que es faci login amb Google.
- `password_hash` pot ser `NULL` si l’usuari només accedeix amb Google.
- El control de permisos web s’ha de fer amb `user_web_roles`.

---

## Control d’accés

- `/alumne` requereix rol `student`.
- `/professor` requereix rol `teacher`.
- `/admin` requereix rol `admin`.

Com que els admins i coordinadors poden tenir diversos rols explícits, no cal fer excepcions complexes.

Exemple:

```text
Oriol Torrents té admin + coordinator + teacher
```

Per tant, pot accedir a:

```text
/admin
/professor
```

---

## Google Docs i Google Sheets

La connexió real amb Google Docs i Google Sheets encara està pendent. L'esquema actual no conté taules `google_*`, `documents_*` ni staging de webhooks.

Flux previst:

```text
Google Docs / Google Sheets
        ↓
Servei PHP de sincronització
        ↓
Base de dades MySQL/MariaDB
        ↓
Validació i importació
        ↓
Base de dades de Mediterrani
        ↓
Alumnat / professorat / administració
```

Primer cas d'ús previst:

```text
Google Sheet d'evidències
        ↓
importació manual validada
        ↓
alumne + edició + objectiu + evidència
```

Abans de crear taules de sincronització, cal definir el format del full, la identitat estable de cada fila, les validacions i la relació entre evidències, alumnes, edicions i objectius. Una dada contextual ha d'anar lligada a `project_academic_year_id`, no només a `project_id`.

Normes:

- No posar credencials de Google al JavaScript.
- No publicar claus de Google.
- Validar dades abans d’importar-les.
- Guardar logs de sincronització.
- No publicar dades sensibles d’alumnes directament des d’un Google Sheet.
- Les dades privades o avaluables han de passar per la base de dades.

---

## Rúbriques i notes

El model actual només cobreix objectius, indicadors d'assoliment i el nivell seleccionat per alumne a `student_indicador_assoliment`. La importació de notes, les rúbriques, les puntuacions, les observacions i les evidències vinculades a alumnes encara estan pendents.

Taules previstes més endavant:

```text
rubrics
rubric_criteria
rubric_levels
assessments
assessment_students
assessment_scores
teacher_observations
```

Criteris:

- Les notes i observacions són dades sensibles.
- No s’han de mostrar mai públicament.
- L’accés ha d’estar restringit per rol.
- Les dades d’avaluació han d’estar controlades a la base de dades.
- Els Google Sheets poden servir com a font d’importació, però no com a publicació directa de notes.

---

## Manera de treballar

Abans de modificar fitxers importants:

1. Revisar l’estructura actual.
2. Explicar quins fitxers es canviaran.
3. Fer canvis petits i coherents.
4. No barrejar moltes funcionalitats en una sola intervenció.
5. Prioritzar estabilitat i claredat.
6. Actualitzar el README quan hi hagi canvis importants.
7. No fer canvis destructius sense confirmació explícita.

## Model recomanat de visibilitat

Quan es treballi en projectes, rutes o vistes que mostrin contingut educatiu, el criteri recomanat és:

- una sola vista per projecte;
- seccions condicionades pel context d'accés;
- no duplicar plantilles per cada perfil;
- no mostrar dades sensibles a visitants ni alumnat si no toca;
- reservar programacions completes, deadlines i informació interna per al professorat que imparteix;
- deixar la porta oberta a un mode de visitant amb informació pública i organitzativa.

Si un canvi implica separar massa la informació en fitxers o rutes diferents, cal aturar-se i valorar una solució més centralitzada.

---

## Canvis destructius

No fer sense confirmació explícita:

```text
DROP DATABASE
DROP TABLE
TRUNCATE TABLE
DELETE sense WHERE
reescriptura completa de fitxers importants
eliminació de carpetes
canvis massius en l’estructura
```

Quan calgui fer un canvi destructiu, primer cal explicar:

```text
què s’eliminarà
per què cal eliminar-ho
com es pot recuperar
quina alternativa menys destructiva hi ha
```

---

## Roadmap pendent

Les bases d'arquitectura, connexió PDO, projectes des de la base de dades, layout, login, rols i dashboards ja estan implementades. Les prioritats pendents són:

```text
1. [FET] Reforçar CSRF, sessions, auditoria i permisos contextuals.
   - CSRF complet: tots els formularis POST coberts (login, canvi contrasenya,
     admin, impersonate, sync-documents)
   - Idle timeout de sessió (2 hores) amb neteja automàtica
   - Auditoria: login (exit/fail), logout, canvi contrasenya (exit/fail),
     accessos a dashboards, accions admin
   - Rate limiting: 5 intents/IP, 10 intents/email en finestra de 15 minuts
   - Logout via POST amb CSRF (GET també funciona)
   - Revalidació de sessió cada 5 minuts (is_active + rols)
2. [FET] Reduir la lògica i el SQL concentrats a l'administració.
   - AdminDashboardService: 600→155 línies (extret a 4 sub-serveis)
   - AdminClassroomService: 814→193 línies (extret a 3 sub-serveis + lookup service)
   - Creats: ClassroomCsvImportService, ClassroomLookupService,
     ClassroomMemberImportService, ClassroomTaskImportService
3. Integrar realment Google Docs i Google Sheets.
4. Completar rúbriques, criteris, puntuacions i observacions.
5. Ampliar proves automatitzades i verificacions de seguretat.
```

---

## Skills disponibles

Consultar els fitxers de `docs/skills/` abans de fer tasques específiques.

Skills disponibles:

```text
docs/skills/01-arquitectura-php.md
docs/skills/02-base-de-dades.md
docs/skills/03-rutes-i-vistes.md
docs/skills/04-auth-rols-i-seguretat.md
docs/skills/05-google-docs-sheets.md
docs/skills/06-css-i-ui.md
docs/skills/07-assets-projectes.md
```

---

## Fonts canòniques de documentació

Per evitar informació duplicada o contradictòria, cada document té una responsabilitat concreta:

- `AGENTS.md`: criteris generals, arquitectura, seguretat i normes de treball;
- `database/README.md`: esquema i procediments de base de dades;
- `docs/skills/`: procediments detallats per àrea;
- `README.md`: introducció, instal·lació i enllaços a la documentació canònica;
- `database/schema.sql`: autoritat executable per reconstruir una base de dades neta.

Quan hi hagi una discrepància sobre l'esquema executable, preval `database/schema.sql`. La configuració de connexió efectiva sempre prové de `.env`.

---

## Regla principal per a Codex

Abans de fer canvis, Codex ha de tenir present:

```text
Aquest projecte és una aplicació PHP pròpia, modular, sense frameworks grans, amb dades educatives sensibles i preparada per créixer de manera ordenada.
```

Prioritats:

```text
1. Seguretat.
2. Claredat.
3. Estructura.
4. Dades ben modelades.
5. Reutilització.
6. Escalabilitat.
7. Disseny i experiència d’usuari.
```

---

## Resum del criteri general

No construir una col·lecció de pàgines PHP independents.

Construir una aplicació web modular on:

```text
public/index.php rep les peticions
app/ conté la lògica
resources/views/ conté les plantilles
config/ conté la configuració
database/ conté SQL i migracions
storage/ conté logs i cache
MySQL/MariaDB guarda les dades
Google Docs/Sheets poden actuar com a font sincronitzable
```

El codi ha de permetre que Mediterrani evolucioni cap a una plataforma educativa completa.
