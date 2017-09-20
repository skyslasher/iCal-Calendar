iCal Calender in IP Symcon lesen und verarbeiten
===

Diese Bibliothek beinhaltet zwei Module zur Einbindung von Kalenderdateien/-feeds im iCal-Format in IP Symcon:
* **iCal Calendar Reader**
* **iCal Calendar Notifier**


**Inhaltverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

Mit dem Modul **iCal Calendar Reader** werden Kalenderdaten eingelesen (getestet mit Google Calendar, ownCloud Calendar und Synology Calendar), das Modul **iCal Calendar Notifier** reagiert mit einstellbaren Vor- und Nachlaufzeiten mit einer Statusvariable auf Kalenderereignisse. Zum aktuellen Status kann ein Skript weitere Kalenderdaten des/der auslösende(n) Ereigniss(e) abfragen. Es sind beliebig viele **iCal Calendar Notifier**-Instanzen mit unterschiedlichen Einstellungen an eine **iCal Calendar Reader**-Instanz koppelbar. 

Damit ist es z.B. sehr einfach möglich einen zentralen Anwesenheitskalender im Internet zu pflegen, IP Symcon steuert damit automatisch Heizung, Alarmanlage und Anwesenheitssimulation. Mit der Auswertung von zusätzlichen Informationen im Kalendereintrag können z.B. bestimmte Transponder für den Zugang gesperrt bzw. freigeschaltet werden.

Auch die Visualisierung von Einträgen in öffentlichen Kalendern (z.B. Müllabfuhrtermine, Kinoprogramm, ...) im Webfront können mit mehreren **iCal Calendar Notifier**-Instanzen ohne viel Skript-Programmierung gesteuert werden. Z.B. werden Abfuhrtermine immer bereits 1 Tag vorher angezeigt, das Kinoprogramm zeigt prominent den Spielplan des aktuellen Tages, weiter unten die restliche Woche.

Kalender werden beim Laden unter Berücksichtigung ihrer jeweiligen Zeitzone in die lokale Zeitzone umgerechnet, sich wiederholende Termine als mehrere Einzeltermine abgespeichert.

Diese Bibliothek nutzt folgende externe Bibliotheken im Verzeichnis `/lib`:
* iCalcreator (Version 2.24) `https://github.com/iCalcreator/iCalcreator`
* RRULE for PHP (Stand 2017-05-15) `https://github.com/rlanvin/php-rrule`


### 2. Voraussetzungen

- IP-Symcon ab Version 4.x
- Kalender im iCal-Format


### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.
`git://github.com/skyslasher/iCal-Calendar`


### 4. Einrichten der Instanzen in IP-Symcon

Unter "Instanz hinzufügen" eine I/O-Instanz **iCal Calendar Reader** hinzufügen. Dieser ist unter dem Hersteller **ergomation systems** aufgeführt.  

__Konfigurationsseite__:

Name                | Beschreibung
------------------- | ---------------------------------
Calendar URL        | URL zum iCal-Feed
Username            | Benutzer für den Zugriff auf den Feed
Passwort            | Passwort dieses Benutzers
Synchronization     | 
Cachesize (days)    | Anzahl der Tage für die Ereignisse in der Zukunft gelesen werden sollen
Update-freq. (mins) | Nach wie vielen Minuten soll ein Update des Feeds gelesen werden

Sobald eine URL angegeben und gespeichert wurde beginnt die Synchronisierung. Fehler beim Zugriff auf den Kalender stehen im Systemlog (Tabreiter **Meldungen** in der IP-Symcon Management Konsole). Bei jeder Änderung der Parameter wird eine sofortige Synchronisation und ein Update auf alle angemeldeten Notifier gegeben.

Mit "Instanz hinzufügen" nun eine Instanz **iCal Calendar Notifier** hinzufügen. Dieser ist ebenfalls unter dem Hersteller **ergomation systems** aufgeführt.

Die **iCal Calendar Notifier** haben folgende Konfigurationsoptionen:

__Konfigurationsseite__:

Name                | Beschreibung
------------------- | ---------------------------------
Lagged Notification | 
Prenotify (mins)    | Wie viele Minuten vor dem Ereignisstart soll die Statusvariable "Presence" auf "true" gesetzt werden
Delay (mins)        | Wie viele Minuten nach dem Ereignisende soll die Statusvariable "Presence" auf "false" gesetzt werden

Es ist zu beachten, dass es je nach Einstellungen und Kalendereinträgen Überlappungen geben kann.

### 5. Statusvariablen und Profile

Die Statusvariable wird automatisch angelegt. Das Löschen führt zu Fehlfunktionen.


#### Statusvariablen

Die **iCal Calendar Notifier**-Instanzen haben folgende Statusvariable:

Name     | Typ     | Beschreibung
-------- | ------- | ----------------
Presence | Boolean | Zeigt an ob ein Kalendereintrag unter Berücksichtigung der im Modul angegebenen Zeiten aktiv ist


#### Profile:

Es werden keine Variablenprofile angelegt.


### 6. WebFront

Die Statusvariablen sind mir Profilen für das WebFront vorbereitet.


### 7. PHP-Befehlsreferenz

#### iCal Calendar Reader

`json_string ICCR_GetClientConfig(integer $InstanceID);`   
Gibt einen Array mit sämtlichen registrierten Notifier-Konfigurationen als JSON-codierten String aus. 

`void ICCR_GetCachedCalendar(integer $InstanceID);`   
Gibt einen Array mit dem zwischengespeicherten und in die lokale Zeitzone übertragenen Kalenderdaten als JSON-codierten String aus.

`void ICCR_TriggerNotifications(integer $InstanceID);`   
Forciert eine sofortige Überprüfung, ob Notifications an die registrierten Notifier gesendet werden müssen.
Diese Funktion wird intern jede Minute aufgerufen.
Die Funktion liefert keinerlei Rückgabewert.  

`void ICCR_UpdateCalendar(integer $InstanceID);`   
Forciert eine sofortiges Neuladen des Kalenders.
Diese Funktion wird intern regelmäßig, wie in "Update-freq. (mins)" konfiguriert, aufgerufen.
Die Funktion liefert keinerlei Rückgabewert.  

`void ICCR_UpdateClientConfig(integer $InstanceID);`   
Forciert ein sofortiges Neuladen der Konfigurationen aller registrierten Notifier.
Die Funktion liefert keinerlei Rückgabewert.  


#### iCal Calendar Notifier

`boolean ICCN_GetNotifierPresence(integer $InstanceID);`   
Gibt den Wert der Statusvariable "Presence" zurück.  

`json_string ICCN_GetNotifierPresenceReason(integer $InstanceID);`   
Gibt einen Array der den "Presence"-Status bedingenden Ereignisse als JSON-codierten String aus.
