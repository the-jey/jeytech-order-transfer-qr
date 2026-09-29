=== JeyTech Order Transfer QR for WooCommerce ===
Contributors: jeytech
Tags: woocommerce, bank transfer, sepa, qr code, payments
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Un QR SEPA généré localement pour les commandes impayées par virement, avec le montant exact en euros et la référence de commande préremplis.

== Description ==

Les clients qui paient par virement recopient généralement le bénéficiaire, l’IBAN, le montant et la référence à la main. JeyTech Order Transfer QR ajoute un QR EPC/GiroCode à côté de ces informations lisibles sur la page de confirmation et dans l’e-mail client des commandes en attente.

Le client le scanne avec une application bancaire compatible, vérifie les informations et confirme le virement dans cette application. L’extension prépare le virement. Elle ne traite aucun paiement, ne se connecte à aucune banque, ne rapproche aucun règlement et ne marque aucune commande comme payée.

* Utilise les comptes bancaires déjà configurés dans WooCommerce. Choisissez explicitement le compte bénéficiaire.
* Vérifie le pays SEPA, la longueur et la clé de contrôle de l’IBAN, le nom du bénéficiaire et le BIC.
* Utilise le montant exact de la commande en EUR et une référence configurable contenant `{order_number}`.
* Affiche un QR uniquement pour les commandes en attente réglées par virement bancaire WooCommerce (BACS).
* Fonctionne avec HPOS et le stockage classique des commandes. Utilise le point d’accroche WooCommerce pris en charge par les pages de confirmation classiques et à blocs.
* Conserve toutes les informations de paiement lisibles à côté du QR. Les e-mails au format texte contiennent ces informations sans image.
* Interface traduisible et instructions client suivant la langue de l’e-mail ou du site. WordPress distribue les traductions disponibles dans ses paquets de langue.
* Génère les images PNG sur votre propre serveur WordPress, sans GD, Imagick, service QR externe ni suivi.

Les URL des images sont signées, expirent après 30 jours et sont liées à la clé de commande et aux informations exactes de paiement. Elles cessent de fournir un QR lorsque la commande est payée ou annulée, que son montant ou sa référence change, ou que le compte bancaire sélectionné change. Les images déjà enregistrées ou mises en cache par un fournisseur d’e-mail ne peuvent pas être retirées. Aucun fichier QR public n’est enregistré dans le dossier des téléversements.

Toutes les applications bancaires ne prennent pas en charge EPC/GiroCode. Testez le QR avec les banques de vos clients avant de l’activer. Les clients peuvent toujours saisir les mêmes informations manuellement.

Nécessite WooCommerce 9.6 ou ultérieur et l’extension zlib de PHP. Les paiements par QR EPC utilisent l’EUR. Le BIC est obligatoire pour un bénéficiaire situé hors de l’EEE. Les autres devises, les factures QR suisses, SPAYD et la confirmation automatique du paiement ne sont pas pris en charge dans cette version.

== Installation ==

1. Installez et activez WooCommerce, puis installez et activez cette extension.
2. Configurez le nom du bénéficiaire, l’IBAN et le BIC dans les réglages du virement bancaire de WooCommerce.
3. Ouvrez WooCommerce > Order Transfer QR. Choisissez le compte bénéficiaire, conservez `{order_number}` dans la référence et activez les emplacements d’affichage souhaités.
4. Enregistrez. Utilisez une commande de test BACS en EUR pour vérifier la page de confirmation, l’e-mail client et le QR décodé avant toute activation sur une boutique en production.

== Frequently Asked Questions ==

= Le scan du QR confirme-t-il le paiement ? =
Non. Il préremplit les informations de virement dans une application bancaire compatible. Le client doit encore autoriser le virement dans cette application et le marchand doit confirmer sa réception selon sa procédure habituelle.

= Pourquoi aucun QR n’apparaît-il ? =
Vérifiez que l’affichage est activé, qu’un compte bancaire valide est sélectionné, que la commande est en attente, que son moyen de paiement est BACS et que sa devise est EUR. Les informations de paiement non valides ou trop volumineuses sont ignorées plutôt que de produire un QR incorrect. Une modification des coordonnées bancaires impose de sélectionner à nouveau le compte.

= Que se passe-t-il si je réordonne les comptes bancaires de WooCommerce ? =
Le bénéficiaire sélectionné reste identique. La sélection dépend du nom du compte, de l’IBAN et du BIC, et non de sa position dans la liste.

= Que se passe-t-il si une autre extension modifie les coordonnées BACS d’une commande ? =
L’extension respecte le filtre des comptes de WooCommerce. Si le compte sélectionné est retiré ou que les champs IBAN/BIC finaux sont modifiés, elle omet le QR pour éviter toute contradiction avec les coordonnées affichées par WooCommerce.

= Pourquoi l’image QR d’un ancien e-mail ne se charge-t-elle plus ? =
Le lien peut avoir expiré ou les informations de commande ou de paiement peuvent avoir changé. Si la commande remplit toujours les conditions, rouvrir sa page de confirmation produit un nouveau lien. Les images mises en cache peuvent rester visibles ; vérifiez toujours la commande et les informations de paiement actuelles avant tout virement.

= Puis-je aussi utiliser Unpaid Transfer Guard ? =
Oui. Cette extension ne modifie ni le statut des commandes, ni le stock, ni les règles d’annulation. Lorsqu’une commande est annulée, son lien QR signé cesse de fournir une image.

= L’extension envoie-t-elle des données à un service QR ? =
Non. Le PNG est généré par votre serveur WordPress. Un serveur mandataire d’images d’e-mail peut récupérer l’URL signée lorsque le client ouvre son e-mail, comme pour les autres images d’e-mail hébergées.

= Comment obtenir de l’assistance ? =
Utilisez le forum d’assistance WordPress.org après publication ou contactez support@jeytech.app. Indiquez les versions de l’extension, de WordPress, de WooCommerce et de PHP. Ne publiez aucune clé de commande ni URL d’image signée dans une capture publique.

= Comment obtenir la traduction française ? =
Les traductions sont gérées sur translate.wordpress.org et distribuées par les mises à jour de langue de WordPress après validation. Le ZIP de l’extension ne contient aucun fichier de traduction. Sans paquet de langue disponible, l’interface utilise l’anglais. Les captures françaises utilisent un paquet de développement installé séparément.

== Screenshots ==

1. Configuration et validation bancaire en anglais.
2. Confirmation de commande avec QR et informations de virement lisibles en anglais.
3. E-mail réel WooCommerce des commandes en attente, en anglais.
4. Configuration et validation bancaire en français.
5. Confirmation de commande avec QR et informations de virement lisibles en français.
6. E-mail réel WooCommerce des commandes en attente, en français.

== Changelog ==

= 1.0.0 =
* Première version : QR EPC local, montant exact en EUR et référence de commande, sélection du bénéficiaire validée, images signées, intégration aux confirmations classiques et à blocs et aux e-mails client, prise en charge des paquets de langue.

== Third-party code ==

L’encodeur inclut des parties de BaconQrCode 2.0.8 et DASPRiD Enum 1.0.7 avec des espaces de noms isolés, sous licence BSD 2-Clause. Les mentions de droits d’auteur et les licences sont incluses dans vendor/. Les sources et les modifications locales sont documentées dans THIRD_PARTY.txt. Aucune bibliothèque n’est téléchargée à l’exécution.
