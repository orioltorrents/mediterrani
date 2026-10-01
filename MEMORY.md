# Memòria del projecte entre sessions

Última actualització: 2026-10-01

Memòria viva per reprendre el desenvolupament i conservar el context de les decisions. Les normes generals són a [AGENTS.md](AGENTS.md); els procediments de base de dades, a [database/README.md](database/README.md). L'estat documentat a continuació no equival a una verificació funcional de l'aplicació.

## 1. Estat actual

- **Documentació alineada el 2026-10-01:** `README.md`, `AGENTS.md`, `.opencode/skills/google-docs-sheets/SKILL.md` i `.opencode/skills/assets-projectes/SKILL.md` s'han ajustat per no presentar com a implementades taules o pipelines que no són a `database/schema.sql`.
- **Skills migrades el 2026-10-01:** les skills han passat de `docs/skills/*.md` a `.opencode/skills/<nom>/SKILL.md`, amb frontmatter `name` i `description` perquè OpenCode les pugui carregar. Verificat que cada `name` coincideix amb la carpeta i que no queden fitxers `.md` antics a `docs/skills/`.
- **Esquema ajustat el 2026-10-01:** `database/schema.sql` canvia `project_academic_years.status DEFAULT 'active'` a `DEFAULT 'actiu'` i afegeix `projects` al bloc `DROP TABLE IF EXISTS`. El canvi està fet al fitxer, però no s'ha aplicat ni verificat contra la base local.
- **Verificació parcial:** `php scripts/check-code-quality.php` passa el lint PHP i la coherència d'esquema; continua informant SQL/DDL existent a `app/Controllers/PublicController.php`, pendent de valorar si es refactoritza.
- **Objectius, criteris i indicadors actualitzats:** el backend i el dashboard treballen amb `objectius_aprenentatge -> criteris_assoliment -> indicadors_assoliment`, incloent les descripcions completa/simplificada dels objectius i el menú directe. Els tres objectius OA1-OA3 tenen `project_id = 2` (Mediterrani) i estan assignats a `project_academic_years.id = 4` (Mediterrani 2026-2027). Les assignacions incorrectes a Rates (`id = 3`) s'han retirat.
- **Criteris agrupats per objectiu:** la secció `criteris` del dashboard mostra un bloc col·lapsable per cada objectiu, també per als objectius sense criteris, amb formularis per crear i editar les dues descripcions.
- **Selector de descripció dels objectius:** cada bloc de criteris permet veure les redaccions simplificada, completa o ambdues; el comportament es reinicialitza també quan la secció es carrega dinàmicament.
- **Presentació final dels blocs d’objectius:** els blocs de criteris mostren OA i recompte, dos botons per alternar entre descripció completa i simplificada, els criteris directament a continuació i el formulari per afegir criteris al final.
- **Botons de descripció independents:** `Completa` i `Simplificada` es poden activar individualment o alhora; no es permet deixar-les totes dues desactivades i l’estat actiu té un estil visual diferenciat.
- **Ordre visual del bloc de criteris:** després de les descripcions es mostra el recompte, el títol `Criteris d'avaluació de l'objectiu d'aprenentatge` i la llista de criteris.
- **Codi de criteri:** `criteris_assoliment.codi` és opcional, queda buit per als criteris existents i es pot editar al dashboard abans de les dues descripcions. La base local i `database/schema.sql` ja estan actualitzats; la migració és `database/migrations/20261001_add_criteri_code.sql`.
- **Secció d’objectius reorganitzada:** el menú mostra `Objectius d'aprenentatge`; el dashboard agrupa els OA per projecte i edició en blocs col·lapsables, permet editar-los i incorpora una alta que filtra les edicions segons el projecte i crea també l’assignació a `project_academic_year_objectius`.
- **Slug de Mediterràniament actualitzat:** el projecte amb `id = 2` ara té `slug = mediterraniament` en lloc de `mediterrani`. Les relacions internes continuen basades en l’ID del projecte.
- **Indicadors en taula visual:** la secció d’indicadors mostra una taula per OA amb una fila per criteri, el seu codi i quatre cel·les de color amb descriptors. Els botons `Completa` i `Simplificada` permeten mostrar una, l’altra o totes dues; l’edició queda en un formulari col·lapsable per fila.
- **Codis dels nivells d’assoliment:** les capçaleres de la taula d’indicadors són `AE`, `AN`, `AS` i `NA`, en aquest ordre, corresponents respectivament a verd fosc, verd clar, groc i vermell. El nom complet queda disponible al tooltip i a l’editor.
- **Mapatge definitiu dels colors dels indicadors:** la base local utilitza ara `blau` (AE), `verd` (AN), `taronja` (AS) i `vermell` (NA), amb 10 registres per nivell. Els textos dels descriptors s'han conservat i el dashboard i la vista pública utilitzen aquests codis.
- **Descripció simplificada dels OA:** la base local tenia `descripcio_simplificada` com `VARCHAR(255)`, fet que podia fer fallar l’edició de textos llargs mentre el servei ocultava l’error. Ara és `TEXT`, coherent amb `schema.sql`; la migració és `20261001_fix_objective_simplified_description_length.sql`.
- **Descripcions dels OA als criteris:** els botons `Completa` i `Simplificada` permeten mostrar-ne una, totes dues o cap, igual que els selectors de descriptors dels indicadors.
- **CA agrupats per projecte:** la secció de criteris ara agrupa els objectius i criteris segons `objectius_aprenentatge.project_id`; cada projecte té un bloc independent amb recompte OA/CA i botó `Mostrar/Amagar`.
- **Indicadors agrupats i col·lapsables per OA:** cada objectiu té un bloc `Mostrar/Amagar`, les dues descripcions de l’OA tenen botons independents i els descriptors complets/simplificats dels indicadors tenen un selector separat que permet mostrar-ne un, l’altre o cap.
- **Selectors d’indicadors corregits:** la lògica dels botons de descriptors ara està separada explícitament de la lògica de descripcions de l’OA, i cada text porta l’etiqueta `Complet` o `Simplificat` quan es mostren tots dos.
- **Delegació dels botons d’indicadors:** els selectors de descriptors ara utilitzen un manejador delegat al document, de manera que continuen funcionant quan la secció d’indicadors es carrega dinàmicament.

## 2. Decisions i el perquè

- **2026-10-01 — Mantenir una memòria editable a l'arrel, amb quatre apartats.** Acordat amb l'usuari per conservar el punt de represa, els motius de les decisions i els aprenentatges entre sessions.
- **2026-10-01 — Separar normes i context evolutiu.** `AGENTS.md` queda com a font de criteris estables; `MEMORY.md` només ha de conservar context que ajudi a reprendre la feina o evitar errors.
- **2026-10-01 — Modelar els indicadors per criteri d’assoliment.** Els indicadors ja no depenen directament de l’objectiu: `criteris_assoliment` és la relació intermèdia i cada indicador guarda descriptor complet i simplificat. El dashboard i la vista pública segueixen aquesta jerarquia.

## 3. Aprenentatges i errors a evitar

- **Codi i documentació poden venir d'etapes diferents.** Hi ha serveis i skills heretats d'Entorns de Natura que parlen de taules absents de l'esquema actual. Abans de reprendre documents, Google, assets, webhooks o avaluació avançada, contrastar-ho amb `database/schema.sql` i adaptar el model a Mediterrani.
- **Model existent no vol dir flux complet.** `evidencies_alumnes` existeix, però la importació des de Google Sheets continua pendent de disseny i validació.

## 4. Pròxims passos

1. **Revisar serveis heretats o futurs:** contrastar `DocumentService`, `GoogleSyncService`, `DocumentImportService`, `ClassroomWebhookService`, `ClassroomStructureSnapshotProcessingService` i serveis d'avaluació amb l'esquema actual abans de reutilitzar-los o anunciar-los com a operatius.
2. **Concretar el primer import d'evidències:** definir el format del full, la identitat estable de les files, el tractament de reimportacions i les validacions d'alumne, edició i objectiu abans de dissenyar noves taules. És el primer cas d'ús previst a `AGENTS.md`; la implementació queda pendent de concretar amb l'usuari.
3. **Reprendre verificació tècnica amb MySQL actiu:** executar `php scripts/check-code-quality.php` quan la base local accepti connexions i decidir si es refactoritza el SQL detectat a `PublicController.php`.
4. **Validar l’actualització d’objectius:** provar al dashboard la creació i edició de criteris i indicadors i confirmar que la pàgina pública mostra els descriptors de la nova estructura. La sintaxi i la coherència de l’esquema ja s’han verificat; queda la comprovació visual amb sessió d’administració.
