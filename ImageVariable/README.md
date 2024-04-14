# Bild+Variable Kachel

![Bild+Variable Kachel](https://github.com/da8ter/images/blob/1c5fe63e9757e81e6d8c4c84a63e0b39fa00247c/button-kachel2.jpg)


### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Kachelkonfiguration](#5-Kachelkonfiguration)

### 1. Funktionsumfang

* Die "Button- Kachel" ermöglicht es bis zu 10 Button in einer Kachel abzubilden. Möglich ist das schalten einer Bool-Variable. Der Inhalt eines Button kann frei konfiguriert werden. Möglich ist die Anzeige von: Icon, Variablen-Wert, Variablen-Name oder einem freien Text. Wie bei all meinen Kacheln kann das Aussehen weitestgehend frei konfiguriert werden. Möglich sind eigene Hintergrundbilder (inkl. einstellbarer Transparenz), Hintergrundfarben und Schriftgrößen.

### 2. Voraussetzungen

- IP-Symcon ab Version 7.1

### 3. Software-Installation

* Über den Module Store
* Über das Module Control folgende URL hinzufügen
https://github.com/da8ter/TileVisu-Button-Kachel.git


### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann die Wallbox-Kachel mithilfe des Schnellfilters gefunden werden. (Suchbegriff: Button, TileVisu oder Kachel)  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Kachelkonfiguration

Grundsätzlicher Hinweis:
Standardmäßig sind alle Objekte in der Kachelansicht ausgeblendet. Sie werden nur angezeigt, wenn du sie entsprechend konfigurierst. Bitte beachte, dass nicht alle Änderungen an der Konfiguration automatisch in der Kachelansicht sichtbar sind. Sollten Änderungen nicht sofort erscheinen, lade bitte die Seite oder den iFrame neu.

__Kacheldesign__
Name     | Beschreibung
-------- | ------------------
Standard-Hintergrundbild|Ein-/Ausschalten des Standard-Hintergrundbildes.
Hintergrundbild|Auswahl eines eigenen Medienobjekts als Hintergrund.
Transparenz Bild|Einstellung der Transparenz des Hintergrundbildes, um es abzudunkeln oder farblich anzupassen. 
Kachelhintergrundfarbe|Farbe des Kachelhintergrunds (wird nur bei eingestellter Bildtransparenz sichtbar)

__Schalter__
Name     | Beschreibung
-------- | ------------------
Schalter1|Bool-Variable die geschaltet werden soll. Die Variable benötigt ein Variablenprofil mit Assoziationen. Icon und die Farbe des Button werden aus dem Variablenprofil ausgelesen. Wenn dort keine Farbe eingestellt ist wird nur der Text angezeigt und kein farbiger Button!
Einstellung|Öffnet ein Fenster mit weiteren Konfigurationsmöglichkeiten.
Schriftgröße|Stellt die Schriftgröße der Buttonbeschriftung ein.
Schalter 2 bis 10|siehe Schalter 1

__Einstellungen__
Name     | Beschreibung
-------- | ------------------
Breite|Die Button-Breite in Pixel. Dies ist z.B. sinnvoll wenn der Aus-Status eine andere Textlänge hat als der Ein-Status und sich daraus eine unterschiedliche Button-Breite ergibt.
Variablenname anzeigen|Anzeige des Variablennamen aus- oder einschalten.
Icon anzeigen|Anzeige des Assoziationsicon aus- oder einschalten.
Variablen-Icon anzeigen|Zeigt statt des Assiziations-Icon das Variablen-Icon an.
Wert anzeigen|Anzeige des Variablenwert aus- oder einschalten.
Variablennamen überschreiben|Der eingegebenen Text wird an stelle des Variabelnnamen angezeigt.
