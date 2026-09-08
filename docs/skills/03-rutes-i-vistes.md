# Skill 03 — Rutes i vistes

## Objectiu

Organitzar les rutes i les vistes del projecte **Mediterrani** sense crear fitxers PHP solts per cada pàgina.

El projecte ha de funcionar com una aplicació PHP modular.

## Estat actual

### Implementat

- entrada única a `public/index.php`;
- router declaratiu propi a `app/Support/Router.php` amb rutes `GET`, `POST` i `ANY`;
- rutes públiques per portada, llistat de projectes, detall de projecte, tasques, notes, documents, grups, alumnes, objectius i login;
- rutes privades per a alumnat, professorat, administració i sincronització manual de documents;
- vistes ja creades a `resources/views/public`, `auth`, `students`, `teachers` i `admin`;
- layout compartit a `resources/views/layouts/app.php`.

### Encara previst

- fer que el prefix d'idioma governi completament l'idioma intern;
- una vista 404 dedicada;
- afinar la vista de projecte per més contextos d'accés sense duplicar fitxers.

## Criteri d'edició

Quan una vista mostri documents, notes o materials que depenen del curs concret del projecte, la unitat funcional ha de ser `project_academic_years`.

- `projects` identifica el projecte base;
- `project_academic_years` identifica l'edició activa;
- les vistes públiques han de resoldre l'edició abans de pintar dades sensibles o contextuals.

---

## Punt d’entrada

El punt d’entrada és:

```text
public/index.php
```

Totes les peticions principals han de passar per aquest fitxer.

---

## Rutes actuals

El router actual és `app/Support/Router.php` i les rutes es registren a `public/index.php`. Aquesta és la matriu de rutes que es poden mantenir ara:

| Ruta | Mètode | Controlador | Accés | Vista |
| --- | --- | --- | --- | --- |
| `/` | GET | `PublicController::home()` | Públic | `public.home` |
| `/ca` | GET | `PublicController::home()` | Públic | `public.home` |
| `/ca/que-es-entorns` | GET | `PublicController::about()` | Públic | `public.about` |
| `/projectes` | GET | `PublicController::projects()` | Públic | `public.projects` |
| `/ca/projectes` | GET | `PublicController::projects()` | Públic | `public.projects` |
| `/es/projectes` | GET | `PublicController::projects()` | Públic | `public.projects` |
| `/en/projectes` | GET | `PublicController::projects()` | Públic | `public.projects` |
| `/{ca|es|en}/projectes/{slug}` | GET | `PublicController::projectDetail()` | Públic amb blocs contextuals | `public.project-detail` |
| `/{ca|es|en}/projectes/{slug}/tasques` | GET | `PublicController::projectTasks()` | Públic amb blocs contextuals | `public.project-tasks` |
| `/{ca|es|en}/projectes/{slug}/notes` | GET | `PublicController::projectNotes()` | Ruta pública, contingut restringit a alumnat autenticat | `public.project-notes` |
| `/{ca|es|en}/projectes/{slug}/documents` | GET | `PublicController::projectDocuments()` | Públic amb documents filtrats per context | `public.project-documents` |
| `/{ca|es|en}/projectes/{slug}/grups` | GET | `PublicController::projectGroups()` | Alumne: equip propi; professorat: equips de les classes assignades | `public.project-groups` |
| `/{ca|es|en}/projectes/{slug}/alumnes` | GET | `PublicController::projectStudents()` | Professorat, coordinació i administració | `public.project-students` |
| `/{ca|es|en}/projectes/{slug}/objectius-aprenentatge` | GET | `PublicController::projectObjectives()` | Contextual per edició | `public.project-objectives` |
| `/login` | GET/POST | `AuthController::login()` | Públic | `auth.login` |
| `/logout` | Qualsevol | `AuthController::logout()` | Sessió, si existeix | Sense vista |
| `/canviar-contrasenya` | GET/POST | `AuthController::changePassword()` | Usuari amb canvi obligatori | `auth.change-password` |
| `/dashboard` | GET | `AuthController::redirectToDashboard()` | Usuari autenticat | Redirecció |
| `/alumne` | Qualsevol | `StudentController::dashboard()` | `student` | `students.dashboard` |
| `/professor` | Qualsevol | `TeacherController::dashboard()` | `teacher` | `teachers.dashboard` |
| `/admin` | Qualsevol | `AdminController::dashboard()` | `admin` | `admin.dashboard` |
| `/admin/impersonate-student` | POST | `AuthService::impersonateStudent()` | Actor `admin` | Redirecció |
| `/admin/stop-impersonation` | POST | `AuthService::stopImpersonating()` | Actor `admin` | Redirecció |
| `/admin/sync-documents` | GET | `DocumentSyncController::index()` | `admin` | `admin.document-sync` |
| `/admin/sync-documents` | POST | `DocumentSyncController::store()` | `admin` | `admin.document-sync` |
| `/api/classroom/webhook` | POST | `ApiClassroomController::webhook()` | Token Bearer | JSON |

Nota: el router retorna `404` quan no troba cap ruta i `405` quan la ruta existeix però el mètode HTTP no és permès. Algunes rutes es mantenen com a `ANY` perquè el controlador actual encara gestiona formularis i pantalla al mateix endpoint.

Una ruta pot ser pública i, alhora, contenir blocs protegits. Per exemple, la ruta de notes existeix públicament, però la informació de notes només s'ha de mostrar a l'alumnat autenticat corresponent.

---

## Rutes previstes

```text
/alumne/projectes
/alumne/materials
/alumne/rubriques
/professor/grups
/professor/alumnes
/professor/projectes
/professor/rubriques
/professor/notes
/admin/usuaris
/admin/projectes
/admin/google-sources
/admin/sincronitzacio
/admin/logs
/admin/configuracio
```

Aquestes rutes formen part del mapa funcional desitjat, però no s'han de documentar com a disponibles fins que existeixin al router.

---

## Vistes

Les vistes han d’estar a:

```text
resources/views/
```

Estructura recomanada:

```text
resources/views/
├── layouts/
├── public/
├── auth/
├── students/
├── teachers/
└── admin/
```

---

## Distinció d'estat

- `previstes` són les rutes o vistes que el projecte encara vol incorporar o millorar.
- `actuals` són les que ja es poden veure i mantenir al codi.

Quan un fitxer ja existeix, s'ha de documentar com a realitat del projecte, no com a pla.

## Layouts

Fitxers recomanats:

```text
resources/views/layouts/app.php
resources/views/layouts/header.php
resources/views/layouts/footer.php
```

Objectiu:

- no repetir `<html>`, `<head>`, `<body>`, header i footer a cada pàgina;
- tenir una estructura visual comuna;
- facilitar canvis globals de disseny.

## Nota important per a assets públics

Els fitxers JavaScript i CSS que la web ha de carregar des del navegador han d’estar dins de `public/assets/`.
Si un script es posa a `assets/` fora de `public/`, el navegador no el veurà i les interaccions del panell (per exemple, el botó Editar) deixaran de funcionar.

---

## Vistes públiques

Carpeta:

```text
resources/views/public/
```

Fitxers actuals:

```text
about.php
home.php
projects.php
project-detail.php
project-tasks.php
project-notes.php
project-documents.php
access-denied.php
```

Funció:

```text
about.php             → pàgina qui som
home.php              → pàgina inicial
projects.php          → llistat de projectes
project-detail.php    → detall genèric d’un projecte
project-tasks.php     → tasques visibles segons context
project-notes.php     → notes per alumnat autenticat quan correspongui
project-documents.php → documents filtrats segons context
project-groups.php     → equips i membres filtrats per alumne, professor i classe
project-students.php   → alumnes de la classe contextual del projecte
project-objectives.php → objectius i semàfor d'assoliment de l'alumne
access-denied.php     → missatge d'accés no autoritzat
```

## Model recomanat de visibilitat

La vista de projecte hauria de ser única i mostrar seccions diferents segons el context d'accés.

Orientació recomanada:

- visitant: només informació pública i organitzativa;
- alumnat: tasques assignades, bastides, ajudes i dades d'aula quan toqui;
- professorat visitant: programacions i visió general, però no dades sensibles ni deadlines;
- professorat que imparteix: contingut complet del projecte;
- administració: tot.

Si cal mostrar més o menys informació, és preferible condicionar blocs dins la mateixa vista abans que crear fitxers separats per rol. Les subpàgines actuals de tasques, notes i documents són vistes funcionals del mateix projecte, no plantilles duplicades per perfil.

---

## Vistes d’autenticació

Carpeta:

```text
resources/views/auth/
```

Fitxers actuals:

```text
change-password.php
login.php
```

`forgot-password.php` és una possible vista futura, però no existeix actualment.

---

## Vistes d’alumnes

Carpeta:

```text
resources/views/students/
```

Fitxers actuals:

```text
dashboard.php
```

Les vistes `projects.php`, `materials.php` i `rubrics.php` són previstes, no actuals.

---

## Vistes de professorat

Carpeta:

```text
resources/views/teachers/
```

Fitxers actuals:

```text
dashboard.php
```

Les vistes `groups.php`, `students.php`, `projects.php`, `rubrics.php` i `grades.php` són previstes, no actuals.

---

## Vistes d’administració

Carpeta:

```text
resources/views/admin/
```

Fitxers actuals:

```text
dashboard.php
document-sync.php
```

Les vistes `users.php`, `projects.php`, `google-sources.php`, `sync.php`, `logs.php` i `settings.php` són previstes, no actuals.

---

## Projectes

No crear fitxers com:

```text
projectes/agroparc.php
projectes/liquencity.php
projectes/projecte-rius.php
```

Fer servir vistes genèriques per projecte, carregant dades segons `slug` i context:

```text
resources/views/public/project-detail.php
resources/views/public/project-tasks.php
resources/views/public/project-notes.php
resources/views/public/project-documents.php
```

No crear una plantilla diferent per a cada projecte ni per a cada rol.

Exemples de slug:

```text
projecte-rius
mat-penedes
agroparc
projecte-orenetes
liquencity
vespa-velutina
```

---

## Controladors relacionats

Controladors actuals:

```text
AdminController.php
ApiClassroomController.php
AuthController.php
DocumentSyncController.php
PublicController.php
StudentController.php
TeacherController.php
```

Exemple de responsabilitat:

```text
PublicController            → home, llistat, detall, tasques, notes i documents de projecte
ApiClassroomController      → webhook staging de Classroom
AuthController              → login, logout i redirecció de dashboard
StudentController           → dashboard d’alumne
TeacherController           → dashboard professor
AdminController             → dashboard admin
DocumentSyncController      → importació manual JSON de documents
```

---

## Rutes i idioma

L’idioma principal és:

```text
ca
```

Rutes públiques actuals amb prefix:

```text
/ca/projectes
/es/projectes
/en/projectes
/ca/projectes/{slug}
/es/projectes/{slug}
/en/projectes/{slug}
```

El router reconeix el prefix `ca`, `es` i `en` per llistats i fitxes de projecte. Ara mateix, però, el prefix capturat no governa completament l'idioma intern perquè els controladors reben principalment el `slug`; l'idioma continua depenent de la lògica existent de sessió o paràmetres. Fer que el prefix sigui la font efectiva de l'idioma és una millora pendent.

---

## 404

Si una ruta no existeix, mostrar una resposta 404.

Si un projecte no existeix, també mostrar 404.

No mostrar errors interns al visitant.

Estat actual: el router retorna text pla `Pagina no trobada`. Una vista 404 dedicada encara és pendent.

---

## Sortida HTML segura

Totes les dades que vinguin de base de dades i es mostrin en HTML han d’escapar-se amb:

```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```

Excepció: contingut HTML validat i controlat, com contingut sincronitzat i sanititzat.

---

## Criteri principal

Una nova pàgina no hauria d’obligar a duplicar capçalera, peu, connexió, permisos ni lògica comuna.

Les vistes mostren dades.

Els controladors preparen dades.

Els models llegeixen i escriuen dades.

Els serveis gestionen lògica complexa.
