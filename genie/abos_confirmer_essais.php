<?php

/**
 * Confirmer les abonnements en essai depuis 15minutes
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Genie
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('base/abstract_sql');
include_spip('inc/filtres');

// precaution, mais le cron n'est lance que si la constante de definie
if (!defined('_DUREE_CONSULTATION_ESSAI')) {
	define('_DUREE_CONSULTATION_ESSAI', 15 * 60);
} // 15 minutes

/**
 * Confirmer les abonnements en cours d'essai,
 * une fois le delai de 15min ecoule
 *
 */
function genie_abos_confirmer_essais_dist() {

	$elapsed = date('Y-m-d H:i:s', time() - _DUREE_CONSULTATION_ESSAI);

	// sur les abonnements en essai abonne_uid = id_transaction_essai
	$res = sql_select(
		'A.*,T.pay_id',
		'spip_abonnements AS A JOIN spip_transactions AS T ON T.id_transaction=A.abonne_uid',
		'A.id_transaction_essai>0 AND A.statut=\'prepa\' AND T.statut=\'ok\' AND T.date_paiement<' . sql_quote($elapsed)
	);

	$n = 0;
	while ($row = sql_fetch($res)) {
		#var_dump($row);

		$ppps = $row['pay_id'];
		$refabonne = $row['abonne_uid'];

		$r = sql_fetsel('id_transaction,transaction_hash,statut,date_transaction,date_paiement', 'spip_transactions', 'id_transaction=' . intval($row['id_transaction_echeance']));
		#var_dump($r);

		// si la transaction est deja en echec, c'est qu'on a une tentative precedente refusee
		// on essaye pas a moins de 24h d'intervalle
		if (strncmp($r['statut'], 'echec', 5) == 0) {
			// si la transaction date de plus de 4j on a fait au moins 3 tentatives de paiement
			// on abandonne en resiliant l'abonnement
			$old = date('Y-m-d H:i:s', strtotime('-4 day'));
			if ($r['date_transaction'] < $old) {
				spip_log('Essai annule : trop de tentatives de paiement refuse ' . $row['id_abonnement'] . '/' . $row['abonne_uid'] . '/' . $r['id_transaction'], 'confirmeressais' . _LOG_INFO_IMPORTANTE);
				sql_updateq('spip_abonnements', ['statut' => 'resilie'], 'id_abonnement=' . intval($row['id_abonnement']));
				continue;
			}

			$last = date('Y-m-d H:i:s', strtotime('-24 hour'));
			// on ne traite pas cette transaction pour le moment
			if ($r['date_paiement'] > $last) {
				spip_log('Essai en attente (derniere tentative du ' . $r['date_paiement'] . ' refusee) ' . $row['id_abonnement'] . '/' . $row['abonne_uid'] . '/' . $r['id_transaction'], 'confirmeressais' . _LOG_INFO_IMPORTANTE);
				continue;
			}
		}

		spip_log('Confirmer Essai ' . $row['id_abonnement'] . '/' . $row['abonne_uid'] . '/' . $r['id_transaction'], 'confirmeressais' . _LOG_INFO_IMPORTANTE);
		$n++;

		$call_directplus = charger_fonction('directplus', 'presta/paybox/call');
		$out = $call_directplus($r['id_transaction'], $r['transaction_hash'], $refabonne, $ppps);
		#var_dump($out);
	}
	spip_log("$n essais confirmes", 'confirmeressais');
	return 1;
}
