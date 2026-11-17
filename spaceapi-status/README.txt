Lizenz: GNU GENERAL PUBLIC LICENSE. Siehe LICENSE.

SpaceAPI Status 2.1.0

Neu in 2.0: eigene öffentliche SpaceAPI
- Unter /spaceapi.json (ohne schöne Permalinks: /?spaceapi_json=1) liefert die Website eine
  eigene SpaceAPI nach Version 15 aus (api_compatibility ["15"]), mit CORS (*) und
  Content-Type application/json; charset=utf-8, wie auf spaceapi.io empfohlen.
- Einschalten unter Einstellungen → SpaceAPI → "Öffentliche SpaceAPI ausliefern".
  Ausgeschaltet antwortet /spaceapi.json mit "nicht gefunden".
- Die Adresse der versteckten SpaceAPI wird nie ausgegeben.

Woraus die öffentliche SpaceAPI besteht
1. Eigene Angaben: Name, Logo (ohne Angabe das Logo der Website), Website, Adresse,
   Koordinaten, Zeitzone, Ländercode, Wegbeschreibung, Kontakt (E-Mail, Mailingliste,
   Telefon, Matrix, Mastodon, XMPP), Statustext, Status-Icons, Links, Projekte, Webcams,
   RSS-Feed der Website (feeds.blog).
2. Aus der versteckten SpaceAPI weitergegeben: nur angehakte Felder, z. B. state.open,
   state.lastchange oder einzelne Sensoren. Listen werden als Ganzes weitergegeben.
   Ein weitergegebener Wert ersetzt die eigene Angabe. Ist die versteckte SpaceAPI nicht
   erreichbar, fehlen diese Felder; ein fehlendes state.open bedeutet laut Standard
   "Status gerade unbekannt".
3. Werkstatt-Öffnungen aus dem Plugin "El-Team Termine" (ab 1.1): Die SpaceAPI hat kein
   Feld für zukünftige Öffnungen. Deshalb erscheinen sie
   - als Kalender: feeds.calendar (iCal, /?el_termin_ics=oeffnungen),
   - im Zusatzfeld ext_upcoming_openings (eigene Felder mit Präfix ext_, wie auf
     spaceapi.io empfohlen): [{ name, start, end, all_day, url, location }],
     Zeiten in Unix-Sekunden,
   - und bei geschlossener Werkstatt im Statustext (state.message), z. B.
     "Geschlossen – nächste Öffnung: Fr., 4.12., 19:00–22:00 Uhr (El-Café im Dezember)".
     Liefert die versteckte SpaceAPI einen eigenen Statustext und ist er zur Weitergabe
     angehakt, bleibt dieser stehen.
   Ein Termin ändert nie state.open.

Die Einstellungsseite zeigt eine Vorschau der öffentlichen SpaceAPI und weist auf fehlende
Pflichtfelder hin. Zusätzlich lässt sich die Ausgabe mit dem Validator auf spaceapi.io prüfen.

Wie bisher (lesen weiterhin die versteckte SpaceAPI)
- [space_status] für den Status
- Header-Anzeige direkt unter dem Header (Standard) oder nach dem <body>-Tag
- Widget "SpaceAPI Kurzstatus"
- Automatische Erkennung der aktuell gelieferten JSON-Felder, konfigurierbare Labels
- [spaceapi field="space"] für beliebige Felder, Punktnotation (state.open,
  location.lat), Array-Indizes (temperature.0.value), fallback und label

Beispiele:
[spaceapi field="space"]
[spaceapi field="space" label="Name"]
[spaceapi field="state.open" label="Status"]
[spaceapi field="location.lat" label="Breitengrad"]
[spaceapi field="state.open" label="Status" fallback="Nicht verfügbar"]

Für Entwickler
- Filter spaceapi_status_public_data( $daten ) vor der Ausgabe.

Update von 1.x
- Alle bisherigen Einstellungen bleiben erhalten. Die öffentliche SpaceAPI ist nach dem
  Update ausgeschaltet, bis sie eingeschaltet wird. Die Adresse /spaceapi.json wird beim
  ersten Seitenaufruf nach dem Update angelegt; falls nicht: Einstellungen → Permalinks
  einmal speichern.

Updates
- Ab dieser Version kommen Updates aus den GitHub-Releases des Repos WP-Plugins
  (Dashboard → Aktualisierungen). Manuell prüfen: in der Plugin-Liste "Nach Updates suchen"
  oder Dashboard → Aktualisierungen → "Erneut prüfen". Release anlegen: RELEASING.md im Repo.
