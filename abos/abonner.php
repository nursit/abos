<?php
/**
 * Abonner un auteur
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\API
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');
/**
 * @param int $id_abo_offre
 * @param array $options
 *   int id_auteur
 *   string statut
 *   string prix_initial
 *   string prix_echeance
 *   int id_commande
 *   string date_debut
 *   string mode_paiement
 * @return int|bool
 */
function abos_abonner_dist($id_abo_offre, $options = array()){

	$id_abonnement = 0;
	if ($row = sql_fetsel("*","spip_abo_offres","id_abo_offre=".intval($id_abo_offre))){

		$defaut = array(
			'id_auteur' => 0,
			'statut' => 'prepa',
			'prix_ht_initial' => null,
			'prix_ht_echeance' => null,
			'id_commande' => 0,
			'date' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
			'date_debut' => '',
			'mode_paiement' => '',
		);
		$options = array_merge($defaut,$options);

		// trouver l'auteur
		if (!$id_auteur=$options['id_auteur']
			AND isset($GLOBALS['visiteur_session']['id_auteur'])
			AND $GLOBALS['visiteur_session']['id_auteur'])
		  $id_auteur = $GLOBALS['visiteur_session']['id_auteur'];

		// cas particulier : on demande explicitement a ignorer l'auteur connecte, il sera cree plus tard
		// deprecated : servait dans un workflow sans commande
		if ($id_auteur==-1) $id_auteur=0;

		$statut = $options['statut'];
		if (!$id_auteur){
			$statut = 'prepa';
		}

		$prix_ht_initial = $options['prix_ht_initial'];
		if (is_null($prix_ht_initial)){
			$prix_ht_initial = $row['prix_ht'];
		}

		$prix_ht = $prix_ht_initial;
		if ($options['prix_ht_echeance']){
			$prix_ht = $options['prix_ht_echeance'];
		}
		elseif(intval($row['prix_ht_renouvellement']*100)) {
			$prix_ht = $row['prix_ht_renouvellement'];
		}

		$fonction_prix = charger_fonction("abooffre","prix");
		$prix_ttc = round($fonction_prix($id_abo_offre,$prix_ht),2);

		// creer l'abonnement
		$date_debut = ($options['date_debut']?$options['date_debut']:date('Y-m-d H:i:s'));
		$date_echeance = $date_debut;
		if ($statut=='ok'){
			$date_echeance = strtotime($date_debut);
			$date_echeance = strtotime("+".$row['duree'],$date_echeance);
			$date_echeance = date('Y-m-d H:i:s',$date_echeance);
		}
		$ins= array(
			'id_abo_offre'=>$id_abo_offre,
			'id_auteur'=>$id_auteur,
			'id_commande'=>$options['id_commande'],
			'date'=>$options['date'],
			'date_debut'=>$date_debut,
			'date_echeance'=>$date_echeance,
			'duree_echeance'=>$row['duree'],
			'mode_echeance' => $row['mode_renouvellement'],
			'prix_echeance'=>$prix_ttc,
			'statut'=>$statut,
			'mode_paiement'=>$options['mode_paiement'],
		);
		// si c'est un abonnement actif, on le met en date_fin=date_echeance
		// si c'est un paiement recurrent periodique, pas de date de fin, il sera passe en resilie par cron si date passee
		if ($statut=='ok' and $ins['mode_echeance']!=='tacite'){
			$ins['date_fin'] = $date_echeance;
		}

		// permettre des ajustement metiers sur les abonnements (date de fin par periode par exemple)
		if ($abos_personaliser = charger_fonction("personaliser","abos",true)){
			$ins = $abos_personaliser($ins);
		}

		$id_abonnement = sql_insertq('spip_abonnements',$ins);

		if (!$id_abonnement){
			spip_log("Impossible de creer l'abonnement en base ".var_export($ins,true),"abos"._LOG_ERREUR);
			return false;
		}

		// affecter les credits eventuels
		$limites = array();

		$rowsl = sql_allfetsel("*","spip_mots_liens","objet='abooffre' AND id_objet=".intval($id_abo_offre));
		foreach($rowsl as $rowl){
			$limites[$rowl['id_mot']] = (isset($rowl['limite']) AND $rowl['limite']>0)?$rowl['limite']:'inf';
		}

		if (count($limites)){
			$limites = serialize($limites);
			sql_updateq("spip_abonnements",array("credits_echeance"=>$limites,"credits"=>$limites),"id_abonnement=".intval($id_abonnement));
		}

		if ($statut=='ok') {
			$notifications = charger_fonction("notifications","inc");
			$notifications('activerabonnement',$id_abonnement,array('statut'=>$statut,'statut_ancien'=>'prepa'));
		}


	}
	return $id_abonnement;
}
