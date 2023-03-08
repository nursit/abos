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
function abos_log($abo_log) {
	$par = '';
	if (defined('_IS_CLI') AND _IS_CLI) {
		$par = _T('public:par_auteur') . ' [CLI]';
	} else if (isset($GLOBALS['visiteur_session']['id_auteur'])) {
		$par = _T('public:par_auteur') . ' #' . $GLOBALS['visiteur_session']['id_auteur'] . ' ' . $GLOBALS['visiteur_session']['nom'];
	} else {
		$par = _T('public:par_auteur') . ' ' . $GLOBALS['ip'];
	}

	$abo_log = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ' | '
		. $par
		. ' : ' . $abo_log . "\n--\n";
	return $abo_log;
}

/**
 * Verifier lors de la creation d'une commande depuis un panier qu'on a pas 2 abonnements aux échances incompatibles
 * (les moyens de paiement ne permettent pas de melanger du mensuel et de l'annuel par exemple)
 *
 * @param int $id_commande
 * @param int $id_panier
 * @param bool $supprimer_panier
 * @return bool
 */
function abos_verifier_commande_echeances_compatibles($id_commande, $id_panier, $supprimer_panier = true) {

	$supprimer_panier_apres = $supprimer_panier;
	if ($supprimer_panier) {
		sql_delete('spip_paniers_liens', 'id_panier=' . intval($id_panier));
	}

	$duree = false;
	$details = sql_allfetsel('*', 'spip_commandes_details', 'id_commande='.intval($id_commande));
	$rang = 1;
	foreach ($details as $detail) {
		if ($detail['objet'] === 'abooffre') {
			$abooffre = sql_fetsel('*', 'spip_abo_offres', 'id_abo_offre='.intval($detail['id_objet']));
			if ($abooffre['mode_renouvellement']) {
				// on note la premiere duree avec renouvellement que l'on trouve
				if ($duree === false or $duree === $abooffre['duree']) {
					$duree = $abooffre['duree'];
				} else {
					spip_log("abos_verifier_commande_echeances_compatibles: abooffre #".$detail['id_objet'] ." duree ".$abooffre['duree']." incompatible avec précédente duree $duree, on l'enleve de la commande", "abos". _LOG_INFO_IMPORTANTE);
					sql_delete('spip_commandes_details', 'id_commandes_detail='.$detail['id_commandes_detail']);
					// et les autres sont remises dans le panier si il devait être vidé
					if ($supprimer_panier) {
						spip_log("abos_verifier_commande_echeances_compatibles: (on la remets dans le panier qui a été vidé)", "abos". _LOG_INFO_IMPORTANTE);
						$supprimer_panier_apres = false;
						$insert = [
							'id_panier' => $id_panier,
							'objet' => 'abooffre',
							'id_objet' => $detail['id_objet'],
							'quantite' => $detail['quantite'],
							'reduction' => $detail['reduction'],
							'rang' => $rang++,
						];
						sql_insertq("spip_paniers_liens", $insert);
					}
				}
			}
		}
	}

	return $supprimer_panier_apres;
}

/**
 * Calculer les echeances d'une commande
 * @param $id_commande
 */
function abos_calculer_echeances_commande($id_commande) {

	$echeances = [
		0 => [
			'montant' => 0,
			'montant_ht' => 0,
			'nb' => 1
		]
	];

	$details = sql_allfetsel('*', 'spip_commandes_details', 'id_commande=' . intval($id_commande), '', 'id_commandes_detail');
	$echeances_type = '';
	foreach ($details as $detail) {
		$prix_ht = $detail['prix_unitaire_ht'] * $detail['quantite'];
		$prix = $prix_ht * (1.0 + $detail['taxe']);
		$echeances[0]['montant'] += $prix;
		$echeances[0]['montant_ht'] += $prix_ht;

		// trouver toutes les offres d'abonnement en renouvellement tacite dans la commande
		// et calculer les echeances
		// on ne prend en compte que les offres avec periode=1 (1 mois ou 1 an),
		// et si plusieurs offres avec periodes de type differentes, seule la premiere periode rencontree sera prise en compte
		if (
			$detail['objet'] == 'abooffre'
			and $offre = sql_fetsel('*', 'spip_abo_offres', 'id_abo_offre=' . intval($detail['id_objet']))
		) {
			$type = '';
			if ($offre['mode_renouvellement'] == 'tacite') {
				if (strpos($offre['duree'], 'month') !== false and intval($offre['duree'])) {
					$type = 'mois';
				}
				if (strpos($offre['duree'], 'year') !== false and intval($offre['duree'])) {
					$type = 'annee';
				}
				if (
					$type
					and (!$echeances_type or $echeances_type == $type)
				) {
					$echeances_type = $type;
					if (!isset($echeances[1])) {
						$echeances[1] = ['montant' => 0, 'montant_ht' => 0, 'nb' => 0];
					}
					$prix_renouvellement_ht = $prix_ht;
					$prix_renouvellement = $prix;
					if (floatval($offre['prix_ht_renouvellement']) > 0.01) {
						$prix_renouvellement_ht = $offre['prix_ht_renouvellement'] * $detail['quantite'];
						$prix_renouvellement = $prix_renouvellement_ht * (1.0 + $detail['taxe']);
					}
					$echeances[1]['montant_ht'] += $prix_renouvellement_ht;
					$echeances[1]['montant'] += $prix_renouvellement;
				}
			}
		}
	}

	if ($echeances_type) {
		foreach ($echeances as $k => $echeance) {
			// on force en string pour la serialization qui sinon reintroduit des virgules non significatives
			$echeances[$k]['montant_ht'] = (string)round($echeances[$k]['montant_ht'], 2);
			$echeances[$k]['montant'] = (string)round($echeances[$k]['montant'], 2);
		}

		if (
			count($echeances) == 2
			and $echeances[0]['montant'] == $echeances[1]['montant']
			and $echeances[0]['montant_ht'] == $echeances[1]['montant_ht']
		) {
			if ($echeances[0]['nb'] > 0 and $echeances[1]['nb'] > 0) {
				$echeances[0]['nb'] += $echeances[1]['nb'];
			} else {
				$echeances[0]['nb'] = 0;
			}
			unset($echeances[1]);
		}

		$set = [
			'echeances_type' => $echeances_type,
			'echeances' => serialize($echeances),
		];
	}
	else {
		$set = [
			'echeances_type' => '',
			'echeances' => '',
		];
	}
	include_spip('action/editer_objet');
	include_spip('inc/autoriser');
	autoriser_exception('modifier', 'commande', $id_commande);
	objet_modifier('commande', $id_commande, $set);
	autoriser_exception('modifier', 'commande', $id_commande, false);
}
