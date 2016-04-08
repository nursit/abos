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
function action_prolonger_abonnement_dist($arg=null){

	if (is_null($arg)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$arg = $securiser_action();
	}

	list($id_abonnement,$nb_mois) = explode('-',$arg);

	include_spip('inc/autoriser');
	if (autoriser('modifier','abonnement',$id_abonnement)
	  AND $row = sql_fetsel("*","spip_abonnements","id_abonnement=".intval($id_abonnement))){

		$set = array(
			'commentaire' => $row['commentaire']
				. "Prolongation de $nb_mois mois par auteur #" . $GLOBALS['visiteur_session']['id_auteur']
			  . "\n--\n"
		);
		$set['date_echeance'] = date('Y-m-d H:i:s',strtotime("+$nb_mois month",strtotime($row['date_echeance'])));
		if (intval($row['date_fin']) AND $row['date_fin']>$row['date_debut']){
			$set['date_fin'] = date('Y-m-d H:i:s',strtotime("+$nb_mois month",strtotime($row['date_fin'])));
		}
		sql_updateq("spip_abonnements",$set,"id_abonnement=".intval($id_abonnement));
	}

}

