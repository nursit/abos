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
		$par = _T('public:par_auteur').' #'.$GLOBALS['visiteur_session']['id_auteur'].' '.$GLOBALS['visiteur_session']['nom'];
	}
	else {
		$par = _T('public:par_auteur').' '.$GLOBALS['ip'];
	}

	$abo_log = date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME'])." | "
		. $par
		.' : '.$abo_log . "\n--\n";
	return $abo_log;
}


/**
 * Calculer les echeances d'une commande
 * @param $id_commande
 */
function abos_calculer_echeances_commande($id_commande) {

	$echeances = array(
		0 => array(
			'montant' => 0,
			'nb' => 1
		)
	);

	$details = sql_allfetsel('*','spip_commandes_details','id_commande='.intval($id_commande),'','id_commandes_detail');
	foreach ($details as $detail) {
		$prix = $detail['prix_unitaire_ht'] * (1.0 + $detail['taxe']) * $detail['quantite'];
		$echeances[0]['montant'] += $prix;
		$echeances_type = '';

		if ($detail['objet']=='abooffre'
		  and $offre = sql_fetsel('*','spip_abo_offres','id_abo_offre='.intval($detail['id_objet']))) {
			$type = '';
			if (strpos($offre['duree'],'month')!==false and intval($offre['duree'])){
				$type = 'mois';
			}
			if ($type
			  and (!$echeances_type or $echeances_type==$type)) {
				$echeances_type = $type;
				if (!isset($echeances[1])) {
					$echeances[1] = array('montant'=>0,'nb'=>0);
				}
				$prix_renouvellement = $prix;
				if (floatval($offre['prix_ht_renouvellement'])>0.01) {
					$prix_renouvellement = $offre['prix_ht_renouvellement'] * (1.0 + $detail['taxe']) * $detail['quantite'];
				}
				$echeances[1]['montant'] += $prix_renouvellement;
			}
		}

		if ($echeances_type) {
			foreach($echeances as $k=>$echeance) {
				// on force en string pour la serialization qui sinon reintroduit des virgules non significatives
				$echeances[$k]['montant'] = (string)round($echeances[$k]['montant'],2);
			}

			if (count($echeances)==2
				and $echeances[0]['montant']==$echeances[1]['montant']) {
				if ($echeances[0]['nb']>0 and $echeances[1]['nb']>0) {
					$echeances[0]['nb'] += $echeances[1]['nb'];
				}
				else {
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
			objet_modifier('commande',$id_commande, $set);
			autoriser_exception('modifier', 'commande', $id_commande, false);
		}

	}

}