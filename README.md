# Mediterrani

**Projecte Mediterrani** és una aplicació web educativa per a projectes de 1r d'ESO.

Aquest document separa el que ja està implementat del que encara és previst.

## Documentació canònica

- `AGENTS.md`: criteris generals, arquitectura, seguretat i normes de treball.
- `database/README.md`: esquema i procediments de base de dades.
- `docs/skills/`: procediments detallats per àrea.
- `database/schema.sql`: autoritat executable per reconstruir una base de dades neta.

Aquest `README.md` és la introducció breu del projecte. Els detalls s'han de mantenir a la font especialitzada corresponent per evitar duplicacions.

## Estat actual

### Implementat

- aplicació PHP modular amb `app/`, `config/`, `public/`, `resources/`, `database/` i `storage/`;
- entrada única des de `public/index.php`;
- router declaratiu propi a `app/Support/Router.php`;
- connexió PDO centralitzada a `config/database.php` i configuració amb `.env`;
- vistes públiques, d'autenticació, d'alumnat, de professorat i d'administració;
- layout comú a `resources/views/layouts/app.php` amb cache-busting per CSS i JS;
- rutes actuals per a portada, projectes, detall de projecte, login, logout i dashboards privats;
- projectes carregats des de base de dades amb traduccions i assets associats;
- fitxa pública de projecte amb selecció d'asset i bloc contextual de notes per alumnat autenticat;
- login bàsic amb sessió, CSRF a tots els formularis POST, idle timeout de sessió, auditoria de login/logout/contrasenya/accessos, rate limiting d'intents i control de rols;
- analítica de visites a `site_visits` i panell d'administració amb estadístiques;
- dashboard d'administració amb visites, dispositius, sistemes operatius, geografia, mapa Leaflet, pàgines més vistes i visites recents;
- objectius d'aprenentatge, indicadors d'assoliment i semàfor personalitzat per alumne;
- equips de projecte i membres filtrats per edició i classe;
- fitxa de projecte amb seccions configurables des de `project_sections`, incloent grups i alumnes;
- capa de Google Workspace amb taules pròpies lligades a `project_academic_years`;
- endpoint webhook `POST /api/classroom/webhook` per a ingesta staging segura des de Google Apps Script, protegit amb Bearer Token i validat amb PowerShell local i ngrok;
- processador manual `ClassroomStructureSnapshotProcessingService` per transformar files `classroom_structure_snapshot` del staging en fases, tasques i enllaços Classroom a les taules finals;
- pipeline complet validat: Google Classroom API → Google Apps Script → Sheet → Webhook → Staging → Admin → Base de dades;
- refactor d'`AdminDashboardService` (600→155 línies) i `AdminClassroomService` (814→193 línies) amb extracció a 7 nous serveis;
- CSS actiu a `public/assets/css/styles.css` i JavaScript actiu a `public/assets/js/scripts.js`;
- carpeta d'assets real amb logos de projectes, col·laboradors i eines.

### Encara previst

- ampliació de rutes multidioma a `es` i `en`;
- sincronització real amb Google Docs i Google Sheets;
- nous `event_type` de Classroom com `classroom_task_snapshot` i `classroom_grade_snapshot`;
- rúbriques, notes completes i observacions d'aula;
- refinament del model de visibilitat per context d'accés;
- login amb Google.

## Distinció

- `implementat` vol dir que hi ha codi o estructura real al repositori i es pot executar ara.
- `previst` vol dir que està definit com a criteri, documentació o roadmap, però encara no és una part completa del producte.

## Arquitectura

- `public/index.php` rep les peticions.
- `app/` conté controladors, models, serveis i helpers.
- `resources/views/` conté les plantilles.
- `database/` conté l'esquema i les migracions.
- `public/assets/` conté CSS, JS i imatges públiques.

## Base de dades

El nom canònic de la base de dades local és `mediterrani`. La connexió llegeix el valor efectiu de `DB_NAME` a `.env`.

### Ja present

- `users`, `web_roles`, `user_web_roles`, `project_roles`;
- `academic_years`, `classes`, `class_members`, `class_member_history`, `class_teachers`;
- `projects`, `project_translations`, `project_academic_years`, `project_class_assignments`;
- `project_assets`, `project_asset_links`;
- `documents`, `document_sources`, `document_fragments`, `document_visibility_rules`;
- `project_sections`, `project_section_roles`;
- `objectius_aprenentatge`, `indicadors_assoliment`, `project_academic_year_objectius`, `student_indicador_assoliment`;
- `assessment_sources`, `assessment_import_runs`, `assessment_records`, `assessment_import_errors`;
- `assessment_phases`, `assessment_tasks`, `project_academic_year_phases`, `project_academic_year_phase_tasks`, `assessment_supports`, `assessment_task_resources`;
- `project_teams`, `project_team_members`, `project_team_member_roles`;
- `google_sources`, `google_documents`, `google_document_blocks`, `google_sheet_rows`, `google_sync_runs`, `google_sync_errors`;
- `classroom_webhook_runs`, `classroom_webhook_rows`;
- `site_pages`;
- `login_attempts`;
- `classrooms`, `classroom_members`, `classroom_project_academic_years`;
- `assessment_task_classroom_links`;
- `settings`;
- `site_visits`.

### Observació

- `site_visits` es garanteix des del servei d'analítica si encara no existeix.
- `database/schema.sql` és l'autoritat executable per reconstruir una base neta; `database/README.md` documenta el procediment.
- `scripts/check-schema-coherence.php` valida camps legacy i relacions mal situades després de canvis d'esquema.
- `scripts/check-code-quality.php` executa les verificacions bàsiques: lint PHP, coherència d'esquema i controladors sense SQL/DDL directe.
- si la base ja existia abans d'aquesta capa, també cal aplicar `database/09_document_tables_fix.sql`.
- per deixar els documents completament lligats a l'edició, també cal aplicar `database/17_documents_project_id_cleanup.sql` si la base ve d'una versió anterior.
- si la base ve d'una versió anterior, `assessment_records.project_id` també s'ha d'eliminar amb `database/18_assessment_records_project_id_cleanup.sql`.
- `project_id` continua sent correcte en relacions de catàleg del projecte base, com `project_translations`, `project_asset_links` i `project_sections`; el que s'elimina és l'ús de `project_id` com a context de document, import o edició.
- `project_team_member_roles` és la font per mostrar, filtrar i comptar múltiples rols de projecte per membre; `project_team_members.project_role_id` queda com a rol principal de compatibilitat.

### Documents

- els documents van lligats a `project_academic_years`, no directament a `projects`;
- la clau funcional recomanada és `project_academic_year_id + slug`;
- `project_id` és correcte per al catàleg base del projecte, però en documents i avaluació es considera herència històrica i s'està eliminant del model.

### Avaluació

- `assessment_phases` i `assessment_tasks` defineixen la plantilla reutilitzable;
- `project_academic_year_phases` activa o desactiva fases per edició de projecte;
- `project_academic_year_phase_tasks` activa o desactiva tasques per edició de projecte;
- així una fase o tasca pot reutilitzar-se en diferents anys sense copiar la definició.
- `assessment_sources` i `assessment_import_runs` treballen per `project_academic_year_id`;
- les notes i imports s'aïllen per edició, no només per projecte;
- `assessment_records` es llegeix a través de `assessment_sources`.
- la vista pública de notes és només per alumnat autenticat;
- les notes de document són internes per defecte i no s'han de mostrar a visitants ni alumnat.

### Regla del model

- `projects` és el catàleg base del projecte;
- `project_academic_years` és la unitat funcional quan una dada depèn del curs concret;
- si una entitat canvia per edició, no s'ha de resoldre només amb `projects`.

### Quan usar cada una

- `projects`: nom, slug, ordre, activació i relacions que són comunes a totes les edicions;
- `project_academic_years`: documents, imports, notes, assignacions, visibilitat i qualsevol dada que pugui variar per curs;
- si tens dubte, pregunta't si la dada canviaria l'any que ve sense canviar el projecte base; si la resposta és sí, usa `project_academic_years`.

### Encara previst

- integració real amb Google Docs i Google Sheets;
- estructures de rúbriques i notes definitives;
- ampliacions de visibilitat per context si calen més endavant.

## Rutes actuals

```text
/
/{ca|es|en}
/{ca}/que-es-entorns
/projectes
/{ca|es|en}/projectes
/{ca|es|en}/projectes/{slug}
/{ca|es|en}/projectes/{slug}/tasques
/{ca|es|en}/projectes/{slug}/notes
 /{ca|es|en}/projectes/{slug}/documents
/{ca|es|en}/projectes/{slug}/grups
/{ca|es|en}/projectes/{slug}/alumnes
/{ca|es|en}/projectes/{slug}/objectius-aprenentatge
/login
/logout
/canviar-contrasenya
/dashboard
/alumne
/professor
/admin
/admin/impersonate-student
/admin/stop-impersonation
/admin/sync-documents
POST /api/classroom/webhook
```

## Vistes actuals

- `resources/views/public/home.php`
- `resources/views/public/about.php`
- `resources/views/public/projects.php`
- `resources/views/public/project-detail.php`
- `resources/views/public/project-tasks.php`
- `resources/views/public/project-notes.php`
- `resources/views/public/project-documents.php`
- `resources/views/public/project-groups.php`
- `resources/views/public/project-students.php`
- `resources/views/public/project-objectives.php`
- `resources/views/public/access-denied.php`
- `resources/views/auth/login.php`
- `resources/views/auth/change-password.php`
- `resources/views/students/dashboard.php`
- `resources/views/teachers/dashboard.php`
- `resources/views/admin/dashboard.php`
- `resources/views/admin/document-sync.php`

## Projectes

Projectes ja definits:

```text
projecte-rius
mat-penedes
agroparc
projecte-orenetes
liquencity
vespa-velutina
ratpenats
```

Les fitxes públiques es generen amb una plantilla genèrica i es complementen amb assets del catàleg.

## Seguretat

- `.env` no s'ha de publicar;
- les contrasenyes es gestionen amb `password_hash()` i `password_verify()`;
- les zones privades depenen de sessió i rol;
- la sortida HTML s'ha d'escapar;
- Rols web: `student`, `teacher`, `guest_teacher`, `coordinator`, `admin`.

## UI

- el CSS actiu és `public/assets/css/styles.css`;
- el layout fa cache-busting de CSS i JS;
- les vistes noves han de seguir classes clares i coherents.

## Tecnologies

- PHP 8.2+ recomanat
- HTML
- CSS
- JavaScript
- MySQL / MariaDB, provat amb MariaDB 10.4.28
- PDO
- Apache amb XAMPP

## Com començar

1. Configura `.env`.
2. Engega Apache i MySQL a XAMPP.
3. Crea la base de dades `mediterrani`.
4. Importa `database/schema.sql` des de l'arrel del projecte per reconstruir una base neta.
5. Obre `http://localhost/mediterrani/public/`.
6. Si no veus canvis de CSS, fes un refresh fort.

Per actualitzar una base existent, segueix `database/README.md` i aplica només les migracions incrementals que corresponguin.

## Verificació

Abans de tancar canvis de codi, executa:

```text
php scripts/check-code-quality.php
```

També es pot executar amb Composer:

```text
composer check
```

## Full de ruta

1. [FET] Reduir la lògica i el SQL concentrats a l'administració: `AdminDashboardService` 600→155 línies, `AdminClassroomService` 814→193 línies.
2. Integrar realment Google Docs i Google Sheets.
3. Completar rúbriques, criteris, puntuacions i observacions.
4. Ampliar proves automatitzades i verificacions de seguretat.

## Autoria

Oriol Torrents Cabestany i Oriol Rovira Bertran
