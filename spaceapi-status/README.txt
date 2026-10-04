Lizenz: GNU GENERAL PUBLIC LICENSE. Siehe LICENSE.

SpaceAPI Status 1.4.0

- [space_status] für den Status
- Header-Anzeige direkt unter dem Header (Standard)
- Admin: Titel, Aktivierung, Position, Endpoint, Cache
- Automatische Erkennung der aktuell gelieferten JSON-Felder
- Konfigurierbare Labels pro Feld
- [spaceapi field="space"] für beliebige scalar/primitive Felder
- Punktnotation für verschachtelte Felder, z.B. state.open oder location.lat
- Array-Indizes, z.B. temperature.0.value
- fallback und eigenes label im Shortcode möglich

Beispiele:
[spaceapi field="space"]
[spaceapi field="space" label="Name"]
[spaceapi field="state.open" label="Status"]
[spaceapi field="location.lat" label="Breitengrad"]
[spaceapi field="state.open" label="Status" fallback="Nicht verfügbar"]
