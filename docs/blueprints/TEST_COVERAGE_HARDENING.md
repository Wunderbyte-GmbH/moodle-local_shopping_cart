# Testlücken & Härtungsplan – `local_shopping_cart`

> **Zweck:** Dieses Dokument hält die Befunde des Test-Audits vom **2026-06-12** fest
> (PHPUnit + Behat) und plant die Schließung der Lücken. Es ist ein **Planungs- und
> Tracking-Dokument**: Die Checkboxen in §5 bilden den tatsächlichen Umsetzungsstand ab
> und werden beim Abarbeiten gepflegt.
>
> **Anlass:** Frage, ob die grüne Suite „echt" grün ist oder ob Tests nur bestehen, weil
> Assertions weichgespült wurden (z. B. ein „oder" in die Bedingung gehängt). Der Audit
> hat genau diese Sorte trügerischer Grünheit an einzelnen Stellen bestätigt — vor allem
> ausgerechnet in den **Steuer-Assertions**.
>
> **Methodik:** Vollständige Sichtung aller 22 PHPUnit-Testdateien (~5 480 LOC) und
> 19 Behat-Features (~2 689 LOC); die roten Funde wurden direkt am Code gegengeprüft.
>
> **Stand 2026-06-12:** Phasen A, B und der schließbare Teil von C umgesetzt. PHPUnit
> **124 Tests / 856 Assertions grün** (vorher 118/740). **Kein Code-Bug** durch die
> schärferen Tests aufgedeckt — die Funde waren ausschließlich Test-Schwächen. Offen:
> die Gateway-abhängigen Behat-e2e-Abschlüsse (strukturell, siehe Phase C) und C4
> (Georg-Entscheidung).

---

## 1. Gesamtbild

Die Suite ist **breiter und ehrlicher als befürchtet**. Die Tax-Funktionalität (Reverse
Charge bei gültiger Auslands-UID) ist in **Behat** sauber in beide Richtungen abgesichert.
Auf **PHPUnit**-Seite gibt es jedoch an wenigen, aber wichtigen Stellen Assertions, die
deutlich schwächer sind, als sie aussehen — und sie sitzen in den Steuer-Helfern.

| Bereich | Bewertung |
|---|---|
| Preis-/Netto-/Brutto-/Tax-Berechnung (`cartstore`, `taxcategories`) | ✅ scharf (exakte Beträge) |
| Credits (Add/Refund/Costcenter) | ✅ scharf (Restguthaben exakt auf 0) |
| Stornos – *dass* storniert wird (Identifier/Status) | ✅ solide |
| Stornos – *erstatteter Betrag* / Storno-Gebühr > 0 (PHPUnit) | ⚠️ Lücke |
| Steuer „fällt an" (`assertcartstoretax`) | ✅ scharf (`assertGreaterThan(0, …)`) |
| Steuer „entfällt" (Reverse Charge, `assertcartstoretaxnull`) | 🔴 stumpf (`(int)`-Cast) |
| Exakte Steuer-/Preistabelle (`assertcartstoreexacttax`) | 🔴 key-blind (`array_diff`) |
| `vatnrchecker::is_active` / `is_checkout_allowed` TRUE-Pfad | 🔴 ungetestet (mislabeled) |
| `checkout_manager`-Interna | ⚠️ nur Smoke-Tests |
| Installment end-to-end (Persistenz/Ledger) | ⚠️ Lücke (PHPUnit entwertet, Behat nur Vorschau) |
| Gast-Checkout bis bezahlter Abschluss | ⚠️ Lücke (Behat endet bei Button-Aktivierung) |
| Behat: Cashier, Credits, Consumption, VAT | ✅ stark (Aktion → Zustand → Ledger) |

---

## 2. Rote Funde – schwache/irreführende Assertions (verifiziert)

### 2.1 `assertcartstoreexacttax` ist key-blind und einseitig
**Datei:** `tests/checkout_process_test_setup.php:330-343`
```php
$arrdiff = array_diff($expectedtax[$row], (array) $historyrecord); // For debugging.
$this->assertEmpty($arrdiff);
```
`array_diff` vergleicht **nur Werte** (als Strings, lose) und **einseitig**: Der Test ist
grün, sobald jeder erwartete Wert *irgendwo* im History-Record als Wert vorkommt — egal
unter welcher Spalte.
- **Fängt:** fehlende Werte, komplett falsche Werte, falsche Zeilenzahl (das `assertCount`
  davor ist echt und scharf).
- **Fängt nicht:** vertauschte Spalten (`tax` ↔ `taxpercentage`: beide Werte sind „irgendwo"
  vorhanden → grün), lose Typ-/Stringgleichheit (`1.58` == `"1.580"`).
- Der Kommentar `// For debugging.` belegt, dass dies nie als echte Assertion gedacht war.

**Auswirkung:** Entwertet die komplette **Installment-Steuertabelle** in
`checkout_installment_test::test_checkout_process` — die Ratenbeträge *sehen* geprüft aus,
sind aber nicht spaltengenau abgesichert.

**Fix:** Key-weiser Vergleich statt `array_diff`:
```php
foreach ($expectedtax[$row] as $key => $expected) {
    $this->assertEqualsWithDelta((float)$expected, (float)$historyrecord->$key, 0.001,
        "assertcartstoreexacttax row $row, field $key");
}
```

### 2.2 Reverse-Charge-Nullprüfung stumpf durch `(int)`-Cast
**Datei:** `tests/checkout_process_test_setup.php:350-356`
```php
$this->assertEquals((int)$historyrecord->taxpercentage, 0, 'assertcartstoretaxnull_taxpercentage');
$this->assertEquals((int)$historyrecord->tax, 0, 'assertcartstoretaxnull_tax');
```
Dies ist **die** zentrale Assertion für „gültige Auslands-UID → Steuer entfällt". Der
`(int)`-Cast verschluckt jede Reststeuer < 1.0 (`(int)0.49 === 0`, `(int)0.99 === 0`). Ein
voller falscher Satz (z. B. 2.0) fliegt noch auf, sub-1.0-Reste nicht.

**Fix:** `$this->assertEqualsWithDelta(0.0, (float)$historyrecord->tax, 0.001, …);`

### 2.3 `is_active` / `is_checkout_allowed`-Tests prüfen das Gegenteil ihrer Aussage
- `tests/checkout_process/items/vatnrchecker_test.php:66` — Config so gesetzt, dass
  „conditions are met", Message „Expected … return true", assertiert aber `assertFalse`.
  **Selbstwidersprüchlich**; der TRUE-Aktivierungspfad (`onlywithvatnrnumber=1` /
  Voluntarily-Flag) wird nie getestet.
- `tests/checkout_process/checkout_manager_test.php:152` — `is_checkout_allowed`, Message
  „should return true", assertiert `assertFalse`; die `viewed`-Kernbedingung wird nie grün
  getestet.
- `tests/classes/vatnumbervoluntarily_test.php:49-87` — 6 Assertions, alle mit derselben
  „return true"-Message, davon 3 `assertFalse` (Verhalten korrekt, Messages Copy-Paste).

**Fix:** Je einen echten TRUE-Pfad-Fall ergänzen (`assertTrue`) und einen FALSE-Pfad-Fall
mit korrekter Message.

---

## 3. Gelbe Funde – Smoke statt Verifikation (niedrigere Priorität)

| # | Datei:Zeile | Problem |
|---|---|---|
| 3.1 | `tests/checkout_process/checkout_manager_test.php:70-86` (`test_render_overview`) | nur `assertIsArray` + Key-Existenz, kein Inhalt |
| 3.2 | `…/checkout_manager_test.php:91-112` (`test_set_manager_data`, :111) | `assertIsArray($…['item_list'] ?? [])` → `?? []` macht Assertion auch ohne Daten grün |
| 3.3 | `…/checkout_manager_test.php:117-132` (`test_get_checkout_validation`) | ruft Validierung, prüft nur, dass `render_checkout_button()` *irgendeinen* Bool liefert (≈ tautologisch) |
| 3.4 | `…/checkout_manager_test.php:167-180` (`test_cache_interaction`) | `get_cache()` legt bei Miss selbst `[]` an → `assertIsArray` immer wahr |
| 3.5 | `tests/checkout_installment_test.php:307` (`test_installment_downpayment`) | `assertTrue(!array_key_exists(…) || empty(…))` — Tautologie-`||` |
| 3.6 | `tests/cartitem_test.php:129-132` (`test_as_array_contains_all_fields`) | prüft Key-Existenz per Reflection aus derselben Klasse (linke=rechte Quelle), keine Werte |
| 3.7 | `tests/shopping_cart_cache_test.php:592` (u. a.) | `assertCount(1,…)` korrekt, aber Message „two"/„no" — irreführendes Copy-Paste |
| 3.8 | `tests/checkout_process/items/shopping_cart_credits_test.php:86-99` (`test_render_body`) | nur Typ-Prüfung; übergebene `credits`/`currency` tauchen in keiner Assertion auf |

---

## 4. Coverage-Lücken (fehlende Tests)

- **L1 – Installment end-to-end:** PHPUnit-Tabelle durch §2.1 entwertet, Behat nur
  Preis-Vorschau (`shopping_cart_cashier_installment.feature:34` bricht vor Checkout ab).
  Es gibt **keinen durchgeführten Ratenkauf** mit Ledger für Anzahlung + Folgeraten; auch
  Fälligkeits-/Mahnungslogik (`reminderdaysbefore`, `timebetweenpayments`) wird nur gesetzt,
  nicht verifiziert.
- **L2 – Gast-Checkout bis bezahlter Abschluss:** `shopping_cart_guest_checkout.feature`
  endet bei Button-Aktivierung/Validierung — **kein bezahlter Gast-Kauf**, kein Ledger-/
  Enrolment-Nachweis für den auto-erstellten Gast und keine Verifikation der
  Gast→Vollnutzer-Konvertierung (`observer::checkout_completed`).
- **L3 – Storno-Geldbetrag:** Cancel-Test prüft *dass* storniert wurde, nicht den
  **erstatteten Betrag** ins Guthaben; Storno mit Gebühr > 0 in PHPUnit ungetestet
  (nur Cashier-Behat).
- **L4 – `checkout_manager`-Interna:** `render_overview`, `set_manager_data`, `set_cache`,
  `get_checkout_validation` nur als Smoke-Tests (§3.1–3.4).
- **L5 – Exakter Reverse-Charge-Betrag = 0** über `assertcartstoreexacttax` (nach §2.1-Fix)
  und **mixed-country tax** (Items mit unterschiedlichem `taxcountrycode` im selben Cart)
  auf Checkout-Ebene.
- **L6 – Steuer komplett aus (`enabletax=0`):** kein dedizierter Test.

---

## 5. Implementierungsplan (Checkboxen = realer Stand)

> Reihenfolge nach Aufwand/Wirkung. Jede Phase wird mit `--testsuite
> local_shopping_cart_testsuite` (PHPUnit) bzw. dem Behat-Gate abgeschlossen.
> **Wichtig:** Nach dem Härten von §2.1/§2.2 müssen die bestehenden Tax-Tests **weiterhin
> grün** sein — falls nicht, deckt der schärfere Test einen echten Bug auf (dann Bug
> dokumentieren, nicht Assertion zurückdrehen).

### Phase A – Schwache Assertions härten (bestehende Tests, kein neuer Flow) ✅ ABGESCHLOSSEN 2026-06-12

> PHPUnit nach Phase A: **118 Tests grün, 809 Assertions** (vorher 740). Keine der
> geschärften Assertions hat einen Code-Bug aufgedeckt — alle bestehenden Tax-/Logikpfade
> sind spaltentreu korrekt. **Kein Produktivcode geändert.**

- [x] **A1** `assertcartstoreexacttax` (§2.1): `array_diff` durch key-weisen, typbewussten
  Vergleich ersetzt (`assertEqualsWithDelta` für numerische Felder, `assertEquals` für
  String-Felder wie `taxcategory`). Input-only-Marker (`useinstallment`/`useinstallments`),
  die keine History-Spalten sind, werden bewusst übersprungen.
  `tests/checkout_process_test_setup.php:337-360`.
- [x] **A2** `assertcartstoretaxnull` (§2.2): `(int)`-Cast → `assertEqualsWithDelta(0.0,
  (float)…, 0.001)`. Reststeuer < 1.0 fällt jetzt auf. `…test_setup.php:368-371`.
- [x] **A3** `vatnrchecker_test::test_is_active` (§2.3): TRUE-Pfad (`onlywithvatnrnumber=1`
  → `assertTrue`) + zwei FALSE-Pfade mit korrekten Messages.
- [x] **A4** `checkout_manager_test::test_is_checkout_allowed` (§2.3): TRUE-Pfad
  (`is_checkout_allowed(1,1,0)`) + zwei FALSE-Pfade, Messages korrigiert.
- [x] **A5** `vatnumbervoluntarily_test::test_is_active` (§2.3): alle Assertion-Messages an
  die jeweilige Erwartung (true/false) angeglichen.
- [x] **A6** Gelbe Funde: §3.2 (`?? []` raus → `currentstep`/`show_progress_line` geprüft),
  §3.4 (`body_mandatory_count`-Inhalt statt `assertIsArray`), §3.5 (`assertEmpty` statt
  Tautologie-`||`), §3.7 (Messages „two"/„no" → „one").

### Phase B – Fehlende PHPUnit-Tests — ABGESCHLOSSEN 2026-06-12

> Kein Code-Bug aufgedeckt. Drei Plan-Korrekturen (durch die Tests gelernt): B3 ist durch
> A2 bereits erfüllt; B4 ist architektonisch nicht anwendbar; B6 ist durch A6 + die
> Integrationsabdeckung in `checkout_process_test` abgedeckt. Details unten.

- [x] **B1 (L3)** Storno-Rückerstattungsbetrag: neuer `test_self_cancel_refunds_full_price`
  in `shopping_cart_buy_and_cancel_test.php` — **Selbst-Storno** (Area `main`, vom Provider
  als kündbar markiert) ohne Gebühr → volles Guthaben = Preis, Ledger `credits`=Preis,
  `fee`=0. *(Lernen: Cashier-Storno für einen Fremduser mit `credit=0` refundet by design
  0 — der automatische Refund greift nur beim Selbst-Storno.)*
- [x] **B2 (L3)** Storno **mit Gebühr > 0**: neuer `test_self_cancel_with_fee_refunds_price_minus_fee`
  — `cancelationfee=3`, Selbst-Storno → Guthaben = Preis − 3, Ledger `fee`=3, `credits`=netto.
- [x] **B3 (L5)** Reverse-Charge **exakter Betrag**: *durch A2 abgedeckt* — der gehärtete
  `assertcartstoretaxnull` prüft jetzt `tax≈0.0` **und** `taxpercentage≈0.0` per
  float-delta spaltengenau pro Record (Szenario „Only vatnumber mandatory, valid").
  Ein zusätzliches `assertcartstoreexacttax` bot nur marginalen Mehrwert (Preis) bei
  Risiko des Wert-Ratens → bewusst weggelassen.
- [x] **B4 (L5)** Mixed-Country-Tax: **architektonisch nicht anwendbar** — `taxcountrycode`
  ist ein **Cart-weiter** Wert (`cartstore::local_shopping_cart_save_address_in_cache` setzt
  ihn einmal aus der Billing-Adresse/VAT-Country), nicht pro Item. Was pro Item variiert ist
  die tax*Kategorie* (A/B/C) → bereits in `checkout_process_test` Szenario 1/2 spaltengenau
  abgedeckt; länderabhängige Sätze in Behat `taxes_VAT_addresses`. Kein künstlicher Test gebaut.
- [x] **B5 (L6)** `enabletax=0`: neues Provider-Szenario „Tax processing disabled, no tax
  on any item" (shipping-Adresse, `enabletax=0`) → `assertcartstoretaxnull`. Grün, kein
  Bug. `tests/checkout_process_test.php`.
- [x] **B6 (L4)** `checkout_manager`-Interna: **durch A6 + Integrationsabdeckung erfüllt**.
  A6 prüft `set_manager_data` (currentstep/show_progress_line) und `set_cache`
  (body_mandatory_count-Inhalt) deterministisch. `render_overview` + `get_checkout_validation`
  werden in `checkout_process_test` mit echtem Step-Setup end-to-end ausgeführt
  (`render_overview()` Z. 111, `submit_step` → `get_checkout_validation`), gegen die
  geschärften `assertvalidcheckout`/`assertinvalidcheckout`-Helfer. Ein zusätzlicher
  Unit-Test ohne Step-Setup wäre config-abhängig und nicht deterministisch → bewusst
  weggelassen.
- [x] **B7 (L1)** Installment-Persistenz: `checkout_installment_test::test_checkout_process`
  um den **fully-settled End-State** erweitert — nach der finalen Rate
  `get_open_installments()==0` **und** `get_due_installments()==0`. (Die Raten-Beträge sind
  durch A1 jetzt spaltengenau abgesichert; Verlauf der offenen Raten 3→2→0 ist verifiziert.)
  *(Lernen: Plan ist downpayment + 2 Raten, nicht 3.)*

### Phase C – Fehlende Behat-Flows (end-to-end)

> **Zentrale Erkenntnis:** Alle drei e2e-Abschluss-Lücken (C1/C2/C3) hängen an **derselben
> Infrastruktur-Grenze**: Behat kann einen Checkout nur abschließen, wenn der Restbetrag
> durch Credits oder Cashier-Cash **0** wird. Es gibt **keinen Gateway-Mock** — kein
> bestehender Behat-Test durchläuft eine echte Bezahlung (alle nutzen Credits→0 oder
> Cashier-Cash). Die abschließbaren Flows sind dadurch bereits stark abgedeckt (Cashier,
> Credits, Costcenter-Abschluss `costcenter_credit_manager:543-573`, Stornos, VAT). Die
> offenen Lücken sind genau die, die einen externen Gateway bzw. einen Restbetrag > 0
> brauchen. Statt Schein-Behat-Tests zu bauen, ist der schließbare Kernteil auf
> Unit-Ebene abgedeckt.

- [x] **C1 (L2)** Gast→Vollnutzer-Konvertierung: **als PHPUnit gelöst** statt Behat. Neue
  `tests/guestcheckout_test.php` deckt `create_guest_user` / `is_guest_checkout_user` /
  `convert_guest_to_real_user` ab (Registrierungsdaten übernommen, `auth='manual'`,
  Username `checkout_<email>`, Pending-Guest-Record gelöscht, Nicht-Gast nicht
  konvertierbar). Der **bezahlte** Gast-Abschluss bleibt Behat-seitig offen: ein Gast hat
  keine Credits und keinen Cashier-Kontext, die Gast-Zahlung läuft nur über den externen
  Gateway → in Behat nicht abschließbar. *(Eine harmlose CLI-Warnung aus
  `complete_user_login` ist inhärent zu `create_guest_user`.)*
- [~] **C2 (L1)** Ratenkauf: **Persistenz ist via B7 (PHPUnit) abgedeckt** (Verlauf der
  offenen Raten 3→2→0, Beträge spaltengenau durch A1). Der Behat-UI-Abschluss eines
  Ratenkaufs braucht eine Anzahlungs-Zahlung > 0 → Gateway-blockiert. Offen bleibt nur die
  reine UI-Verifikation des Abschlusses.
- [~] **C3** Costcenter-Abschluss ist bereits abgedeckt (`costcenter_credit_manager`
  Szenario mit „Payment successful" + Ledger, Z. 543-573). Das letzte Szenario (Z. 576)
  ist **absichtlich** ein Nicht-Abschluss-Fall (Restpreis 5.10 EUR bei zu wenig Guthaben);
  ein Abschluss bräuchte Gateway/Cashier-Cash und würde die Testabsicht verfälschen → nicht
  erzwungen.
- [ ] **C4** `shopping_cart_debug.feature` (laut Kommentar Z. 31 zur Löschung vorgesehen):
  **Georg-Entscheidung** — Löschen einer fremden Datei nicht eigenmächtig. Vorschlag:
  entfernen oder in echten Feature-Test überführen.

### Phase D – Abschluss

- [x] **D1** Voller PHPUnit-Lauf grün (`--testsuite local_shopping_cart_testsuite`):
  **124 Tests / 856 Assertions**.
- [x] **D2** Behat unverändert grün — Phase A/B/C haben **keine** Behat-Features geändert
  (Phase C wurde auf PHPUnit umgeleitet bzw. als Gateway-blockiert dokumentiert), daher
  bleibt der zuletzt grüne Stand (55 Szenarien) gültig. Kein neuer Behat-Lauf nötig.
- [x] **D3** Dokument mit Endstand aktualisiert (dieses Update).

---

## 6. Prüfkriterien für „echt grün"

Ein gehärteter Test gilt erst als fertig, wenn er **auch rot werden kann**:
1. Nach jeder Assertion-Härtung (§2) einmal kurz einen falschen Erwartungswert einsetzen und
   bestätigen, dass der Test fehlschlägt — dann zurücksetzen. So ist belegt, dass die
   Assertion greift und nicht tautologisch ist.
2. Keine `array_diff`-, `?? default`-, `(int)`-Weichspüler in Geld-/Steuer-Assertions.
3. Jede `foreach`-Assertion über eine Datenmenge braucht davor ein `assertNotEmpty`/
   `assertCount`, das die Nicht-Leere garantiert (sonst läuft die Assertion nie).
4. Assertion-Message und tatsächliche Erwartung stimmen überein.

---

## 7. Referenz – Audit-Befunde nach Datei

| Datei | Status |
|---|---|
| `tests/checkout_process_test_setup.php` | 🔴 §2.1, §2.2 (Tax-Helfer härten) |
| `tests/checkout_process/items/vatnrchecker_test.php` | 🔴 §2.3 (`test_is_active`) |
| `tests/checkout_process/checkout_manager_test.php` | 🔴 §2.3 / ⚠️ §3.1–3.4 |
| `tests/classes/vatnumbervoluntarily_test.php` | ⚠️ §2.3 (Messages) |
| `tests/checkout_installment_test.php` | ⚠️ §3.5; entwertet durch §2.1 |
| `tests/cartitem_test.php` | ⚠️ §3.6 |
| `tests/shopping_cart_cache_test.php` | ⚠️ §3.7 (nur Message) |
| `tests/checkout_process/items/shopping_cart_credits_test.php` | ⚠️ §3.8 |
| `tests/taxcategories_test.php` | ✅ solide |
| `tests/cartstore_test.php`, `cartstore_mulitple_items_test.php` | ✅ solide |
| `tests/shopping_cart_credits_test.php` | ✅ solide |
| `tests/shopping_cart_buy_and_cancel_test.php` | ✅ solide (außer L3-Lücke) |
| `tests/shopping_cart_cache_test.php`, `expiration_date_test.php` | ✅ solide |
| Behat: `taxes_*`, `cashier*`, `credit_manager`, `consumption`, `user_checkcout` | ✅ stark |
| Behat: `guest_checkout` | ⚠️ L2 (kein bezahlter Abschluss) |
| Behat: `cashier_installment`, `costcenter_credit_manager:576` | ⚠️ L1/C3 (Vorschau-Abbruch) |
| Behat: `debug`, `taxes_settings`, `misc_settings` | ⚠️ Dummy/Roundtrip (C4) |
