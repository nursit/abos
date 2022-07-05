<?php

/**
 * Renouveler un abonnement suite au paiement reussi d'une echeance
 *
 * recuperer la transaction et son abonnement associe par id_transaction ou par abo_uid
 * et verifier que c'est bien le bon
 * noter sur l'abonnement que le paiement reussi,
 * et si besoin repousser sa date de fin et/ou d'echeance
 * pour qu'il reste actif jusqu'au prochain paiement
 *
 * @plugin     bank
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\API
 */

if (!defined('_ECRIRE_INC_VERSION')) { return;
}

include_spip('base/abstract_sql');

/**
 * @param int $id_transaction
 * @param string $abo_uid
 *   numero d'abonne chez le presta bancaire
 * @param string $mode_paiement
 *   mode de paiement (presta bancaire)
 * @param string $validite
 * @return bool|int
 *   false si pas reussi
 */
function abos_renouveler_abonnement_dist($id_transaction, $abo_uid, $mode_paiement, $validite = '') {

	spip_log("abos/renouveler_abonnement id_transaction=$id_transaction abo_uid=$abo_uid mode=$mode_paiement", 'bank');

	$id_abonnement = 0;

	// recuperer la transaction et son abonnement associe
	// et verifier que c'est bien le bon
	if ($id_transaction) {
		if (!$trans = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction))) {
			spip_log("abos_renouveler_abonnement_dist: transaction $id_transaction inconnue", 'abo_erreurs' . _LOG_ERREUR);
			return false;
		}

		if ($trans['statut'] == 'commande') {
			spip_log("abos_renouveler_abonnement_dist: La transaction $id_transaction n'a pas ete reglee (abo $abo_uid)", 'abo_erreurs' . _LOG_ERREUR);
			return false;
		}

		if (!$id_commande = $trans['id_commande']) {
			spip_log("abos_renouveler_abonnement_dist: La transaction $id_transaction n'a pas de id_commande associee (abo $abo_uid)", 'abo_erreurs' . _LOG_ERREUR);
			return false;
		}

		if (!$abo_uid and $trans['abo_uid']) {
			$abo_uid = $trans['abo_uid'];
		}

		if (
			!$abo_uid
			or !$abo = sql_fetsel('*', 'spip_abonnements', 'abonne_uid=' . sql_quote($abo_uid))
			or !$id_abonnement = $abo['id_abonnement']
		) {
			spip_log("abos_renouveler_abonnement_dist: Impossible de retrouver l'abo lie a la transaction $id_transaction / abonne_uid $abo_uid", 'abo_erreurs' . _LOG_ERREUR);
			return false;
		}

		if ($abo['statut'] == 'prepa') {
			spip_log("abos_renouveler_abonnement_dist: Abonnement #$id_abonnement encore en statut=prepa (transaction $id_transaction / abonne_uid $abo_uid)", 'abo_erreurs' . _LOG_ERREUR);
			return false;
		}

		// si la transaction est reglee et liee a une souscription, on appelle le pipeline bank_abos_renouveler_abonnement
		// pour que la souscription se mette a jour
		if ($trans['parrain'] == 'souscription' and $trans['statut'] === 'ok') {
			$id_abonnement = pipeline(
				'bank_abos_renouveler_abonnement',
				[
					'args' => [
						'id_transaction' => $id_transaction,
						'abo_uid' => $abo_uid,
						'mode_paiement' => $mode_paiement,
					],
					'data' => $id_abonnement,
				]
			);
		}

		if (
			!$id_abonnement
			or !$abo = sql_fetsel('*', 'spip_abonnements', 'id_abonnement=' . intval($id_abonnement))
		) {
			spip_log("abos_renouveler_abonnement_dist: plus rien a faire, bank_abos_renouveler_abonnement a retourne $id_abonnement pour $id_transaction / abonne_uid $abo_uid", 'abo_erreurs' . _LOG_ERREUR);
		}

		$set = [];

		if ($abo['duree_echeance'] === '1 month') {
			$prochaine_echeance = $abo['date_echeance'];
			$time_ref = strtotime($trans['date_paiement']);
			if (!$time_ref) {
				$time_ref = strtotime($trans['date_transaction']);
			}
			if (!$time_ref) {
				$time_ref = $_SERVER['REQUEST_TIME'];
			}

			$datep15 = date('Y-m-d H:i:s', strtotime('+15 day', $time_ref));

			// retablir un abo qui avait ete resilie a tort (puisqu'on a un paiement)
			if ($abo['statut'] == 'resilie') {
				$prochaine_echeance = $abo['date_debut']; // on recalcul l'echeance depuis le debut
				$set['date_fin'] = '0000-00-00 00:00:00';
				$set['statut'] = 'ok';
				if ($validite) {
					if ($validite !== 'echeance') {
						$set['date_fin'] = $validite;
					}
				}
				elseif ($validite = $trans['validite']) {
					$d = date($validite . '-d H:i:s', strtotime($abo['date_debut']));
					$d = strtotime($d);
					$d = strtotime('+1 month', $d);
					$set['date_fin'] = date('Y-m-d H:i:s', $d);
				}
				$datep15 = date('Y-m-d H:i:s', strtotime('+5 day'));
			}

			// recaler la prochaine echeance si trop en avance (double appel anterieur ou erreur de calcul)
			while ($prochaine_echeance > $datep15) {
				$prochaine_echeance = date('Y-m-d H:i:s', strtotime('-' . $abo['duree_echeance'], strtotime($prochaine_echeance)));
			}
			// l'incrementer pour atteindre celle du mois prochain
			while ($prochaine_echeance < $datep15) {
				$prochaine_echeance = date('Y-m-d H:i:s', strtotime('+' . $abo['duree_echeance'], strtotime($prochaine_echeance)));
			}
			$set['date_echeance'] = $prochaine_echeance;
		}
		else {
			$prochaine_echeance = $abo['date_echeance'];
			$prochaine_echeance = date('Y-m-d H:i:s', strtotime('+' . $abo['duree_echeance'], strtotime($prochaine_echeance)));
			$set['date_echeance'] = $prochaine_echeance;
		}

		sql_updateq('spip_abonnements', $set, 'id_abonnement=' . intval($abo['id_abonnement']));
	}

	return $id_abonnement;
}
