# Checkout-Manager → Dynamic Forms – Migrations-Blueprint

> **Zweck:** Dieses Dokument beschreibt den schrittweisen Umbau der Checkout-Steps von
> `local_shopping_cart` vom heutigen Custom-Mechanismus (Input-Scraping + eigener Webservice
> `control_checkout_process`) auf `core_form\dynamic_form` (Moodle AJAX-Forms-API).
> Es ist ein **Planungsdokument** — es wird nichts umgebaut, bevor die offenen Fragen (§8)
> geklärt sind und das Behat-Netz (Phase 0) steht.
>
> Anlass: Bug-Cluster vom 2026-06-11 (Gast-Checkout): stumme Validierungsfehler,
> Datenshape-Drift zwischen zwei `get_template_render_data()`-Implementierungen,
> Input-Scraping-Sonderfälle. Alle drei Bug-Klassen sind strukturelle Folgen des
> Custom-Mechanismus und wären mit dynamic_forms nicht möglich gewesen.

---

## 1. Ist-Architektur (Stand 2026-06)

### 1.1 Beteiligte

| Baustein | Datei | Rolle |
|---|---|---|
| Manager | `classes/local/checkout_process/checkout_manager.php` | Orchestriert Steps, hält Validierungs-Buchhaltung |
| Step-Basisklasse | `classes/local/checkout_process/checkout_base_item.php` | Contract: `render_body()`, `check_status()`, `is_active()`, `is_mandatory()`, `is_head()`, `get_*_feedback()` |
| Steps | `items/addresses.php`, `items/vatnrchecker.php`, `items/termsandconditions.php`, `items/shopping_cart_credits.php` | Per `glob()` entdeckt, via `get_ordernumber()` sortiert |
| Webservice | `classes/external/control_checkout_process.php` | Einziger Transport für alle Step-Interaktionen |
| JS | `amd/src/checkout_manager.js` | Input-Scraping + Partial-Re-Render |
| State | MUC-Cache `cachebookingpreprocess` (**MODE_SESSION**, Key = userid) | `steps[<step>][data/valid/mandatory]`, `viewed`, `body_mandatory_count`, `checkout_validation` |

### 1.2 Datenfluss heute

1. `render_body()` liefert fertiges Mustache-HTML; Eingabefelder tragen
   `data-shopping-cart-process-data="true"`.
2. `checkout_manager.js → getChangedInputs()` kratzt **alle** so markierten Inputs zusammen
   (inkl. Sonderbehandlung: unchecked Radios → `[]`, Checkbox-`value`-Mutation) und schickt sie
   als JSON-String an `control_checkout_process`.
3. `check_preprocess()` ruft `check_status($cachedstep, $changedinputJSON)` des Steps am
   aktuellen `currentstep`-Index auf; der Step parst das JSON selbst, merged in seine
   Cache-Daten und liefert `['data','mandatory','valid']`.
4. Checkout-Button: `get_checkout_validation()` = alle mandatory Steps valid **und**
   `body_count <= count(viewed)` (jeder Body-Step muss einmal gerendert worden sein).
5. Feedback: `get_error_feedback()`/`get_validation_feedback()` (statisch, pro Step) →
   Alert-Container; `get_info_feedback()` → permanenter Info-Alert.

### 1.3 Bekannte Schwächen (belegte Bug-Klassen)

- **Stumme Validierung:** `check_status()` kann `valid=false` liefern, ohne dass irgendetwas
  beim User ankommt (Gast-E-Mail-Duplikat, 2026-06-11). Es gibt keinen Per-Feld-Fehlerkanal —
  Fehler müssen manuell durch Step-Code, Cache, Template und JS gefädelt werden.
- **Datenshape-Drift:** `render_body()`-Templates und ihre Datenlieferanten sind nur per
  Konvention gekoppelt (`address.mustache` vs. zwei verschiedene
  `get_template_render_data()`-Implementierungen).
- **Input-Scraping:** Das JS kennt die Semantik der Felder nicht; Radios/Checkboxen brauchen
  Sonderfälle; neue Feldtypen (Editor, Filepicker, Autocomplete) sind praktisch nicht anschließbar.
- **Zähler-Buchhaltung:** `viewed`/`body_mandatory_count` werden einmalig gecacht und können
  gegen die dynamische `is_active()`-Realität driften (z. B. VAT-Step erscheint/verschwindet
  per Checkbox).
- Kein sesskey-/Form-Token-Konzept im Custom-Transport (WS-Auth trägt das, aber Forms-API
  gäbe es geschenkt).

### 1.4 Was bereits richtig liegt

- `modal_new_address` und `delete_user_address` **sind** `dynamic_form`s und werden via
  `core_form/dynamicform` bzw. `core_form/modalform` inline gemountet — der Integrationsbeweis
  im eigenen Code.
- `cartstore` + Session-Cache als State-Backend funktionieren und bleiben unangetastet.
- Gast-Flows (observer `user_loggedin`/`checkout_completed`, `migrate_checkout_cache`) hängen
  nur am Cache-Inhalt, nicht am Transport.

---

## 2. Ziel-Architektur

### 2.1 Grundidee: Wizard aus dynamic_forms

Jeder **Body-Step** wird eine `dynamic_form`-Subklasse. Der Manager orchestriert nur noch:
welcher Step ist aktiv, Progress-Bar, Step-Wechsel. Validierung, Fehlerdarstellung und
Persistenz übernimmt die Forms-API.

```
classes/local/checkout_process/
├── checkout_manager.php          (verschlankt: Stepliste, Progress, checkout_allowed)
├── checkout_step_form.php        (NEU: abstrakte Basis, extends core_form\dynamic_form)
└── steps/
    ├── addresses_form.php        (Gast-Registrierung + Adresswahl)
    ├── vatnrchecker_form.php
    └── termsandconditions_form.php
```

`shopping_cart_credits` bleibt Head-/Display-Item ohne Form (rein darstellend).

### 2.2 Basisklasse `checkout_step_form`

Gemeinsames Verhalten, damit die Steps dünn bleiben:

```php
abstract class checkout_step_form extends dynamic_form {
    // Contract analog checkout_base_item:
    abstract public static function get_ordernumber(): int;
    abstract public static function is_active(array $managercache): bool;
    public static function is_mandatory(): bool { return true; }
    abstract public static function get_icon_progress_bar(): string;

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();          // wie control_checkout_process heute
    }
    protected function check_access_for_dynamic_submission(): void {
        require_login();                             // Gast-Checkout-User sind echte User
    }
    public function set_data_for_dynamic_submission(): void {
        // Defaults aus checkout_manager::get_cache($USER->id)['steps'][static::step_key()]['data']
    }
    public function process_dynamic_submission() {
        // 1. validierte Daten in den Step-Cache schreiben, valid=true setzen
        // 2. Rückgabe an JS: ['nextstep' => …, 'progress' => …, 'checkout_allowed' => …]
    }
}
```

Entscheidend: **`validation()` ist der einzige Validierungsort.** Per-Feld-Fehler
(`$errors['guest_email'] = …`) rendert die Forms-API automatisch am Element — die gesamte
Fehler-Fädelung über `$lasterrors`/`guest_email_error`/Alert-Container aus dem heutigen
Code entfällt ersatzlos.

### 2.3 JS danach

`checkout_manager.js` schrumpft auf:

1. Aktiven Step mounten: `new DynamicForm(container, stepFormClass)` —
   `core_form/dynamicform` lädt das Form-HTML inkl. JS-Init selbst (kein eigener
   Render-Roundtrip mehr).
2. `FORM_SUBMITTED`-Event → Antwort enthält `nextstep`/`progress`/`checkout_allowed` →
   nächsten Step mounten, Progress-Bar + Checkout-Button-Partial neu rendern.
3. Progress-Bar-Klicks: Step-Index setzen, Form des Ziel-Steps mounten (nur rückwärts oder
   auf bereits valide Steps — erzwingt die Reihenfolge, die heute der `viewed`-Zähler
   approximiert).

`getChangedInputs()`, die `data-shopping-cart-process-data`-Konvention und die
Radio-/Checkbox-Sonderfälle entfallen. `control_checkout_process` bleibt nur noch für
Navigation/Render nicht-migrierter Steps (Hybrid, §3) und wird in Phase 4 entfernt oder
auf ein Minimal-API (Progress/Button-State) reduziert.

### 2.4 Checkout-Button-Logik

`checkout_validation` wird trivial: **alle aktiven mandatory Steps haben `valid=true` im
Cache** (gesetzt ausschließlich durch erfolgreiche `process_dynamic_submission`).
`viewed` und `body_mandatory_count` entfallen — der Wizard erzwingt, dass man nur per
gültigem Submit weiterkommt.

⚠️ **Verhaltensänderung:** Heute aktiviert sich der Checkout-Button „live" beim letzten
gültigen Input, ohne expliziten Klick. Im Wizard-Modell gibt es stattdessen pro Step einen
expliziten Submit („Weiter" bzw. beim letzten Step direkt der Checkout-Button, der vorher
die Step-Form submittet). Bei Ein-Step-Konfigurationen (häufigster Fall: nur Billing-Adresse)
kann der Submit mit dem Checkout-Klick verkettet werden (`dynamicform.submitFormAjax()` →
bei Erfolg Payment-Modal öffnen). → Offene Frage O1.

### 2.5 Der Addresses-/Gast-Step (der harte Teil)

Aufteilung des heutigen Mischmasch-Steps:

- **mform-Teil** (`addresses_form`): `guest_firstname/lastname/email` (Text-Elemente,
  Pflicht), Adressauswahl als Radio-Gruppe pro `addresskey`.
  Die E-Mail-Duplikat-Prüfung (`get_guest_registration_error()`) wandert 1:1 nach
  `validation()` und erscheint damit automatisch **am Feld**.
- **Karten-Optik der Adress-Radios:** Standard-mform-Radios bekommen die Karten-Darstellung
  über ein überschriebenes Element-Template
  (`$mform->getElement(...)->setTemplate()` bzw. Renderer-Override
  `core_form/element-radio` im Plugin-Scope). Fallback-Entscheidung: akzeptieren wir
  schlichtere Optik zugunsten von Standard-Elementen? → Offene Frage O2.
- **Bleibt außerhalb der Form** (Mustache neben dem Form-Container, wie heute):
  Login-Panel + SSO-Buttons (postet eh gegen `login/index.php`), Tab-Leiste
  Registrieren/Login, Inline-Adress-Anlage (`modal_new_address` bleibt unverändert;
  ihr `FORM_SUBMITTED` triggert ein `set_data`-Reload der Step-Form statt des heutigen
  Template-Redraws — damit entfällt auch `redrawRenderedAddresses()` samt
  Datenshape-Kopplung).
- **VAT-Step:** Der VIES-Check (heute eigener Verify-Button-Roundtrip) wird serverseitige
  `validation()` beim Submit. Der „voluntarily"-Toggle, der den Step ein-/ausblendet,
  wird ein normales Form-Element des vorherigen Steps, das `is_active()` des VAT-Steps
  über den Cache steuert (wie heute, nur ohne `changedinput`-String-Sniffing).

### 2.6 Was explizit NICHT angefasst wird

- `cartstore`, Cache-Definition `cachebookingpreprocess` (Key, MODE_SESSION) und damit die
  komplette Gast-Migration (`migrate_checkout_cache`, observer) — nur die **Producer** der
  Step-Daten ändern sich, das Datenformat `steps[<step>]['data']` bleibt.
- `checkout.php` (Seite), `checkout_manager_head`, Payment-Region/`gateways_modal`,
  `shopping_cart_credits`-Darstellung.
- Konsumenten von `checkout_manager::return_stored_addresses_for_user()` /
  `return_stored_vatnuber_country_code()` (lesen nur Cache).

---

## 3. Hybrid-Contract — der Schlüssel zur inkrementellen Migration

Der Manager unterstützt übergangsweise **beide** Step-Arten:

```php
// checkout_manager beim Rendern eines Steps:
if (is_subclass_of($classname, checkout_step_form::class)) {
    // NEU: nur Container + Form-Klassenname ausliefern, JS mountet DynamicForm.
    $body = ['formclass' => $classname, 'container' => true];
} else {
    // LEGACY: render_body()/check_status() wie bisher über control_checkout_process.
    $body = $iteminstance->render_body(...);
}
```

Damit ist **jeder Step einzeln migrier- und releasebar**, Legacy-Steps funktionieren
unverändert weiter, und ein Rollback ist pro Step trivial (Klasse zurücktauschen).

---

## 4. Migrationsplan (Phasen)

| Phase | Inhalt | Aufwand | Risiko | Releasebar |
|---|---|---|---|---|
| **0** | **Behat-Sicherheitsnetz**: Szenarien für (a) Checkout nur Billing-Adresse, (b) + Terms, (c) + VAT freiwillig/pflicht, (d) Gast-Checkout happy path, (e) Gast mit existierender E-Mail → Fehler sichtbar, (f) Credits/Zero-Price. Generator-Support für Cart-Items prüfen/ergänzen. | 2 Tage | — | ja (reiner Testcode) |
| **1** | Hybrid-Contract im Manager + Basisklasse `checkout_step_form` + **Pilot: Terms-Step** (eine Checkbox — minimale Fläche, validiert den ganzen Mechanismus inkl. Progress/Buttons/JS) | 1,5–2 Tage | niedrig | ja |
| **2** | **VAT-Step** (VIES in `validation()`, voluntarily-Toggle entkoppeln) | 1–1,5 Tage | mittel | ja |
| **3** | **Addresses-/Gast-Step** (Form + Element-Template für Karten + `modal_new_address`-Anbindung via `set_data`-Reload) | 2–3 Tage | mittel-hoch | ja |
| **4** | **Aufräumen**: Legacy-Pfad raus (`check_status`-Contract, `getChangedInputs`, `data-shopping-cart-process-data`, Alert-Fädelung, `viewed`-Zähler), `control_checkout_process` reduzieren/entfernen, `checkout_base_item` deprecaten, Doku | 1–2 Tage | niedrig (Netz aus Phase 0) | ja |

**Summe: ~7,5–10,5 Entwicklertage.** Zwischen den Phasen darf beliebig viel Zeit liegen —
der Hybrid-Modus ist ein stabiler Zwischenzustand.

---

## 5. Risiken & Gegenmaßnahmen

| Risiko | Einschätzung | Gegenmaßnahme |
|---|---|---|
| Regression auf dem Zahlungspfad | hoch im Schadensfall | Phase 0 zuerst; Hybrid = kleiner Blast-Radius pro Release |
| mform-Optik passt nicht zur Karten-UI | mittel | Element-Templates (O2) in Phase 3 als Spike vorziehen (½ Tag Prototyp vor Commitment) |
| Verlust des „Live-Enable" des Checkout-Buttons | UX-Entscheidung | O1 vorab mit Georg klären; Ein-Step-Kette als Default-Vorschlag |
| Session-Cache-Format ändert sich versehentlich | niedrig | Format eingefroren (§2.6); Gast-Migrations-Behat in Phase 0 |
| Externe Abhängige von `control_checkout_process` | klären | WS ist `ajax`-only registriert (nicht in einem externen Service gebündelt) — vermutlich nur Eigen-JS; vor Phase 4 verifizieren (O3). Kurios am Rande: deklarierte Capability ist `mod/booking:readresponses` und die Description stimmt nicht — beim Umbau korrigieren. |
| Theme-Kompatibilität (mehrere Kunden-Themes) | mittel | Pilot-Step (Phase 1) auf allen Ziel-Themes sichten |

---

## 6. Nicht-Ziele

- Kein Umbau von `cartstore`/Preislogik/Taxes/Installments.
- Keine Änderung der Gast-Konvertierung (`convert_guest_to_real_user`) — separater Punkt:
  E-Mail-Re-Validierung bei Konvertierung (Race), siehe Security-Notiz unten.
- Kein Redesign der Checkout-Optik; Ziel ist Verhaltens- und Optik-Parität.

---

## 7. Flankierende Findings (unabhängig vom Umbau sinnvoll)

1. `convert_guest_to_real_user()` re-validiert die E-Mail **nicht** zum Konvertierungszeitpunkt
   → theoretisches Duplikat-Race zwischen Registrierung und Checkout-Abschluss. Einzeiler:
   `get_guest_registration_error()` dort erneut aufrufen, bei Fehler nicht konvertieren.
2. Vorbestehend rote Tests `addresses_test::test_check_status` /
   `test_get_required_address_keys` (Semantik-Änderungen aus Commit 62f409a) — beim Umbau
   des Step-Contracts (Phase 3) ohnehin neu zu schreiben.
3. `checkout.php`: `identifier`-Fallback aus `$ME` ohne `clean_param`; leere Antwort im
   `jsononly`-Fehlpfad.

---

## 8. Offene Fragen (vor Phase 1 mit Georg klären)

- **O1 — Submit-UX:** Ein-Step-Konfiguration: Checkout-Button submittet Step-Form implizit
  (Vorschlag) — oder expliziter „Weiter"-Klick auch bei nur einem Step?
- **O2 — Adress-Karten:** Investition in mform-Element-Templates für die Karten-Radio-Optik,
  oder Standard-Radio-Liste akzeptieren?
- **O3 — `control_checkout_process`:** Gibt es externe Konsumenten (Apps, Kunden-Plugins)?
  Falls ja: Deprecation-Pfad statt Entfernung in Phase 4.
- **O4 — Zeitfenster:** Der Umbau sollte nicht parallel zu laufenden Gast-Checkout-Iterationen
  passieren; Start frühestens, wenn GH-187 stabil/released ist.
