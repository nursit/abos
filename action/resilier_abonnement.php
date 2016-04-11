<?php
/**
 * Resilier un abonnement
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\action
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');
function action_resilier_abonnement_dist($arg=null){

	if (is_null($arg)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$arg = $securiser_action();
	}

	$immediat = false;
	$resilier = charger_fonction('resilier','abos');
	$raison = (test_espace_prive()?"resil BO par admin":"resil par client");
	if ($id_abonnement=intval($arg)){
		list(,$immediat) = explode('-',$arg);
		$immediat = ($immediat=='echeance')?false:true;
	}
	elseif (substr($arg,0,3)=='uid'
	 AND $uid = substr($arg,3)
	 AND $id_abonnement = sql_getfetsel("id_abonnement","spip_abonnements","abonne_uid=".sql_quote($uid))) {
		$immediat = false;
	}

	if ($id_abonnement){
		$row = sql_fetsel("mode_paiement,abonne_uid,id_transaction_essai,statut","spip_abonnements","id_abonnement=".intval($id_abonnement));

		if ($row['statut']=='prepa' AND $row['id_transaction_essai']>0){
			// c'est une offre d'essai 15min
			// il suffit d'annuler id_transaction_essai
			sql_updateq("spip_abonnements",array("id_transaction_essai"=>0),"id_abonnement=".intval($id_abonnement));
			spip_log('Annulation offre d\'essai '.$id_abonnement."/".$row['abonne_uid'],'abos_resil'._LOG_INFO_IMPORTANTE);
		}
		else {
			$resilier($id_abonnement,array('immediat'=>$immediat,'message'=>$raison));
		}
	}

}
