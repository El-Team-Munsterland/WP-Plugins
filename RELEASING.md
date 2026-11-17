# Plugin-Releases und Updates

Die Plugins in diesem Repo werden nicht über den WordPress-Marktplatz verteilt, sondern über
GitHub-Releases. WordPress zeigt neue Versionen wie gewohnt unter **Dashboard → Aktualisierungen**
und in der Plugin-Liste an.

## Ein Release anlegen

1. Im Plugin die Version erhöhen, z. B. in `el-team-termine/el-team-termine.php`:
   `Version: 1.2.0` (und, falls vorhanden, die Versions-Konstante im selben File).
2. Committen und pushen.
3. Tag `<plugin-ordner>/v<version>` anlegen und pushen:

   ```
   git tag -a el-team-termine/v1.2.0 -m "El-Team Termine 1.2.0"
   git push origin el-team-termine/v1.2.0
   ```

4. Die GitHub Action **Plugin-Release** (`.github/workflows/release.yml`) prüft das Tag, baut
   `el-team-termine.zip` **nur mit diesem Plugin-Ordner** und legt das Release an. Die
   Release-Notizen sind die Commits seit dem letzten Release dieses Plugins, die diesen Ordner
   betreffen. Ergebnis unter *Actions* und *Releases* auf GitHub.

Die Action bricht ab (und es entsteht kein Release), wenn
- der Ordner zum Tag fehlt oder das Tag kein `…/v1.2.3` ist,
- die Version im Plugin-Kopf nicht zum Tag passt,
- die Kopie der Update-Prüfung im Plugin von `lib/github-updater.php` abweicht.

Ein fehlgeschlagenes Tag wieder entfernen: `git push origin :refs/tags/el-team-termine/v1.2.0`
und `git tag -d el-team-termine/v1.2.0`.

## Mehrere Plugins in einem Repo

Git kennt keine „Produkte“: ein Tag zeigt auf einen Stand des ganzen Repos. Dass ein Release
nur ein Plugin enthält, regeln das Präfix im Tag und `git archive "<tag>:<ordner>"` im Workflow.
Nützlich:

- Releases eines Plugins: `git tag -l 'el-team-termine/*' --sort=-v:refname`
- Änderungen eines Plugins seit dem letzten Release:
  `git log el-team-termine/v1.1.0..HEAD -- el-team-termine/`

GitHubs „Latest release“ gilt für das ganze Repo; die Update-Prüfung der Plugins sucht deshalb
selbst das höchste Release mit ihrem eigenen Präfix.

## Update-Prüfung in WordPress

- Jedes Plugin enthält `includes/github-updater.php` (Kopie von `lib/github-updater.php`) und im
  Kopf `Update URI: https://github.com/El-Team-Munsterland/WP-Plugins/<ordner>`. Dadurch fragt
  WordPress für diese Plugins nicht den Marktplatz.
- GitHub wird höchstens alle 12 Stunden gefragt (nach einem Fehler nach 1 Stunde erneut).
- **Manuell prüfen:** in der Plugin-Liste „Nach Updates suchen“ unter dem Plugin, oder
  **Dashboard → Aktualisierungen → Erneut prüfen**. Beides fragt GitHub sofort.
- „Details ansehen“ zeigt die Release-Notizen.
- Optional in `wp-config.php` ein GitHub-Token für angemeldete Abfragen (ohne Anmeldung erlaubt
  GitHub 60 Abfragen pro Stunde je Server-IP, das reicht normalerweise):
  `define( 'EL_TEAM_GITHUB_TOKEN', '…' );` – ein Fine-grained Token ohne Rechte genügt für
  öffentliche Repos.

Nach einer Änderung an `lib/github-updater.php`: `bin/sync-updater.sh` ausführen und die
Kopien mit einchecken. Bei größeren Änderungen den Klassennamen hochzählen
(`El_Team_GitHub_Updater_2`), damit Plugins mit alter und neuer Kopie nebeneinander laufen.

## Wichtig

- Ein Plugin entweder über diese Updates **oder** per `git pull`/Symlink aktualisieren, nicht
  beides: Ein WordPress-Update ersetzt den Plugin-Ordner.
- Der Ordnername im ZIP ist immer der Plugin-Ordner (`el-team-termine/`), damit WordPress das
  Plugin wiedererkennt und es aktiv bleibt.
- Die erste Version mit Update-Prüfung (Termine 1.2.0, SpaceAPI Status 2.1.0) muss einmal
  von Hand installiert werden; danach kommen Updates über WordPress.
