[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20version-0.78-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-6.0%20%3E-green.svg)](https://community.symcon.de/t/ip-symcon-6-0-testing/44478)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions) [![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#spenden)  

# FritzBox <!-- omit in toc -->

## Inhaltsverzeichnis <!-- omit in toc -->

- [Vorbemerkungen zur Library](#vorbemerkungen-zur-library)
- [Vorbemerkungen zur Integration von Geräten](#vorbemerkungen-zur-integration-von-geräten)
- [Hinweise zum Symcon-System / Host](#hinweise-zum-symcon-system--host)
- [Folgende Module beinhaltet das FritzBox Repository](#folgende-module-beinhaltet-das-fritzbox-repository)
- [Changelog](#changelog)
- [Spenden](#spenden)
- [Lizenz](#lizenz)

----------

## Vorbemerkungen zur Library

**todo**
Diese Library ....  

----------

## Vorbemerkungen zur Integration von Geräten  

Es werden Instanzen zum auffinden (Discovery) und einrichten (Konfigurator) von Geräten in Symcon bereitgestellt.  
Diese Instanzen werden nur korrekt funktionieren, wenn die betreffenden Geräte entsprechend Konfiguriert wurden.  

**todo**
Es wird dringend empfohlen vor der Integration in IPS folgende Parameter in der FritzBox zu konfigurieren / zu prüfen:

- Zugangsdaten einen Benutzers  
- Berechtigung der Zugangsdaten  
- Anrufmonitor, sofern gewünscht, per Telefon aktivieren. (#96*5* wählen)  
...

----------

## Hinweise zum Symcon-System / Host  

Um Ereignisse von der FritzBox in Symcon zu verarbeiten wird ein Webhook pro [IO-Modul](FritzBox%20IO/README.md) erzeugt.  
Hier wird beim anlegen der Instanz automatisch nur der interne WebServer von Symcon auf Port 3777 eingetragen.
Die IP-Adresse auf welchem Symcon die Daten empfängt wird automatisch ermittelt.

Bei System mit aktiven NAT-Support funktioniert die automatische Erkennung der eigenen IP-Adresse nicht. __Hier wird automatisch die NATPublicIP aus den [Symcon-Spezialschaltern](https://www.symcon.de/service/dokumentation/entwicklerbereich/spezialschalter/) benutzt.__  
<span style="color:red">**Auch bei Systemen mit aktiven NAT-Support wird extern automatisch nur der Port 3777 beim anlegen von IO-Instanzen unterstützt.**</span>  
  
Sollte es nötig sein, so können bei Bedarf die eigene IP und der Port, sowie die Verwendung von https,  in den IO-Instanzen unter `Experteneinstellungen` geändert und fixiert werden.

Damit Geräte über das [Discovery-Modul](FritzBox%20Discovery/README.md) gefunden werden können, müssen bei in gerouteten Netzen und bei NAT Systemen Multicast-Pakete korrekt weitergeleitet werden.  
<span style="color:red">**Discovery funktioniert nicht in einem Docker Container welcher per NAT angebunden ist. Diese Konstellation wird aufgrund der fehlenden Multicast Fähigkeiten von Docker nicht unterstützt. In diesem Fall muss der [Konfigurator](FritzBox%20Configurator/README.md) manuell angelegt und konfiguriert werden.**</span>  
Für das Discovery werden Pakete über die Multicast-Adresse `239.255.255.250` auf Port `1900` gesendet und UDP Pakete auf Port `1901` empfangen.  


----------

## Folgende Module beinhaltet das FritzBox Repository  

- __FritzBox Discovery__ ([Dokumentation](FritzBox%20Discovery/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Konfigurator__ ([Dokumentation](FritzBox%20Configurator/))  
	Kurze Beschreibung des Moduls.

- __FritzBox IO__ ([Dokumentation](FritzBox%20IO/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Anruf-Monitor__ ([Dokumentation](FritzBox%20Callmonitor/))  
	Kurze Beschreibung des Moduls.

- __FritzBox DVBC__ ([Dokumentation](FritzBox%20DVBC/))  
	Kurze Beschreibung des Moduls.

- __FritzBox DynDNS__ ([Dokumentation](FritzBox%20DDNS/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Geräte Informationen__ ([Dokumentation](FritzBox%20Device%20Info/))  
	Kurze Beschreibung des Moduls.

- __FritzBox DHCP Server__ ([Dokumentation](FritzBox%20DHCP%20Server/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Dateifreigabe__ ([Dokumentation](FritzBox%20File%20Share/))  
	Kurze Beschreibung des Moduls.

- __FritzBox FritzBox Firmware Informationen__ ([Dokumentation](FritzBox%20Firmware%20Info/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Host Filter__ ([Dokumentation](FritzBox%20Host%20Filter/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Hosts__ ([Dokumentation](FritzBox%20Hosts/))  
	Kurze Beschreibung des Moduls.

- __FritzBox MyFritz__ ([Dokumentation](FritzBox%20MyFritz/))  
	Kurze Beschreibung des Moduls.

- __FritzBox NAS Storage__ ([Dokumentation](FritzBox%20NAS%20Storage/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Powerline__ ([Dokumentation](FritzBox%20Powerline/))  
	Kurze Beschreibung des Moduls.

- __FritzBox SmartHome__ ([Dokumentation](FritzBox%20Homeautomation/))  
	Kurze Beschreibung des Moduls.

- __FritzBox SmartHome Konfigurator__ ([Dokumentation](FritzBox%20Homeautomation%20Configurator/))  
	Kurze Beschreibung des Moduls.

- __FritzBox Telefonie__ ([Dokumentation](FritzBox%20Telephony/))  
	Kurze Beschreibung des Moduls.

- __FritzBox NTP-Server & Systemzeit__ ([Dokumentation](FritzBox%20Time/))  
	Kurze Beschreibung des Moduls.

- __FritzBox UPnP MediaServer__ ([Dokumentation](FritzBox%20UPnP%20MediaServer/))  
	Kurze Beschreibung des Moduls.

- __FritzBox WAN Interface__ ([Dokumentation](FritzBox%20WAN%20Common%20Interface/))  
	Kurze Beschreibung des Moduls.

- __FritzBox DSL Interface__ ([Dokumentation](FritzBox%20WAN%20DSL%20Link/))  
	Kurze Beschreibung des Moduls.

- __FritzBox WAN Physical Interface__ ([Dokumentation](FritzBox%20WAN%20Physical%20Interface/))  
	Kurze Beschreibung des Moduls.

- __FritzBox WAN IP Connection__ ([Dokumentation](FritzBox%20WAN%20IP%20Connection/))  
	Kurze Beschreibung des Moduls.

- __FritzBox NAT Port Forwarding__ ([Dokumentation](FritzBox%20WAN%20PortMapping/))  
	Kurze Beschreibung des Moduls.

- __FritzBox WebDav Speicher__ ([Dokumentation](FritzBox%20WebDav%20Storage/))  
	Kurze Beschreibung des Moduls.

- __FritzBox WLAN__ ([Dokumentation](FritzBox%20WLAN/))  
	Kurze Beschreibung des Moduls.

----------

## Changelog

Version 0.78:  
- Port Forwarding hat die eigene PublicIP bei NAT nicht berücksichtigt.  
- Port Forwarding hat Netzwerke mit 2 Ziffern im ersten Octet (z.B. 10.x.y.z) nicht unterstützt. 
- UpnP Unsubscribe ergänzt für das korrekte Abmelden von Events an der FritzBox. (Sollte den HTTP 412 Precondition Failed Fehler beheben.)  
- Fehlte ein Eintrag in der Host-Tabelle der FritzBox und war das automatische umbenennen von Host-Variablen in einer WLAN-Instanz aktiv, so konnte der Name der Variable gelöscht werden und wurde als unnamed Object angezeigt.  
- Discovery-Instanz funktioniert unter Linux-OS wieder.  

Version 0.77:  
- Vorhandene WAN DSL-Verbindung Instanz wurde im Konfigurator nicht korrekt erkannt.  
- Offene Konfigurationsformulare konnten beim Modul-Update Fehlermeldungen werfen und wurden geschlossen.  
- WLAN-Instanz für das Gäste-WLAN hat immer die gleichen Geräte wie WLAN 1 angezeigt.  
  
Version 0.76:  

- Anrufmonitor hat Vorwahlen und Leerzeichen in Rufnummern nicht berücksichtigt.  
- Verfügbarkeit des Anrufmonitor der FritzBox wird vom Konfigurator immer neu überprüft.  
- LinkStatus Variablen werden als String Variablen angelegt.  
- Debug Meldungen von Ereignissen aus dem LogFile entfernt.  
- Discovery-Instanz sucht auf allen Netzwerkschnittstellen und allen IP-Adressen nach Geräten und präferiert in absteigender Reihenfolge den Hostnamen, IPv6 Adressen und zuletzt IPv4 Adressen der FritzBox(en).  

Version 0.75:  

 - Powerline-Instanz unterstützt automatisches umbenennen der Gerätevariablen.  
 - IPv6 unterstützung hinzugefügt (Betrifft sowohl Discovery-Instanz, als auch den FritzBox IO).  
 - Discovery-Instanz nutzt die UUID der Geräte um nach der Suche alle IPv4 und IPv6 Adressen korrekt den Geräten zuordnen zu können.
 - Discovery-Instanz präferiert Hostnamen vor IP-Adressen.  
 - Firmware Information Instanz, welche auch das wechseln des Updatekanal und schalten des AutoUpdate zulässt.  
 - 'Homeautomation Configurator' / 'SmartHome Konfigurator' wird unter 'Konfiguratoren' angelegt.  
 - Host-Instanz lässt das abwählen von Statusvariablen (von Netzwerkgeräten) zu.  
 - WLAN-Instanz lässt das abwählen von Statusvariablen (von Netzwerkgeräten) zu.  
 - Host-Filter Instanz lässt das abwählen von Statusvariablen (von Netzwerkgeräten) zu.   
 - Host-Filter Instanz stellt schaltbare Statusvariablen bereit, um Netzwerkgeräten den Internetzugang zu sperren.  
 - Sollen Variablen von WLAN-Instanzen automatisch umbenannt werden, so erfolgt dies jetzt auch wenn die Geräte offline sind.  
 - 5GhZ WLAN wird wieder korrekt in der Konfiguration erkannt.  
 - Viele Konfigurationsformulare überarbeitet.  
 - DVB-C Instanz war defekt.  
 - Sendersuchlauf für DVB-C kann gestartet/gestoppt werden.  
 - DVB-C kann de/-aktiviert werden. Achtung, Fritzbox startet neu!  
 - Rufumleitungen der Telefonie-Instanz waren teilweise defekt.  
 - Benennung der Rufumleitungen und Anrufsperren der Telefonie-Instanz verbessert.  
 - Anrufsperren können nicht mehr geschaltet werden.  

Version 0.74:  

 - Fehlermeldungen wenn die Host-Tabelle deaktiviert wurde.  
 - Neues Modul 'Homeautomation Configurator' / 'SmartHome Konfigurator'.  
 - 'Homeautomation' / 'SmartHome' Modul legt Variablen an.  
 - Durch Änderungen des HTTP-Header ab einer nicht genauer bekannten Fritz!OS Version traten 'Subscribe' Fehlermeldungen auf. Der Fehler ist behoben.  

Version 0.73:  

- Neues Modul 'Powerline' um den Zustand der Powerline Geräte darzustellen.  
- Neues Modul 'DVBC' um den Zustand des DBV-S Empfängers darzustellen und zu steuern.  
- Neues Modul 'Homeautomation' / 'SmartHome'
- Links zur Doku waren defekt.  
- Bestimmte Rufumleitungen konnten zu Fehlern führen.  
- Namen und Übersetzungen der Variablen der Rufumleitungen erweitert.  
- Bessere Fehlerbehandlung beim Auswerten der Antwort vom Event-Subscribe.  
- Fehlermeldung beim Zugriff auf die Telefonbücher und Host-Datei, wenn Symcon startet.  

Version 0.72:  

- Fehlermeldung beim Zugriff auf die Telefonbücher.  
  
Version 0.71:  

- Buffer für Anrufmonitor konnte korrupt sein, was zu Fehlermeldungen führte.  
- Timer für Anrufliste wurde falsch berechnet.  

Version 0.70:  

- Parameter für Aktionen des Anrufmonitor unterstützen Platzhalter wie bei der WebFront-Benachrichtigung z.B. {NAME}  
- Anrufliste als HTML-Box kann in der Telefonie-Instanz deaktiviert werden.  
- Abrufintervall der Anrufliste wird auf Mitternacht gesetzt, wenn der Anrufmonitor aktiv ist.  
- Entsprechender Hinweis in der Konfiguration der Telefonie-Instanz wenn Intervall der Anrufliste nicht benötigt wird.
- Namen der Telefonbücher konnten zu einem Fehler und vermehrten anlegen der Medien-Objekte führen.  
- Anrufliste unterstützt auch jetzt die Relative Datumsangabe mit gestern und heute.  

Version 0.69:  

- Verlagerung der XML Dateien in der TMP Verzeichnis rückgängig gemacht.  
- Telefonbücher und Daten der Hosts werden unterhalb des IO in einem Media-Object, mit aktivem Cache gespeichert.  
- Dadurch reduzierte Schreibzyklen auf das Speichermedium.  
- IO prüft ob Telefonie-Funktionen vorhanden sind.
- Konfigurator zeigt Telefonie-Instanzen nur bei Vorhandensein von Telefonie an.
- IO prüft ob der Port für den Anrufmonitor auf der FritzBox freigeschaltet wurde.  
- Konfigurator bringt eine Meldung wenn der Anrufmonitor auf der FritzBox nicht freigeschaltet wurde.  
- Telefonie-Instanzen laden Telefonbücher, Rufumleitungen und Anrufsperren beim übernehmen der Änderungen und somit auch beim anlegen/neustart.  
- IO ermittelt die Vorwahl (OKZ, LKZ inklusive Prefix).  
- Anrufliste-Instanz übernimmt Vorwahl vom IO.
- Anrufliste-Instanz meldet wenn die Vorwahl nicht erkannt wurde und fordert zum manuellen konfigurieren auf.  
- WLAN und Host-Filter Instanzen geben einen Link zum Konfigurator aus, wenn die Host-Instanz fehlt.  

Version 0.68:  

- Ist der NATSupport von Symcon aktiviert, aber keine PublicIP konfiguriert, so wird im FritzBox-IO eine Meldung ausgegeben.  
- NATSupport und FritzBox in anderen Subnetzen werden unterstützt (außer Discovery-Instanz!).  
- Fehlende Übersetzungen im Konfigurator ergänzt
- Neue Instanz-Funktion für die Suche nach Kontakten in den Telefonbüchern (FB_GetPhonebookEntrysByNumber(12345 /* FritzBox Telefonie */, string $Number))  
- Neue Instanz-Funktion für die Rückwärtssuche (FB_SearchNameByNumber(12345 /* FritzBox Telefonie */, string $Number, string $AreaCode))  
- Neues Modul 'Host Filter' um Hosts den WAN Zugriff zu sperren (noch nicht fertig).  

Version 0.62:  

- Discovery-Instanz war mit TTL Änderung kaputt  
- Discovery-Instanz priorisiert bei IO's https Verbindungen
- FritzBox WAN Common Interface (FritzBox allgemeine WAN-Schnittstelle) hat nach einem Update nicht korrekt funktioniert  

Version 0.60:  

- Neues Modul: FritzBox WAN Physical Interface (FritzBox physikalische WAN-Schnittstelle)  
- Modul FritzBox WAN DSL Link (FritzBox WAN DSL-Verbindung) war fehlerhaft  
- https-Verbindungen zur FritzBox waren defekt  
- Konnte der EventWebhook (Ereignis-WebHook) nicht ermittelt werden, z.B. weil die FritzBox die Verbindung ablehnte, wird jetzt der Status korrekt zurückgemeldet  
- Discovery-Instanz setzt den TTL auf 4, damit die Multicast-Pakete geroutet werden können  
- Allgemeines Fehlerhandling verbessert  
- UPnP Subscribe benutzt UPnP/2.0 und HTTP/1.1  
- Schreibfehler vom Statusvariable 'FritzBox registriert' im MyFritz-Modul korrigiert (Nur wenn Variable neu angelegt wird)  
- WLAN-Zustand wird nach dem Schalten automatisch abgefragt, wenn Events nicht unterstützt werden  
- FB_SetDeflectionEnable hat die Statusvariablen nicht nachgeführt  
- Ein eventuell vorhandenes altes Variablenprofil 'FB.MByte' wird automatisch gelöscht und neu erzeugt
  
Version 0.50:  

- Beta Release für Symcon 6.0  
- Readme erweitert
- `FritzBox-IO` nutzt HTTP Keep-Alive
- Unnötige Debug-Ausgabe in IO bei aktiven Anrufmonitor entfernt  

----------

## Spenden  
  
  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## Lizenz  

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
