# Skill 04 — Autenticació, rols i seguretat

## Objectiu

Preparar el sistema d’accés, sessions, permisos i seguretat del projecte **Mediterrani**.

El projecte gestionarà dades d’alumnat i professorat, per tant la seguretat és una prioritat.

## Estat actual

### Implementat

- login bàsic amb email i contrasenya amb rate limiting (5 intents/IP, 10 intents/email en 15 minuts);
- sessió activa amb `user_id`, `email` i `roles`;
- regeneració d'identificador de sessió en iniciar sessió;
- idle timeout de sessió (2 hores) amb neteja automàtica;
- token CSRF al formulari de login;
- token CSRF a totes les accions POST (admin, sync-documents, impersonate, canvi contrasenya);
- CSRF per header `X-CSRF-Token` a `DocumentSyncController`;
- auditoria amb `LogService` per a accions admin, impersonació, login (exit/fail), logout, canvi de contrasenya (exit/fail), importació de documents i accessos a dashboards;
- comprovació de rols amb `AuthService::requireRole()`;
- comprovació contextual d'accés a edicions amb `ProjectAccessService`;
- logout via POST amb CSRF (el GET també funciona sense CSRF per compatibilitat);
- revalidació de sessió cada 5 minuts (usuari actiu i rols actuals);
- comprovació d'usuari actiu en iniciar sessió;
- fitxa pública de projecte amb bloc contextual de notes per alumnat autenticat.
- accés contextual a grups i alumnes per edició i classe;
- semàfor d'assoliment de l'alumne llegit de `student_indicador_assoliment` sense exposar dades d'altres alumnes.

### Encara previst

- login amb Google;
- refinament progressiu del context de visibilitat per professorat visitant;
- control més fi de seccions privades dins de la fitxa de projecte.

---

## Taules principals

```text
users
web_roles
user_web_roles
project_roles
project_team_member_roles
```

`web_roles` i `user_web_roles` controlen l'accés general a la web. `project_roles` descriu funcions dins d'equips o projectes i no substitueix els permisos web. `project_team_member_roles` assigna un o més rols de projecte a cada pertinença d'equip.

`project_team_members.project_role_id` es manté com a rol principal de compatibilitat. Les comprovacions contextuals, llistats i comptatges de rols de projecte han d'utilitzar `project_team_member_roles` quan es necessiti el model complet.

---

## Rols disponibles

```text
student
teacher
guest_teacher
coordinator
admin
```

## Model de visibilitat recomanat

Per al futur, convé distingir entre rol d'accés i context de visualització.

Proposta de criteri:

- visitant: accés públic sense sessió, només contingut general;
- student: veu tasques, bastides, ajudes i contingut d'aula autoritzat;
- teacher visitor: veu programacions i informació general del projecte, sense contingut sensible;
- teacher assigned: veu el contingut complet, incloent deadlines i materials interns;
- admin: veu tota la informació.

La implementació recomanada és una sola vista per projecte amb blocs condicionats per context, no pantalles duplicades per perfil.

`ProjectAccessService` aplica les regles mínimes d'accés a una edició concreta:

- `admin` i `coordinator` poden veure les edicions de projecte;
- `teacher` només pot veure edicions de l'any actual assignades a les seves classes, amb estat d'edició i assignació `pendent`, `actiu` o `realitzat`;
- `student` només pot veure edicions de l'any actual assignades a la seva classe, amb estat d'edició i assignació `actiu` o `realitzat`;
- visitants sense sessió no passen per l'accés contextual i només reben els blocs públics de les vistes.

Les dades de grups i alumnes han de conservar el context `project_academic_year_id` i, quan la navegació parteix d'una classe concreta, el paràmetre `classe`. El professorat només ha de veure les classes assignades; coordinació i administració poden veure el conjunt autoritzat.

---

## Jerarquia conceptual

```text
admin       → també pot actuar com a coordinator i teacher
coordinator → també pot actuar com a teacher
teacher     → professor
student     → alumne
```

A la base de dades és preferible assignar rols web explícits a `user_web_roles`.

Exemples:

```text
Oriol Torrents → admin + coordinator + teacher
Oriol Rovira   → coordinator + teacher
Àlex Martí     → teacher
Aiman          → student
Sílvia         → student
```

---

## Login

El login bàsic amb email i contrasenya ja està implementat.

Més endavant es preveu login amb Google.

Camps importants de `users`:

```text
email
google_id
password_hash
is_active
last_login_at
```

---

## Contrasenyes

No guardar mai contrasenyes en text pla.

Per crear hash:

```php
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
```

Per verificar:

```php
$isValid = password_verify($password, $user['password_hash']);
```

Els usuaris que només accedeixin amb Google poden tenir:

```text
password_hash = NULL
```

### Contrasenyes temporals d'alumnat

Quan s'importen alumnes des de CSV, el fitxer no ha de contenir cap contrasenya. El criteri actual és:

- generar una contrasenya aleatòria només per construir l'hash inicial;
- `users.must_change_password = 1` en crear l'usuari;
- crear un token d'activació d'un sol ús amb expiració de 48 hores;
- permetre que l'alumne defineixi la seva contrasenya a `/activar-compte`;
- guardar només el hash amb `password_hash()`, mai la contrasenya en text pla.

Flux actual de l'importador d'alumnes:

- si el CSV conté una columna `password` o `users.password`, la importació es rebutja;
- per a cada usuari nou, el sistema genera una contrasenya aleatòria i en desa només l'hash;
- el resum de la importació mostra un enllaç d'activació, no una contrasenya;
- si l'usuari ja existeix, una importació normal no li canvia la contrasenya.

Flux actual d'activació:

```text
L'admin importa el CSV sense contrasenyes
        ↓
Mediterrani crea l'usuari amb hash aleatori i `must_change_password = 1`
        ↓
Mediterrani crea un token hashat amb expiració de 48 hores
        ↓
L'admin comparteix l'enllaç d'activació de manera controlada
        ↓
L'alumne obre `/activar-compte` i defineix una contrasenya
        ↓
El token queda marcat com a utilitzat i no es pot reutilitzar
        ↓
L'alumne inicia sessió amb la seva nova contrasenya
```

Aquest flux evita transportar contrasenyes inicials dins del CSV. La taula `user_activation_tokens` només guarda el hash del token, la data d'expiració i la data d'ús. El token original només apareix a l'enllaç d'activació generat en el resum immediat de la importació.

Si es perd l'enllaç abans de l'activació, no s'ha de recuperar cap contrasenya ni token de la base de dades. Cal generar un nou enllaç mitjançant un flux administratiu de reemissió.

### Reset administratiu

El panell d'administració permet generar un nou enllaç de reset per a un alumne actiu des de la llista d'alumnes.

- l'acció valida server-side que l'identificador correspon a un alumne actiu;
- els tokens d'activació anteriors encara no utilitzats queden invalidats;
- el dashboard mostra l'enllaç només després de l'acció i el conserva temporalment a la sessió;
- l'alumne defineix una contrasenya nova a `/activar-compte`;
- el reset queda registrat a l'auditoria administrativa;
- l'administrador no veu ni estableix directament la contrasenya final.

---

## Login amb Google

El login amb Google encara no està implementat.

Quan s’implementi:

- no posar claus de Google al JavaScript;
- no publicar secrets;
- guardar `google_id` a `users`;
- continuar fent servir `user_web_roles` per decidir permisos web;
- no confiar només en el correu sense validar-lo correctament.

---

## Canvi de contrasenya obligatori

La ruta `/canviar-contrasenya` permet als usuaris canviar la contrasenya.
El camp `users.must_change_password` força el canvi en el primer inici de sessió.

---

## Sessions

Les zones privades han de requerir sessió:

```text
/alumne
/professor
/admin
```

La sessió ha de guardar com a mínim:

```text
user_id
email
roles
```

Cookies de sessió actuals:

```text
lifetime = 7200 (2 hores)
path = /
secure = true només sota HTTPS
httponly = true
samesite = Lax
```

El sistema regenera l'identificador de sessió en iniciar sessió. Hi ha un idle timeout de 2 hores: si no hi ha activitat, la sessió es destrueix automàticament i l'usuari es redirigeix al login en el proper accés.

`AuthService::check()` revalida la sessió cada 5 minuts contra la base de dades: si l'usuari ha estat desactivat, la sessió es tanca automàticament; si els rols han canviat, s'actualitzen a la sessió sense tancar-la. El canvi de rols es registra a auditoria com `auth=roles_updated`.

---

## CSRF

El token CSRF està implementat al formulari de login i als formularis POST sensibles del panell d'administració.

Cada formulari que modifica dades ha d'enviar `csrf_token` i el controlador l'ha de validar abans d'executar l'acció. Les accions admin sense token vàlid han de quedar bloquejades i registrades a auditoria.

---

## Autorització server-side de les accions administratives

El rol `admin` permet accedir al panell, però no converteix automàticament qualsevol ID rebut del navegador en una dada vàlida. El CSRF només valida l'origen de la petició; no valida el recurs ni les relacions que es volen modificar.

Per aquest motiu, cada servei administratiu ha de validar també:

- que l'usuari, projecte, classe, edició, equip o objectiu existeix;
- que els IDs relacionats pertanyen al context esperat;
- que els valors rebuts pertanyen al catàleg permès, com els rols web o els estats;
- que una operació no modifica dades fora de l'edició o projecte seleccionat;
- que una acció privilegiada deixa una entrada d'auditoria.

### Regles actuals del panell admin

- `/admin` exigeix rol `admin` abans de carregar el dashboard o processar accions POST.
- `AdminActionService` només executa accions conegudes i delega cada operació al servei corresponent.
- La impersonació només permet seleccionar un usuari actiu que tingui el rol `student`.
- Els canvis d'equip filtren les eliminacions per `project_academic_year_id`; canviar un equip no pot eliminar les pertinences de l'alumne en altres projectes o edicions.
- Un canvi d'equip no pot creuar una edició acadèmica diferent de la que origina el formulari.
- Les assignacions projecte-classe resolen l'edició a partir de l'any acadèmic de la classe, i no a partir de l'última edició disponible del projecte.
- Les actualitzacions d'usuaris validen l'existència dels rols web i de les classes rebudes.
- Un administrador no es pot eliminar el seu propi rol `admin` des del formulari d'edició d'usuaris.
- Els objectius i els indicadors només es poden modificar si l'objectiu i el projecte existeixen.
- La importació d'usuaris no crea rols web arbitraris: un rol desconegut fa fallar la fila d'importació.

Quan una operació treballa amb una relació contextual, la consulta d'escriptura ha d'incloure aquesta relació en el `WHERE` o validar-la abans de fer la modificació. No és suficient validar que l'ID sigui un enter ni confiar en un camp ocult del formulari.

Les proves d'autorització han de cobrir com a mínim:

- un alumne amb equips en dues edicions diferents;
- un canvi d'equip amb un `team_id` d'una altra edició;
- un `objective_id`, `project_id` o `class_id` inexistent;
- un CSV amb un rol web desconegut;
- un administrador que intenta eliminar-se el seu propi rol `admin`;
- una petició amb CSRF vàlid però amb IDs manipulats.

---

## Rate limiting al login

`AuthService::attemptLogin()` aplica rate limiting abans de verificar credencials:

- Taula `login_attempts` amb `ip_address`, `email`, `attempted_at` i `success`.
- Límit per IP: 5 intents fallits en 15 minuts.
- Límit per email: 10 intents fallits en 15 minuts.
- Els registres antics (>15 min) s'esborren automàticament a cada comprovació.
- Si el límit s'excedeix, `attemptLogin()` retorna `false` i `getLoginRateError()` retorna el missatge d'error específic.
- `AuthController::handleLogin()` mostra el missatge de rate limit si existeix, en lloc del genèric "Email o contrasenya incorrectes".
- Els intents exitosos també s'emmagatzemen (no afecten el comptatge) per tenir un registre complet.

---

## Webhook

L'endpoint `POST /api/classroom/webhook` es protegeix amb `Authorization: Bearer <token>`.
El token es configura a `CLASSROOM_WEBHOOK_TOKEN` al `.env`.
Sense un token vàlid, el webhook retorna 401.

---

## Control d'accés

Regles mínimes:

```text
/alumne   → requereix student
/professor → requereix teacher
/admin    → requereix admin
```

Com que admin i coordinator tenen rols múltiples explícits, el codi pot comprovar rols simples.

Exemple:

```text
Oriol Torrents té admin + coordinator + teacher
```

Per tant pot accedir a:

```text
/admin
/professor
```

---

## Usuaris actius

El camp:

```text
is_active
```

ha de controlar si un usuari pot accedir.

Si `is_active = 0`, l’usuari no pot iniciar sessió. Aquesta comprovació es fa durant el login; la sessió existent no es revalida automàticament en cada petició.

---

## Dades sensibles

No exposar públicament:

- notes;
- observacions d’aula;
- dades personals d’alumnes;
- dades internes de professorat;
- logs;
- configuració;
- informació de Google Sources privada.
- programacions internes si no correspon al context;
- deadlines i materials docents si el rol no ho permet.

---

## Sortida HTML segura

Fer servir:

```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```

per mostrar dades en HTML.

---

## SQL segur

Fer servir sempre consultes preparades amb PDO.

No fer:

```php
$sql = "SELECT * FROM users WHERE email = '$email'";
```

Fer:

```php
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
```

---

## Fitxers sensibles

No publicar:

```text
.env
credencials de Google
fitxers de configuració privada
logs interns
```

Fitxer temporal eliminat (juliol 2026):

```text
public/test-db.php
```

---

## Auditoria

`LogService` escriu esdeveniments a `storage/logs/app.log` amb timestamp ISO i format clau=valor.

Esdeveniments auditats actualment:

```text
auth=login                   email=X roles=Y,Z
auth=login_failed            email=X reason=empty_fields|user_not_found_or_inactive|invalid_password|no_roles
auth=logout                  user_id=X
auth=change_password         user_id=X
auth=change_password_failed  user_id=X reason=empty_fields|password_mismatch|too_short|same_password|user_not_found|incorrect_current
access=dashboard_student     user_id=X
access=dashboard_teacher     user_id=X
access=dashboard_admin       user_id=X
auth=logout_csrf_failed      user_id=X
auth=session_invalidated     user_id=X reason=inactive_or_deleted
auth=roles_updated           user_id=X roles=Y,Z
admin_action=*               actor_id=X ...
admin_action=csrf_failed     actor_id=X
admin_action=impersonate_student  actor_id=X target_student_id=Y
admin_action=stop_impersonation   actor_id=X target_student_id=Y
```
`AdminActionService::auditAdminAction()` afegeix context adicional (estat, quantitats, etc.) segons l'acció.

---

## Errors

En desenvolupament local es poden veure errors per depurar.

En producció:

- no mostrar errors interns;
- registrar errors a logs;
- mostrar missatge genèric a l’usuari.

---

## Logs

Els logs han d’anar a:

```text
storage/logs/
```

No han d’estar dins `public/`.

---

## Canvis destructius

No fer sense confirmació explícita:

```text
DROP DATABASE
DROP TABLE
TRUNCATE TABLE
DELETE sense WHERE
eliminar usuaris
eliminar rols
eliminar dades d’avaluació
```

---

## Mancances conegudes

Aquests punts formen part del backlog de seguretat. No s'han de presentar com a resolts:

- la política d'analítica, retenció, anonimització i exclusió de rutes sensibles encara no està definida.

---

## Criteri principal

La seguretat s’ha de decidir al servidor, no al navegador.

JavaScript pot millorar l’experiència d’usuari, però no pot ser responsable de protegir dades privades.

El servidor PHP ha de comprovar sempre sessió i permisos.
