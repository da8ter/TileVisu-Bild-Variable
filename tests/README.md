# Regressionstests

Ohne laufendes Symcon aus dem Repository-Verzeichnis ausführen:

```sh
php -l ImageVariable/module.php
php -l tests/bootstrap.php
php -l tests/module_test.php
php tests/module_test.php
git diff --check
```

`tests/bootstrap.php` ist eine kleine SDK-Attrappe mit den Signaturen von `IPSModuleStrict` (Symcon 8.1). Unter `strict_types` fällt jeder lockere Aufruf als `TypeError` auf, der Fehler-Handler der Tests macht zusätzlich jede Warnung zum Abbruch. `IMGV_MODULE=<pfad/module.php>` lässt die Attrappe eine andere Fassung der Kachel laden; die Tests nutzen das für ihre Unterprozesse.

`tests/module_test.php` prüft:

- **Gegenprobe gegen den alten Stand:** dieselben Szenarien laufen mit dem Stand `772a161` (per `git show` in ein Temp-Verzeichnis, eigener Prozess) und mit dem aktuellen Modul ohne Hook. Alle Ausgaben an die Kachel, also Updates und komplette Kacheldokumente, müssen byte-identisch sein. Ohne Git-Historie wird dieser Teil übersprungen (`SKIP`).
- **Nur bei echter Wertänderung:** `VM_UPDATE` mit `$Data[1] === false` schickt nichts, ein neuer Wert Wert und Details als Paar, dasselbe Paar kein zweites Mal (Prüfwert je Variable im Puffer `UpdateHashes`), nach `ApplyChanges` und nach dem Erstaufbau geht es wieder hinaus, ohne `$Data[1]` wird gesendet. Gegenprobe: gegen den Stand `3c6378d` fallen genau die Prüfungen, die keine Nachricht erwarten.
- **Module Strict:** Property-Vorgaben mit echten Typen, Warten auf `KR_READY` und Nachholen bei `IPS_KERNELSTARTED`, keine Referenz auf 0 und kein Abo für Absender 0, Payload-Schlüssel samt Reihenfolge.
- **Bild-Hook** `/hook/imagevariabletile/<ID>?k=bgimage&v=<Version>&t=<Token>`: Registrierung in `Create`, Token, Hook-Adresse statt Base64 im Kacheldokument, Auslieferung (Bytes, Bildtyp, Länge, `Cache-Control`, ETag, 304, `nosniff`), 403 bei falschem oder fehlendem Token, 404 bei unbekanntem Schlüssel, neue Adresse bei `MM_UPDATE`.
- **Rückfall:** ohne registrierten Hook und für Bilder über `ScriptOutputBufferLimit` − 1 KiB bleibt es bei der Data-URI; ein Token aus einem früheren Kernel-Lauf gilt dann nicht.
- **Standardbild nur bei Anzeige:** eine Modulkopie ohne `imgs/` läuft im eigenen Prozess. Ohne Anzeige darf das Bild nicht gelesen werden, mit Anzeige muss der Zugriff auffallen.
- **Gegenprobe der Attrappe:** eine Kopie mit `RegisterPropertyBoolean('BG_Off', 1)` muss mit `TypeError` scheitern.

Nicht abgedeckt sind der echte Kernel (Hook-Registrierung, `HookInstance`-Aufsatz, Ausgabegrenze der Symcon-SAPI) und der Browser. Vor der Übernahme in einer Testinstanz prüfen:

- Modul neu laden, Kachel öffnen: Hintergrund lädt über `/hook/imagevariabletile/<ID>`, das Kacheldokument enthält kein `base64,`.
- Hintergrundbild im selben Medienobjekt ersetzen: die offene Kachel zeigt ohne Neuladen das neue Bild.
- Standardbild aus, Bild-URL gesetzt: die Kachel lädt die URL und aktualisiert im eingestellten Intervall.
- Symcon neu starten: Kachel und Bild sind nach dem Kernelstart da.
