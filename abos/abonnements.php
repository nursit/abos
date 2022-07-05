<?php

/**
 * Lister les abonnements actifs pour un auteur
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\API
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('base/abstract_sql');
function abos_liste_abos_actifs($id_auteur) {
	static $liste = [];
	if (!isset($liste[$id_auteur])) {
		// regarder les abonnements illimites
		$now = date('Y-m-d H:i:s');
		$res = sql_select('id_abonnement,credits', 'spip_abonnements', 'id_auteur=' . intval($id_auteur) . " AND (statut='ok' AND (date_fin>" . sql_quote($now) . ' OR date_fin IS NULL OR date_fin<date_debut))');
		$liste[$id_auteur] = [];
		while ($row = sql_fetch($res)) {
			$liste[$id_auteur][$row['id_abonnement']] = unserialize($row['credits']);
		}

		// si aucun abo, regarder si un abo en test est dispo
		// se caracterise par abo='prepa' et id_transaction_essai>0
		// lie a transaction plus recente que _DUREE_CONSULTATION_ESSAI + une marge
		if (
			!count($liste[$id_auteur])
			and defined('_DUREE_CONSULTATION_ESSAI')
			and defined('_DUREE_CONSULTATION_ESSAI_DELTA')
		) {
			$now = date('Y-m-d H:i:s', time() - _DUREE_CONSULTATION_ESSAI - _DUREE_CONSULTATION_ESSAI_DELTA);
			$res = sql_select(
				'A.*',
				'spip_abonnements AS A JOIN spip_transactions AS T ON T.id_transaction=A.id_transaction_essai',
				'A.id_transaction_essai>0 AND A.id_auteur=' . intval($id_auteur) . " AND A.statut='prepa' AND T.id_auteur=" . intval($id_auteur)
				. " AND T.statut='ok' AND T.date_paiement>='$now'"
			);
			while ($row = sql_fetch($res)) {
				$liste[$id_auteur][$row['id_abonnement']] = unserialize($row['credits']);
			}
		}
	}
	return $liste[$id_auteur];
}
