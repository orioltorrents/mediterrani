# Memòria del projecte entre sessions

Última actualització: 2026-10-01

Memòria viva per reprendre el desenvolupament i conservar el context de les decisions. Les normes generals són a [AGENTS.md](AGENTS.md); els procediments de base de dades, a [database/README.md](database/README.md). L'estat documentat a continuació no equival a una verificació funcional de l'aplicació.

## 1. Estat actual

- **Documentació alineada el 2026-10-01:** `README.md`, `AGENTS.md`, `.opencode/skills/google-docs-sheets/SKILL.md` i `.opencode/skills/assets-projectes/SKILL.md` s'han ajustat per no presentar com a implementades taules o pipelines que no són a `database/schema.sql`.
- **Skills migrades el 2026-10-01:** les skills han passat de `docs/skills/*.md` a `.opencode/skills/<nom>/SKILL.md`, amb frontmatter `name` i `description` perquè OpenCode les pugui carregar. Verificat que cada `name` coincideix amb la carpeta i que no queden fitxers `.md` antics a `docs/skills/`.
- **Esquema ajustat el 2026-10-01:** `database/schema.sql` canvia `project_academic_years.status DEFAULT 'active'` a `DEFAULT 'actiu'` i afegeix `projects` al bloc `DROP TABLE IF EXISTS`. El canvi està fet al fitxer, però no s'ha aplicat ni verificat contra la base local.
- **Verificació pendent:** `php scripts/check-code-quality.php` passa el lint PHP, però no completa la coherència d'esquema perquè MySQL rebutja la connexió local. El mateix procés informa SQL/DDL existent a `app/Controllers/PublicController.php`, pendent de valorar si es refactoritza.

## 2. Decisions i el perquè

- **2026-10-01 — Mantenir una memòria editable a l'arrel, amb quatre apartats.** Acordat amb l'usuari per conservar el punt de represa, els motius de les decisions i els aprenentatges entre sessions.
- **2026-10-01 — Separar normes i context evolutiu.** `AGENTS.md` queda com a font de criteris estables; `MEMORY.md` només ha de conservar context que ajudi a reprendre la feina o evitar errors.

## 3. Aprenentatges i errors a evitar

- **Codi i documentació poden venir d'etapes diferents.** Hi ha serveis i skills heretats d'Entorns de Natura que parlen de taules absents de l'esquema actual. Abans de reprendre documents, Google, assets, webhooks o avaluació avançada, contrastar-ho amb `database/schema.sql` i adaptar el model a Mediterrani.
- **Model existent no vol dir flux complet.** `evidencies_alumnes` existeix, però la importació des de Google Sheets continua pendent de disseny i validació.

## 4. Pròxims passos

1. **Revisar serveis heretats o futurs:** contrastar `DocumentService`, `GoogleSyncService`, `DocumentImportService`, `ClassroomWebhookService`, `ClassroomStructureSnapshotProcessingService` i serveis d'avaluació amb l'esquema actual abans de reutilitzar-los o anunciar-los com a operatius.
2. **Concretar el primer import d'evidències:** definir el format del full, la identitat estable de les files, el tractament de reimportacions i les validacions d'alumne, edició i objectiu abans de dissenyar noves taules. És el primer cas d'ús previst a `AGENTS.md`; la implementació queda pendent de concretar amb l'usuari.
3. **Reprendre verificació tècnica amb MySQL actiu:** executar `php scripts/check-code-quality.php` quan la base local accepti connexions i decidir si es refactoritza el SQL detectat a `PublicController.php`.
