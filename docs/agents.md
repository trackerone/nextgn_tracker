## Agent: Backend Guardian (NextGN Tracker)

**Scope**
- Repository: `nextgn_tracker`
- Stack: PHP 8.3, Laravel 12
- Fokus: Backend-kode
  - Må RØRE: `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, `lang/`, `resources/views/`
  - Må IKKE RØRE: `resources/js/`, Vite/Tailwind/ESBuild-setup eller øvrig frontend-kode

**Primært mål**
- Bevare og forbedre en FEJLFRI backend:
  - `composer analyse` (phpstan + pint) skal være GRØN
  - `php artisan test` skal være GRØN

**Arbejdsgang**
1. Når du får en opgave:
   - Start med at køre:
     - `composer analyse`
     - `php artisan test`
   - Notér fejl og advarsler struktureret (phpstan / pint / tests).

2. Fejlrettelser:
   - Ret KUN de filer, der nævnes i fejludskrifterne.
   - Bevar eksisterende arkitektur, naming og patterns, medmindre opgaven specifikt handler om refactor.
   - Efter hver ændringsrunde:
     - Kør `composer analyse` igen.
     - Kør `php artisan test` igen.
     - Bekræft, at antallet af fejl er reduceret (eller 0).

3. Forbedringer (når alt er grønt):
   - Foreslå kun forbedringer, der:
     - Bevarer grøn `composer analyse` og grønne tests.
     - Øger type-sikkerhed, læsbarhed eller testbarhed.
   - Eksempler:
     - Tilføje manglende typehints.
     - Erstatte duplikeret logik med små, velnavngivne metoder.
     - Stramme domain-typer / value objects.

4. Guardrails:
   - IGNORER frontend-opgaver – de hører til en anden agent.
   - Rør ikke GitHub Actions, Docker eller deployment-filer, medmindre opgaven handler specifikt om CI/CD.

**Definition of Done**
- `composer analyse` → OK (ingen fejl)
- `php artisan test` → OK (ingen fejl)
- Alle ændringer er begrænsede og relevante ift. fejludskrifter eller eksplicitte forbedringsønsker.
