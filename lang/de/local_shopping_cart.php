<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_shopping_cart
 * @category    string
 * @copyright   2021 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accepttermsandconditions'] = "Bestätigung der AGBs verlangen";
$string['accepttermsandconditions:description'] = "Ohne Häkchen bei den AGBs ist buchen nicht möglich.";
$string['accessdenied'] = 'Zugriff verweigert';
$string['accountid'] = 'Zahlungsanbieter-Konto';
$string['accountid:description'] =
        'Wählen Sie aus, über welchen Anbieter (Payment Account) die Zahlungen abgewickelt werden sollen.';
$string['adddiscounttoitem'] = 'Der Preis dieses Artikels kann entweder um einen absoluten Betrag oder einen Prozentwert reduziert werden,
    nicht aber um beides.';
$string['addedtocart'] = '{$a} wurde in den Warenkorb gelegt.';
$string['additonalcashiersection'] = 'Text für den Kassa-Bereich';
$string['additonalcashiersection:description'] = 'HTML Shortcodes oder Buchungsoptionen für den Kassabereich hinzufügen';
$string['addon'] = 'Zusätzliche Zeit festlegen';
$string['addon:description'] = 'Zeit, die zur Ablaufzeit hinzugefügt wird, nachdem der Checkout-Prozess gestartet wurde';
$string['addresses:addnew'] = 'Neue Adresse eingeben';
$string['addresses:billing'] = 'Rechnungsadresse';
$string['addresses:button'] = 'Weiter zur Adresseingabe';
$string['addresses:change'] = 'Adresse ändern';
$string['addresses:confirm:multiple'] = 'Ausgewählte Adressen verwenden';
$string['addresses:confirm:single'] = 'Ausgewählte Adresse verwenden';
$string['addresses:heading'] = 'Adresse';
$string['addresses:newaddress'] = 'Neue Adresse hinzufügen';
$string['addresses:newaddress:address2:label'] = 'Addresszusatz';
$string['addresses:newaddress:address2:placeholder'] = 'Addresszusatz (optional)';
$string['addresses:newaddress:address:error'] = 'Eine gültige Adresse ist erforderlich';
$string['addresses:newaddress:address:label'] = 'Adresse';
$string['addresses:newaddress:address:placeholder'] = 'Straße und Hausnummer / Stiege (optional) / Türnummer (optional)';
$string['addresses:newaddress:checkasdefault'] = 'Als meine standard Adresse setzen';
$string['addresses:newaddress:city:error'] = 'Bitte gib eine Stadt ein';
$string['addresses:newaddress:city:label'] = 'Stadt';
$string['addresses:newaddress:city:placeholder'] = 'Stadt';
$string['addresses:newaddress:name:error'] = 'Bitte gib einen Namen ein';
$string['addresses:newaddress:name:label'] = 'Name';
$string['addresses:newaddress:name:placeholder'] = 'Vollständiger Name (Vor- und Nachname)';
$string['addresses:newaddress:saved'] = 'Die Adresse wurde hinzugefügt';
$string['addresses:newaddress:state:choose'] = 'Wählen...';
$string['addresses:newaddress:state:error'] = 'Bitte wähle ein Land';
$string['addresses:newaddress:state:label'] = 'Land';
$string['addresses:newaddress:state:placeholder'] = 'Tippe um ein land auszuwählen';
$string['addresses:newaddress:submit'] = 'Hinzufügen';
$string['addresses:newaddress:zip:error'] = 'Ungültige Postleitzahl';
$string['addresses:newaddress:zip:label'] = 'PLZ';
$string['addresses:newaddress:zip:placeholder'] = 'Postleitzahl';
$string['addresses:pagetitle'] = 'Adresse';
$string['addresses:select'] = 'Wähle eine {$a}';
$string['addresses:selectionrequired'] = 'Bitte wähle eine Adresse um fortzufahren';
$string['addresses:shipping'] = 'Lieferadresse';
$string['addresses_required:desc'] = 'Adresse während dem Checkout abfragen';
$string['addresses_required:title'] = 'Adresseingabe aktivieren';
$string['addtocart'] = 'In den Warenkorb';
$string['allowchooseaccount'] = 'Erlaube andere Zahlungsanbieter';
$string['allowchooseaccount_desc'] = 'Bei jedem Artikel können individuelle und abweichende Anbieter angegeben werden.';
$string['allowinstallment'] = 'Erlaube Ratenzahlungen';
$string['allowinstallment_help'] = 'Bei Ratenzahlungen muss zuerst nur ein Teil des Gesamtbetrags überwiesen werden.';
$string['allowrebooking'] = 'Umbuchen erlauben';
$string['allowrebooking_desc'] = 'Dies ermöglicht es den Nutzer:innen, bereits gekaufte Items umzubuchen.
Die gekauften Items können mit negativem Preis in den Warenkorb gelegt werden und werden bei Umbuchung storniert,
gleichzeitig wird ein neues Item gebucht. Eine Umbuchung mit negativem Gesamtpreis ist nicht möglich.';
$string['allowrebookingcredit'] = 'Umbuchungsgutschrift';
$string['allowrebookingcredit_desc'] = 'Wenn Sie die Umbuchungsgutschrift aktivieren, bekommt ein:e Nutzer:in eine Gutschrift in Höhe der Buchungs- und Stornogebühr gutgeschrieben,
wenn er:sie innerhalb der Stornofrist ein Item storniert und ein anderes bucht.';
$string['alreadyincart'] = 'Das gewählte Item ist bereits im Warenkorb.';
$string['annotation'] = 'Anmerkung';
$string['annotation_rebook_desc'] = 'Geben Sie eine Anmerkung oder die OrderID der Zahlungstransaktion an, die Sie nachbuchen wollen.';
$string['applydiscount'] = 'Rabatt abziehen';
$string['applytocomponent'] = 'Stornierung an Artikel Plugin melden';
$string['applytocomponent_desc'] = 'Wird ein Artikel irrtümlich doppelt bezahlt, kann das Häkchen entfernt werden um hier zu stornieren, ohne dass die Käuferin aus z.B. dem Kurs ausgeschrieben wird.';
$string['at'] = "Österreich";
$string['backtohome'] = 'Zurück zur Überblicksseite.';
$string['baseurl'] = 'Basis-URL';
$string['baseurldesc'] = 'Geben Sie die Basis-URL für Ihre Rechnungsplattform ein.';
$string['be'] = "Belgien";
$string['bg'] = "Bulgarien";
$string['bookingfee'] = 'Buchungsgebühr';
$string['bookingfee_desc'] = 'Für jede Buchung wird eine Gebühr eingehoben, unabhängig davon, wieviele Artikel gekauft werden und wieiviel sie kosten.';
$string['bookingfeeonlyonce'] = 'Buchungsgebühr nur einmal einheben';
$string['bookingfeeonlyonce_desc'] = 'Die Buchungsgebühr wird nur einmal für jede Nutzer:in eingehoben. Sobald einmal bezahlt wurde, sind alle weiteren Buchungen ohne Buchungsgebühr.';
$string['bookingfeevariable'] = 'Variable Buchungsgebühr';
$string['bookingfeevariable_desc'] = 'Entsprechend der Kostenstelle, können unterschiedliche Buchungsgebühren angegeben werden.';
$string['cachedef_cachedcashreport'] = 'Kassajournal-Cache';
$string['cachedef_cacherebooking'] = 'Umbuchungs-Cache (Rebooking Cache)';
$string['cachedef_cacheshopping'] = 'Shopping Cache';
$string['cachedef_cashier'] = 'Kassier Cache';
$string['cachedef_schistory'] = 'Warenkorb-Cache (Shopping Cart History Cache)';
$string['calculateconsumation'] = 'Gutschrift bei Stornierung abzüglich konsumierter Menge.';
$string['calculateconsumation_desc'] = 'Bei Stornierung wird das Guthaben nach der bereits konsumierten Menge des gekauften Guts berechnet.';
$string['calculateconsumationfixedpercentage'] = 'FIXEN Prozentsatz verwenden statt konsumierte Menge anhand der bereits vergangenen Zeit zu berechnen';
$string['calculateconsumationfixedpercentage_desc'] = 'Wenn Sie hier einen Prozentsatz wählen, wird die konsumierte Menge nicht anhand der seit Kursbeginn
 verstrichenen Zeit berechnet, sondern IMMER mit demselben FIXEN Prozentsatz.';
$string['cancelationfee'] = 'Stornierungsgebühr';
$string['cancelationfee:description'] = 'Automatisch vom Guthaben abgezogene Gebühr bei einer Stornierung durch die/den KäuferIn.
                                        -1 bedeutet, dass Stornierung durch Userin nicht möglich ist.';
$string['canceldidntwork'] = 'Fehler beim Stornieren';
$string['canceled'] = 'Storniert';
$string['cancellationsettings'] = 'Stornierungseinstellungen';
$string['cancelpurchase'] = 'Kauf stornieren';
$string['cancelsuccess'] = 'Erfolgreich storniert';
$string['cartisempty'] = 'Ihr Warenkorb ist leer.';
$string['cartisfull'] = 'Ihr Warenkorb ist voll.';
$string['cash'] = 'Bargeld';
$string['cashier'] = 'Kassa';
$string['cashier_manualrebook'] = 'Manuelle Nachbuchung';
$string['cashier_manualrebook_desc'] = 'Manuelle Nachbuchung einer Zahlungstransaktion wurde durchgeführt.';
$string['cashiermanualrebook'] = 'Manuell nachbuchen mit Anmerkung oder TransaktionsID';
$string['cashout'] = 'Barzahlungen';
$string['cashoutamount'] = 'Barzahlungsbetrag';
$string['cashoutamount_desc'] = 'Negative Beträge sind Entnahmen, positive Beträge Einzahlungen.';
$string['cashoutnoamountgiven'] = 'Es können keine Nullbuchungen durchgeführt werden';
$string['cashoutreason'] = 'Grund für die Bartransaktion';
$string['cashoutreason_desc'] = 'Mögliche Gründe: Wechselgeld, Einzahlung etc.';
$string['cashoutreasonnecessary'] = 'Sie müssen einen Grund eingeben.';
$string['cashoutsuccess'] = 'Barzahlung erfolgreich';
$string['cashreport'] = 'Kassajournal';
$string['cashreport:showcustomorderid'] = 'Benutzerdefinierte OrderID statt der normalen OrderID anzeigen';
$string['cashreport:showcustomorderid_desc'] = 'Achtung: Nur aktivieren, wenn ihr Zahlungsgateway-Plugin benutzerdefinierte OrderIDs unterstützt.';
$string['cashreport_desc'] = 'Hier erhalten Sie einen Überblick über alle getätigten Bezahlungen.
Sie können das Kassajournal auch im gewünschten Format exportieren.';
$string['cashreportsettings'] = 'Kassajournal-Einstellungen';
$string['cashtransfer'] = 'Bargeldumbuchung';
$string['cashtransferamount'] = 'Umbuchungsbetrag';
$string['cashtransferamount_help'] = 'Geben Sie einen positiven Wert ein (nicht 0) der beim ersten Kassier abgezogen und beim zweiten Kassier aufaddiert wird.';
$string['cashtransfercashierfrom'] = 'Von Kassa';
$string['cashtransfercashierfrom_help'] = 'Kassier:in, von deren Kassa das Geld entnommen wird';
$string['cashtransfercashierto'] = 'An Kassa';
$string['cashtransfercashierto_help'] = 'Kassier:in, in deren Kassa das Geld hinzugefügt wird';
$string['cashtransfernopositiveamount'] = 'Kein positiver Wert!';
$string['cashtransferreason'] = 'Grund für die Bargeldumbuchung';
$string['cashtransferreason_help'] = 'Geben Sie einen Grund für die Bargeldumbuchung an.';
$string['cashtransferreasonnecessary'] = 'Sie müssen einen Grund für die Bargeldumbuchung angeben!';
$string['cashtransfersuccess'] = 'Bargeldumbuchung erfolgreich';
$string['checkout'] = '<i class="fa fa-shopping-cart" aria-hidden="true"></i> Weiter zur Bezahlung ❯❯';
$string['checkout_completed'] = 'Checkout abgeschlossen';
$string['checkout_completed_desc'] = 'Der Benutzer mit der ID {$a->userid} hat den Checkout mit identifier {$a->identifier}
 erfolgreich abgeschlossen';
$string['checkvatnrcountrycode'] = "Wählen Sie Ihr Land";
$string['checkvatnrnumber'] = "Geben Sie Ihre UID Nummer";
$string['choose'] = 'Auswählen';
$string['choose...'] = 'Auswählen...';
$string['choosedefaultcountry'] = 'Standardland auswählen';
$string['choosedefaultcountrydesc'] = 'Wählen Sie das Standardland für die Rechnungsadresse aus. Dieses wird verwendet,
 wenn die Kund/innen keine Angaben zur Rechnungsadresse machen.';
$string['chooseplatform'] = 'Plattform wählen';
$string['chooseplatformdesc'] = 'Wählen Sie Ihre Rechnungsplattform aus.';
$string['confirmcancelallbody'] = 'Möchten Sie den Kauf für alle aktuellen Käufer:innen wirklich stornieren?
    Folgende Nutzer:innen erhalten den Kaufpreis zurück:
    {$a->userlist}
    Sie können unten die Bearbeitungsgebühr anführen. Diese wird von der rückerstatteten Summe abgezogen.';
$string['confirmcancelbody'] = 'Möchten Sie diesen Kauf wirklich stornieren? Das lässt sich nicht rückgängig machen.
 Der/die Käufer bekommt den Kaufpreis {$a->price} {$a->currency} abzüglich der Stornierungsgebühr von {$a->cancelationfee} {$a->currency} gutgeschrieben.';
$string['confirmcancelbodyconsumption'] = 'Möchten Sie diesen Kauf wirklich stornieren? Das lässt sich nicht rückgängig machen.
 Der/die Käufer bekommt den Kaufpreis {$a->price} {$a->currency} abzüglich des bereits verbrauchten Anteils von {$a->percentage} und einer Stornierungsgebühr von {$a->cancelationfee} {$a->currency} gutgeschrieben.';
$string['confirmcancelbodynocredit'] = 'Möchten Sie diesen Kauf wirklich stornieren? Das lässt sich nicht rückgängig machen.<br>
 Der/die KäuferIn hat Artikel bereits vollständig konsumiert, der ursprüngliche Preis war {$a->price} {$a->currency}';
$string['confirmcancelbodyuser'] = 'Möchten Sie den Kauf wirklich stornieren?
        Sie bekommen den Kaufpreis ({$a->price} {$a->currency}) abzüglich einer Bearbeitungsgebühr ({$a->cancelationfee} {$a->currency}) als Guthaben: ({$a->credit} {$a->currency})';
$string['confirmcancelbodyuserconsumption'] = '<p><b>Möchten Sie den Kauf wirklich stornieren?</b></p>
<p>
Sie erhalten <b>{$a->credit} {$a->currency}</b> als Guthaben.<br>
<table class="table table-light table-sm">
<tbody>
    <tr>
      <th scope="row">Originalpreis</th>
      <td align="right"> {$a->price} {$a->currency}</td>
    </tr>
    <tr>
      <th scope="row">Prozentuelle Stornogebühr ({$a->percentage})</th>
      <td align="right"> - {$a->deducedvalue} {$a->currency}</td>
    </tr>
    <tr>
      <th scope="row">Bearbeitungsgebühr</th>
      <td align="right"> - {$a->cancelationfee} {$a->currency}</td>
    </tr>
    <tr>
      <th scope="row">Gutschrift</th>
      <td align="right"> = {$a->credit} {$a->currency}</td>
    </tr>
  </tbody>
</table>
</p>
<div class="progress">
  <div class="progress-bar progress-bar-striped bg-$bootrapstyle" role="progressbar"
    style="width: {$a->percentage}" aria-valuenow="{$a->percentage}"
    aria-valuemin="0" aria-valuemax="100">{$a->percentage}
  </div>
</div>';
$string['confirmcancelbodyusernocredit'] = 'Möchten Sie diesen Kauf wirklich stornieren?<br>
 Da Sie den Artikel bereits zur Gänze verbraucht haben, erhalten Sie keine Rückerstattung. (Ursprünglicher Preis: {$a->price} {$a->currency})';
$string['confirmcanceltitle'] = 'Stornierung bestätigen';
$string['confirmpaidback'] = 'Bestätige Auszahlung';
$string['confirmpaidbackbody'] = 'Wollen Sie die Auszahlung bestätigen? Das setzt das Guthaben auf 0.';
$string['confirmpaidbacktitle'] = 'Bestätige Auszahlung';
$string['confirmterms'] = "AGBs akzeptieren";
$string['confirmzeropricecheckout'] = 'Bestätige';
$string['confirmzeropricecheckoutbody'] = 'Für diese Buchung ist keine Zahlung notwendig.
Wollen Sie fortfahren und direkt buchen?';
$string['confirmzeropricecheckouttitle'] = 'Jetzt buchen';
$string['costcenterstrings'] = 'Übersetzungen für Kostenstellen';
$string['costcenterstrings_desc'] = 'Übersetzungen für Kostenstellen';
$string['credit'] = 'Guthaben:';
$string['creditnotmatchbalance'] = 'Summe der Guthaben in Tabelle local_shopping_cart_credits stimmt nicht mit dem letzten Saldo (balance) überein!
Möglicherweise haben Sie doppelte oder fehlerhafte Einträge in der credits-Tabelle für den User mit userid {$a}.';
$string['creditpaidback'] = 'Guthaben ausgezahlt';
$string['credits'] = "Guthaben";
$string['creditsmanager'] = 'Guthaben-Manager';
$string['creditsmanager:correctcredits'] = 'Guthaben korrigieren';
$string['creditsmanager:infotext'] = 'Guthaben für  <b>{$a->username} (ID: {$a->userid})</b> auf- oder abbuchen.';
$string['creditsmanager:payback'] = 'Guthaben zurückbezahlen';
$string['creditsmanagercredits'] = 'Korrekturwert bzw. auszubezahlendes Guthaben';
$string['creditsmanagercredits_help'] = 'Wenn Sie "Guthaben korrigieren" gewählt haben, geben Sie hier den Korrekturwert ein.
Beispiel: Ein/e Benutzer/in hat 110 Euro Guthaben, sollte aber nur 100 Euro Guthaben haben. In diesem Fall beträgt der Korrekturwert -10.
Wenn Sie "Guthaben zurückbezahlen" ausgewählt haben, geben Sie hier den zurückzubezahlenden Betrag ein und geben Sie an, ob Sie in bar oder
per Banküberweisung zurückbezahlen möchten.';
$string['creditsmanagercreditscostcenter'] = 'Kostenstelle der das Guthaben zugeordnet wird';
$string['creditsmanagercreditscostcenter_help'] = 'Wählen Sie die Kostenstellen, für welche das Guthaben in Zukunft eingelöst werden kann. Wenn Sie dieses Feld leer lassen, hängt es von den Einstellungen ab, ob es für alle oder nur für eine bestimmte Kostenstelle eingelöst werden kann.';
$string['creditsmanagermode'] = 'Was möchten Sie tun?';
$string['creditsmanagerreason'] = 'Grund';
$string['creditsmanagersuccess'] = 'Guthabenbuchung wurde durchgeführt.';
$string['creditsused'] = 'Guthaben eingelöst';
$string['creditsusedannotation'] = 'Extra-Zeile für eingelöstes Guthaben';
$string['credittopayback'] = 'Zurückerstatteter Betrag';
$string['currency'] = 'Währung';
$string['cy'] = "Zypern";
$string['cz'] = "Tschechien";
$string['dailysums:downloadpdf'] = 'Tageseinnahmen als PDF herunterladen';
$string['dailysumspdfhtml'] = 'HTML-Vorlage für die Erstellung des Tagessumen-PDFs';
$string['dailysumspdfhtml:description'] = 'Geben Sie HTML-Code ein, der als Vorlage für die Erstellung des Tagessumen-PDFs verwendet werden soll.
Sie können die folgenden Platzhalter verwenden: [[title]], [[date]], [[totalsum]], [[printdate]], [[currency]], [[online]], [[cash]], [[creditcard]], [[debitcard]],
[[manual]], [[creditspaidbackcash]], [[creditspaidbacktransfer]].<br>
Lassen Sie das Feld leer, um die Standard-Vorlage zu verwenden.';
$string['de'] = "Deutschland";
$string['deductible'] = 'Abziehbar:';
$string['defaultcostcenterforcredits'] = 'Standard Kostenstelle zur Einlösung von Guthaben';
$string['defaultcostcenterforcredits_desc'] = 'Ist keine Kostenstelle angegeben, können Guthaben für Artikel dieser Kostenstelle eingelöst werden. Wird hier kein Wert eingegeben, können Guthaben ohne angegebene Kostenstelle für alle Artikel eingelöst werden.';
$string['defaulttaxcategory'] = 'Standard Steuerkategorie';
$string['defaulttaxcategory_desc'] =
        'Standard-Steuerkategorie, die verwendet wird, wenn das Cart-Item diese nicht explizit angibt (z.B. "A")';
$string['definefeesforcostcenters'] = 'Preise entsprechend der Kostenstellen angeben.';
$string['definefeesforcostcenters_desc'] = 'Bitte im folgenden Format eingeben:<br>
  Kostenstelle1:3.5<br>
  Kostenstelle2:5<br>
  Kostenstelle3:0 ';
$string['deletecreditcash'] = 'Ausbezahlt bar';
$string['deletecredittransfer'] = 'Ausbezahlt überwiesen';
$string['deleteledger'] = "Lösche das Zahlungsjournal wenn ein/e NutzerIn das Löschen ihrer Daten verlangt";
$string['deleteledgerdescription'] = "Das Zahlungsjournal enthält Zahlungsinformationen, die aus rechtlichen Gründen womöglich erhalten bleiben müssen.";
$string['discount'] = 'Rabatt';
$string['discountabsolute'] = 'Betrag';
$string['discountabsolute_help'] = 'Reduziere den Preis um diesen Betrag, z.B. "15". Keine Währung eingeben.';
$string['discountpercent'] = 'Prozent';
$string['discountpercent_help'] = 'Reduziere den Preis um diesen Prozentwert, z.B. "10". Kein %-Zeichen eingeben.';
$string['dk'] = "Dänemark";
$string['downloadcashreportlimit'] = 'Download-Limit festlegen';
$string['downloadcashreportlimitdesc'] = 'Geben Sie die maximale Anzahl an Zeilen ein, die beim Download des Kassajournals heruntergeladen werden sollen.
Dies kann Download-Problem bei zu großen Datenmengen beheben.';
$string['downpayment'] = "Anzahlung";
$string['downpayment_help'] = 'Dieser Betrag muss am Anfang überweisen werden. Die Restsumme erst später.';
$string['duedate'] = 'Letztes Zahlungsdatum';
$string['duedate_help'] = 'An diesem Datum muss der volle Betrag überwiesen werden.
Liegt das Datum 100 Tage in der Zukunft und es sind zwei Teilzalungen eingestellt,
muss - nach der ersten Zahlung - die Hälfte des offenen Betrags nach 50 Tagen
und der Rest nach 100 Tagen bezahlt werden.';
$string['duedatevariable'] = 'Fällig N Tage nach erster Anzahlung';
$string['duedatevariable_help'] = 'Anzahl Tage NACH der ersten Anzahlung nach denen der volle Betrag überwiesen worden sein muss.';
$string['ee'] = "Estland";
$string['el'] = "Griechenland";
$string['email'] = 'E-Mail';
$string['enableinstallments'] = 'Ermögliche Ratenzahlungen';
$string['enableinstallments_desc'] = 'Für jeden verkauften Artikel kann eingestellt werden, ob Ratenzahlungen möglich sind und zu welchen Konditionen.';
$string['enabletax'] = 'MWSt aktivieren';
$string['enabletax_desc'] = 'Soll MWSt im Wartenkorb angezeigt und verwendet werden';
$string['entervatnr'] = 'Sie können Ihre Umsatzsteuer-ID eingeben, wenn Sie für ein Unternehmen einkaufen.';
$string['erpnext'] = 'ERPNext';
$string['erpnext_content'] = 'Sehr geehrte Kundin, sehr geehrter Kunde,<br><br>Im Anhang finden Sie Ihre Rechnung.<br><br>Mit freundlichen Grüßen,<br>Wunderbyte Support Team';
$string['erpnext_reference_doctype'] = 'Sales Invoice';
$string['erpnext_subject'] = 'Ihre Rechnung';
$string['error:alreadybooked'] = 'Sie haben diesen Artikel bereits gebucht.';
$string['error:alreadybookedtitle'] = 'Bereits gebucht';
$string['error:cancelationfeetoohigh'] = 'Stornogebühr darf nicht größer sein als der zurückerstattete Betrag!';
$string['error:capabilitymissing'] = 'FEHLER: Ihnen fehlt eine erforderliche Berechtigung.';
$string['error:cashiercapabilitymissing'] = 'FEHLER: Ihnen fehlt die Berechtigung zum Erstellen von Kassenbelegen.';
$string['error:choosevalue'] = 'Sie müssen hier einen Wert auswählen.';
$string['error:costcentersdonotmatch'] = 'Diese Kurse können nicht gemeinsam gebucht werden.';
$string['error:costcentertitle'] = 'Andere Kostenstelle';
$string['error:fullybooked'] = 'Sie können nicht mehr buchen, da bereits alle Plätze belegt sind.';
$string['error:fullybookedtitle'] = 'Ausgebucht';
$string['error:gatewaymissingornotsupported'] = 'Sie haben entweder noch kein Zahlungs-Gateway eingerichtet
oder das eingerichtete Zahlungsgateway wird nicht unterstützt.';
$string['error:generalcarterror'] = 'Sie können dieses Item aufgrund eines Fehlers nicht in den Warenkorb legen.
Bitte wenden Sie sich an einen Administrator.';
$string['error:mustnotbeempty'] = 'Darf nicht leer sein.';
$string['error:negativevaluenotallowed'] = 'Bitte einen positiven Wert eingeben.';
$string['error:nofieldchosen'] = 'Sie müssen ein Feld auswählen.';
$string['error:noreason'] = 'Bitte geben Sie einen Grund an.';
$string['error:notpositive'] = 'Bitte geben Sie eine positive Zahl ein.';
$string['errorinvalidvatnr'] = 'Die übermittelte UID {$a} ist ungültig';
$string['errorselectcountry'] = 'Bitte Land auswählen';
$string['es'] = "Spanien";
$string['eu'] = "Europäische Union";
$string['expirationtime'] = 'Anzahl Minuten für Ablauf des Warenkorbs';
$string['expirationtime:description'] = 'Wie lange darf sich eine Buchung maximal im Warenkorb befinden?';
$string['failure'] = 'Fehler.';
$string['fi'] = "Finnland";
$string['firstname'] = 'Vorname';
$string['fixedpercentageafterserviceperiodstart'] = 'Fixen Prozentsatz erst ab dem vom Plugin zur Verfügung gestellten Start der Service-Periode abziehen';
$string['fixedpercentageafterserviceperiodstart_desc'] = 'Aktivieren Sie diese Einstellungn, wenn der Prozentsatz erst ab einer bestimmten Start-Zeit
 abgezogen werden soll (muss im entsprechenden Plugin konfiguriert werden, z.B. Kursbeginn oder Semesterbeginn).';
$string['floatonly'] = 'Nur Dezimalzahlen werden akzeptiert. Das richtige Trennzeichen hängt von Ihrem System ab.';
$string['foo'] = 'foo';
$string['for'] = "für";
$string['fr'] = "Frankreich";
$string['furtherpayments'] = 'Weitere Zahlungen';
$string['gateway'] = 'Gateway';
$string['gb'] = "Vereinigtes Königreich";
$string['getrefundforcredit'] = 'Das Guthaben kann für einen zukünftigen Kauf genutzt werden.';
$string['globalcurrency'] = 'Währung';
$string['globalcurrencydesc'] = 'Wählen Sie die Währung für Preise aus.';
$string['history'] = "Käufe";
$string['hr'] = "Kroatien";
$string['hu'] = "Ungarn";
$string['id'] = 'ID';
$string['identifier'] = 'TransaktionsID';
$string['ie'] = "Irland";
$string['incorrectnumberofpayments'] = 'Preis muss ohne Restbetrag durch die Anzahl der Zahlungen teilbar sein.';
$string['initialtotal'] = 'Preis: ';
$string['installment'] = "Ratenzahlung";
$string['installmentpaymentisdue'] = 'Nicht vergessen: {$a->itemname}, {$a->price} {$a->currency}. <a href="/local/shopping_cart/installments.php">Bitte hier zahlen</a>';
$string['installmentpaymentwasdue'] = 'Nicht vergessen: {$a->itemname}, {$a->price} {$a->currency}. <a href="/local/shopping_cart/installments.php">Bitte hier zahlen</a>';
$string['installments'] = "Ratenzahlungen";
$string['installmentsettings'] = 'Einstellungen Ratenzahlungen';
$string['insteadof'] = "anstatt";
$string['invoicingplatformdescription'] = 'Wählen Sie Ihre bevorzugte Rechnungsplattform aus den folgenden Optionen aus.';
$string['invoicingplatformheading'] = 'Bitte wählen Sie Ihre Rechnungsplattform';
$string['it'] = "Italien";
$string['item_added'] = 'Artikel hinzugefügt';
$string['item_bought'] = 'Artikel gekauft';
$string['item_canceled'] = 'Artikel storniert';
$string['item_deleted'] = 'Artikel gelöscht';
$string['item_expired'] = 'Zeit für Artikel im Warenkorb abgelaufen';
$string['item_notbought'] = 'Artikel konnte nicht gekauft werden';
$string['itemcanceled'] = 'Nutzer/in mit der id {$a->userid} hat Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} storniert';
$string['itemcouldntbebought'] = 'Artikel {$a} konnte nicht gekauft werden.';
$string['itemexpired'] = 'Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} ist abgelaufen';
$string['itemid'] = 'ItemID';
$string['itemname'] = 'Kurs';
$string['itempriceisnet'] = 'Preise für Artikel sind Nettopreise: Addiere die Steuer';
$string['itempriceisnet_desc'] = 'Wenn die an den Warenkorb übergebenen Preise Nettopreise sind, dann aktivieren Sie diese Checkbox,
um die Steuern zu den Artikelpreisen hinzuzufügen. Wenn die Artikel die Steuer bereits enthalten und somit Bruttopreise sind,
deaktivieren Sie diese Checkbox, um die Steuer auf der Grundlage des Bruttowertes des Artikels zu berechnen';
$string['lastname'] = 'Nachname';
$string['ledger'] = "Zahlungsjournal";
$string['ledgerinstallment'] = 'Folgende Ratenzahlung wurde geleistet: Zahlung Nummer {$a->id}, Fälligkeit {$a->date}';
$string['local/shopping_cart:cashier'] = 'NutzerIn hat Kassier-Rechte';
$string['lt'] = "Litauen";
$string['lu'] = "Luxemburg";
$string['lv'] = "Lettland";
$string['manualrebookingisallowed'] = 'Manuelles Nachbuchen an der Kassa erlauben';
$string['manualrebookingisallowed_desc'] = 'Mit dieser Einstellung kann die Kassierin Zahlungen nachbuchen,
 die bereits online bezahlt wurden, die aber im Kassajournal fehlen. (<span class="text-danger">Achtung:
 Aktivieren Sie dieses Feature nur, wenn Sie sicher sind, dass Sie es wirklich benötigen. Falsche Handhabung kann
 zu fehlerhaften Einträgen in der Datenbank führen!</span>)';
$string['markedforrebooking'] = 'Fürs Umbuchen markiert';
$string['markforrebooking'] = 'Kurs umbuchen';
$string['maxitems'] = 'Max. Anzahl von Buchungen im Warenkorb';
$string['maxitems:description'] = 'Maximale Anzahl von Buchungen im Warenkorb für den/die Nutzer/in festlegen';
$string['modulename'] = 'Warenkorb';
$string['mt'] = "Malta";
$string['mycart'] = 'Mein Warenkorb';
$string['nl'] = "Niederlande";
$string['nocostcenter'] = 'Keine Kostenstelle';
$string['nofixedpercentage'] = 'Kein fixer Prozentsatz';
$string['noinstallments'] = "Aktuell keine Ratenzahlungen";
$string['noitemsincart'] = 'Es gibt keine Artikel im Warenkorb';
$string['nolimit'] = 'Kein Limit';
$string['nopaymentaccounts'] = '<div class="text-danger font-weight-bold">Kein Zahlungsanbieter-Konto vorhanden!</div>';
$string['nopaymentaccountsdesc'] =
        '<p><a href="{$a->link}" target="_blank">Hier klicken, um ein Zahlungsanbieter-Konto anzulegen.</a></p>';
$string['nopermission'] = "No permission to cancel";
$string['nopermissiontoaccesspage'] = '<div class="alert alert-danger" role="alert">Sie sind nicht berechtigt, auf diese Seite zuzugreifen.</div>';
$string['notenoughcredits'] = 'Nicht genügend Guthaben vorhanden.';
$string['nouserselected'] = 'Noch niemand ausgewählt';
$string['nousersfound'] = 'Keine Nutzerinnen gefunden.';
$string['novatnr'] = "Keine UID verwenden";
$string['numberofpayments'] = 'Anzahl der Zahlungen';
$string['numberofpayments_help'] = 'Anzahl notwendiger Zahlungen NACH der ersten Zahlung. Bitte beachten Sie, dass Ratenzahlungen nicht möglich sind, wenn nicht genügend Zeit bis zum Kursbeginn verbleibt, unter Berücksichtigung der Anzahl der Zahlungen und der Zeit zwischen den Zahlungen (Admin-Plugin-Einstellung).';
$string['on'] = "am";
$string['onlyone'] = 'Nur einer dieser Werte kann mehr als 0 sein.';
$string['optioncancelled'] = 'Buchungsoption storniert';
$string['orderdetails'] = 'Bestellübersicht';
$string['orderid'] = 'OrderID';
$string['owncountrycode'] = "Land der eigenen Firma";
$string['owncountrycode_desc'] = "Zum automatischen Prüfen der UID muss auch die UID der eigenen Firma übermittelt werden.";
$string['owncountrytax'] = 'Verwende eigene Steuervorlage';
$string['owncountrytax_desc'] = 'Benutze die Heimatland Steuervorlage für alle europäischen Kunden.';
$string['ownvatnrnumber'] = "UID Nummer der eigenen Firma";
$string['ownvatnrnumber_desc'] = "Zum automatischen Prüfen der UID muss auch die UID der eigenen Firma übermittelt werden.";
$string['paid'] = 'Bezahlt';
$string['paidby'] = 'Bezahlt mit';
$string['paidby:americanexpress'] = 'American Express';
$string['paidby:dinersclub'] = 'Diners Club';
$string['paidby:eps'] = 'EPS';
$string['paidby:mastercard'] = 'Mastercard';
$string['paidby:unknown'] = 'Unbekannt';
$string['paidby:visa'] = 'VISA';
$string['paidwithcash'] = 'Barzahlung bestätigen';
$string['paidwithcreditcard'] = 'Kreditkartenzahlung bestätigen';
$string['paidwithdebitcard'] = 'Bankomatkartenzahlung bestätigen';
$string['pathtoinvoices'] = 'Rechnungspfad';
$string['pathtoinvoices_desc'] = 'Pfad im Moodle Dataroot. Kann z.B. in ein Repository gelegt werden, um direkten Zugang auf die Rechnungen zu haben.';
$string['payment'] = 'Bezahlmethode';
$string['payment_added'] = 'Nutzer/in hat eine Zahlung gestartet';
$string['payment_added_log'] = 'Nutzer/in mit der id {$a->userid} hat für den Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} einen Zahlungsprozess mit dem identifier {$a->identifier} gestartet';
$string['paymentaborted'] = 'Abgebrochen';
$string['paymentbrand'] = 'Marke';
$string['paymentcanceled'] = 'Storno';
$string['paymentcashier'] = 'an der Kassa';
$string['paymentcashier:cash'] = 'in bar an der Kassa';
$string['paymentcashier:creditcard'] = 'mit Kreditkarte an der Kassa';
$string['paymentcashier:debitcard'] = 'mit Bankomatkarte an der Kassa';
$string['paymentcashier:manual'] = 'mit Fehler - manuell nachgebucht';
$string['paymentconfirmed'] = 'Zahlung bestätigt und gebucht.';
$string['paymentconfirmed_desc'] = 'Nutzer/in mit der id {$a->userid} hat für die Nutzer/in mit der id {$a->relateduserid} einen Zahlungsprozess mit dem identifier {$a->identifier} erfolgreich abgeschlossen';
$string['paymentcredits'] = 'mit Guthaben';
$string['paymentdenied'] = 'Zahlung abgelehnt!';
$string['paymentmethod'] = 'Bezahlmethode';
$string['paymentmethodcashier'] = 'Kassa';
$string['paymentmethodcashier:cash'] = 'Kassa (Bar)';
$string['paymentmethodcashier:creditcard'] = 'Kassa (Kreditkarte)';
$string['paymentmethodcashier:debitcard'] = 'Kassa (Bankomatkarte)';
$string['paymentmethodcashier:manual'] = 'Manuell nachgebucht';
$string['paymentmethodcredits'] = 'Guthaben';
$string['paymentmethodcreditscorrection'] = 'Guthabenkorrektur';
$string['paymentmethodcreditspaidbackcash'] = 'Guthabenrückzahlung bar';
$string['paymentmethodcreditspaidbacktransfer'] = 'Guthabenrückzahlung überwiesen';
$string['paymentmethodonline'] = 'Online';
$string['paymentmethodrebookingcreditscorrection'] = 'Guthaben durch Umbuchung';
$string['paymentonline'] = 'via Online-Zahlung';
$string['paymentpending'] = 'Keine Rückmeldung';
$string['paymentstatus'] = 'Status';
$string['paymentsuccess'] = 'Erfolg';
$string['paymentsuccessful'] = 'Zahlung erfolgreich!';
$string['paymentsuccessfultext'] = 'Der Zahlungsanbieter hat Ihre Zahlung bestätigt. Vielen Dank für Ihren Kauf!';
$string['pending'] = 'Warten...';
$string['pl'] = "Polen";
$string['pluginname'] = 'Warenkorb';
$string['previouspurchases'] = 'Bisherige Käufe';
$string['price'] = 'Preis';
$string['print'] = 'Drucken';
$string['privacyheading'] = "Privatsphäreneinstellungen";
$string['privacyheadingdescription'] = "Einstellungen in Verbindung mit den Moodle Privatsphäreneinstellugnen";
$string['pt'] = "Portugal";
$string['rebooking'] = 'Umbuchung';
$string['rebookingalert'] = "Um umzubuchen fügen Sie bitte noch einen weiteren Kurs in Ihrem Einkaufswagen hinzu";
$string['rebookingcredit'] = 'Umbuchungsgutschrift';
$string['rebookingfee'] = 'Umbuchungsgebühr';
$string['rebookingfee_desc'] = 'Für jede Umbuchung wird eine Gebühr eingehoben, wenn die normale Stornoperiode vorbei ist.';
$string['rebookingheading'] = "Umbuchungen";
$string['rebookingheadingdescription'] = "Käufe können unter gewissen Umständen umgebucht werden. Das bedeutet, dass z.B. ein gekaufter Kurs storniert wird. Anstatt eines Guthabens wird sofort auf einen anderen Kurs umgebucht. Dabei fällt keine neuerliche Buchungsgebühr an. Eventuelle Überzahlungen verfallen.";
$string['rebookingidentifier'] = 'Guthaben für Umbuchung mit identifer {$a}';
$string['rebookingmaxnumber'] = "Maximale Anzahl an Umbuchungen";
$string['rebookingmaxnumberdesc'] = "Es werden zum Beispiel nur 3 Umbuchungen innerhalb von 100 Tagen erlaubt";
$string['rebookingperiod'] = "Umbuchungsperiode";
$string['rebookingperioddesc'] = "Die Zeit, in der die maximale Anzahl von Umbuchungen beschränkt werden kann. Typischerweise die Dauer eines Semesters. Wert in Tagen.";
$string['receipt'] = 'Buchungsbestätigung';
$string['receipt:bookingconfirmation'] = 'Buchungsbest&auml;tigung';
$string['receipt:dayofweektime'] = 'Tag & Uhrzeit';
$string['receipt:location'] = 'Ort';
$string['receipt:name'] = 'Name';
$string['receipt:price'] = 'Preis';
$string['receipt:total'] = 'Gesamtsumme';
$string['receipt:transactionno'] = 'Transaktionsnummer';
$string['receipthtml'] = 'HTML-Vorlage zur Erstellung von Kassenbelegen';
$string['receipthtml:description'] = 'Sie können die folgenden Platzhalter verwenden:
[[price]], [[pos]], [[name]], [[location]], [[dayofweektime]], [[originalprice]], [[outstandingprice]] zwischen [[items]] und [[/items]].
 Außerhalb von [[items]] können Sie auch [[sum]], [[firstname]], [[lastname]], [[mail]], [[address]], [[date]], [[invoice_number]] und [[order_number]] verwenden.
 Verwenden Sie nur einfaches HTML, das von TCPDF unterstützt wird.';
$string['receiptimage'] = 'Hintergrundbild für den Kassenbeleg';
$string['receiptimage:description'] = 'Laden Sie ein Hintergrundbild für den Kassenbeleg hoch, das z.B. Ihr Logo enthält.';
$string['remainingcredit'] = 'Verbleibendes Guthaben:';
$string['remainingtotal'] = 'Preis:';
$string['reminderdaysbefore'] = "Erinnerung x Tage vorher";
$string['reminderdaysbefore_desc'] = "Die eingestellte Anzahl Tage vor fälliger Zahlung erscheint eine Nachricht für die/den Benutzer:in auf Ihrer Seite";
$string['reports'] = 'Berichte';
$string['restart'] = 'Nächste/r KundIn';
$string['ro'] = "Rumänien";
$string['rounddiscounts'] = 'Rabatte runden';
$string['rounddiscounts_desc'] = 'Rabatte auf ganze Zahlen runden (mathematisch, ohne Nachkommastellen)';
$string['samecostcenter'] = 'Nur eine Kostenstelle pro Zahlungsvorgang';
$string['samecostcenter_desc'] = 'Alle Items im Warenkorb müssen die selbe Kostenstelle haben.
Items mit unterschiedlichen Kostenstellen müssen separat gebucht werden.';
$string['samecostcenterforcredits'] = 'Guthaben nur für selbe Kostenstellen verwenden';
$string['samecostcenterforcredits_desc'] = 'Wenn diese Einstellung aktiviert ist und einE NutzerIn Guthaben erhält, so kann dieses Guthaben nur für Artikel der selben Kostenstelle verwendet werden.';
$string['saveinvoicenumber'] = 'Nur Rechnungsnummer speichern';
$string['sch_paymentaccountid'] = "Wechsle das Zahlungsanbieter-Konto";
$string['se'] = "Schweden";
$string['searchforitem'] = 'Suche...';
$string['selectuser'] = 'Wähle eine/n TeilnehmerIn aus...';
$string['selectuserfirst'] = 'Wähle zuerst eine Nutzerin.';
$string['sendpaymentbutton'] = 'Zur Bezahlung';
$string['shopping_cart:canbuy'] = 'Kann kaufen';
$string['shopping_cart:cashier'] = 'Ist berechtigt für die Kassa';
$string['shopping_cart:cashiermanualrebook'] = 'Kann Benutzer:innen manuell nachbuchen';
$string['shopping_cart:cashtransfer'] = 'Kann Bargeld von einer Kassa auf eine andere Kassa umbuchen';
$string['shopping_cart:changepaymentaccount'] = 'Kann den paymentaccount von Artikeln ändern';
$string['shopping_cart:history'] = 'Verlauf (History) anzeigen';
$string['shoppingcarthistory'] = 'Alle bisherigen Käufe einer Person';
$string['showdailysums'] = '&sum; Tageseinnahmen anzeigen';
$string['showdailysumscurrentcashier'] = '&sum; Tageseinnahmen der aktuell eingeloggten Kassier:in anzeigen';
$string['showdescription'] = 'Zeige Beschreibung';
$string['showorderid'] = 'Order-ID anzeigen...';
$string['showvatnrchecker'] = "Verwende UID Nummer und verzichte gegebenenfalls auf Umsatzsteuer";
$string['showvatnrcheckerdescription'] = "Bei erfolgreicher Überprüfung kann auf die Einhebung der Umsatzsteuer verzichtet werden";
$string['si'] = "Slowenien";
$string['sk'] = "Slowakei";
$string['startinvoicenumber'] = "Mit dieser Nummer beginnt der Rechnungskreislauf";
$string['startinvoicenumber_desc'] = "Sie können einen Prefix eingeben. Es muss allerdings auch eine Zahl enthalten sein";
$string['startinvoicingdate'] = 'Mit dem folgenden Datum beginnen Sie mit der Rechnungsstellung';
$string['startinvoicingdatedesc'] = 'Geben Sie einen Unix Timestamp für den Zeitpunkt ein, ab dem Sie Rechnungen generieren wollen.
 Kopieren Sie ihn von dort: https://www.unixtimestamp.com/';
$string['success'] = 'Erfolgreich.';
$string['taxcategories'] = 'Steuerkategorien und anwendbare Steuersätze';
$string['taxcategories_desc'] = 'Steuerkategorien und anwendbare Steuersätze (in %) pro User-Land.';
$string['taxcategories_examples_button'] = '(Beispiele)';
$string['taxcategories_invalid'] = 'Der eingegebene Text kann nicht als Steuerkategorien interpretiert werden!';
$string['taxsettings'] = 'Warenkorb Steuern';
$string['termsandconditions'] = "AGBs";
$string['termsandconditions:description'] = "Sie können hier z.B. ein PDF verlinken. Für Übersetzungen verwenden Sie die
 <a href='https://docs.moodle.org/402/de/Multi-language_content_filter' target='_blank'>Moodle Sprachfilter</a>.";
$string['testing:description'] = 'Hier können Sie Test-Items zum Warenkorb hinzufügen, um das Warenkorb-Plugin zu testen.';
$string['testing:item'] = 'Test-Item';
$string['testing:title'] = 'Warenkorb-Demo';
$string['timebetweenpayments'] = 'Zeit zwischen Zahlungen';
$string['timebetweenpayments_desc'] = 'Die Zeit zwischen Zahlungen, üblicherweise 30 Tage.';
$string['timecreated'] = 'Erstellt';
$string['timemodified'] = 'Abgeschlossen';
$string['titledailysums'] = 'Tageseinnahmen';
$string['titledailysums:all'] = 'Gesamteinnahmen';
$string['titledailysums:current'] = 'Aktuelle:r Kassier:in';
$string['titledailysums:total'] = 'Saldo';
$string['token'] = 'Token';
$string['tokendesc'] = 'Geben Sie Ihr Authentifizierungstoken ein. Für ERPNExt benützen sie: &lt;api_key&gt;:&lt;api_secret&gt;';
$string['total'] = 'Gesamt:';
$string['total_gross'] = 'Gesamt Brutto:';
$string['total_net'] = 'Gesamt Netto:';
$string['uniqueidentifier'] = 'Eindeutige Buchungsid';
$string['uniqueidentifier_desc'] = 'Jede Buchung benötigt eine eindeutige id. Diese startet üblicherweise bei 1, kann aber auch höher gesetzt werden. Wenn sie z.b. auf 10000000 gesetzt wird, hat der erste Kauf die ID 10000001. Wenn das Feld gesetzt wird, wird ein Error geworfen, sobald die Anzahl der Stellen überschritten wird. Wird der Wert auf 1 gesetzt, sind nur neun Buchungen möglich.';
$string['unknown'] = ' - Zahlmethode unbekannt';
$string['usecredit'] = 'Verwende Guthaben:';
$string['useinstallments'] = "Ratenzahlungen aktivieren";
$string['user'] = 'Teilnehmerin...';
$string['useraddeditem'] = 'Nutzer/in mit der id {$a->userid} hat Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} hinzugefügt';
$string['userboughtitem'] = 'Nutzer/in mit der id {$a->userid} hat Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} gekauft';
$string['userdeleteditem'] = 'Nutzer/in mit der id {$a->userid} hat Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} gelöscht';
$string['userid'] = 'Nutzer:in id';
$string['usermodified'] = 'Bearbeitet von';
$string['usernotboughtitem'] = 'Nutzer/in mit der id {$a->userid} konnte den Aritkel {$a->itemid} {$a->component} für die Nutzer/in mit der id {$a->relateduserid} nicht kaufen';
$string['usevatnr'] = "UID eingeben";
$string['vatnrcheckerheading'] = "UID überprüfen";
$string['vatnrcheckerheadingdescription'] = "Vor dem Zahlen kann eine UID eingegeben und überprüft werden";
$string['verify'] = "UID prüfen";
$string['xi'] = "Nordirland";
$string['youcancanceluntil'] = 'Sie können bis {$a} stornieren.';
$string['youcannotcancelanymore'] = 'Stornieren ist nicht möglich.';
$string['yourcart'] = 'Ihr Warenkorb';
