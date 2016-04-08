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
 * @return float
 */
function inc_abos_taux_tva($id_abonnement){
	$id_abo_offre = sql_getfetsel("id_abo_offre","spip_abonnements","id_abonnement=".intval($id_abonnement));
	$tva = sql_getfetsel("taux_tva","spip_abo_offres","id_abo_offre=".intval($id_abo_offre));

	if (!strlen($tva)){
		$tva = 0.2;
	}
	else {
		$tva = floatval($tva);
	}

	return $tva;
}