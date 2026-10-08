# TileVisu Bild+Variable Kachel

Symcon-Kachel (HTML-SDK), die den Wert einer Variable auf einem Hintergrundbild zeigt (Medienobjekt, Bild-URL oder Standardbild). Öffentliches Repo `da8ter/TileVisu-Bild-Variable`. Bedienung: `ImageVariable/README.md`.

Betriebsdaten dieses Rechners (Zweige, Testsystem, Werkzeuge außerhalb des Repos) stehen in `CLAUDE.local.md` (nicht eingecheckt).

## Aufbau

- **`ImageVariable/`**: einziges Modul, Klasse `TileVisuImageVariableTile`, Präfix `IMGV`, `IPSModuleStrict`, ab Symcon 8.1.
- **Bild-Hook** `/hook/imagevariabletile/<ID>`: natives `RegisterHook` in `Create`, Adresse mit Inhaltsversion und Token; ohne Hook oder über der Ausgabegrenze Rückfall auf die Data-URI. Einzelheiten: `tests/README.md`.
- **Nachrichtenfilter:** `VM_UPDATE` zählt nur mit `$Data[1] === true` (fehlt die Angabe, wird gesendet); Prüfwerte je Variable im Puffer `UpdateHashes`, geleert in `ApplyChanges` und beim Erstaufbau.
- **Abos und Referenzen** nur für zugeordnete IDs > 0: Absender 0 heißt bei `RegisterMessage` „jedes Objekt“.
- **Icon-Baustein** `symcon-icons-shared` (v3) im `<head>` von `module.html`: lädt Symcons `/icons.js` einmal ins Visu-Hauptfenster statt in jede Kachel. Wortgleiche Kopie in mehreren TileVisu-Repos, die Quelle liegt außerhalb des Repos: **nie von Hand ändern**. Icons nur `fa-light`.

## Prüfen

```bash
php -l ImageVariable/module.php
php tests/module_test.php     # SDK-Attrappe, Gegenproben gegen ältere Commits (braucht die Git-Historie)
git diff --check
```

Umfang und die Handprobe in einer Symcon-Testinstanz: `tests/README.md`.

## Regeln

- **Commits:** deutsche Botschaft, ein Thema je Commit, **ohne** Co-Authored-By-Zeile; Prüfungen vorher.
- **Nie** `git checkout`/`git restore` auf Dateien: die Arbeitskopie kann nicht committete Arbeit enthalten.
- **Push und Release nur auf Zuruf.** Release: `version`, `build` und `date` in `library.json` hochsetzen (`date` ist ein Unix-Zeitstempel).
- **Öffentliches Repo:** keine IP-Adressen, Ports, Instanz-IDs, Token, Pfade unter `/Users/`, keine Personendaten – auch nicht in Tests und Kommentaren. FontAwesome Pro ist lizenziert: keine Font-Dateien, Kit-Kennungen oder Lizenzdaten.
- **Symcon-Standards:** `strict_types`, `IPSModuleStrict` mit vollen Typen, Darstellungen statt Variablenprofilen, Texte über `locale.json`, Nutzertexte sagen „Symcon“.
- **Kachel-Payload unverändert halten:** Eigenschaften, Idents und Payload-Schlüssel samt Reihenfolge sind durch die Tests festgeschrieben.

## Wissen

Gemeinsames Symcon-Plattformwissen (Lebenszyklus, Hooks, Timer, Kacheln, Icons): https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/docs/plattform – lokal `../List/docs/plattform/`. Symcon-Fragen am offiziellen Handbuch prüfen, nicht aus dem Modulbestand ableiten.
