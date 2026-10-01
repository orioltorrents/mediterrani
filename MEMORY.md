# Memòria del projecte entre sessions

Última actualització: 2026-10-01

Memòria viva per reprendre el desenvolupament. Les normes estables són a [AGENTS.md](AGENTS.md), la introducció a [README.md](README.md), els procediments de base de dades a [database/README.md](database/README.md) i els procediments específics a `.opencode/skills/`.

## 1. Estat actual

- L'esquema executable té 32 taules i `site_pages` forma part del model actual per al contingut públic global.
- `database/schema.sql` és la font executable per a una base neta; les migracions incrementals són a `database/migrations/`.
- El model educatiu actual és `objectius_aprenentatge -> criteris_assoliment -> indicadors_assoliment`.
- Els indicadors tenen `descriptor_complet`, `descriptor_simplificat` i els colors `blau`, `verd`, `taronja` i `vermell`.
- El dashboard d'administració permet gestionar objectius, criteris, codis de criteri i indicadors.
- La vista de l’alumnat mostra el redactat simplificat de l'OA i dels CA, i els descriptors simplificats en targetes de color. Cada OA és col·lapsable i el nivell actual només es ressalta visualment.
- Encara falta definir l'avaluació detallada per CA: el model proposat és una taula `student_criteri_indicador_assoliment` amb alumne, edició, CA, indicador seleccionat i docent avaluador. L'estat de l'OA es calcularia internament a partir dels CA, sense mostrar notes ni codis a l'alumnat.
- També està pendent una taula `student_objective_reflections` per guardar, per alumne, edició i OA, què ha fet bé, què pot millorar i el proper pas. La reflexió seria privada i editable per l'alumne.
- La integració real amb Google Docs/Sheets, la importació d'evidències i les rúbriques completes continuen pendents.
- `php scripts/check-schema-coherence.php`, el lint PHP i `git diff --check` passen. `php scripts/check-code-quality.php` encara detecta SQL a `PublicController.php`.
- El model d'evidències s'ha de pensar amb cura abans d'implementar-lo: `evidencies_alumnes` mantindria l'OA com a context principal i una futura taula d'enllaç permetria relacionar una evidència amb un o més CA, sense duplicar-la. El full de càlcul seria font d'importació i la base de dades, font final.

## 2. Decisions i el perquè

- Els indicadors depenen dels criteris d'assoliment, no directament dels objectius; això permet diversos indicadors per criteri.
- Les dades contextuals d'un curs o projecte utilitzen `project_academic_years`.
- `site_pages` es manté com a taula activa perquè el codi i la base local la fan servir per al contingut públic i la sincronització opcional amb Google Docs.
- Els alumnes no veuen els codis `AE`, `AN`, `AS` ni `NA`; veuen targetes de color amb el descriptor simplificat.
- La gradació formativa proposada per a ús intern és `Pendent d'evidències`, `En procés`, `Assolit` i `Consolidat`. Inicialment, un OA es consideraria assolit quan tots els seus CA tinguessin com a mínim el nivell satisfactori; aquesta regla encara s'ha de confirmar.

## 3. Aprenentatges i errors a evitar

- Abans de donar per implementada una funcionalitat, contrastar el codi i `database/schema.sql`; els serveis heretats poden mencionar models que no existeixen.
- No executar migracions sobre una base existent sense identificar l'estat de partida i fer una còpia de seguretat.
- Google Docs/Sheets pot ser una font d'importació o sincronització, però les dades publicades i avaluables han de passar per la base de dades i els permisos de l'aplicació.

## 4. Pròxims passos

1. Fer una prova visual amb almenys un alumne assignat i un alumne sense accés, incloent OA, CA i descriptors.
2. Decidir si es refactoritza el SQL de `PublicController.php` cap a un servei.
3. Definir i implementar el primer format d'importació d'evidències des de Google Sheets.
4. Decidir i modelar l'avaluació per CA i les reflexions formatives abans d'afegir formularis per al professorat o l'alumnat.
5. Dissenyar l'avaluació massiva per `project_teams`: el professorat podrà aplicar un indicador d'un CA a tots els membres de l'equip o només a una selecció. L'aplicació massiva és una ajuda d'eficiència, però cada valoració s'ha de guardar individualment per alumne i es podrà revisar després.
6. Dissenyar una taula de fases del projecte, probablement contextualitzada per edició, abans d'afegir fases, tasques o terminis a les vistes.
7. Valorar la implementació del quadern de bitàcola directament a la web. Cal comparar aquest flux amb mantenir una plantilla externa, extreure dades amb Google Apps Script o treballar amb un full sincronitzat.
8. Definir i implementar la sincronització entre fulls de càlcul i la web: identificador estable de fila, validacions, reimportacions, errors, permisos i font final de dades.
9. Ampliar les proves automatitzades de permisos, visibilitat i estructura educativa.
