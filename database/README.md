# Base de dades de Mediterrani

Aquest directori conté l'esquema SQL de la base de dades nova de **Mediterrani** i els ajustos incrementals necessaris per actualitzar instal·lacions existents.

La base de dades es va iniciar de zero a partir d'una còpia del codi d'Entorns de Natura. Per aquest motiu, alguns serveis i documents generals encara poden mencionar taules de l'aplicació anterior que no formen part de l'esquema actual de Mediterrani.

## Fonts de veritat

- `database/schema.sql` és l'autoritat executable per crear una base de dades neta.
- `database/migrations/` conté canvis incrementals per a bases ja existents.
- El nom i les credencials de la base de dades es llegeixen des de `.env`.
- Si una taula només apareix a la documentació però no a `schema.sql` ni en una migració aplicable, no s'ha de considerar implementada.

## Esquema actual

`schema.sql` crea 27 taules:

### Usuaris i accés

- `users`
- `user_activation_tokens`
- `web_roles`
- `user_web_roles`
- `login_attempts`

### Idiomes, cursos i classes

- `languages`
- `academic_years`
- `classes`
- `class_members`
- `class_teachers`
- `class_member_history`

### Projectes i equips

- `projects`
- `project_roles`
- `project_academic_years`
- `project_translations`
- `project_class_assignments`
- `project_teams`
- `project_team_members`
- `project_team_member_roles`
- `project_sections`

### Objectius i assoliment

- `objectius_aprenentatge`
- `project_academic_year_objectius`
- `indicadors_assoliment`
- `student_indicador_assoliment`

### Classroom i analítica

- `classrooms`
- `classroom_members`
- `site_visits`

## Relacions principals

La unitat funcional dels projectes és `project_academic_years`: relaciona un projecte base amb un curs acadèmic concret.

```text
projects 1 ---- N project_academic_years N ---- 1 academic_years
```

Les classes pertanyen a un curs acadèmic. Alumnes i professorat es relacionen amb les classes mitjançant taules pont.

```text
academic_years 1 ---- N classes
classes N ---- N users, mitjançant class_members
classes N ---- N users, mitjançant class_teachers
```

Les edicions de projecte s'assignen a classes i poden contenir equips.

```text
project_academic_years N ---- N classes, mitjançant project_class_assignments
project_academic_years 1 ---- N project_teams
project_teams 1 ---- N project_team_members N ---- 1 users
```

Cada membre d'un equip pot tenir més d'un rol de projecte.

```text
project_team_members N ---- N project_roles
                       mitjançant project_team_member_roles
```

Els objectius es poden assignar a una edició concreta i avaluar individualment per alumne.

```text
project_academic_years N ---- N objectius_aprenentatge
                       mitjançant project_academic_year_objectius

users 1 ---- N student_indicador_assoliment
```

Els Classroom estan vinculats al curs, a una edició de projecte i als usuaris que en són membres.

```text
academic_years 1 ---- N classrooms
project_academic_years 1 ---- N classrooms
classrooms N ---- N users, mitjançant classroom_members
```

## Reconstrucció d'una base neta

> **Atenció:** `schema.sql` executa `DROP TABLE IF EXISTS`. Importar-lo sobre una base existent elimina les taules afectades i les seves dades.

Abans de reconstruir una base:

1. Comprova que `DB_NAME` a `.env` apunta a la base correcta.
2. Fes una còpia de seguretat si hi ha dades que cal conservar.
3. Importa `database/schema.sql` amb phpMyAdmin o amb el client de MySQL/MariaDB.
4. Carrega les dades inicials només si hi ha un seed específic i compatible.
5. Verifica l'accés a l'aplicació amb un usuari d'administració.

Exemple amb el client de MySQL des de PowerShell, substituint els valors pels de l'entorn local:

```powershell
Get-Content database/schema.sql | mysql -u USUARI -p NOM_BASE_DADES
```

En una reconstrucció neta no s'han d'executar després totes les migracions històriques: els seus resultats ja estan incorporats a `schema.sql`.

## Actualització d'una base existent

No s'ha d'importar `schema.sql` per actualitzar una base que contingui dades.

El procediment és:

1. Fer una còpia de seguretat.
2. Comprovar l'estructura i les migracions ja aplicades.
3. Escollir només la migració necessària per passar de l'estat actual al següent.
4. Revisar manualment qualsevol consulta d'actualització abans d'executar-la.
5. Executar la migració en un entorn de prova quan sigui possible.
6. Verificar claus foranes, índexs i dades després del canvi.

Migracions disponibles:

| Migració | Finalitat | Observacions |
|---|---|---|
| `20260909_rename_classroom_columns.sql` | Renomena columnes antigues de Classroom. | Només per esquemes que encara utilitzin els noms `google_classroom_*`. |
| `20260909_create_classrooms.sql` | Normalitza tipus i índexs de `classrooms`. | Requereix que la taula i les columnes noves ja existeixin. |
| `20260909_create_classroom_members.sql` | Crea la relació entre Classroom i usuaris. | No crea usuaris automàticament. |
| `20260909_project_teams_class_id.sql` | Afegeix la relació real entre equips i classes. | Inclou una actualització basada en `class_group`; cal revisar els valors no resolts. |
| `20260918_create_user_activation_tokens.sql` | Crea els tokens d'activació d'usuaris. | La taula queda vinculada a `users` amb eliminació en cascada. |

Les migracions no disposen actualment d'una taula de control automàtic. Abans d'aplicar-ne una, cal comprovar manualment si el canvi ja existeix per evitar columnes, claus o índexs duplicats.

## Taules heretades o futures

La documentació procedent d'Entorns de Natura pot mencionar taules relacionades amb:

- documents i fragments;
- assets de projectes;
- avaluacions avançades;
- webhooks de Classroom;
- sincronització amb Google Docs i Google Sheets;
- configuració i pàgines del lloc.

Aquestes taules no formen part de l'esquema executable actual mentre no apareguin a `schema.sql` o en una migració nova. Abans de recuperar una funcionalitat antiga, cal revisar el model i adaptar-lo a Mediterrani; no s'ha de copiar automàticament l'esquema anterior.

## Criteris per a canvis futurs

- Afegir els canvis nous mitjançant una migració incremental.
- Incorporar també el resultat final a `schema.sql` perquè una instal·lació neta quedi completa.
- Fer servir InnoDB, `utf8mb4` i `utf8mb4_unicode_ci`.
- Definir claus foranes i índexs explícits.
- Utilitzar `project_academic_years` quan una dada depengui del curs concret.
- No posar SQL de manteniment d'esquema dins controladors PHP.
- No executar operacions destructives sense còpia de seguretat i confirmació explícita.
- Actualitzar aquest document i `docs/skills/02-base-de-dades.md` quan canviï l'esquema.
