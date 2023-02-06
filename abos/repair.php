<?php

/**
 * Reparer les abonnements
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
/**
 * Maintenance des abonnements
 * -> activer des abonnements payes mais pas actives (probleme technique)
 * -> purger les abonnements jamais confirmes (crees il y a plus de 2 jours, transaction abandonnee)
 * -> renouveler les abonnements echus mais valides (notification paybox qui n'est pas arrivee, mais debit ok car carte bleue ok)
 * -> passer les abos finis en resilies
 *
 */
function abos_repair_dist() {

	// activer des abonnements payes mais pas actives (probleme technique)
	/*
	include_spip('inc/bank');
	$res = sql_select("*","spip_abonnements","id_auteur>0 AND id_transaction_echeance>0 AND statut='prepa'");
	while ($row = sql_fetch($res)){
		if ($rowt = sql_fetsel("*","spip_transactions","id_transaction=".intval($row['id_transaction_echeance']))
		  AND $rowt['statut']=='ok'){

			// verifier qu'on a pas deja un abo OK sur cette transaction
			$ids = sql_allfetsel("id_abonnement","spip_abonnements_liens","objet='transaction' AND id_objet=".intval($row['id_transaction_echeance']));
			$ids = array_column($ids, 'id_abonnement');
			if (sql_countsel("spip_abonnements",sql_in('id_abonnement',$ids)." AND statut='ok'")){
				spip_log("annulation commande abonnement ".$row['id_abonnement'],'abos_reparer_cron'._LOG_INFO_IMPORTANTE);
				sql_updateq("spip_abonnements",array('id_transaction_echeance'=>0),"id_abonnement=".intval($row['id_abonnement']));
			}
			else {
				spip_log("activation/transaction ".$rowt['id_transaction'],'abos_reparer_cron'._LOG_INFO_IMPORTANTE);
				$config = bank_config($rowt['mode']);
				$activer_abonnement = charger_fonction('activer_abonnement','abos');
				$activer_abonnement($rowt['id_transaction'],$rowt['abo_uid '],$config['presta'],'',$row['id_auteur']);
			}
		}
	}
	*/

	// jeter les vieux abos pas souscrit finalement
	/*
	$debut = date('Y-m-d H:i:s',strtotime("-14 day"));
	$res = sql_select("*","spip_abonnements","date_debut<='$debut' AND statut='prepa'");
	while ($row = sql_fetch($res)){
		$rest = sql_select("*","spip_transactions","id_transaction=".intval($row['id_transaction_echeance']));
		if ($rowt = sql_fetch($rest)
		  AND $rowt['statut']!='ok'){
			sql_delete("spip_abonnements_liens","id_abonnement=".intval($row['id_abonnement'])." AND objet='transaction' AND id_objet=".$rowt['id_transaction']);
		  sql_delete("spip_transactions","id_transaction=".$rowt['id_transaction']);
		  sql_delete("spip_abonnements","id_abonnement=".intval($row['id_abonnement']));
		}
	}
	*/

	$compter = charger_fonction('compter', 'abos');
	$compter();


	$resilier = charger_fonction('resilier', 'abos');

	// marquer en resilies les abos finis
	$fin = date('Y-m-d H:i:s', strtotime('-1 day'));
	$abonnements = sql_allfetsel('id_abonnement', 'spip_abonnements', 'statut=' . sql_quote('ok') . ' AND date_fin<=' . sql_quote($fin) . ' AND date_fin>=date_debut');
	foreach ($abonnements as $abonnement) {
		$resilier($abonnement['id_abonnement'], ['immediat' => true, 'message' => 'resiliation auto abonnement fini', 'notify_bank' => false]);
		spip_log('resiliation auto abonnement fini (date fin) abo' . $abonnement['id_abonnement'], 'resiliation_auto' . _LOG_INFO_IMPORTANTE);
	}

	// marquer en resilies les abos dont echeance passee, sans fin connue
	$fin = date('Y-m-d H:i:s', strtotime('-2 day'));
	$abonnements = sql_allfetsel('id_abonnement', 'spip_abonnements', 'statut=' . sql_quote('ok') . ' AND date_echeance<=' . sql_quote($fin) . ' AND date_fin<date_debut');
	foreach ($abonnements as $abonnement) {
		$resilier($abonnement['id_abonnement'], ['immediat' => true, 'message' => 'resiliation auto abonnement fini', 'notify_bank' => false]);
		spip_log('resiliation auto abonnement fini (echeance impayee, pas de fin prevue) abo' . $abonnement['id_abonnement'], 'resiliation_auto' . _LOG_INFO_IMPORTANTE);
	}


	// marquer en resilie les souscriptions recurrentes mensuelles liees aux abonnements resilies
	if (test_plugin_actif('souscription')) {
		$ids = sql_allfetsel(
			'S.id_souscription',
			"spip_abonnements as A
		JOIN spip_abonnements_liens as L on (L.id_abonnement=A.id_abonnement)
		JOIN spip_transactions as T on (L.id_objet=T.id_transaction AND L.objet='transaction')
		JOIN spip_souscriptions AS S ON (S.id_souscription=T.tracking_id AND T.parrain='souscription')",
			"A.statut='resilie' AND S.abo_statut='ok'"
		);
		if (count($ids)) {
			$ids = array_column($ids, 'id_souscription');
			spip_log('Resilier souscriptions ' . implode(',', $ids) . ' car abos resilies', 'resiliation_auto' . _LOG_INFO_IMPORTANTE);
			$set = [
				'abo_statut' => 'resilie',
				'abo_fin_raison' => 'abonnement resilie',
			];
			sql_updateq('spip_souscriptions', $set, sql_in('id_souscription', $ids));
		}
	}

	// ne doit plus servir, couverture de bug
	// renouveler_abos_valides_echus();

	// selectionner les abos qui ont une date de fin pourrie
	/*
	$echeance = date('Y-m-d H:i:s',strtotime("-45 day"));
	$res = sql_select("*","spip_abonnements","date_fin<date_debut AND date_fin>='1999-01-01 00:00:00' AND date_fin<'2001-01-01 00:00:00' AND (date_echeance<='$echeance' OR statut='resilie')");
	while ($row = sql_fetch($res)){
		$set = array();
		if ($row['statut']=='resilie')
			$set["date_fin"]="date_echeance";
		else
			$set["date_fin"]="NULL";
		spip_log("Nettoyage vieil abonnement perime avec date_fin erronnee : ".$row['id_abonnement']." / ".$row['date_fin'],'abos_reparer_cron'._LOG_INFO_IMPORTANTE);
		sql_update("spip_abonnements",$set,"id_abonnement=".intval($row['id_abonnement']));
	}
	*/
}


/**
 * Renouveler les abos mensuels echus, mais encore valide
 * on ne renouvelle que ceux qui ont une date de fin (date de validite de la CB)
 * et paye par paybox
 * Ces abonnements devraient etre renouveles par notification paybox, mais qui n'est pas arrivee au serveur
 * @deprecated
 */
function renouveler_abos_valides_echus() {
	$renouveler = charger_fonction('renouveler', 'abos');
	$activer = charger_fonction('activer_abonnement', 'abos');

	$regler_transaction = charger_fonction('regler_transaction', 'bank');

	$echeance = date('Y-m-d H:i:s', strtotime('-2 day'));
	$now = date('Y-m-d H:i:s');
	$res = sql_select(
		'id_abonnement,abonne_uid,mode_paiement,date_echeance',
		'spip_abonnements',
		"date_echeance<'$echeance' AND date_fin>'$now' AND mode_paiement='paybox' AND duree_echeance='1 month'",
		'',
		'date_echeance'
	);

	while (sql_count($res)) {
		while ($row = sql_fetch($res)) {
			if ($last_trans = tester_offline($row['id_abonnement'])) {
				// verifier la date de paiement
				if ($last_trans['statut'] == 'ok' and $last_trans['date_transaction'] > date('Y-m-d H:i:s', strtotime('-4 day'))) {
					$id_transaction = $last_trans['id_transaction'];
				} else {
					$id_transaction = $renouveler($row['id_abonnement']);
					$res_prec = sql_select('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction));
					$row_prec = sql_fetch($res_prec);

					// verifier que la transaction n'est pas deja payee. Si c'est le cas

					$set = [
						'autorisation_id' => sql_quote('offline'),
						'mode' => sql_quote('paybox'),
						'montant_regle' => 'montant',
						'date_paiement' => sql_quote($row['date_echeance']),
						'statut' => sql_quote('ok'),
						'reglee' => sql_quote('oui'),
					];

					sql_update('spip_transactions', $set, 'id_transaction=' . intval($id_transaction));

					$regler_transaction($id_transaction, ['row_prec' => $row_prec, 'notifier' => false]);
				}

				$activer($id_transaction, $row['abonne_uid'], $row['mode_paiement']);
				spip_log('Renouvellement abonnement ' . $row['id_abonnement'] . '/' . $id_transaction, 'abos_reparer_cron' . _LOG_INFO_IMPORTANTE);
			}
		}
		$res = sql_select(
			'id_abonnement,abonne_uid,mode_paiement,date_echeance',
			'spip_abonnements',
			"date_echeance<'$echeance' AND date_fin>'$now' AND mode_paiement='paybox'",
			'',
			'date_echeance'
		);
	}
}


/**
 * Verifier qu'un abonnement a bien recu des transactions reelles recemment sinon il est douteux, et on le resilie
 * @param int $id_abonnement
 * @return array|bool
 */
function tester_offline($id_abonnement) {
	static $done = [];

	// si deja passe par la avec un false et resiliation on ne ressaye pas
	if (isset($done[$id_abonnement]) and !$done[$id_abonnement]) {
		return $done[$id_abonnement];
	}

	//prendre les deux dernieres transactions
	$res = sql_select('id_objet AS id_transaction', 'spip_abonnements_liens', 'id_abonnement=' . sql_quote($id_abonnement) . " AND objet='transaction'", '', 'date DESC', '0,6');
	while ($row = sql_fetch($res)) {
		$id_transaction = $row['id_transaction'];
		$trans = sql_fetsel('id_transaction,statut,date_transaction,autorisation_id', 'spip_transactions', 'id_transaction=' . intval($id_transaction));
		if ($trans['autorisation_id'] != 'offline' and $trans['statut'] == 'ok') {
			return $trans;
		}
	}

	// on a pas trouvee de vrai transaction recette, on le resilie
	$resilier = charger_fonction('resilier', 'abos');
	$resilier($id_abonnement, ['immediat' => true, 'message' => 'resiliation auto des offline douteux', 'notify_bank' => false]);
	spip_log('resiliation auto offline abo' . $id_abonnement, 'resiliation_auto_offline' . _LOG_INFO_IMPORTANTE);

	return false;
}
