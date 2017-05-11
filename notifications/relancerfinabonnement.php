<?php
/**
 * Relancer le donnateur mensuel apres la fin de son don pour cause de CB perimee
 * pour l'inviter a souscrire a nouveau
 *
 * @plugin     abonnement
 * @copyright  2013
 * @author     Olivier Tétard
 * @licence    GNU/GPL
 * @package    SPIP\abonnement\Genie
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

/**
 * @param string $quoi
 * @param int $id_abonnement
 * @param array $options
 */
function notifications_relancerfinabonnement_dist($quoi, $id_abonnement, $options){

	$abonnement = sql_fetsel("*", "spip_abonnements", "id_abonnement=" . intval($id_abonnement));

	// on prend l'email de l'id_auteur
	$email = sql_getfetsel("email", "spip_auteurs", "id_auteur=" . intval($abonnement['id_auteur']));

	if ($email){
		$texte = recuperer_fond("notifications/relancer_fin_abonnement", array('id_abonnement' => $id_abonnement));

		include_spip('inc/notifications');
		notifications_envoyer_mails($email, $texte);
	}

}