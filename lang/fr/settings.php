<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Schplurtz le Déboulonné <Schplurtz@laposte.net>
 */
$lang['smtp_host']             = 'Votre serveur SMTP sortant.';
$lang['smtp_port']             = 'Port d\'écoute du serveur SMTP. Habituellement 25. 465 pour SSL';
$lang['smtp_ssl']              = 'Type de chiffrement utilisé lors des communications avec votre serveur SMTP.';
$lang['smtp_ssl_o_']           = 'aucun';
$lang['smtp_ssl_o_ssl']        = 'SSL';
$lang['smtp_ssl_o_tls']        = 'TLS';
$lang['smtp_allow_insecure']   = 'Accepter un certificat serveur invalide ou auto-signé ? N\'activez cette option que si vous faites confiance au serveur SMTP en dépit de son certificat non fiable.';
$lang['auth_user']             = 'SI une authentification est nécessaire, indiquez ici le nom d\'utilisateur.';
$lang['auth_pass']             = 'Mot de passe du compte ci dessus.';
$lang['localdomain']           = 'Nom à utiliser durant la phase HELO du protocole SMTP. Devrait être le FQDN du serveur web sur lequel DokuWiki fonctionne. Laisser vide pour une détection automatique.';
$lang['debug']                 = 'Afficher un journal d\'erreur complet en cas d\'échec d\'envoi. Désactiver lorsque tout fonctionne.';
