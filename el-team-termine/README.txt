Lizenz: GNU GENERAL PUBLIC LICENSE. Siehe LICENSE im Hauptordner.

El-Team Termine 1.2.1

Termine wie Treffen, El-Café oder Ausfahrten mit Datum, Ort und Anmeldung.

Eingabe
- Menüpunkt "Termine" im Adminbereich, Kasten "Termin" beim Bearbeiten:
  Beginn (Datum, Uhrzeit), Ende (optional), ganztägig, Ort, Adresse,
  Link zu Anmeldung/Infos, Status (findet statt, abgesagt, verschoben)
- Terminarten (z. B. Treffen, El-Café, Ausfahrt) unter Termine → Terminarten
- Titel, Text, Textauszug und Beitragsbild wie bei Beiträgen
- Die Terminliste im Admin ist nach Termin sortiert, neueste zuerst
- "Werkstatt geöffnet" je Termin: automatisch (nach Terminart), ja oder nein.
  Bei jeder Terminart lässt sich einstellen, dass ihre Termine Werkstatt-Öffnungen sind.

Ausgabe
- Übersicht unter /termine/: kommende Termine nach Datum,
  /termine/?zeitraum=vergangen für vergangene; je Terminart unter /terminart/<slug>/
- Auf jeder Terminseite ein Infokasten (Wann, Wo, Karte, Anmeldung) und
  "In den eigenen Kalender eintragen (.ics)"
- Kalender zum Abonnieren: /?el_termin_ics=alle (kommende Termine und die letzten 90 Tage)
- Kalender nur mit Werkstatt-Öffnungen: /?el_termin_ics=oeffnungen
  (ohne abgesagte und verschobene Termine; das Plugin "SpaceAPI Status" nennt ihn
  in der SpaceAPI unter feeds.calendar und zeigt die nächsten Öffnungen an)
- Strukturierte Daten (schema.org/Event) für Suchmaschinen
- Karte: nur ein Link zu OpenStreetMap, nichts eingebettet, kein Abruf fremder Server

Shortcode
[termine]                                  die nächsten 5 Termine
[termine anzahl="3"]
[termine zeitraum="vergangen" anzahl="10"] vergangene Termine
[termine art="el-cafe"]                    nur eine Terminart (Slug)

Theme
- Für Entwickler: el_team_termine_kommend( $anzahl ), el_team_termine_oeffnungen( $anzahl ),
  el_team_termin_ist_oeffnung( $post ), el_team_termine_query( $args ),
  el_team_termine_liste_html( $termine ), el_team_termin_daten( $post ),
  el_team_termin_zeit_text( $post )
- Das el-team-wp-theme zeigt ab 1.10 "Nächste Termine" auf der Startseite
  (Einstellung unter Design → Startseite)

Hinweise
- Nach dem Aktivieren sind die Adressen /termine/ sofort da. Falls nicht:
  Einstellungen → Permalinks einmal speichern.
- Beim Deaktivieren bleiben die Termine gespeichert.
- Zeiten gelten in der Zeitzone der Website (Einstellungen → Allgemein).
- Wiederkehrende Termine (z. B. jeden ersten Freitag) gibt es noch nicht;
  jeder Termin wird einzeln angelegt.

Updates
- Ab dieser Version kommen Updates aus den GitHub-Releases des Repos WP-Plugins
  (Dashboard → Aktualisierungen). Manuell prüfen: in der Plugin-Liste "Nach Updates suchen"
  oder Dashboard → Aktualisierungen → "Erneut prüfen". Release anlegen: RELEASING.md im Repo.

1.2.1
- RSS-Feed /termine/feed/: neu angekündigte Termine zuerst (statt nur kommender Termine nach
  Termindatum), Termindatum im Titel, "Wann · Wo" vor der Kurzbeschreibung.
