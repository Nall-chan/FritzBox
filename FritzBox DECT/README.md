[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox DECT-Geräte <!-- omit in toc -->

Auslesen der an der FritzBox angemeldeten DECT-Geräte.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen](#5-statusvariablen)
- [6. Visualisierung](#6-visualisierung)
  - [Kachel Visualisierung](#kachel-visualisierung)
  - [WebFront Visualisierung](#webfront-visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

- Je DECT-Gerät (Mobilteile, aber auch DECT-Smart-Home-Geräte wie Heizkörperregler): Zustand `aktiv` laut FritzBox, Modell und verfügbare Firmware-Aktualisierung.  
- Firmware-Aktualisierung eines DECT-Geräts per PHP-Befehl starten.  

## 2. Voraussetzungen

- Symcon ab Version 9.0
- FritzBox mit DECT-Basis

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox DECT'-Modul unter dem Hersteller 'AVM' aufgeführt.

**Konfigurationsseite**:

![Config](imgs/config.png)  

**Konfigurationsparameter**:  

| Eigenschaft                             | Name                  | Typ     | Standardwert | Beschreibung                                                          |
| --------------------------------------- | --------------------- | ------- | ------------ | --------------------------------------------------------------------- |
| Aktualisierungsintervall                | RefreshInterval       | integer | 300          | Intervall in Sekunden, in dem die Werte abgefragt werden; `0` = aus   |
| Geräte-Variablen automatisch umbenennen | RenameDeviceVariables | boolean | false        | Variablen umbenennen, wenn sich der Gerätename in der FritzBox ändert |

Beispiel:  

```php
IPS_SetProperty(12345, 'RenameDeviceVariables', true);
IPS_ApplyChanges(12345);
```

## 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

| Ident           | Name             | Typ     | Beschreibung                                           |
| --------------- | ---------------- | ------- | ------------------------------------------------------ |
| `DECT<ID>`      | Name vom Gerät   | boolean | Je DECT-Gerät: Gerät ist laut FritzBox aktiv           |
| UpdateAvailable | Update verfügbar | boolean | Unterhalb der Gerätevariable: neue Firmware verfügbar  |
| Model           | Modell           | string  | Unterhalb der Gerätevariable: Modell des Gerätes       |

`<ID>` ist die ID des Gerätes in der FritzBox (`NewID` aus `FB_GetGenericDectEntry`), z.B. `DECT3`.  

> [!NOTE]
> Was die FritzBox unter `aktiv` versteht, ist von AVM nicht beschrieben. An der Test-Box waren ein FRITZ!Fon im Standby und ein Heizkörperregler beide nicht aktiv.

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz wird als Kachel in der Darstellung `Liste` angezeigt. Das Bild zeigt die maximierte Kachel mit allen Variablen.  
Jedes DECT-Gerät wird als An/Aus-Wert dargestellt, `Update verfügbar` ebenfalls als An/Aus-Wert und `Modell` als Text. Keine der Variablen ist bedienbar.  

![Kachel Liste](imgs/tile_list.png)  

### WebFront Visualisierung

![WebFront](imgs/webfront.png)  

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben.  

---

```php
integer|false FB_GetNumberOfDectEntries(integer $InstanzID);
```

Liefert die Anzahl der DECT-Geräte.  

```php
var_dump(FB_GetNumberOfDectEntries(12345)); // int(2)
```

---

```php
array|false FB_GetGenericDectEntry(integer $InstanzID, integer $Index);
```

Liefert ein DECT-Gerät über seine Position. `$Index` beginnt bei `0` und ist kleiner als `FB_GetNumberOfDectEntries`. Alle Werte sind Strings.  

| Schlüssel           | Beschreibung                                                  |
| ------------------- | ------------------------------------------------------------- |
| NewID               | ID des Gerätes, für `FB_GetSpecificDectEntry` und `FB_DectDoUpdate` |
| NewName             | Name des Gerätes                                              |
| NewModel            | Modell, z.B. `FRITZ!Fon C6`                                   |
| NewActive           | `1` = aktiv                                                   |
| NewUpdateAvailable  | `1` = neue Firmware verfügbar                                 |
| NewUpdateSuccessful | Ergebnis der letzten Aktualisierung, z.B. `unknown`           |
| NewUpdateInfo       | Zusatzinformation zur Aktualisierung                          |

```php
$Entry = FB_GetGenericDectEntry(12345, 0);
echo $Entry['NewID'] . ': ' . $Entry['NewModel']; // 3: FRITZ!Fon C6
```

---

```php
array|false FB_GetSpecificDectEntry(integer $InstanzID, string $ID);
```

Liefert ein DECT-Gerät über seine ID. Das Array enthält dieselben Schlüssel wie bei `FB_GetGenericDectEntry`, aber ohne `NewID`.  

```php
$Entry = FB_GetSpecificDectEntry(12345, '3');
echo $Entry['NewName'];
```

---

```php
string|false FB_GetDectListPath(integer $InstanzID);
```

Liefert den Pfad zur XML-Liste aller DECT-Geräte. Der Pfad enthält eine Session-ID und ist nur begrenzt gültig.  

```php
echo FB_GetDectListPath(12345); // /devicedectlist.lua?sid=…
```

---

```php
bool FB_DectDoUpdate(integer $InstanzID, string $ID);
```

Startet die Firmware-Aktualisierung eines DECT-Gerätes. Liefert `true` bei Erfolg.  
*Nicht live getestet.*  

> [!CAUTION]
> Das Gerät installiert die neue Firmware und ist dabei nicht erreichbar.

```php
FB_DectDoUpdate(12345, '3');
```

## 8. Aktionen

Keine Aktionen verfügbar.

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#2-changelog)

### 2. Spenden

Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)  

## 10. Lizenz

  IPS-Modul:  
  [Custom NC-SA](../LICENSE)  
