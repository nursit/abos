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
function abos_log($abo_log){
	$par = "";
	if (isset($GLOBALS['visiteur_session']['id_auteur'])){
		$par = _T('public:par_auteur') . ' #' . $GLOBALS['visiteur_session']['id_auteur'] . ' ' . $GLOBALS['visiteur_session']['nom'];
	} else {
		$par = _T('public:par_auteur') . ' ' . $GLOBALS['ip'];
	}

	$abo_log = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . " | "
		. $par
		. ' : ' . $abo_log . "\n--\n";
	return $abo_log;
}


/**
 * Calculer les echeances d'une commande
 * @param $id_commande
 */
function abos_calculer_echeances_commande($id_commande){

	$echeances = array(
		0 => array(
			'montant' => 0,
			'montant_ht' => 0,
			'nb' => 1
		)
	);

	$details = sql_allfetsel('*', 'spip_commandes_details', 'id_commande=' . intval($id_commande), '', 'id_commandes_detail');
	foreach ($details as $detail){
		$prix_ht = $detail['prix_unitaire_ht'] * $detail['quantite'];
		$prix = $prix_ht * (1.0 + $detail['taxe']);
		$echeances[0]['montant'] += $prix;
		$echeances[0]['montant_ht'] += $prix_ht;
		$echeances_type = '';

		// trouver toutes les offres d'abonnement en renouvellement tacite dans la commande
		// et calculer les echeances
		// on ne prend en compte que les offres avec periode=1 (1 mois ou 1 an),
		// et si plusieurs offres avec periodes de type differentes, seule la premiere periode rencontree sera prise en compte
		if ($detail['objet']=='abooffre'
			and $offre = sql_fetsel('*', 'spip_abo_offres', 'id_abo_offre=' . intval($detail['id_objet']))
		){
			$type = '';
			if ($offre['mode_renouvellement']=='tacite'){
				if (strpos($offre['duree'], 'month')!==false and intval($offre['duree'])){
					$type = 'mois';
				}
				if (strpos($offre['duree'], 'year')!==false and intval($offre['duree'])){
					$type = 'annee';
				}
				if ($type
					and (!$echeances_type or $echeances_type==$type)
				){
					$echeances_type = $type;
					if (!isset($echeances[1])){
						$echeances[1] = array('montant' => 0, 'montant_ht' => 0, 'nb' => 0);
					}
					$prix_renouvellement_ht = $prix_ht;
					$prix_renouvellement = $prix;
					if (floatval($offre['prix_ht_renouvellement'])>0.01){
						$prix_renouvellement_ht = $offre['prix_ht_renouvellement'] * $detail['quantite'];
						$prix_renouvellement = $prix_renouvellement_ht * (1.0+$detail['taxe']);
					}
					$echeances[1]['montant_ht'] += $prix_renouvellement_ht;
					$echeances[1]['montant'] += $prix_renouvellement;
				}
			}
		}

		if ($echeances_type){
			foreach ($echeances as $k => $echeance){
				// on force en string pour la serialization qui sinon reintroduit des virgules non significatives
				$echeances[$k]['montant_ht'] = (string)round($echeances[$k]['montant_ht'], 2);
				$echeances[$k]['montant'] = (string)round($echeances[$k]['montant'], 2);
			}

			if (count($echeances)==2
				and $echeances[0]['montant'] == $echeances[1]['montant']
				and $echeances[0]['montant_ht'] == $echeances[1]['montant_ht']
			){
				if ($echeances[0]['nb']>0 and $echeances[1]['nb']>0){
					$echeances[0]['nb'] += $echeances[1]['nb'];
				} else {
					$echeances[0]['nb'] = 0;
				}
				unset($echeances[1]);
			}

			$set = array(
				'echeances_type' => $echeances_type,
				'echeances' => serialize($echeances),
			);
			include_spip('action/editer_objet');
			include_spip('inc/autoriser');
			autoriser_exception('modifier', 'commande', $id_commande);
			objet_modifier('commande', $id_commande, $set);
			autoriser_exception('modifier', 'commande', $id_commande, false);
		}

	}

}