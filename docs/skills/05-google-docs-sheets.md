# Skill 05 — Google Docs i Google Sheets

## Objectiu

Preparar el projecte **Mediterrani** per sincronitzar continguts i dades des de Google Workspace.

Google Docs i Google Sheets poden funcionar com a espai de treball del professorat.

La web ha de mostrar aquesta informació de manera controlada, segura i estructurada.

## Estat actual

### Implementat

- configuració base a `config/google.php`;
- integració parcial amb Google Docs API mitjançant compte de servei per sincronitzar pàgines públiques globals a `site_pages`;
- servei `GoogleSyncService` present com a stub de consulta, sense connexió real amb l'API;
- estructura de base preparada per a fonts, documents sincronitzats, files importades, execucions i errors;
- importació manual JSON de documents amb `DocumentSyncController` i `DocumentImportService`;
- model de dades per a projectes, assets i avaluació ja existent;
- quan el contingut és d'una edició concreta, la unitat funcional és `project_academic_years`.

### Encara previst

- integració real completa amb API de Google per a projectes, documents interns i Sheets;
- OAuth d'usuari si més endavant cal sincronitzar contingut fora del compte de servei;
- importació robusta directa de Docs i Sheets;
- sanitització HTML, límits d'importació, logs, reintents i reprocessament d'errors;
- definició definitiva del flux entre document origen, `google_sources`, `document_sources`, BD i vista final;
- sincronització automàtica amb cron o worker.

---

## Flux general previst

```text
Google Docs / Google Sheets
        ↓
Servei PHP de sincronització
        ↓
Base de dades MySQL/MariaDB
        ↓
Web pública / alumnat / professorat / administració
```

---

## Flux implementat per pàgines públiques globals

Les pàgines públiques globals que no depenen d'una edició concreta poden usar `site_pages` com a taula final publicable.

Flux actual:

```text
Google Doc
        ↓
acció manual d'admin
        ↓
SitePageService::syncPage()
        ↓
site_pages.content_json / plain_text / version_hash
        ↓
vista pública
```

Regles:

- la web pública no llegeix Google Docs en directe;
- Google Docs només s'invoca des d'accions d'administració o scripts de prova;
- el Google Doc s'identifica a `site_pages.google_file_id`;
- el contingut publicat és el JSON sincronitzat a `site_pages.content_json`;
- si `content_json` és buit, la vista pot mostrar un fallback local no sincronitzat;
- les credencials del compte de servei no es versionen.

Configuració local necessària:

```env
GOOGLE_SYNC_ENABLED=true
GOOGLE_SERVICE_ACCOUNT_PATH=storage/credentials/google-service-account.json
GOOGLE_CA_BUNDLE_PATH=storage/certs/cacert.pem
```

Fitxers locals privats:

```text
storage/credentials/google-service-account.json
storage/certs/cacert.pem
```

El document de Google s'ha de compartir amb el `client_email` del compte de servei com a lector.

El render actual de Google Docs a pàgines públiques suporta títols, capçaleres, paràgrafs, negreta, cursiva, enllaços `http/https`, llistes numerades, llistes de pics i nivells bàsics d'indentació. Imatges i taules encara no formen part d'aquest render.

---

## Nivells actuals

L'estat del projecte s'ha de llegir en tres nivells diferents:

```text
1. Importació manual JSON de documents → implementada.
2. Persistència Google Workspace       → taules preparades.
3. API Google Docs per site_pages      → implementació parcial.
4. API Google per projectes i Sheets   → pendent.
```

La importació manual JSON no és encara sincronització amb l'API de Google. Serveix per carregar documents, fonts, fragments i regles de visibilitat a partir d'un payload controlat.

---

## Principi general

Google és l’espai d’edició del professorat.

La base de dades és l’espai controlat des d’on la web mostra informació.

`project_id` continua sent útil per al catàleg base del projecte, però quan el contingut depèn d'un curs o d'una edició concreta cal partir de `project_academic_year_id`.

Quan un contingut tingui diferents nivells de visibilitat, la recomanació és classificar-lo per context i no publicar directament el document original sense control.

`GoogleSyncService` actualment només recupera fonts actives per `project_academic_year_id` i retorna un resultat `pending` a `syncProjectAcademicYear()`. No llegeix Google Docs ni Google Sheets, no crea `google_sync_runs`, no registra errors i no actualitza `last_synced_at`.

---

## Google Docs

Ús previst:

- programacions;
- guies didàctiques;
- textos públics dels projectes;
- materials d’alumnes;
- materials de professorat;
- documents multilingües.

Nivells de visibilitat recomanats:

```text
public
students
teachers
assigned_teachers
admin
```

Exemples:

- `public`: presentació i organització general;
- `students`: bastides, ajudes i materials d'alumnat;
- `teachers`: programacions i visió general;
- `assigned_teachers`: programacions completes, terminis i materials interns;
- `admin`: contingut i control total.

Opcions:

```text
1. Incrustar document directament.
2. Llegir document amb API i mostrar-lo.
3. Sincronitzar document a la base de dades.
```

---

## Quan no cal base de dades

No cal base de dades si el document és:

- públic;
- simple;
- només informatiu;
- sense dades sensibles;
- sense necessitat de filtres;
- sense necessitat de permisos interns.

Exemples:

```text
document públic de presentació
guia pública
document de difusió
```

---

## Quan sí convé base de dades

Sí convé base de dades si el document és:

- intern;
- sensible;
- reutilitzable;
- multilingüe;
- relacionat amb projectes;
- amb visibilitat segons rol;
- necessari per dashboards;
- necessari per historial o control de versions.

Exemples:

```text
programacions internes
materials de professorat
continguts d’alumnat amb permisos
documents per projecte i idioma
```

---

## Google Sheets

Ús previst:

- rúbriques;
- dades de camp;
- llistats;
- calendaris;
- registres;
- dades científiques;
- dades agregades;
- importacions d’alumnes o grups.

Per a dades d'aula i avaluació, la regla és més estricta: el Sheet pot ser font d'entrada, però la publicació final ha de passar per la base de dades i pels permisos del sistema.

---

## Quan no cal base de dades amb Sheets

No cal base de dades si el Sheet és:

- públic;
- simple;
- sense dades personals;
- sense dades sensibles;
- només visual;
- sense dashboards avançats.

Exemples:

```text
calendari públic
taula pública de resultats agregats
llistat públic de recursos
```

---

## Quan sí cal base de dades amb Sheets

Sí cal base de dades si el Sheet conté:

- alumnes;
- notes;
- rúbriques avaluades;
- observacions;
- dades d’aula;
- dades privades;
- dades filtrades per usuari;
- dades per dashboards;
- dades que cal validar;
- dades que necessiten historial.

Exemples:

```text
notes d’alumnes
rúbriques
seguiment de grups
dades científiques per gràfiques
observacions de professorat
```

---

## Taules existents

```text
google_sources
google_documents
google_document_blocks
google_sheet_rows
google_sync_runs
google_sync_errors
classroom_webhook_runs
classroom_webhook_rows
```

Aquestes taules ja existeixen. El que està pendent és fer-les servir en una sincronització real amb l'API.

Les taules `classroom_webhook_*` són staging d'ingesta des de Google Apps Script; no substitueixen cap taula final d'avaluació.

També existeix la capa de documents pròpia de l'aplicació:

```text
documents
document_sources
document_fragments
document_visibility_rules
```

`document_sources` descriu fonts associades als documents interns de l'aplicació. `google_sources` descriu possibles fonts de Google Workspace per edició de projecte. La relació definitiva entre totes dues capes encara és una decisió pendent.

Regla clau: les taules `google_*` no substitueixen les taules `documents_*`.

```text
google_*    -> capa d'origen i sincronització amb Google Workspace
documents_* -> capa interna publicable de l'aplicació
```

El contingut de Google Docs no s'ha de mostrar directament a la web final sense validació, sanitització i transformació quan calgui. La capa `documents`, `document_fragments` i `document_visibility_rules` continua sent la font que l'aplicació pot publicar, filtrar i mostrar segons permisos.

Flux recomanat per Docs:

```text
Google Docs
→ google_sources
→ google_documents
→ google_document_blocks
→ documents / document_fragments / document_visibility_rules
→ web pública / alumnat / professorat / administració
```

Flux recomanat per Sheets:

```text
Google Sheets
→ google_sources
→ google_sheet_rows
→ assessment_records o altres taules finals
→ web privada o dashboards
```

---

## `google_sources`

Registra documents o fulls de Google vinculats a una edició concreta de projecte.

Camps:

```text
id
project_academic_year_id
source_type
google_file_id
google_file_url
sheet_name
range_name
language_code
content_type
visibility
sync_mode
is_active
last_synced_at
created_at
updated_at
```

Tipus de font:

```text
google_doc
google_sheet
google_drive_file
```

Tipus de contingut:

```text
programacio
material_alumnes
material_professorat
rubrica
dades_camp
calendari
noticies
```

Visibilitat:

```text
public
students
teachers
assigned_teachers
admin
```

Nota de model: `google_sources.visibility` utilitza valors en plural (`students`, `teachers`, `assigned_teachers`). La capa interna de documents utilitza valors relacionats però no idèntics: `documents.default_visibility` fa servir `student`, `teacher` i `assigned_teacher`, i `document_visibility_rules.visibility_type` diferencia `public`, `role`, `project_role`, `class` i `assigned_teacher`. Aquesta diferència s'ha de tenir present en qualsevol transformació entre Google i documents interns.

Mode de sincronització:

```text
manual
automatic
disabled
```

---

## `google_documents`

Guarda contingut processat de Google Docs.

Camps:

```text
id
google_source_id
project_academic_year_id
language_code
title
content_html
plain_text
version_hash
synced_at
is_active
created_at
updated_at
```

La clau funcional ha de ser `google_source_id + language_code`, i la dada ha d'anar lligada a `project_academic_year_id`.

---

## `google_document_blocks`

Guarda blocs o fragments processats d'un document sincronitzat.

Camps:

```text
id
google_document_id
google_source_id
project_academic_year_id
visibility_level
section_title
slug
content_html
display_order
is_active
created_at
updated_at
```

La clau funcional recomanada és `google_document_id + slug`. La taula manté també `google_source_id` i `project_academic_year_id` per facilitar filtres de lectura i coherència amb l'edició de projecte.

---

## `google_sheet_rows`

Guarda files importades de Google Sheets.

Camps:

```text
id
google_source_id
project_academic_year_id
external_id
row_number
row_data_json
row_hash
is_active
synced_at
created_at
updated_at
```

Aquesta taula es pot usar com a zona intermèdia abans de transformar les dades cap a taules finals.

---

## `google_sync_runs`

Registra cada execució de sincronització.

Camps:

```text
id
google_source_id
project_academic_year_id
started_by_user_id
started_at
finished_at
status
rows_read
rows_created
rows_updated
rows_skipped
errors_count
message
```

Estats:

```text
pending
running
completed
completed_with_warnings
failed
```

---

## `google_sync_errors`

Registra errors de sincronització.

Camps:

```text
id
google_sync_run_id
project_academic_year_id
row_number
field_name
error_message
raw_value
created_at
```

---

## Sincronització recomanada

Fase inicial:

```text
importació manual JSON de documents
```

Fase posterior:

```text
sincronització automàtica amb cron
```

Fase avançada:

```text
Apps Script o notificacions des de Google
```

Components actuals de la fase inicial:

- `DocumentSyncController`: protegeix la pantalla amb rol `admin`, rep el JSON i mostra resultat o error;
- `DocumentImportService`: valida l'estructura bàsica del payload i importa documents, fonts, fragments i regles;
- `resources/views/admin/document-sync.php`: formulari d'entrada manual del JSON.

Limitació actual: aquesta importació manual no té CSRF propi i no defineix límits explícits de mida del payload.

---

## Validació de dades

Abans d’importar dades d’un Sheet cal validar:

- columnes esperades;
- emails;
- dates;
- números;
- duplicats;
- camps obligatoris;
- relació amb projectes;
- relació amb classes;
- dades buides;
- formats incorrectes.

Per a contingut contextual, la validació ha de partir de `project_academic_year_id`.

---

## Identificador estable

Cada fila important d’un Sheet hauria de tenir un identificador estable.

Exemples:

```text
id_mostra
id_criteri
id_alumne
id_registre
external_id
```

Si no hi ha identificador, es pot utilitzar una combinació controlada de camps, però és menys recomanable.

---

## Estratègia d’importació

Estratègia recomanada:

```text
1. Llegir dades del Sheet.
2. Guardar files a google_sheet_rows.
3. Validar dades.
4. Transformar dades.
5. Actualitzar taules finals.
6. Registrar sync_run.
7. Registrar errors si n’hi ha.
```

---

## Webhook Google Classroom

El projecte disposa d'un webhook staging per rebre payloads de Google Classroom enviats des de Google Apps Script.

Ruta:

```text
POST /api/classroom/webhook
```

Flux actual:

```text
Google Apps Script o prova local
→ POST /api/classroom/webhook
→ ApiClassroomController
→ ClassroomWebhookService
→ classroom_webhook_runs / classroom_webhook_rows
```

Configuració necessària:

```env
CLASSROOM_WEBHOOK_TOKEN=
```

El controlador valida el header:

```text
Authorization: Bearer <token>
```

El valor rebut ha de coincidir amb `CLASSROOM_WEBHOOK_TOKEN`. El token no s'ha d'escriure mai al codi ni al JavaScript públic.

Estat actual:

- el webhook està implementat com a staging genèric;
- accepta `event_type = classroom_extraction_test` i `classroom_structure_snapshot`;
- guarda el payload complet a `classroom_webhook_runs.raw_payload_json`;
- guarda cada fila a `classroom_webhook_rows.raw_row_json`;
- no transforma encara dades cap a notes, rúbriques o tasques finals;
- s'ha validat localment amb PowerShell contra XAMPP;
- s'ha validat des de Google Apps Script mitjançant ngrok amb resposta `200` i `received_rows = 1`.

Payload de prova:

```json
{
  "source": "google_apps_script",
  "event_type": "classroom_extraction_test",
  "extracted_at": "2026-07-24 19:54:05",
  "rows": [
    {
      "academic_year": "2025-2026",
      "classroom_key": "25-26_4esoab_projecte-rius",
      "project_slug": "projecte-rius",
      "message": "Prova webhook local",
      "row_count": 28
    }
  ]
}
```

Prova local amb PowerShell:

```powershell
$body = @{
  source = "google_apps_script"
  event_type = "classroom_extraction_test"
  extracted_at = "2026-07-24 19:54:05"
  rows = @(
    @{
      academic_year = "2025-2026"
      classroom_key = "25-26_4esoab_projecte-rius"
      project_slug = "projecte-rius"
      message = "Prova webhook local"
      row_count = 28
    }
  )
} | ConvertTo-Json -Depth 5

Invoke-RestMethod `
  -Uri "http://localhost/mediterrani/public/api/classroom/webhook" `
  -Method Post `
  -Headers @{ Authorization = "Bearer <token>" } `
  -ContentType "application/json" `
  -Body $body
```

Resposta correcta:

```json
{
  "ok": true,
  "run_id": 1,
  "received_rows": 1
}
```

Per provar des de Google Apps Script real cal una URL accessible públicament. `localhost` no és accessible des dels servidors de Google. Opcions recomanades:

- ngrok;
- Cloudflare Tunnel;
- servidor de staging accessible per HTTPS.

Prova validada amb ngrok:

```text
Google Apps Script
→ https://<subdomini-ngrok>/mediterrani/public/api/classroom/webhook
→ XAMPP local
→ MySQL staging
```

Resposta observada:

```text
200
{"ok":true,"run_id":2,"received_rows":1}
```

La URL de ngrok és temporal i no s'ha de documentar com a configuració fixa. Quan es tanqui la sessió de ngrok, la URL deixa de servir.

Event types previstos:

```text
classroom_extraction_test  -> prova de connectivitat i staging
classroom_structure_snapshot -> fases, tasques i enllaços Classroom des d'un Sheet controlat
classroom_task_snapshot    -> tasques i identificadors Classroom sense notes, si cal separar-ho més endavant
classroom_grade_snapshot   -> notes, rúbriques i criteris avaluats
```

Els nous `event_type` s'han d'afegir explícitament al servei i han de continuar guardant primer el payload cru abans de processar-lo cap a taules finals.

Per `classroom_structure_snapshot`, els camps obligatoris de cada fila són:

```text
academic_year
classroom_key
project_slug
phase_key
phase_title
task_key
task_title
task_url
```

Camps opcionals recomanats:

```text
classroom_name
classroom_url
google_classroom_id
google_course_work_id
google_rubric_id
role_filter
```

Aquest event_type queda primer en staging. El processament manual des de l'admin, mitjançant `ClassroomStructureSnapshotProcessingService`, llegeix les files `pending` i crea o actualitza:

```text
classrooms
classroom_project_academic_years
assessment_phases
assessment_tasks
project_academic_year_phases
project_academic_year_phase_tasks
assessment_task_classroom_links
```

El processament manual no toca usuaris, notes, rúbriques finals ni comentaris. Cada fila passa a `processed` o `error` amb `process_error` informatiu.

### Pipeline complet: Google Classroom → base de dades

L'extracció des de Google Apps Script funciona amb dues funcions principals al GAS:

1. **`generarLlistatAssignmentsResumit(idCurs)`** — llegeix un curs de Classroom:
   - Cursos actius (amb `Classroom.Courses.list`)
   - Topics (amb `Classroom.Courses.Topics.list`)
   - CourseWork publicat i esborrany (amb `Classroom.Courses.CourseWork.list`)
   - Genera una pestanya `{classroomKey}_phases+tasks` al Sheet actiu amb 14 columnes:
     `academic_year, classroom_key, classroom_name, classroom_url, google_classroom_id, project_slug, phase_key, phase_title, task_key, task_title, task_url, role_filter, google_course_work_id, google_rubric_id`
2. **`sendStructureSnapshot()`** — llegeix la pestanya `structure_snapshot` del Sheet, converteix les files a un array d'objectes (amb `readSheetRows` + `rowsToObjects`) i envia un POST JSON al webhook amb `event_type = classroom_structure_snapshot`.

Flux complet validat:

```text
Google Classroom API
    ↓ (GAS: generarLlistatAssignmentsResumit)
Sheet → pestanya structure_snapshot
    ↓ (GAS: sendStructureSnapshot → POST JSON amb Authorization Bearer)
Ngrok tunnel → XAMPP local
    ↓ (ApiClassroomController → ClassroomWebhookService)
classroom_webhook_rows (staging, process_status = pending)
    ↓ (Admin → botó "Processar snapshots pendents")
ClassroomStructureSnapshotProcessingService
    ↓ (AdminClassroomService::importTaskLinkRowData → transacció)
classrooms, assessment_phases, assessment_tasks,
project_academic_year_phases, project_academic_year_phase_tasks,
assessment_task_classroom_links
```

**Regla de `role_filter`:**
- Si `role_filter` està buit o val `"General"`, la tasca es mostra a tots els alumnes.
- Si `role_filter` conté un rol concret (ex. `"Cartògraf/a"`), només el veuen els alumnes amb aquell rol de projecte.

---

## Normes de seguretat

- No posar credencials de Google al JavaScript.
- No posar claus de Google dins el repositori.
- No publicar fitxers de credencials.
- No mostrar dades privades directament des de Google Sheets.
- No publicar notes ni dades d’alumnes.
- Validar sempre abans d’importar.
- Guardar logs de sincronització.
- No esborrar dades automàticament sense control.

---

## Backlog pendent

Aquests punts no estan resolts encara i formen part de l'evolució prevista:

- implementar OAuth o compte de servei amb scopes mínims;
- llegir contingut real de Google Docs i Google Sheets des del servidor;
- sanititzar `content_html` abans de publicar-lo;
- definir límits de mida per importacions manuals i automàtiques;


- registrar `google_sync_runs` i `google_sync_errors` durant sincronitzacions reals;
- definir política de reintents i reprocessament d'errors;
- automatitzar sincronització amb cron, worker o mecanisme equivalent;
- decidir la relació definitiva entre `google_sources` i `document_sources`;
- normalitzar o mapar explícitament les visibilitats singulars i plurals.

---

## Criteri principal

Google Docs i Google Sheets poden ser fonts de treball.

La web ha de mostrar dades des de la base de dades quan calgui seguretat, permisos, filtres, dashboards o historial.

La integració amb Google s’ha de fer des del servidor PHP, no des del navegador.
