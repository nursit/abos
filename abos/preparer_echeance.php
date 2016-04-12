<?php
/**
 * Renouveler un abonnement
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\API
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');
/**
 * TODO : revoir lien abonnement-transaction qui doit passer par la commande
 * @param $id
 * @return bool|int
 */
function abos_preparer_echeance_dist($id){
	spip_log("abos/preparer_echeance id=$id","bank");

	if (strncmp($id,"uid:",4)==0){
		$where = "abonne_uid=".sql_quote(substr($id,4));
	}
	else {
		$where = "id_abonnement=".intval($id);
	}


	$row = sql_fetsel("id_transaction_echeance,id_abonnement","spip_abonnements",$where);
	$id_abonnement = $row['id_abonnement'];

	if (!$row)
		return false;

	// si deja une transaction echeance, verifier si encore en commande et recente => on peut la recycler
	if ($row['id_transaction_echeance']) {
		$trans = sql_fetsel("id_transaction",
			"spip_transactions",
			"id_transaction=".intval($row['id_transaction_echeance'])." AND statut=".sql_quote("commande")." AND date_transaction>".sql_quote(date('Y-m-d H:i:s',strtotime("-2 day"))));
		if ($trans)
			return $row['id_transaction_echeance'];
		// remettre transaction_echeance a 0 car sinon c'est elle qui va encore ressortir en dessous
		sql_updateq("spip_abonnements",array("id_transaction_echeance"=>0),"id_abonnement=".intval($id_abonnement)." AND id_transaction_echeance=".intval($row['id_transaction_echeance']));
	}


	// creer la transaction correspondante
	include_spip('inc/abos');
	$id_transaction = abos_creer_transaction($id_abonnement);

	if (!$id_transaction) {
		return false;
	}
	else {
		// marquer la transaction/echeance
		sql_updateq("spip_abonnements",array("id_transaction_echeance"=>$id_transaction),"id_abonnement=".intval($id_abonnement)." AND id_transaction_echeance=0");

		// verifier
		$res = sql_select("id_transaction_echeance","spip_abonnements","id_abonnement=".intval($id_abonnement));
		if (!$row = sql_fetch($res)
		  OR $row['id_transaction_echeance']!=$id_transaction) {
			sql_delete("spip_transactions","id_transaction=".intval($id_transaction));
			sql_delete("spip_abonnements_liens","objet='transaction' AND id_objet=".intval($id_transaction)." AND id_abonnement=".intval($id_abonnement));
			return $row['id_transaction_echeance']?$row['id_transaction_echeance']:false;
		}

		// repercuter id_transaction_echeance sur la souscription eventuelle
		$ids = sql_allfetsel('id_objet','spip_abonnements_liens',"objet='transaction' AND id_abonnement=".intval($id_abonnement));
		$ids = array_map('reset',$ids);
		if ($id_souscription = sql_getfetsel("tracking_id","spip_transactions","parrain=".sql_quote('souscription')." AND ".sql_in('id_transaction',$ids))){
			sql_updateq("spip_souscriptions",array('id_transaction_echeance'=>$id_transaction),"id_souscription=".intval($id_souscription)." AND id_transaction_echeance=0 AND abo_statut='ok'");
		}
	}
	return $id_transaction;
}
