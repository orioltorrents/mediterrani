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

Quan s'importen alumnes des de CSV, no s'ha d'utilitzar una contrasenya inicial comuna per a tot el grup. El criteri recomanat és:

- una contrasenya temporal única per alumne;
- `users.must_change_password = 1` en crear l'usuari;
- canvi obligatori a `/canviar-contrasenya` en el primer inici de sessió;
- guardar només el hash amb `password_hash()`, mai la contrasenya en text pla.

Flux actual de l'importador d'alumnes:

- si el CSV informa la columna `password`, aquesta contrasenya temporal s'utilitza per crear el hash inicial;
- si el CSV deixa `password` buit per a un usuari nou, el sistema genera una contrasenya temporal única;
- les contrasenyes generades només es mostren al resum immediat de la importació perquè es puguin comunicar de manera controlada;
- si l'usuari ja existeix, una importació normal no li canvia la contrasenya.

Flux recomanat amb Google Apps Script:

```text
GAS genera una contrasenya temporal única per alumne
        ↓
GAS crea el CSV amb email, name, surname, password i la resta de camps necessaris
        ↓
L'admin importa el CSV a Mediterrani
        ↓
Mediterrani desa només password_hash i must_change_password = 1
        ↓
GAS envia a cada alumne el seu email, contrasenya temporal i URL de login
        ↓
L'alumne inicia sessió i canvia obligatòriament la contrasenya
        ↓
Mediterrani actualitza password_hash, must_change_password = 0 i password_changed_at
```

Aquest flux permet que GAS conegui les contrasenyes temporals només en el moment d'enviar-les, mentre que l'aplicació conserva únicament el hash. Després del canvi obligatori, la contrasenya real de l'alumne no queda visible ni recuperable en text pla.

Si es perd una contrasenya temporal abans del primer accés, no s'ha de recuperar de la base de dades. Cal generar-ne una de nova mitjançant un flux de reset o reimportació controlada amb una nova columna `password`.

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
