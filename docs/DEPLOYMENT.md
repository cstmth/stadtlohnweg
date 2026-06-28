# Deployment Guide (Google Cloud Platform)

Diese Dokumentation beschreibt die Architektur und den Deployment-Prozess der "Stadtlohnweg Waschliste" auf der Google Cloud Platform (GCP).

## 1. Google Cloud Dienste (Architektur)

Die Anwendung ist als stateless Docker-Container konzipiert, nutzt jedoch native GCP-Dienste, um Daten dauerhaft zu speichern und Hintergrundaufgaben auszuführen.

### Cloud Run (Web Server)
Cloud Run ist das Herzstück der Anwendung. Hier läuft das Docker-Image (basierend auf FrankenPHP und Alpine Linux). Cloud Run skaliert bei Traffic hoch und bei Inaktivität auf 0 (Scale-to-Zero), was Kosten spart. Das bedeutet jedoch, dass der Container "stateless" ist – nach einem Neustart oder beim Herunterskalieren ist das lokale Dateisystem wieder im Urzustand.

### Cloud Storage (Datenbank Persistenz)
Da die App eine einfache SQLite-Datenbank verwendet, würde die Datenbank bei einem Container-Neustart auf Cloud Run gelöscht werden. Um dies zu verhindern, wird ein **Google Cloud Storage Bucket** genutzt.
Dieser Bucket wird über einen **Volume Mount (GCS FUSE)** beim Start des Cloud Run Services in den Container unter `/app/storage/database` eingehängt. Dadurch liest und schreibt der SQLite-Treiber direkt in den Cloud Storage Bucket, und die Daten bleiben persistent über alle Container-Neustarts hinweg.

### Cloud Scheduler (Cronjobs)
Cloud Run Container "schlafen" (die CPU wird gedrosselt), wenn kein Web-Traffic vorhanden ist. Daher funktioniert der native Laravel-Scheduler (`php artisan schedule:work`) im Hintergrund als Endlosprozess nicht zuverlässig.
Stattdessen nutzen wir den **Google Cloud Scheduler**, der jede Minute einen HTTP-GET-Request an eine geheime Route der App (`/tasks/run-scheduler`) sendet. Diese Route führt dann intern `Artisan::call('schedule:run')` aus.

---

## 2. Environment Variablen (Umgebungsvariablen)

Da die lokale `.env` Datei aus Sicherheitsgründen per `.dockerignore` nicht mit in das Docker-Image gebaut wird, müssen alle notwendigen Variablen im Google Cloud Run Service unter **"Environment variables"** gepflegt werden.

Eine Übersicht der für den Produktionsbetrieb nötigen Variablen findest du auch in der Vorlage `.env.server`:

| Variable | Beschreibung | Beispiel / Wert |
|---|---|---|
| `APP_NAME` | Name der Anwendung | `"Stadtlohnweg Waschliste"` |
| `APP_ENV` | Umgebung | `production` |
| `APP_KEY` | Laravel Verschlüsselungs-Key | `base64:...` *(Unbedingt setzen, sonst stürzt die App ab!)* |
| `APP_DEBUG` | Fehlermeldungen | `false` |
| `DB_CONNECTION` | Datenbank-Treiber | `sqlite` |
| `DB_DATABASE` | Exakter Pfad zum GCS Volume Mount | `/app/storage/database/database.sqlite` |
| `SESSION_DRIVER` | Treiber für Sessions | `cookie` *(Zwingend, da SQLite auf GCS zu langsam ist)* |
| `CACHE_STORE` | Treiber für Cache | `array` *(Zwingend, selbiger Grund wie Sessions)* |
| `SCHEDULER_TOKEN` | Geheimes Passwort für den Cronjob | `mein-geheimes-passwort-123` |
| `ARTISAN_TOKEN` | Geheimes Passwort für Artisan-Befehle | `artisan-geheim-456` |
| `GOOGLE_CLIENT_ID` | OAuth Google ID | `...` |
| `GOOGLE_CLIENT_SECRET`| OAuth Google Secret | `...` |

> [!WARNING]  
> Die Optionen `SESSION_DRIVER=cookie` und `CACHE_STORE=array` sind essenziell für die Performance! Belässt du diese auf `database` oder `file`, muss Laravel bei jedem Seitenaufruf Caches über das Netzwerk vom Cloud Storage lesen und schreiben, was die App extrem verlangsamt (Ladezeiten > 500ms).

---

## 3. Scheduling (Cronjobs einrichten)

Damit Laravel-Aufgaben wie das Aufräumen von alten Reservierungen zuverlässig laufen, muss der Google Cloud Scheduler eingerichtet werden.

1. Gehe in der Google Cloud Console zu **Cloud Scheduler**.
2. Erstelle einen neuen Job:
   - **Häufigkeit (Frequency):** `* * * * *` (Jede Minute)
   - **Zieltyp:** `HTTP`
   - **URL:** `https://deine-cloud-run-url.run.app/tasks/run-scheduler`
   - **HTTP-Methode:** `GET`
3. Erweitere die Einstellungen ("Mehr anzeigen") und setze einen **HTTP-Header**:
   - **Name:** `X-Scheduler-Token`
   - **Wert:** *(Muss exakt mit der Variable `SCHEDULER_TOKEN` aus deinen Cloud Run Environment Variables übereinstimmen!)*
4. Speichern.

Cloud Scheduler ruft nun minütlich die Route auf. Der Controller validiert den Header gegen das Token und führt bei Übereinstimmung die fälligen Laravel-Schedules aus.

---

## 4. Manuelle Artisan Befehle ausführen

Um administrative Befehle (wie das Leeren von Caches) auf dem produktiven Cloud Run Service auszuführen, existiert eine gesicherte HTTP-Route.

**URL-Format:**
`https://deine-cloud-run-url.run.app/tasks/artisan?token=DEIN_ARTISAN_TOKEN&command=BEFEHL`

**Beispiele (einfach im Browser aufrufen):**
- **Cache leeren:** `/tasks/artisan?token=artisan-geheim-456&command=cache:clear`
- **Views leeren:** `/tasks/artisan?token=artisan-geheim-456&command=view:clear`
- **Datenbank seeden:** `/tasks/artisan?token=artisan-geheim-456&command=db:seed`

*(Alternativ kann das Token auch als HTTP-Header `X-Artisan-Token` übergeben werden).*

---

## 5. Deployment-Ablauf (Schritt-für-Schritt)

1. **Bucket anlegen:** Einen Standard Cloud Storage Bucket in der gleichen Region wie Cloud Run erstellen.
2. **Code deployen:** Den Code auf GitHub pushen, sodass Cloud Build das neue Image baut.
3. **Cloud Run konfigurieren:**
   - Variablen gemäß `.env.server` eintragen.
   - Tab **Volumes** -> Neues Volume "Cloud Storage-Bucket" anlegen und den zuvor erstellten Bucket auswählen.
   - Tab **Container** -> "Volume bereitstellen" (Mount volume) anklicken.
   - Den Bereitstellungspfad (Mount Path) auf `/app/storage/database` setzen.
4. **Cloud Scheduler** wie oben in Punkt 3 beschrieben anlegen.
