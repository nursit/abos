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
 *   int id_transaction
 *   string date_debut
 * @return array|bool
 */
function abos_abonner_dist($id_abo_offre, $options = array()){

	$id_abonnement = 0;
	if ($row = sql_fetsel("*","spip_abo_offres","id_abo_offre=".intval($id_abo_offre))){

		$defaut = array(
			'id_auteur'=>0,
			'statut'=>'prepa',
			'prix_initial'=>null,
			'prix_echeance'=>null,
			'id_transaction'=>0,
			'date_debut'=>''
		);
		$options = array_merge($defaut,$options);

		// trouver l'auteur
		if (!$id_auteur=$options['id_auteur']
			AND isset($GLOBALS['visiteur_session']['id_auteur'])
			AND $GLOBALS['visiteur_session']['id_auteur'])
		  $id_auteur = $GLOBALS['visiteur_session']['id_auteur'];

		// cas particulier : on demande explicitement a ignorer l'auteur connecte, il sera cree plus tard
		if ($id_auteur==-1) $id_auteur=0;

		$statut = $options['statut'];
		if (!$id_auteur)
			$statut = 'prepa';

		$prix_initial = $options['prix_initial'];
		if (is_null($prix_initial))
			$prix_initial = $row['prix'];

		if ($options['prix_echeance']){
			$prix = $options['prix_echeance'];
		}
		else {
			$prix = (intval($row['prix_renouvellement']*100)?$row['prix_renouvellement']:$prix_initial);
		}

		// creer l'abonnement
		$date_debut = ($options['date_debut']?$options['date_debut']:date('Y-m-d H:i:s'));
		$ins= array(
			'id_abo_offre'=>$id_abo_offre,
			'id_auteur'=>$id_auteur,
			'date_debut'=>$date_debut,
			'date_echeance'=>$date_debut,
			'duree_echeance'=>$row['duree'],
			'prix_echeance'=>$prix,
			'statut'=>$statut,
		);
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

		// creer la transaction correspondante, avec le prix du premier mois !
		include_spip('inc/abos');
		$id_transaction = abos_creer_transaction($id_abonnement,$prix_initial,$options['id_transaction']);

		if(!$id_transaction AND intval($prix_initial * 100)>0) {
			spip_log("Erreur lors de la creation de la transaction en base ".var_export(array($id_abonnement,$prix_initial,$options['id_transaction']),true),"abos"._LOG_ERREUR);
			sql_delete("spip_abonnements","id_abonnement=".intval($id_abonnement));
			return false;
		}
		else {
			// marquer la transacion/echeance
			sql_updateq("spip_abonnements",array("id_transaction_echeance"=>$id_transaction),"id_abonnement=".intval($id_abonnement));
			return array($id_transaction,$id_abonnement);
		}

	}
	return false;
}
