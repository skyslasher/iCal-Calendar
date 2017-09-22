Kalender im Webfront anzeigen
===

In diesem Beispiel wird gezeigt, wie Kalenderdaten aus mehrerern **iCalCalendarReader**-Instanzen in einer Calendar-Control im Webfront angezeigt werden können. Die Kalendereinträge haben für jeden Kalender eine unterschiedliche Farbe.

Grundlage für die Visualisierung ist die Calendar-Control [Full Calendar](https://fullcalendar.io/)


**Inhaltverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)

### 1. Funktionsumfang

Full-Blown Calendar-Control, die umfangreich den eigenen Wünschen angepasst werden kann. Siehe hierzu die Dokumentation der Control unter https://fullcalendar.io/docs/  
Kalendereinträge sind auf ID, Titel, Start- und Endzeitpunkt beschränkt.


### 2. Voraussetzungen

- IP-Symcon ab Version 4.x
- Kalender im iCal-Format
- Installierte und lauffähige **iCalCalendarReader**-Instanzen
- Optional installierte und lauffähige **iCalCalendarNotifier**-Instanzen


### 3. Installation

* Die Dateien `calendar.html` und `feed.php` in ein Verzeichnis unterhalb des WebFront-User-Verzeichnisses `user` kopieren.  
* In der Datei `calendar.html` händisch folgende Anpassungen vornehmen:
  * Ab Zeile 26 werden im Array `eventSources` zwei Kalenderquellen definiert. Quellen können entweder eine **iCalCalendarReader**- oder eine **iCalCalendarNotifier**-Instanz sein. Hier müssen die Instanz-IDs mit gültigen Werten ersetzt werden. Hierfür die Instanz-IDs aus dem IP Symcon Objektbaum heraussuchen und innerhalb des Arrays `eventSources` im Objekt `data` in die Property `InstanceID` eintragen.
  * Es können beliebig viele Quellen zu einem Kalender hinzugefügt werden, hier einfach analog zu den beiden Einträgen verfahren.
  * Die Farbeinstellungen `color` und `textColor` nach Gusto anpassen
* Im WebFront-Editor an beliebiger Position ein Element "Externe Seite" hinzufügen, mit der URL `/user/[Verzeichnisname]/calendar.html`.  

Wenn alles korrekt gelaufen ist wird im WebFront nun eine Calendar Control mit den Inhalten der beiden Kalender-Feeds angezeigt.  

Wenn man **iCalCalendarNotifier**-Instanzen als Feed-Quelle verwendet können sie als smarte Filter auf Kalenderdaten eingesetzt werden, um z.B. nur bestimmte Zeiträume um den momentanen Zeitpunkt anzuzeigen. Sie haben auch einen Einfluss darauf, wie von der zugehörigen **iCalCalendarReader**-Instanz Kalendereinträge aus der Vergangenheit geladen werden: Wenn das Ende des Eintrags plus die im Notifier gesetzte Delay-Zeit noch in der Zukunft liegt wird der Eintrag geladen, ansonsten verworfen.  

Die Calendar Control ist umfassend dokumentiert (siehe oben), es gibt hier noch genug Spielraum für Anpassungen.  

Das Theming kann in Zeile 6 angepasst werden. Für andere Bootstrap-Themes den Theme-Namen `darkly` im CSS-Pfad durch einen dieser Theme-Namen ersetzen:
cosmo, cyborg, darkly, flatly, journal, lumen, paper, readable, sandstone, simplex, slate, solar, spacelab, superhero, united oder yeti.
