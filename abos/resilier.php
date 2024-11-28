<?php

/**
 * Resilier un abonnement
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
 * @param $id
 * @param array $options
 *   bool immediat
 *   string message
 *   bool notify_bank
 * @return bool
 */
function abos_resilier_dist($id, $options = []) {
	$abo_log = (isset($options['message']) ? $options['message'] : '');
	$immediat = (isset($options['immediat']) ? $options['immediat'] : false);
	$graceful = (isset($options['graceful']) ? $options['graceful'] : false);
	$notify_bank = (isset($options['notify_bank']) ? $options['notify_bank'] : true);
	$erreur = (isset($options['erreur']) ? $options['erreur'] : false);

	if (!$abo_log) {
		$abo_log = 'Resiliation';
		$abo_log .= ($immediat ? ' (Immediat)' : ' (A echeance)');
	}

	$id_abonnement = $id;
	if (!is_numeric($id)) {
		$uid = $id;
		if (strncmp($uid, 'uid:', 4) == 0) {
			$uid = substr($uid, 4);
		}
		$row = sql_fetsel('*', 'spip_abonnements WHERE abonne_uid=' . sql_quote($uid));
		$id_abonnement = $row['id_abonnement'];
	} else {
		$row = sql_fetsel('*', 'spip_abonnements WHERE id_abonnement=' . intval($id_abonnement));
	}

	$ok = true;
	if (defined('_TEST_BLOCK_ABOS')) {
		spip_log('Resiliations bloquees par _TEST_BLOCK_ABOS', 'abos_resil' . _LOG_INFO_IMPORTANTE);
		$ok = _TEST_BLOCK_ABOS;
	} else {
		// notifier au presta bancaire si besoin
		if ($notify_bank) {
			$ok = '?';
			// recuperer le presta par le mode en base
			include_spip('inc/bank');
			$config = bank_config($row['mode_paiement'], true);
			$presta = $config['presta'];

			if ($presta_resilier = charger_fonction('resilier_abonnement', "presta/$presta/call", true)) {
				$ok = $presta_resilier($row['abonne_uid']);
				if (!$ok) {
					spip_log("Resiliation abo $id_abonnement refuse par le prestataire", 'abos_resil' . _LOG_ERREUR);
				}
			}

			if (!$ok or $ok === '?') {
				// TODO ajouter un message a l'abonnement pour le feedback user
				bank_simple_call_resilier_abonnement($row['abonne_uid'], $row['mode_paiement']);
				spip_log('Envoi email de desabo ' . $row['abonne_uid'] . ' au webmestre', 'abos_resil' . _LOG_INFO_IMPORTANTE);


				// neanmoins, si plus d'echeance prevue, on peut finir
				// (cas d'un abos deja resilie fin de mois qu'on veut forcer a resilier immediatement)
				if (
					$row['date_echeance'] >= $row['date_fin']
					and $row['date_fin'] > $row['date_debut']
				) {
					$ok = true;
				} elseif ($row['date_echeance'] < date('Y-m-d H:i:s', strtotime('-2 month'))) {
					$ok = true;
				} // ou si on est dans le back-office : c'est une operation admin, on l'accepte
				elseif (test_espace_prive() and $ok === '?') {
					$ok = true;
				}

				if (!$ok) {
					sql_updateq('spip_abonnements', ['message' => _T('abos:info_demande_resiliation_en_cours')], 'id_abonnement=' . intval($id_abonnement));
				}
			}
		}
	}

	if ($ok) {
		// si graceful est que l'abo a deja été résilié, on ne fait rien
		if ($graceful && $row['statut'] === 'resilie') {
			return $ok;
		}

		$set = [];
		$now = date('Y-m-d H:i:s');
		if ($immediat) {
			$set['statut'] = sql_quote('resilie');
			if (!intval($row['date_fin']) or $row['date_fin'] > $now) {
				$set['date_fin'] = sql_quote($now);
			}
			if (!intval($row['date_echeance']) or $row['date_echeance'] > $now) {
				$set['date_echeance'] = sql_quote($now);
			}
		} else {
			$set['date_fin'] = 'date_echeance';
			$set['message'] = sql_quote(_T('abos:info_resiliation_prochaine_echeance'));
		}

		// plus de relance pour un abonnement resilie
		$set['relance'] = sql_quote('off');

		if ($abo_log) {
			include_spip('inc/abos');
			$set['log'] = sql_quote($row['log'] . abos_log($abo_log));
		}

		sql_update('spip_abonnements', $set, 'id_abonnement=' . intval($id_abonnement));
		spip_log($log = "resiliation abo $id/$id_abonnement : " . var_export($set, true), 'abos_resil' . _LOG_INFO_IMPORTANTE);

		// email webmaster pour surveillance
		$message = generer_url_ecrire('abonnement', "id_abonnement=$id_abonnement", true, false) . "\n";
		if ($notify_bank) {
			$message .= "AVEC notification a la banque (interruption forcée des paiements)\n";
		} else {
			$message .= "sans notification a la banque\n";
		}
		$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
		$message .= "\n\n" . $log;
		$sujet = "Resiliation abonnement $id_abonnement";
		$u = parse_url($GLOBALS['meta']['adresse_site']);
		$host = preg_replace(',^www\.,', '', $u['host']);
		$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $message, "resiliations@$host");


		// et on appelle le pipeline
		$args = [
			'id' => empty($row['abonne_uid']) ? $id : ('uid:' . $row['abonne_uid']),
			'message' => isset($options['message']) ? $options['message'] : '',
			'notify_bank' => false, // on a deja fait si besoin
			'erreur' => $erreur,
		];
		$now = date('Y-m-d H:i:s');
		if (isset($options['immediat']) and $options['immediat']) {
			$args['statut'] = 'resilie';
			$args['date_fin'] = $now;
			$args['date_echeance'] = $now;
		} else {
			$args['date_fin'] = 'date_echeance';
		}

		// appel du pipeline
		// pour mettre a jour les infos de statut/date de fin d'abonnement
		pipeline(
			'bank_abos_resilier',
			[
				'args' => $args,
				'data' => true,
			]
		);
	}

	return $ok;
}
