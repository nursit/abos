<?php
/**
 * Fonctions utiles
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Inc
 */

/**
 * Mise en forme des logs abonnement
 * @param $abo_log
 * @return string
 */
function abos_log($abo_log){
	$par = "";
	if (isset($GLOBALS['visiteur_session']['id_auteur'])){
		$par = _T('public:par_auteur').' #'.$GLOBALS['visiteur_session']['id_auteur'].' '.$GLOBALS['visiteur_session']['nom'];
	}
	else {
		$par = _T('public:par_auteur').' '.$GLOBALS['ip'];
	}

	$abo_log = date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME'])." | "
		. $par
		.' : '.$abo_log . "\n--\n";
	return $abo_log;
}

/**
 * Creer la transaction en statut commande pour un abonnement
 * @param int $id_abonnement
 * @param null $prix_abo
 * @return int
 */
function abos_creer_transaction($id_abonnement=0,$prix_abo=null, $id_transaction=0){

	$options = array();
	if (isset($_COOKIE['affiliate'])
		AND strlen($_COOKIE['affiliate'])){
		$options['parrain'] = $_COOKIE['affiliate'];
		if (isset($_COOKIE['affiliate_tracking']) && intval($_COOKIE['affiliate_tracking']))
			$options['tracking_id'] = $_COOKIE['affiliate_tracking'];
	}

	if ($id_abonnement=intval($id_abonnement)
	  AND $row = sql_fetsel("*","spip_abonnements","id_abonnement=".intval($id_abonnement))){

		$options['id_auteur'] = $row['id_auteur'];
		$total = (is_null($prix_abo)?$row['prix_echeance']:$prix_abo);

		$abos_taux_tva = charger_fonction("abos_taux_tva","inc");
		$taux_tva = $abos_taux_tva($id_abonnement);
		// on prend la TVA en compte ici
		$total_ht = $total / (1.0 + $taux_tva);
		$options['montant_ht'] = round($total_ht,2);

		// ouvrir la transaction si besoin
		if (!$id_transaction
		  OR !$id_transaction = sql_getfetsel("id_transaction","spip_transactions","id_transaction=".intval($id_transaction)." AND statut=".sql_quote('commande'))){
			$inserer_transaction = charger_fonction("inserer_transaction","bank");
			$id_transaction = $inserer_transaction($total,$options);
		}
		else {
			$set = $options;
			$set['montant'] = $total;
			sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));
		}

		if (!$id_transaction) return 0;

		sql_insertq('spip_abonnements_liens',array('id_abonnement'=>$id_abonnement,'id_objet'=>$id_transaction,'objet'=>'transaction','date'=>$row['date_echeance']));
		return $id_transaction;
	}

	return 0;
}