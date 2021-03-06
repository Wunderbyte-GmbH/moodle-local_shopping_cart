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

$string['pluginname'] = 'Warenkorb';
$string['sendpaymentbutton'] = 'Zur Bezahlung';

$string['addtocart'] = 'In den Warenkorb';
$string['mycart'] = 'Mein Warenkorb';

// Settings.
$string['maxitems'] = 'Max. Anzahl von Buchungen im Warenkorb';
$string['maxitems:description'] = 'Maximale Anzahl von Buchungen im Warenkorb für den/die Nutzer/in festlegen';
$string['expirationtime'] = 'Anzahl Minuten für Ablauf des Warenkorbs';
$string['expirationtime:description'] = 'Wie lange darf sich eine Buchung maximal im Warenkorb befinden?';
$string['cancelationfee'] = 'Stornierungsgebühr';
$string['cancelationfee:description'] = 'Automatisch vom Guthaben abgezogene Gebühr bei einer Stornierung durch die/den KäuferIn.
                                        -1 bedeutet, dass Stornierung durch Userin nicht möglich ist.';
$string['addon'] = 'Zusätzliche Zeit festlegen';
$string['addon:description'] = 'Zeit, die zur Ablaufzeit hinzugefügt wird, nachdem der Checkout-Prozess gestartet wurde';
$string['additonalcashiersection'] = 'Text für den Kassa-Bereich';
$string['additonalcashiersection:description'] = 'HTML Shortcodes oder Buchungsoptionen für den Kassabereich hinzufügen';
$string['accountid'] = 'Zahlungsanbieter-Konto';
$string['accountid:description'] = 'Wählen Sie aus, über welchen Anbieter (Payment Account) die Zahlungen abgewickelt werden sollen.';
$string['nopaymentaccounts'] = '<div class="text-danger font-weight-bold">Kein Zahlungsanbieter-Konto vorhanden!</div>';
$string['nopaymentaccountsdesc'] = '<p><a href="{$a->link}" target="_blank">Hier klicken, um ein Zahlungsanbieter-Konto anzulegen.</a></p>';

$string['showdescription'] = 'Zeige Beschreibung';

// Capabilities.
$string['shopping_cart:canbuy'] = 'Kann kaufen';
$string['shopping_cart:history'] = 'Verlauf (History) anzeigen';
$string['shopping_cart:cashier'] = 'Ist berechtigt für die Kassa';

// File: lib.php.
$string['foo'] = 'foo';

// Cache.
$string['cachedef_cashier'] = 'Cashier cache';
$string['cachedef_cacheshopping'] = 'Shopping cache';

// Errors.

$string['itemcouldntbebought'] = 'Artikel {$a} konnte nicht gekauft werden.';
$string['noitemsincart'] = 'Es gibt keine Artikel im Warenkorb';

// Cart.
$string['total'] = 'Gesamt:';
$string['paymentsuccessful'] = 'Zahlung erfolgreich!';
$string['paymentsuccessfultext'] = 'Der Zahlungsanbieter hat Ihre Zahlung bestätigt. Vielen Dank für Ihren Kauf!';
$string['backtohome'] = 'Zurück zur Überblicksseite.';

$string['success'] = 'Erfolgreich.';
$string['pending'] = 'Warten...';
$string['failure'] = 'Fehler.';

$string['cartisempty'] = 'Ihr Warenkorb ist leer.';
$string['yourcart'] = 'Ihr Warenkorb';

// Cashier.
$string['paymentonline'] = 'via Online-Zahlung';
$string['paymentcashier'] = 'an der Kassa';
$string['paymentcashier:cash'] = 'in bar an der Kassa';
$string['paymentcashier:creditcard'] = 'mit Kreditkarte an der Kassa';
$string['paymentcashier:debitcard'] = 'mit Bankomatkarte an der Kassa';
$string['paymentcredits'] = 'mit Guthaben';
$string['unknown'] = ' - Zahlmethode unbekannt';
$string['paid'] = 'Bezahlt';
$string['paymentconfirmed'] = 'Zahlung bestätigt und gebucht.';
$string['restart'] = 'Nächste/r KundIn';
$string['print'] = 'Drucken';
$string['previouspurchases'] = 'Bisherige Käufe';
$string['checkout'] = 'Zur Kassa';
$string['nouserselected'] = 'Noch niemand ausgewählt';
$string['selectuser'] = 'Wähle eine/n TeilnehmerIn aus...';
$string['user'] = 'Teilnehmerin...';
$string['searchforitem'] = 'Suche...';

$string['payedwithcash'] = 'Barzahlung bestätigen';
$string['payedwithcreditcard'] = 'Kreditkartenzahlung bestätigen';
$string['payedwithdebitcard'] = 'Bankomatkartenzahlung bestätigen';

$string['cancelpurchase'] = 'Stornieren';
$string['canceled'] = 'Storniert';
$string['canceldidntwork'] = 'Fehler beim Stornieren';
$string['cancelsuccess'] = 'Erfolgreich storniert';

$string['youcancanceluntil'] = 'Sie können bis {$a} stornieren.';
$string['youcannotcancelanymore'] = 'Stornieren ist nicht möglich.';

$string['confirmcanceltitle'] = 'Bestätige Stornierung';
$string['confirmcancelbody'] = 'Möchten Sie wirklich den Kauf stornieren? Das lässt sich nicht rückgängig machen.
                                Unten kann die Gutschrift angepasst werden.';
$string['confirmcancelbodyuser'] = 'Möchten Sie wirklich den Kauf stornieren?
                                    Sie bekommen den Kaufpreis abzüglich einer Bearbeitungsgebühr in der Höhe von {$a} Euro gutgeschrieben.';

$string['confirmcancelallbody'] = 'Möchten Sie wirklich den Kauf für alle aktuellen Käufer*innen stornieren?
    Folgende Nutzer*innen erhalten den Kaufpreis zurück:
    {$a->userlist}
    Sie können unten die Bearbeitungsgebühr anführen. Diese wird von der rückerstatteten Summe abgezogen.';

$string['confirmpaidbacktitle'] = 'Bestätige Auszahlung';
$string['confirmpaidbackbody'] = 'Wollen Sie die Auszahlung bestätigen? Das setzt das Guthaben auf 0.';
$string['confirmpaidback'] = 'Bestätige Auszahlung';

$string['confirmzeropricecheckouttitle'] = 'Mit Guthaben bezahlen';
$string['confirmzeropricecheckoutbody'] = 'Sie haben genug Guthaben, um Ihren Kauf zur Gänze zu bezahlen. Wollen Sie fortfahren?';
$string['confirmzeropricecheckout'] = 'Bestätige';

$string['deletecredit'] = 'Ausbezahlt';
$string['credit'] = 'Guthaben:';

$string['cashier'] = 'Kassa';

$string['initialtotal'] = 'Preis:';
$string['usecredit'] = 'Verwende Guthaben:';
$string['deductible'] = 'Abziehbar:';
$string['remainingcredit'] = 'Verbleibendes Guthaben:';
$string['remainingtotal'] = 'Preis:';

// Access.php.
$string['local/shopping_cart:cashier'] = 'NutzerIn hat Kassier-Rechte';

// Report.
$string['reports'] = 'Berichte';
$string['cashreport'] = 'Kassajournal';
$string['cashreport_desc'] = 'Hier erhalten Sie einen Überblick über alle getätigten Bezahlungen.
Sie können das Kassajournal auch im gewünschten Format exportieren.';
$string['accessdenied'] = 'Zugriff verweigert';
$string['nopermissiontoaccesspage'] = '<div class="alert alert-danger" role="alert">Sie sind nicht berechtigt, auf diese Seite zuzugreifen.</div>';
$string['showdailysums'] = '&sum; Tageseinnahmen anzeigen...';
$string['titledailysums'] = 'Tageseinnahmen';
$string['titledailysums:all'] = 'Gesamteinnahmen';
$string['titledailysums:current'] = 'Aktuelle*r Kassier*in';

// Report headers.
$string['timecreated'] = 'Erstellt';
$string['timemodified'] = 'Abgeschlossen';
$string['id'] = 'ID';
$string['identifier'] = 'TransaktionsID';
$string['price'] = 'Preis';
$string['currency'] = 'Währung';
$string['lastname'] = 'Nachname';
$string['firstname'] = 'Vorname';
$string['itemid'] = 'ItemID';
$string['itemname'] = 'Kurs';
$string['payment'] = 'Bezahlmethode';
$string['paymentstatus'] = 'Status';
$string['gateway'] = 'Gateway';
$string['orderid'] = 'OrderID';
$string['usermodified'] = 'Bearbeitet von';

// Payment methods.
$string['paymentmethodonline'] = 'Online';
$string['paymentmethodcashier'] = 'Kassa';
$string['paymentmethodcredits'] = 'Guthaben';
$string['paymentmethodcashier:cash'] = 'Kassa (Bar)';
$string['paymentmethodcashier:creditcard'] = 'Kassa (Kreditkarte)';
$string['paymentmethodcashier:debitcard'] = 'Kassa (Bankomatkarte)';

// Payment status.
$string['paymentpending'] = 'Keine Rückmeldung';
$string['paymentaborted'] = 'Abgebrochen';
$string['paymentsuccess'] = 'Erfolg';
$string['paymentcanceled'] = 'Storno';

// Receipt.
$string['receipthtml'] = 'HTML-Vorlage zur Erstellung von Kassenbelegen';
$string['receipthtml:description'] = 'Sie können die folgenden Platzhalter verwenden:
[[price]], [[pos]], [[name]] zwischen [[items]] und [[/items]].
 Außerhalb von [[items]] können Sie auch [[sum]], [[firstname]], [[lastname]], [[email]] und [[date]] verwenden.
 Verwenden Sie nur einfaches HTML, das von TCPDF unterstützt wird.';
$string['receiptimage'] = 'Hintergrundbild für den Kassenbeleg';
$string['receiptimage:description'] = 'Laden Sie ein Hintergrundbild für den Kassenbeleg hoch, das z.B. Ihr Logo enthält.';

// Shortcodes.
$string['shoppingcarthistory'] = 'Alle bisherigen Käufe einer Person';

// Shopping cart history card.
$string['getrefundforcredit'] = 'Das Guthaben kann für einen zukünftigen Kauf genutzt werden.';

// Form modal_cancel_all_addcredit.
$string['nousersfound'] = 'Keine Nutzerinnen gefunden.';
