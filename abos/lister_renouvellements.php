<?php
/**
 * Lister les abonnements a renouveler
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\API
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('base/abstract_sql');
function abos_lister_renouvellements_dist($date, $mode = "", $mode_echeance = 'tacite', $days = null){
	if (is_null($days)){
		$days = 2;
	}

	// trouver les echeances qui arrivent d'ici $days jours
	// (ou qui sont deja passees)
	$days = intval($days);
	$echeance = date('Y-m-d H:i:s', strtotime("+$days day"));
	$res = sql_select("*", "spip_abonnements",
		"date_echeance<=" . sql_quote($echeance)
		. ($mode ? " AND mode_paiement=" . sql_quote($mode) : "")
		. " AND mode_echeance=" . sql_quote($mode_echeance)
		//." AND id_transaction_echeance=0" # desactive pour les tests
		. " AND statut=" . sql_quote('ok')
		. " AND (date_fin IS NULL OR date_fin>date_echeance OR date_fin<date_debut)");

	$liste = array();
	while ($row = sql_fetch($res)){
		$liste[$row['id_abonnement']] = array('montant' => $row['prix_echeance'], 'uid' => $row['abonne_uid']);
	}

	return $liste;
}