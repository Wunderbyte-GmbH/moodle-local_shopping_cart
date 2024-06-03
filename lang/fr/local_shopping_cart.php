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
 * @copyright   2024 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Shopping Cart';
$string['modulename'] = 'Shopping Cart';

// Chaînes générales.
$string['addtocart'] = 'Ajouter au panier';
$string['allowrebooking'] = 'Autoriser la ré-réservation';
$string['allowrebooking_desc'] = 'Permettre aux utilisateurs de ré-réserver des articles déjà achetés. Ils peuvent être marqués pour une ré-réservation et seront ajoutés au panier avec un prix négatif. Lors de la ré-réservation, ils seront annulés et un autre article sera acheté en même temps. Le prix total de la ré-réservation ne doit pas être négatif.';
$string['allowrebookingcredit'] = 'Crédit de ré-réservation';
$string['allowrebookingcredit_desc'] = 'Si vous activez le crédit de ré-réservation, un utilisateur recevra un remboursement des frais d\'annulation et de réservation s\'il annule un article pendant la période d\'annulation et réserve un autre article.';
$string['cash'] = 'Espèces';
$string['choose...'] = 'Choisir...';
$string['mycart'] = 'Mon panier';
$string['nolimit'] = 'Sans limite';
$string['optioncancelled'] = 'Option de réservation annulée';
$string['rebooking'] = 'Ré-réservation';
$string['rebookingcredit'] = 'Crédit de ré-réservation';
$string['sendpaymentbutton'] = 'Paiement';
$string['showorderid'] = 'Afficher l\'ID de commande...';

// Paramètres.
$string['maxitems'] = 'Max. articles dans le panier';
$string['maxitems:description'] = 'Définir le nombre maximum d\'articles pour le panier de l\'utilisateur';
$string['globalcurrency'] = 'Devise';
$string['globalcurrencydesc'] = 'Choisir la devise pour les prix.';
$string['expirationtime'] = 'Définir le temps d\'expiration en minutes';
$string['expirationtime:description'] = 'Combien de temps l\'article doit-il rester dans le panier ?';
$string['cancelationfee'] = 'Frais d\'annulation';
$string['bookingfee'] = 'Frais de réservation';
$string['bookingfee_desc'] = 'Frais de réservation pour chaque paiement.';
$string['uniqueidentifier'] = 'ID unique';
$string['uniqueidentifier_desc'] = 'Définir l\'ID de départ, si vous le souhaitez. Si vous définissez cette valeur à 10000000, le premier achat aura l\'ID 10000001. Si vous définissez la valeur, le nombre maximal de chiffres sera également défini. Si vous le réglez sur 1, vous pouvez seulement avoir 9 achats.';
$string['bookingfeeonlyonce'] = 'Facturer les frais de réservation une seule fois';
$string['bookingfeeonlyonce_desc'] = 'Chaque utilisateur paie les frais de réservation une seule fois, peu importe combien de paiements il effectue.';
$string['credittopayback'] = 'Montant à rembourser';
$string['cancelationfee:description'] = 'Frais automatiquement déduits pour l\'annulation par l\'utilisateur. -1 signifie que l\'annulation par l\'utilisateur n\'est pas possible.';
$string['addon'] = 'Ajouter du temps';
$string['addon:description'] = 'Ajout au temps d\'expiration après que le processus de paiement soit initié';
$string['additonalcashiersection'] = 'Ajouter un texte pour la section caissier';
$string['additonalcashiersection:description'] = 'Ajouter des shortcodes HTML ou des articles à acheter pour l\'onglet caissier';
$string['accountid'] = 'Compte de paiement';
$string['accountid:description'] = 'Choisissez votre compte de paiement préféré.';
$string['nopaymentaccounts'] = '<div class="text-danger font-weight-bold">Aucun compte de paiement existant!</div>';
$string['nopaymentaccountsdesc'] = '<p><a href="{$a->link}" target="_blank">Cliquez ici pour créer un compte de paiement.</a></p>';
$string['showdescription'] = 'Afficher la description';
$string['rounddiscounts'] = 'Arrondir les réductions';
$string['rounddiscounts_desc'] = 'Arrondir les réductions à des nombres entiers (pas de décimales)';
$string['taxsettings'] = 'Taxes du panier d\'achat';
$string['enabletax'] = 'Activer le traitement des taxes';
$string['enabletax_desc'] = 'Les informations de traitement des taxes doivent-elles être activées pour ce module ?';
$string['taxcategories'] = 'Catégories de taxes et leur pourcentage de taxe';
$string['taxcategories_examples_button'] = '(Exemples)';
$string['taxcategories_desc'] = 'Catégories de taxes par pays-utilisateur et leur pourcentage de taxe.';
$string['taxcategories_invalid'] = 'Les catégories de taxes données ne peuvent pas être analysées !';
$string['itempriceisnet'] = 'Les prix des articles sont des prix nets : ajouter la taxe';
$string['itempriceisnet_desc'] = 'Si les prix transmis au panier sont des prix nets, alors cochez cette case afin d\'ajouter les taxes sur le prix des articles. Si les articles incluent déjà la taxe et sont donc des prix bruts, décochez cette case afin de calculer la taxe basée sur la valeur brute de l\'article';
$string['defaulttaxcategory'] = 'Catégorie de taxe par défaut';
$string['defaulttaxcategory_desc'] = 'Catégorie de taxe à utiliser par défaut lorsque non explicitement déclarée par l\'article du panier (par exemple, "A")';
$string['cancellationsettings'] = 'Paramètres d\'annulation';
$string['calculateconsumation'] = 'Crédit sur annulation moins la valeur déjà consommée';
$string['calculateconsumation_desc'] = 'À l\'annulation, le crédit est calculé en fonction de la part déjà consommée d\'un article acheté.';
$string['calculateconsumationfixedpercentage'] = 'Utiliser un pourcentage FIXE au lieu de calculer la consommation par le temps déjà passé';
$string['calculateconsumationfixedpercentage_desc'] = 'Si vous choisissez un pourcentage ici, la consommation ne sera pas calculée avec le temps qui s\'est écoulé depuis le début de l\'option de réservation. Au lieu de cela, le pourcentage FIXE sera TOUJOURS utilisé.';
$string['nofixedpercentage'] = 'Pas de pourcentage fixe';
$string['fixedpercentageafterserviceperiodstart'] = 'Appliquer le pourcentage fixe uniquement après le début de la période de service';
$string['fixedpercentageafterserviceperiodstart_desc'] = 'Activez cela si vous souhaitez appliquer le pourcentage fixe uniquement après le début de la période de service fournie par le plugin fournissant les articles (par exemple, début du cours ou début du semestre).';
$string['cashreportsettings'] = 'Paramètres du rapport de caisse';
$string['cashreport:showcustomorderid'] = 'Afficher l\'ID de commande personnalisé au lieu de l\'ID de commande normal';
$string['cashreport:showcustomorderid_desc'] = 'Attention : Activez ce paramètre uniquement si votre passerelle de paiement prend en charge les ID de commande personnalisés.';
$string['samecostcenter'] = 'Un seul centre de coût par paiement';
$string['samecostcenter_desc'] = 'Tous les articles de paiement dans le panier doivent avoir le même centre de coût. Les articles avec différents centres de coût doivent être réservés séparément.';

$string['privacyheading'] = "Paramètres de confidentialité";
$string['privacyheadingdescription'] = "Définir le comportement lié aux paramètres de confidentialité dans Moodle";
$string['deleteledger'] = "Supprimer le grand livre à la demande de suppression de l\'utilisateur";
$string['deleteledgerdescription'] = "Le grand livre conservera les informations de paiement que vous pourriez devoir conserver pour des raisons légales, même lorsqu'un utilisateur est supprimé.";

// Capacités.
$string['shopping_cart:canbuy'] = 'Peut acheter';
$string['shopping_cart:history'] = 'Voir l\'historique';
$string['shopping_cart:cashier'] = 'Est un caissier';
$string['shopping_cart:cashiermanualrebook'] = 'Peut réserver manuellement des utilisateurs';
$string['shopping_cart:cashtransfer'] = 'Peut transférer de l\'argent d\'un caissier à un autre';

// Fichier : lib.php.
$string['foo'] = 'foo';

// Cache.
$string['cachedef_cashier'] = 'Cache du caissier';
$string['cachedef_cacheshopping'] = 'Cache de shopping';
$string['cachedef_schistory'] = 'Cache de l\'historique du panier';
$string['cachedef_cachedcashreport'] = 'Cache du rapport de caisse';

// Erreurs.
$string['itemcouldntbebought'] = 'L\'article {$a} n\'a pas pu être acheté';
$string['noitemsincart'] = 'Il n\'y a aucun article dans le panier';
$string['error:capabilitymissing'] = 'ERREUR : Vous n\'avez pas une capacité nécessaire.';
$string['error:cashiercapabilitymissing'] = 'ERREUR : Vous manquez la capacité de caissier nécessaire pour créer des reçus.';
$string['error:costcentertitle'] = 'Centre de coût différent';
$string['error:costcentersdonotmatch'] = 'Vous avez déjà un article avec un centre de coût différent dans votre panier. Vous devez acheter cet article séparément !';
$string['error:fullybookedtitle'] = 'Complet';
$string['error:fullybooked'] = 'Vous ne pouvez plus réserver cet article car il est déjà complet.';
$string['error:alreadybookedtitle'] = 'Déjà réservé';
$string['error:alreadybooked'] = 'Vous avez déjà réservé cet article.';
$string['error:gatewaymissingornotsupported'] = 'Remarque : Votre passerelle de paiement actuelle n\'est soit pas prise en charge, soit vous devez encore configurer une passerelle de paiement.';
$string['error:generalcarterror'] = 'Vous ne pouvez pas ajouter cet article à votre panier car il y avait une erreur. Veuillez contacter un administrateur.';
$string['error:negativevaluenotallowed'] = 'Veuillez entrer une valeur positive.';
$string['error:cancelationfeetoohigh'] = 'Les frais d\'annulation ne peuvent pas être supérieurs au montant à rembourser !';
$string['error:nofieldchosen'] = 'Vous devez choisir un champ.';
$string['error:mustnotbeempty'] = 'Ne doit pas être vide.';
$string['error:noreason'] = 'Veuillez entrer une raison.';
$string['error:notpositive'] = 'Veuillez entrer un nombre positif.';
$string['error:choosevalue'] = 'Veuillez entrer une valeur.';
$string['selectuserfirst'] = 'Sélectionnez d\'abord l\'utilisateur';
$string['notenoughcredits'] = 'Pas assez de crédits disponibles.';

// Panier.
$string['total'] = 'Total :';
$string['total_net'] = 'Total net :';
$string['total_gross'] = 'Total brut :';
$string['paymentsuccessful'] = 'Paiement réussi !';
$string['paymentdenied'] = 'Paiement refusé !';
$string['paymentsuccessfultext'] = 'Votre prestataire de paiement a confirmé le paiement. Merci pour votre achat.';
$string['backtohome'] = 'Retour à l\'accueil.';

$string['success'] = 'Succès';
$string['pending'] = 'En attente';
$string['failure'] = 'Échec';

$string['alreadyincart'] = 'L\'article est déjà dans votre panier.';
$string['cartisfull'] = 'Votre panier est plein.';
$string['cartisempty'] = 'Votre panier est vide.';
$string['yourcart'] = 'Votre panier';
$string['addedtocart'] = '{$a} a été ajouté à votre panier.';
$string['creditnotmatchbalance'] = 'La somme des crédits dans la table local_shopping_cart_credits ne correspond pas au solde le plus récent ! Il pourrait y avoir des entrées en double ou des enregistrements corrompus dans la table des crédits pour l\'userid {$a}';

// Caissier.
$string['paymentonline'] = 'en ligne';
$string['paymentcashier'] = 'au bureau du caissier';
$string['paymentcashier:cash'] = 'en espèces au bureau du caissier';
$string['paymentcashier:creditcard'] = 'par carte de crédit au bureau du caissier';
$string['paymentcashier:debitcard'] = 'par carte de débit au bureau du caissier';
$string['paymentcashier:manual'] = 'avec erreur - ré-réservé manuellement';
$string['paymentcredits'] = 'avec crédits';
$string['unknown'] = ' - méthode inconnue';
$string['paid'] = 'Payé';
$string['paymentconfirmed'] = 'Paiement confirmé';
$string['restart'] = 'Prochain client';
$string['print'] = 'Imprimer';
$string['previouspurchases'] = 'Achats précédents';
$string['checkout'] = '<i class="fa fa-shopping-cart" aria-hidden="true"></i> Procéder au paiement ❯❯';
$string['nouserselected'] = 'Aucun utilisateur sélectionné';
$string['selectuser'] = 'Sélectionner un utilisateur...';
$string['user'] = 'Utilisateur...';
$string['searchforitem'] = 'Rechercher un article...';
$string['choose'] = 'Choisir';

$string['cashout'] = 'Transactions en espèces';
$string['cashoutamount'] = 'Montant de la transaction en espèces';
$string['cashoutnoamountgiven'] = 'Entrez un montant positif (dépôt) ou négatif (retrait), mais pas 0.';
$string['cashoutamount_desc'] = 'Montant négatif pour un retrait, montant positif pour un dépôt.';
$string['cashoutreason'] = 'Raison de la transaction';
$string['cashoutreasonnecessary'] = 'Vous devez donner une raison';
$string['cashoutreason_desc'] = 'Les raisons possibles sont le changement d\'argent, le dépôt bancaire, etc.';
$string['cashoutsuccess'] = 'Transaction en espèces réussie';

$string['cashtransfer'] = 'Transfert d\'argent';
$string['cashtransferamount'] = 'Montant du transfert d\'argent';
$string['cashtransfernopositiveamount'] = 'Aucun montant positif !';
$string['cashtransferamount_help'] = 'Entrez un montant positif. Le montant sera retiré du premier caissier et ajouté au deuxième caissier.';
$string['cashtransferreason'] = 'Raison du transfert d\'argent';
$string['cashtransferreasonnecessary'] = 'Vous devez donner une raison pour laquelle l\'argent a été transféré.';
$string['cashtransferreason_help'] = 'Entrez une raison pour laquelle l\'argent a été transféré.';
$string['cashtransfercashierfrom'] = 'Du caissier';
$string['cashtransfercashierfrom_help'] = 'Caissier duquel le montant est retiré';
$string['cashtransfercashierto'] = 'Au caissier';
$string['cashtransfercashierto_help'] = 'Caissier auquel le montant est donné';
$string['cashtransfersuccess'] = 'Transfert d\'argent réussi';

$string['paidwithcash'] = 'Confirmer le paiement en espèces';
$string['paidwithcreditcard'] = 'Confirmer le paiement par carte de crédit';
$string['paidwithdebitcard'] = 'Confirmer le paiement par carte de débit';
$string['cashiermanualrebook'] = 'Ré-réservation manuelle avec annotation ou TransactionID';
$string['manualrebookingisallowed'] = 'Autoriser la ré-réservation manuelle au bureau du caissier';
$string['manualrebookingisallowed_desc'] = 'Avec ce paramètre activé, le caissier peut ré-réserver manuellement les paiements qui ont déjà été payés en ligne mais qui manquent dans le rapport de caisse. (<span class="text-danger">Attention : Activez uniquement cette fonctionnalité si vous êtes sûr d\'en avoir vraiment besoin. Une utilisation incorrecte pourrait compromettre l\'intégrité de votre base de données !</span>)';

$string['cancelpurchase'] = 'Annuler l\'achat';
$string['canceled'] = 'Annulé';
$string['canceldidntwork'] = 'L\'annulation n\'a pas fonctionné';
$string['cancelsuccess'] = 'Annulation réussie';
$string['applytocomponent'] = 'Annuler sans rappel au plugin';
$string['applytocomponent_desc'] = 'Avec ce paramètre décoché, vous pouvez annuler par exemple une double réservation sans désinscrire un acheteur du cours acheté.';

$string['markforrebooking'] = 'Ré-réserver à un autre cours';
$string['markedforrebooking'] = 'Marqué pour ré-réservation';

$string['youcancanceluntil'] = 'Vous pouvez annuler jusqu\'au {$a}.';
$string['youcannotcancelanymore'] = 'Aucune annulation possible.';

$string['confirmcanceltitle'] = 'Confirmer l\'annulation';
$string['confirmcancelbody'] = "Voulez-vous vraiment annuler cet achat ? Cela ne peut pas être annulé. l\'utilisateur qui a acheté obtiendra son argent en retour dont les frais d\'annulation seront soustraits.";
$string['confirmcancelbodyconsumption'] = 'Voulez-vous vraiment annuler cet achat ? Cela ne peut pas être annulé. L\'utilisateur qui a acheté obtiendra les coûts de {$a->price} {$a->currency} moins la part déjà consommée de {$a->percentage} moins les frais d\'annulation ({$a->cancelationfee} {$a->currency}) comme crédit ({$a->credit} {$a->currency}) pour votre prochain achat. <br><br> <div class="progress"> <div class="progress-bar progress-bar-striped bg-$bootrapstyle" role="progressbar" style="width: {$a->percentage}" aria-valuenow="{$a->percentage}" aria-valuemin="0" aria-valuemax="100">{$a->percentage}</div> </div>';
$string['confirmcancelbodyuser'] = 'Voulez-vous vraiment annuler cet achat ?<br> Vous obtiendrez les coûts de votre achat ({$a->price} {$a->currency}) moins les frais d\'annulation ({$a->cancelationfee} {$a->currency}) comme crédit ({$a->credit} {$a->currency}) pour votre prochain achat.';
$string['confirmcancelbodyuserconsumption'] = '<p><b>Voulez-vous vraiment annuler cet achat ?</b></p> <p> Vous recevrez <b>{$a->credit} {$a->currency}</b> comme crédit.<br> <table class="table table-light table-sm"> <tbody> <tr> <th scope="row">Prix original</th> <td align="right"> {$a->price} {$a->currency}</td> </tr> <tr> <th scope="row">Pourcentage des frais d\'annulation ({$a->percentage})</th> <td align="right"> - {$a->deducedvalue} {$a->currency}</td> </tr> <tr> <th scope="row">Frais d\'annulation</th> <td align="right"> - {$a->cancelationfee} {$a->currency}</td> </tr> <tr> <th scope="row">Crédit</th> <td align="right"> = {$a->credit} {$a->currency}</td> </tr> </tbody> </table> </p> <div class="progress"> <div class="progress-bar progress-bar-striped bg-$bootrapstyle" role="progressbar" style="width: {$a->percentage}" aria-valuenow="{$a->percentage}" aria-valuemin="0" aria-valuemax="100">{$a->percentage} </div> </div>';
$string['confirmcancelbodynocredit'] = 'Voulez-vous vraiment annuler cet achat ?<br> L\'utilisateur a déjà consommé tout l\'article et n\'obtiendra aucun remboursement du prix payé : {$a->price} {$a->currency}';
$string['confirmcancelbodyusernocredit'] = 'Voulez-vous vraiment annuler cet achat ?<br> Vous avez déjà consommé tout l\'article et n\'obtiendrez aucun remboursement du prix payé : {$a->price} {$a->currency}';
$string['confirmcancelallbody'] = 'Voulez-vous vraiment annuler cet achat pour tous les utilisateurs ? Les utilisateurs suivants obtiendront leur argent en retour comme crédit : {$a->userlist} Vous pouvez spécifier les frais d\'annulation ci-dessous. Ils seront déduits du prix d\'achat original.';

$string['confirmpaidbacktitle'] = 'Confirmer le remboursement';
$string['confirmpaidbackbody'] = 'Voulez-vous vraiment confirmer que vous avez remboursé à l\'utilisateur son crédit ? Cela mettra son crédit à 0.';
$string['confirmpaidback'] = 'Confirmer';

$string['confirmzeropricecheckouttitle'] = 'Réserver maintenant';
$string['confirmzeropricecheckoutbody'] = 'Vous n\'avez rien à payer. Voulez-vous procéder et réserver ?';
$string['confirmzeropricecheckout'] = 'Confirmer';

$string['deletecreditcash'] = 'Remboursé en espèces';
$string['deletecredittransfer'] = 'Remboursé par virement';
$string['credit'] = 'Crédit :';
$string['creditpaidback'] = 'Crédit remboursé.';
$string['creditsmanager'] = 'Gestionnaire de crédits';
$string['creditsmanagermode'] = 'Que voulez-vous faire ?';
$string['creditsmanager:infotext'] = 'Ajouter ou retirer des crédits pour <b>{$a->username} (ID: {$a->userid})</b>.';
$string['creditsmanagersuccess'] = 'Les crédits ont été réservés avec succès';
$string['creditsmanagercredits'] = 'Valeur de correction ou crédits à rembourser';
$string['creditsmanagercredits_help'] = 'Si vous avez choisi "Corriger les crédits" alors entrez la valeur de correction ici. Exemple : Un utilisateur a 110 EUR en crédits mais devrait en réalité avoir 100 EUR en crédits. Dans ce cas, la valeur de correction est -10. Si vous avez choisi "Rembourser les crédits" alors entrez le montant à rembourser et choisissez si vous voulez rembourser en espèces ou par virement bancaire.';
$string['creditsmanagerreason'] = 'Raison';
$string['creditsmanager:correctcredits'] = 'Corriger les crédits';
$string['creditsmanager:payback'] = 'Rembourser les crédits';

$string['cashier'] = 'Caissier';

$string['initialtotal'] = 'Prix : ';
$string['usecredit'] = 'Utiliser le crédit :';
$string['deductible'] = 'Déductible :';
$string['remainingcredit'] = 'Crédit restant :';
$string['remainingtotal'] = 'Prix :';
$string['creditsused'] = 'Crédits utilisés';
$string['creditsusedannotation'] = 'Ligne supplémentaire car des crédits ont été utilisés';

$string['nopermission'] = "Pas la permission d'annuler";

// Access.php.
$string['local/shopping_cart:cashier'] = "l\'utilisateur a des droits de caissier";

// Rapport.
$string['reports'] = 'Rapports';
$string['cashreport'] = 'Rapport de caisse';
$string['cashreport_desc'] = 'Ici, vous obtenez un aperçu de toutes les transactions comptables. Vous pouvez également exporter le rapport dans votre format de fichier préféré.';
$string['accessdenied'] = 'Accès refusé';
$string['nopermissiontoaccesspage'] = '<div class="alert alert-danger" role="alert">Vous n\'avez pas la permission d\'accéder à cette page.</div>';
$string['showdailysums'] = '&sum; Afficher les sommes quotidiennes';
$string['showdailysumscurrentcashier'] = '&sum; Afficher les sommes quotidiennes du caissier actuel';
$string['titledailysums'] = 'Revenus quotidiens';
$string['titledailysums:all'] = 'Tous les revenus';
$string['titledailysums:total'] = 'Revenu total';
$string['titledailysums:current'] = 'Caissier actuel';
$string['dailysums:downloadpdf'] = 'Télécharger les sommes quotidiennes en PDF';
$string['dailysumspdfhtml'] = 'Modèle HTML pour le PDF des sommes quotidiennes';
$string['dailysumspdfhtml:description'] = 'Entrez HTML pour créer le PDF des sommes quotidiennes. Vous pouvez utiliser les espaces réservés suivants : [[title]], [[date]], [[totalsum]], [[printdate]], [[currency]], [[online]], [[cash]], [[creditcard]], [[debitcard]], [[manual]], [[creditspaidbackcash]], [[creditspaidbacktransfer]].<br> Laissez ce champ vide pour utiliser le modèle par défaut.';
$string['downloadcashreportlimit'] = 'Limite de téléchargement';
$string['downloadcashreportlimitdesc'] = 'Entrez le nombre maximal de lignes pour le téléchargement du rapport de caisse. En limitant, vous pouvez résoudre les problèmes liés à des quantités trop importantes de données.';

// En-têtes de rapport.
$string['timecreated'] = 'Créé';
$string['timemodified'] = 'Complété';
$string['id'] = 'ID';
$string['identifier'] = 'TransactionID';
$string['price'] = 'Prix';
$string['currency'] = 'Devise';
$string['lastname'] = 'Nom';
$string['firstname'] = 'Prénom';
$string['email'] = 'E-Mail';
$string['itemid'] = 'ItemID';
$string['itemname'] = 'Nom de l\'article';
$string['payment'] = 'Méthode de paiement';
$string['paymentstatus'] = 'Statut';
$string['gateway'] = 'Passerelle';
$string['orderid'] = 'OrderID';
$string['usermodified'] = 'Modifié par';

// Méthodes de paiement.
$string['paymentmethod'] = 'Méthode de paiement';
$string['paymentmethodonline'] = 'En ligne';
$string['paymentmethodcashier'] = 'Caissier';
$string['paymentmethodcredits'] = 'Crédits';
$string['paymentmethodcreditspaidbackcash'] = 'Crédits remboursés en espèces';
$string['paymentmethodcreditspaidbacktransfer'] = 'Crédits remboursés par virement';
$string['paymentmethodcreditscorrection'] = 'Correction des crédits';
$string['paymentmethodcashier:cash'] = 'Caissier (Espèces)';
$string['paymentmethodcashier:creditcard'] = 'Caissier (Carte de crédit)';
$string['paymentmethodcashier:debitcard'] = 'Caissier (Carte de débit)';
$string['paymentmethodcashier:manual'] = 'Ré-réservé manuellement';

$string['paidby'] = 'Payé par';
$string['paidby:visa'] = 'VISA';
$string['paidby:mastercard'] = 'Mastercard';
$string['paidby:eps'] = 'EPS';
$string['paidby:dinersclub'] = 'Diners Club';
$string['paidby:americanexpress'] = 'American Express';
$string['paidby:unknown'] = 'Inconnu';

// Statut de paiement.
$string['paymentpending'] = 'En attente';
$string['paymentaborted'] = 'Interrompu';
$string['paymentsuccess'] = 'Succès';
$string['paymentcanceled'] = 'Annulé';

// Reçu.
$string['receipt'] = 'Reçu';
$string['receipthtml'] = 'Mettre dans le modèle pour le reçu';
$string['receipthtml:description'] = 'Vous pouvez utiliser les espaces réservés suivants : [[price]], [[pos]], [[name]], [[location]], [[dayofweektime]] entre [[items]] et [[/items]]. Avant et après, vous pouvez également utiliser [[sum]], [[firstname]], [[lastname]], [[mail]] et [[date]] (à l\'extérieur du tag [[items]]). Utilisez uniquement l\'HTML de base pris en charge par TCPDF';

$string['receiptimage'] = 'Image de fond pour le reçu du caissier';
$string['receiptimage:description'] = 'Définissez une image de fond, par exemple avec un logo';
$string['receipt:bookingconfirmation'] = 'Confirmation de réservation';
$string['receipt:transactionno'] = 'Numéro de transaction';
$string['receipt:name'] = 'Nom';
$string['receipt:location'] = 'Lieu';
$string['receipt:dayofweektime'] = 'Jour & Heure';
$string['receipt:price'] = 'Prix';
$string['receipt:total'] = 'Somme totale';

// Termes et conditions.
$string['confirmterms'] = "J'accepte les termes et conditions";
$string['accepttermsandconditions'] = "Exiger l'acceptation des termes et conditions";
$string['accepttermsandconditions:description'] = "Sans acceptation des termes et conditions, l'achat n'est pas possible.";
$string['termsandconditions'] = "Termes & Conditions";
$string['termsandconditions:description'] = "Vous pouvez lier à votre PDF. Pour la localisation de ce champ, utilisez <a href='https://docs.moodle.org/402/en/Multi-language_content_filter' target='_blank'>les filtres de contenu multilingue de Moodle</a>.";

// Shortcodes.
$string['shoppingcarthistory'] = 'Tous les achats d\'un utilisateur donné';

// Carte d'historique du panier d'achat.
$string['getrefundforcredit'] = 'Vous pouvez utiliser vos crédits pour acheter un nouvel article.';

// Formulaire modal_cancel_all_addcredit.
$string['nousersfound'] = 'Aucun utilisateur trouvé';

// Modale de réduction.
$string['discount'] = 'Réduction';
$string['applydiscount'] = 'Appliquer la réduction';
$string['adddiscounttoitem'] = 'Vous pouvez réduire le prix de cet article soit par une somme fixe soit par un pourcentage du prix initial. Vous ne pouvez pas appliquer les deux en même temps.';
$string['discountabsolute'] = 'Montant';
$string['discountabsolute_help'] = 'Réduire le prix de ce montant, comme "15". Pas de devise.';
$string['discountpercent'] = 'Pourcentage';
$string['discountpercent_help'] = 'Réduire le prix de ce pourcentage, comme "10". Ne pas entrer le symbole %';
$string['floatonly'] = 'Seules les valeurs numériques (décimales) sont acceptées. Le séparateur correct dépend de votre système.';

// Evénements.
$string['item_bought'] = 'Article acheté';
$string['item_notbought'] = 'Article non acheté';
$string['item_added'] = 'Article ajouté';
$string['item_expired'] = 'Article expiré';
$string['item_deleted'] = 'Article supprimé';
$string['item_canceled'] = 'Article annulé';
$string['useraddeditem'] = 'L\'utilisateur avec l\'userid {$a->userid} a ajouté l\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid}';
$string['userdeleteditem'] = 'L\'utilisateur avec l\'userid {$a->userid} a supprimé l\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid}';
$string['userboughtitem'] = 'L\'utilisateur avec l\'userid {$a->userid} a acheté l\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid}';
$string['usernotboughtitem'] = 'L\'utilisateur avec l\'userid {$a->userid} n\'a pas pu acheter l\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid}';
$string['itemexpired'] = 'L\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid} a expiré';
$string['itemcanceled'] = 'L\'utilisateur avec l\'userid {$a->userid} a annulé l\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid}';
$string['payment_added'] = 'L\'utilisateur a commencé une transaction de paiement';
$string['payment_added_log'] = 'L\'utilisateur avec l\'userid {$a->userid} a commencé un paiement avec l\'identifiant {$a->identifier} pour l\'article {$a->itemid} {$a->component} pour l\'utilisateur avec l\'id {$a->relateduserid}';

// Caches.
$string['cachedef_schistory'] = 'Cache des articles du panier (cache de l\'historique du panier)';
$string['cachedef_cacherebooking'] = 'Cache de ré-réservation';

// Caissier ré-réservation manuelle.
$string['annotation'] = 'Annotation';
$string['annotation_rebook_desc'] = 'Entrez une annotation ou l\'ID de commande de la transaction de paiement que vous souhaitez ré-réserver.';
$string['cashier_manualrebook'] = 'Ré-réservation manuelle';
$string['cashier_manualrebook_desc'] = 'Quelqu\'un a effectué une ré-réservation manuelle d\'une transaction de paiement.';

// Facturation.
$string['invoicingplatformheading'] = 'Veuillez choisir votre plateforme de facturation';
$string['invoicingplatformdescription'] = 'Sélectionnez votre plateforme de facturation préférée parmi les options ci-dessous.';
$string['chooseplatform'] = 'Choisir la plateforme';
$string['chooseplatformdesc'] = 'Sélectionnez votre plateforme de facturation.';
$string['baseurl'] = 'URL de base';
$string['baseurldesc'] = 'Entrez l\'URL de base pour votre plateforme de facturation.';
$string['token'] = 'Jeton';
$string['tokendesc'] = 'Entrez votre jeton d\'authentification. Pour ERPNext utilisez &lt;api_key&gt;:&lt;api_secret&gt;';
$string['startinvoicingdate'] = 'Entrez une date à partir de laquelle vous souhaitez commencer à générer des factures';
$string['startinvoicingdatedesc'] = 'Afin de prévenir la création de factures pour des factures passées, entrez un horodatage UNIX pour la date de début de l\'émission des factures. Obtenez-le ici : https://www.unixtimestamp.com/';
$string['checkout_completed'] = 'Paiement terminé';
$string['checkout_completed_desc'] = 'L\'utilisateur avec l\'userid {$a->userid} a terminé avec succès le paiement avec l\'identifiant {$a->identifier}';
$string['choosedefaultcountry'] = 'Choisir le pays par défaut pour les clients';
$string['choosedefaultcountrydesc'] = 'Sélectionnez le pays par défaut pour vos clients. Si l\'utilisateur ne fournit pas de données de facturation, ce pays est sélectionné pour la facture.';
$string['erpnext'] = 'ERPNext';

// API de confidentialité.
$string['history'] = "Achats";
$string['ledger'] = "Grand livre";
$string['credits'] = "Crédits";

// RGPD.
$string['privacy:metadata:local_shopping_cart_history'] = 'Historique du panier d\'achat';
$string['privacy:metadata:local_shopping_cart_history:userid'] = 'ID de l\'utilisateur qui a obtenu quelque chose.';
$string['privacy:metadata:local_shopping_cart_history:itemid'] = 'ID de l\'article acheté.';
$string['privacy:metadata:local_shopping_cart_history:itemname'] = 'Nom de l\'article acheté';
$string['privacy:metadata:local_shopping_cart_history:price'] = 'Prix de l\'article.';
$string['privacy:metadata:local_shopping_cart_history:tax'] = 'Taxe appliquée à cet article';
$string['privacy:metadata:local_shopping_cart_history:taxpercentage'] = 'Taxe appliquée au prix de cet article en pourcentage flottant';
$string['privacy:metadata:local_shopping_cart_history:fee'] = "Les frais sont uniquement enregistrés lors de l'annulation";
$string['privacy:metadata:local_shopping_cart_history:taxcategory'] = 'Catégorie de taxe définie pour cet article.';
$string['privacy:metadata:local_shopping_cart_history:discount'] = 'Remise appliquée.';
$string['privacy:metadata:local_shopping_cart_history:credits'] = 'Crédits utilisés pour le paiement.';
$string['privacy:metadata:local_shopping_cart_history:currency'] = 'Devise dans laquelle il a été payé.';
$string['privacy:metadata:local_shopping_cart_history:componentname'] = 'Composant qui a fourni l\'article.';
$string['privacy:metadata:local_shopping_cart_history:identifier'] = 'Identifiant du processus de paiement du panier.';
$string['privacy:metadata:local_shopping_cart_history:payment'] = 'Type de paiement.';
$string['privacy:metadata:local_shopping_cart_history:paymentstatus'] = 'La transaction a-t-elle été réussie ou non ?';
$string['privacy:metadata:local_shopping_cart_history:usermodified'] = 'l\'utilisateur qui a effectué la transaction.';
$string['privacy:metadata:local_shopping_cart_history:timecreated'] = 'Heure de création de cette entrée.';
$string['privacy:metadata:local_shopping_cart_history:timemodified'] = 'Heure de modification de cette entrée.';
$string['privacy:metadata:local_shopping_cart_history:canceluntil'] = "Heure jusqu'à annulation.";
$string['privacy:metadata:local_shopping_cart_history:serviceperiodstart'] = 'La période pendant laquelle un article est consommé';
$string['privacy:metadata:local_shopping_cart_history:serviceperiodend'] = 'La période pendant laquelle un article est consommé';
$string['privacy:metadata:local_shopping_cart_history:area'] = 'Un composant peut fournir différentes zones avec des ID indépendants.';
$string['privacy:metadata:local_shopping_cart_history:usecredit'] = 'Stocker si des crédits ont été utilisés pour le paiement de cet article.';
$string['privacy:metadata:local_shopping_cart_history:costcenter'] = 'Le centre de coût de l\'article acheté si fourni par le plugin de l\'article.';
$string['privacy:metadata:local_shopping_cart_history:balance'] = 'Solde après cette réservation.';
$string['privacy:metadata:local_shopping_cart_history:annotiation'] = 'Annotation ou ID de commande.';
$string['privacy:metadata:local_shopping_cart_history:invoiceid'] = 'ID de facture de la plateforme de facturation';

$string['privacy:metadata:local_shopping_cart_credits'] = 'Crédits du panier d\'achat';
$string['privacy:metadata:local_shopping_cart_credits:userid'] = 'ID de l\'utilisateur concerné.';
$string['privacy:metadata:local_shopping_cart_credits:credits'] = 'Crédits.';
$string['privacy:metadata:local_shopping_cart_credits:currency'] = 'Devise dans laquelle il a été payé.';
$string['privacy:metadata:local_shopping_cart_credits:balance'] = 'Solde après cette réservation.';
$string['privacy:metadata:local_shopping_cart_credits:usermodified'] = 'l\'utilisateur qui a effectué la transaction.';
$string['privacy:metadata:local_shopping_cart_credits:timecreated'] = 'Heure de création de cette entrée.';
$string['privacy:metadata:local_shopping_cart_credits:timemodified'] = 'Heure de modification de cette entrée.';

$string['privacy:metadata:local_shopping_cart_ledger'] = "Ce grand livre ne prend en charge que l'insertion et fonctionne comme un enregistrement fiable de tous les paiements.";
$string['privacy:metadata:local_shopping_cart_ledger:userid'] = 'ID de l\'utilisateur qui a acheté l\'article.';
$string['privacy:metadata:local_shopping_cart_ledger:itemid'] = 'ID de l\'article acheté.';
$string['privacy:metadata:local_shopping_cart_ledger:itemname'] = 'Nom de l\'article acheté';
$string['privacy:metadata:local_shopping_cart_ledger:price'] = 'Le prix réellement payé de l\'article';
$string['privacy:metadata:local_shopping_cart_ledger:tax'] = 'Taxe appliquée à cet article';
$string['privacy:metadata:local_shopping_cart_ledger:taxpercentage'] = 'Taxe appliquée au prix de cet article en pourcentage flottant';
$string['privacy:metadata:local_shopping_cart_ledger:taxcategory'] = 'Catégorie de taxe définie pour cet article.';
$string['privacy:metadata:local_shopping_cart_ledger:discount'] = 'Remise donnée en montant absolu.';
$string['privacy:metadata:local_shopping_cart_ledger:credits'] = 'Crédits utilisés pour le paiement.';
$string['privacy:metadata:local_shopping_cart_ledger:fee'] = 'Les frais sont uniquement enregistrés lors de l\'annulation, lorsque le prix retourne à l\'utilisateur, mais des frais d\'annulation sont conservés.';
$string['privacy:metadata:local_shopping_cart_ledger:currency'] = 'Devise utilisée pour payer cet article.';
$string['privacy:metadata:local_shopping_cart_ledger:componentname'] = 'Nom du composant qui a fourni l\'article, comme mod_booking.';
$string['privacy:metadata:local_shopping_cart_ledger:costcenter'] = 'Le centre de coût de l\'article acheté si fourni par le plugin de l\'article.';
$string['privacy:metadata:local_shopping_cart_ledger:identifier'] = 'L\'identifiant est utilisé pendant le paiement pour identifier un panier entier.';
$string['privacy:metadata:local_shopping_cart_ledger:payment'] = 'Le type de paiement.';
$string['privacy:metadata:local_shopping_cart_ledger:paymentstatus'] = 'La transaction a-t-elle été réussie ou non ?';
$string['privacy:metadata:local_shopping_cart_ledger:accountid'] = 'ID du compte de paiement Moodle utilisé.';
$string['privacy:metadata:local_shopping_cart_ledger:usermodified'] = 'Quel utilisateur a effectivement effectué la transaction';
$string['privacy:metadata:local_shopping_cart_ledger:timemodified'] = 'L\'heure modifiée';
$string['privacy:metadata:local_shopping_cart_ledger:timecreated'] = 'L\'heure créée';
$string['privacy:metadata:local_shopping_cart_ledger:canceluntil'] = 'L\'heure d\'annulation';
$string['privacy:metadata:local_shopping_cart_ledger:area'] = 'Un composant peut fournir différentes zones avec des ID indépendants.';
$string['privacy:metadata:local_shopping_cart_ledger:annotation'] = 'Annotation ou ID de commande.';

$string['privacy:metadata:local_shopping_cart_invoices'] = 'Tableau pour les factures émises';
$string['privacy:metadata:local_shopping_cart_invoices:identifier'] = 'Référence à local_shopping_cart_ledger';
$string['privacy:metadata:local_shopping_cart_invoices:timecreated'] = 'Horodatage de la création de l\'enregistrement';
$string['privacy:metadata:local_shopping_cart_invoices:invoiceid'] = 'ID de facture de la plateforme de facturation';
