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
 * Calculer le taux de TVA pour un abonnement donne
 * surchargeable
 * @param int $id_abonnement
 * @param int $id_abo_offre
 * @return float
 */
function inc_abos_taux_tva($id_abonnement, $id_abo_offre = 0){
	include_spip('base/abstract_sql');
	$taxe = '';
	if (!$id_abo_offre AND $id_abonnement){
		$id_abo_offre = sql_getfetsel("id_abo_offre", "spip_abonnements", "id_abonnement=" . intval($id_abonnement));
	}
	if ($id_abo_offre){
		$taxe = sql_getfetsel("taxe", "spip_abo_offres", "id_abo_offre=" . intval($id_abo_offre));
	}

	if (!strlen($taxe)){
		include_spip('inc/config');
		$taxe = lire_config('abos/taxe', 0.2);
	}

	$taxe = floatval($taxe);

	return $taxe;
}