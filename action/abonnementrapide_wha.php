<?php
/**
 * Abonnement rapide par WHA
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\action
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function action_abonnementrapide_wha_dist($arg=null){

	if (!$id_abo_offre=intval(_request('id_abo_offre'))
	 OR !$abonner = charger_fonction('abonner','abos')
	 OR !$res = $abonner($id_abo_offre)
	 OR !$partnerId = _request('m')
	 OR !$row = sql_fetsel("wha_oid","spip_abo_offres","id_abo_offre=".intval($id_abo_offre)." AND statut='publie'")) {
		spip_log('Erreur interne : impossible de creer un abo et sa commande');
		die('Erreur interne');
	}

	list($id_transaction,$id_abonnement) = $res;
	$_SESSION['id_transaction'] = $id_transaction;

	// creer l'url d'abo wha
	include_spip('presta/internetplus/inc/wha_services');
	$id_rubrique = _request('id_rubrique');
	$url = wha_url_abonnement($row['wha_oid'],$id_transaction,$partnerId,$partnerId,array('retour'=>'orange','id_rubrique'=>$id_rubrique),$partnerId==_WHA_NODE_ABO_URL);

	include_spip('inc/headers');
	redirige_par_entete($url);
}
