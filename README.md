# Waschkeller Stadtlohnweg

Reservierungssystem für die Waschkeller des Studierendenwohnheims Stadtlohnweg (Münster).
Löst das bisherige Google-Sheets-System ab.

Gebaut mit **Laravel 13**, **Livewire 4**, **Flux UI**, **Tailwind** und **SQLite**.

## Funktionsumfang

- Öffentlicher **Belegungsplan** als Wochen-Kalender. Pro Tag drei Spalten:
  Maschine links, Maschine rechts, Trockner. Umschaltbar zwischen **A-Block** und **C-Block**.
- Reservierung von **07:00–22:00 Uhr** in **1-Stunden-Slots**, bis zu **einen Monat im Voraus**.
- Buchen **mit und ohne Konto**:
  - **Mit Konto:** Zimmernummer aus dem Profil, Verwaltung unter *Meine Reservierungen*.
  - **Ohne Konto:** 4-stellige **PIN** beim Buchen. Im **selben Browser** werden die
    Buchungen lokal gemerkt (grün markiert, PIN vorausgefüllt, eigener Tab *Meine
    Reservierungen*); von anderen Geräten/Browsern wird zum Stornieren die PIN oder
    ein Konto benötigt.
- Belegte Slots sind **öffentlich sichtbar** (Zimmernummer) und **können nicht überschrieben** werden.
- Reservierungen lassen sich nur **löschen**, nicht verschieben.
- **Datenschutz:** Abgelaufene Reservierungen werden nach **14 Tagen** automatisch gelöscht.
  Öffentlich sind nur zukünftige Slots (der heutige Tag bleibt ganztags sichtbar);
  eigene Reservierungen sind im Konto bis zu 14 Tage rückwirkend einsehbar.

### Zimmernummern-Schema (`aBBBcc`)

`A–D` + drei Ziffern + optional `.Ziffer` für WG-Parteien, z. B. `A115` oder `A115.2`.
Validiert über `App\Models\Reservation::ROOM_REGEX`.

## Lokale Entwicklung

```bash
composer install
npm install
php artisan migrate
npm run dev        # oder: npm run build
php artisan serve
```

## Automatische Löschung (DSGVO)

Der Befehl löscht Reservierungen, die älter als die Aufbewahrungsfrist sind:

```bash
php artisan reservations:purge
```

Er ist täglich um 03:00 Uhr eingeplant (`routes/console.php`). Damit der Scheduler läuft,
auf dem Server einen Cron-Eintrag anlegen:

```cron
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test
```

Zentrale fachliche Konstanten (Blöcke, Geräte, Zeiten, Aufbewahrungsfrist, Vorlauf)
liegen gebündelt in `app/Models/Reservation.php`.
