<?php
/**
 * Abonnement rapide
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\action
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function action_abonnementrapide_dist($arg=null){

	if ($GLOBALS['visiteur_session']['id_auteur'] OR _request('anonyme')){
		if (!$id_abo_offre = intval(_request('id_abo_offre'))
			OR !$abonner = charger_fonction('abonner', 'abos')
			OR !$res = $abonner($id_abo_offre)
		){
			spip_log('Erreur interne : impossible de creer un abo et sa commande');
		}
		list($id_transaction, $id_abonnement) = $res;
		$_SESSION['id_transaction'] = $id_transaction;
	}

}