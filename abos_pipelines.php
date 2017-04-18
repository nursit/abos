<?php
/**
 * Utilisations de pipelines par Abonnements
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Ouvrir les acces a des zones restreintes via un abonnement valide en cours
 * si le plugin acces restreint est present (pipeline du plugin accesrestreint)
 * @param string $zones
 * @param null|int $id_auteur
 * @return string
 */
function abos_accesrestreint_liste_zones_autorisees($zones='', $id_auteur=NULL) {
	$id = NULL;
	if (!is_null($id_auteur))
		$id = $id_auteur;
	elseif (isset($GLOBALS['visiteur_session']['id_auteur']) && $GLOBALS['visiteur_session']['id_auteur'])
		$id = $GLOBALS['visiteur_session']['id_auteur'];
	if (!is_null($id)) {
		$new = abos_liste_zones_acces_auteur($id);
		if ($zones AND $new) {
			$zones = array_unique(array_merge(explode(',',$zones),$new));
			sort($zones);
			$zones = join(',', $zones);
		} else if ($new) {
			sort($new);
			$zones = join(',', $new);
		}
	}
	return $zones;
}

/**
 * Lister les zones accessibles d'un auteur via un abonnement ok
 *
 * @param int $id_auteur
 * @return array
 */
function abos_liste_zones_acces_auteur($id_auteur){
	static $liste_zones = array();
	if (!isset($liste_zones[$id_auteur])){
		$liste_zones[$id_auteur] = array();
		include_spip('base/abstract_sql');
		$now = date('Y-m-d H:i:s');
		if ($id_abo_offres = sql_allfetsel("id_abo_offre","spip_abonnements","id_auteur=".intval($id_auteur)." AND statut='ok' AND (date_fin IS NULL OR date_fin<date_debut OR date_fin>".sql_quote($now).")")){
			$id_abo_offres = array_map('reset',$id_abo_offres);
			$liste_zones[$id_auteur] = sql_allfetsel("id_zone","spip_zones_liens","objet='abooffre' AND ".sql_in('id_objet',$id_abo_offres));
			$liste_zones[$id_auteur] = array_map('reset',$liste_zones[$id_auteur]);
		}
	}
	return $liste_zones[$id_auteur];
}


function abos_preparer_visiteur_session($flux){

	if ($flux['data']['statut']=='6forum'){
		$row = $flux['args']['row'];
		// Indiquer la connexion. A la journee pres ca suffit.
		$connect_quand = strtotime($row['en_ligne']);

		$now = $_SERVER['REQUEST_TIME'];
		if (abs($now - $connect_quand)  >= 64800 /*18h*/) {
			$row['en_ligne'] = date('Y-m-d H:i:s',$now);
			sql_updateq("spip_auteurs", array("en_ligne" => $row['en_ligne']), "id_auteur=" .intval($row['id_auteur']));
			spip_log("Mise a jour en_ligne auteur ".$row['id_auteur'],"abos_enligne"._LOG_DEBUG);
		}
	}
	return $flux;
}


/**
 * Afficher la liste des abonnements d'un auteur sur la page auteur de l'espace prive
 *
 * @pipeline affiche_auteurs_interventions
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 */
function abos_affiche_auteurs_interventions($flux) {
	if ($id_auteur = intval($flux['args']['id_auteur'])) {

		$ins = recuperer_fond('prive/squelettes/inclure/abonnements-auteur', array(
			'id_auteur' => $id_auteur,
			'titre' => _T('abonnement:info_abonnements_auteur')
		), array('ajax' => true));
		$mark = '<!--bank-->';
		if (($p = strpos($flux['data'], $mark)) !== false) {
			$flux['data'] = substr_replace($flux['data'], $ins, $p + strlen($mark), 0);
		}
		else {
			$flux['data'] .= $ins;
		}

	}
	return $flux;
}



/**
 * Compter et afficher les abonnements d'un visiteur
 * pour affichage dans la page auteurs
 *
 * @param array $flux
 * @return array
 */
function abos_compter_contributions_auteur($flux){

	if ($id_auteur = intval($flux['args']['id_auteur'])
	  AND $cpt = sql_countsel("spip_abonnements AS A", "A.id_auteur=".intval($id_auteur).' AND statut='.sql_quote('ok'))){
		$contributions = singulier_ou_pluriel($cpt,'abonnement:info_1_abonnement','abonnement:info_nb_abonnements');
		$flux['data'][] = $contributions;
	}
	return $flux;
}



/**
 * Optimiser la base de données en supprimant les liens orphelins
 * de l'objet vers quelqu'un et de quelqu'un vers l'objet.
 *
 * @pipeline optimiser_base_disparus
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 */
function abos_optimiser_base_disparus($flux){
	include_spip('action/editer_liens');
	$flux['data'] += objet_optimiser_liens(array('abonnement'=>'*'),'*');
	return $flux;
}

/**
 * Declarer les CRON
 *
 * @param array $taches_generales
 * @return array
 */
function abos_taches_generales_cron($taches_generales){
	// pas de cron de congirmation essais si pas de define
	if (defined('_DUREE_CONSULTATION_ESSAI')){
		$taches_generales['abos_confirmer_essais'] = 60; // toutes les 60s
	}
	//$taches_generales['abos_renouveler'] = 3600; // toutes les 3600s
	$taches_generales['abos_reparer'] = 3600*12; // toutes les 12h

	include_spip('inc/config');
	if (lire_config('abos/relances','')){
		$taches_generales['abos_relancer'] = 3600; // toutes les 1h
	}

	if (defined('_ABOS_EMAIL_REPORTING')){
		$taches_generales['abos_daily_reporting'] = 3600*6; // toutes les 6H
	}

	return $taches_generales;
}

/**
 * Mettre a jour un abonnement suite a son paiement :
 * - paiement initial on met a jour le numero d'abonne
 * - paiement echeances, on renouvelle l'abonnement
 *
 * @param $flux
 * @return mixed
 */
/*
function abos_bank_traiter_reglement($flux){
	if ($id_transaction=intval($flux['args']['id_transaction'])){
		// mettre a jour l'uid abonne d'une transaction essai
		if ($abo = sql_fetsel("id_abonnement","spip_abonnements","id_transaction_essai=".intval($id_transaction)." AND abonne_uid=''")){
			sql_updateq("spip_abonnements",array("abonne_uid"=>$id_transaction),"id_abonnement=".intval($abo['id_abonnement']));
		}
		// mettre a jour la date echeance d'un abonnement renouvelle
		// si encore en prepa et pas de $transaction['abo_uid'] on le fait quand meme ici
		// sinon il sera rappelle par bank avec le numero d'abo_uid si paiement mensuel
		elseif ($abo = sql_fetsel("*","spip_abonnements","id_transaction_echeance=".intval($id_transaction))) {
			$transaction = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));

			// activer_abonnement sera rappelle par bank avec le numero d'abo_uid si paiement mensuel
			if ($abo['statut']=='prepa' AND $transaction['abo_uid']){
				return $flux;
			}

			$abo_uid = $abo['abonne_uid'];
			if (!$abo_uid){
				$abo_uid = $transaction['abo_uid'];
				if (!$abo_uid){
					$abo_uid = $id_transaction;
				}
			}
			$mode = $abo['mode_paiement'];
			if (!$mode) {
				$mode = $transaction['mode'];
			}
			// mettre a jour l'auteur ?
			if (!$id_auteur = $abo['id_auteur']){
				if ($transaction['cadeau_email']){
					$id_auteur = sql_getfetsel("id_auteur","spip_auteurs","email=".sql_quote($transaction['cadeau_email']));
				}
				elseif($transaction['id_auteur']){
					$id_auteur = $transaction['id_auteur'];
				}
				elseif ($transaction['auteur']) {
					$id_auteur = sql_getfetsel("id_auteur", "spip_auteurs", "email=" . sql_quote($transaction['auteur']));
				}
			}

			$activer_abonnement = charger_fonction("activer_abonnement","abos");
			$id_abonnement = $activer_abonnement($id_transaction,$abo_uid,$mode,$abo['statut']=='prepa'?'echeance':'',$id_auteur);
			if ($id_abonnement){
				$set = array(
					'id_transaction_echeance'=>0
				);
				if ($abo['duree_echeance']==='1 month'){
					$prochaine_echeance = $abo['date_echeance'];
					$datep15 = date('Y-m-d H:i:s',strtotime("+15 day"));
					// retablir un abo qui avait ete resilie a tort (puisqu'on a un paiement)
					if ($abo['statut']=='resilie'){
						$prochaine_echeance = $abo['date_debut']; // on recalcul l'echeance depuis le debut
						$set['date_fin'] = '';
						$set['statut'] = 'ok';
						if ($validite = $transaction["validite"]){
							$d = date($validite."-d H:i:s",strtotime($abo['date_debut']));
							$d = strtotime($d);
							$d = strtotime("+1 month",$d);
							$set['date_fin'] = date('Y-m-d H:i:s',$d);
						}
						$datep15 = date('Y-m-d H:i:s',strtotime("+5 day"));
					}

					// recaler la prochaine echeance si trop en avance (double appel anterieur ou erreur de calcul)
					while($prochaine_echeance>$datep15){
						$prochaine_echeance = date('Y-m-d H:i:s',strtotime("-".$abo['duree_echeance'],strtotime($prochaine_echeance)));
					}
					// l'incrementer pour atteindre celle du mois prochain
					while($prochaine_echeance<$datep15){
						$prochaine_echeance = date('Y-m-d H:i:s',strtotime("+".$abo['duree_echeance'],strtotime($prochaine_echeance)));
					}
					$set['date_echeance'] = $prochaine_echeance;
				}
				sql_updateq("spip_abonnements",$set,"id_abonnement=".intval($abo['id_abonnement']));
			}
			else {
				spip_log($m = "Impossible d'activer l'abonnement ".var_export($abo,true),"abos"._LOG_ERREUR);
				$envoyer_mail = charger_fonction("envoyer_mail","inc");
				$envoyer_mail("support@nursit.net","Erreur Abonnement".$GLOBALS['meta']['nom_site'],$m);
			}
		}

	}
	return $flux;
}
*/