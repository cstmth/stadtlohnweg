# Stadtlohnweg Waschliste

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
- **Datenschutz:** Abgelaufene Reservierungen werden nach **einem Tag** automatisch gelöscht.
  Nutzerkonten werden nach **zwei Jahren Inaktivität** automatisch entfernt.
  PINs und Passwörter werden verschlüsselt gespeichert.

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

## OAuth-Login (Google)

Login per Google ist über [Laravel Socialite](https://laravel.com/docs/socialite) angebunden.
Ohne Credentials wird der Button nicht angezeigt.

1. Öffne die [Google Cloud Console](https://console.cloud.google.com/).
2. Erstelle ein Projekt (oder wähle ein bestehendes).
3. Navigiere zu **APIs & Dienste → Anmeldedaten → Anmeldedaten erstellen → OAuth-Client-ID**.
4. Wähle Anwendungstyp **Webanwendung**.
5. Füge unter **Autorisierte Weiterleitungs-URIs** hinzu:
   ```
   https://deine-domain.de/auth/google/callback
   ```
   Lokal: `http://localhost:8000/auth/google/callback`
6. Kopiere **Client-ID** und **Clientschlüssel** in die `.env`:
   ```
   GOOGLE_CLIENT_ID=123456789-xxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-xxx
   ```

> Beim ersten Mal muss unter **OAuth-Zustimmungsbildschirm** noch App-Name, E-Mail und Scopes
> (`email`, `profile`, `openid`) konfiguriert werden. Solange die App im Status „Test" ist,
> können nur explizit eingetragene Testnutzer sich anmelden.

## Automatische Löschung (DSGVO)

Zwei Befehle laufen automatisch über den Scheduler (`routes/console.php`):

| Befehl | Intervall | Beschreibung |
|---|---|---|
| `php artisan reservations:purge` | täglich 03:00 | Löscht Reservierungen, die älter als 1 Tag sind |
| `php artisan accounts:purge` | montags 03:30 | Löscht Nutzerkonten, die seit über 2 Jahren inaktiv sind |

Auf **Laravel Cloud** läuft der Scheduler automatisch — der Scheduler muss nur in den
Projekt-Einstellungen aktiviert sein.

## Tests

```bash
php artisan test
```

Zentrale fachliche Konstanten (Blöcke, Geräte, Zeiten, Aufbewahrungsfrist, Vorlauf)
liegen gebündelt in `app/Models/Reservation.php`.
