<?php
/**
 * Decrire une echeance abonnement pour le paiement
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
 *
 * @param int $id_transaction
 * @param bool $force_auto
 *   true : l'echeance sera forcement prelevee automatiquement
 *   false : on peut gerer le paiement echeance manuellement en renvoyant un montant nul
 * @return array|bool
 */
function abos_decrire_echeance_dist($id_transaction,$force_auto = true){

	$desc = array(
		'montant' => 0,
		'freq' => 1, // en nombre de mois
	);

	if ($id_abonnement = sql_getfetsel("id_abonnement","spip_abonnements_liens","objet='transaction' AND id_objet=".intval($id_transaction))
	  AND $abo = sql_fetsel('*','spip_abonnements','id_abonnement='.intval($id_abonnement))
		AND $offre = sql_fetsel('*','spip_abo_offres','id_abo_offre='.intval($abo['id_abo_offre']))
	  ){

		$desc['montant'] = $abo['prix_echeance'];
		if (strtotime($abo['date_debut'])>time()){
			$desc['date_start'] = $abo['date_debut'];
		}
		if (isset($offre['wha_oid']))
			$desc['wha_oid'] = $offre['wha_oid'];

		return $desc;
	}
	elseif($id_abonnement = sql_getfetsel("id_abonnement","spip_abonnements","id_transaction_essai=".intval($id_transaction))){
		// pas de prelevement auto, on gere manuellement
		// tant pis si pas possible...
		$desc['montant'] = 0;
		return $desc;
	}

	return false;
}
