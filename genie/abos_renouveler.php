<?php
/**
 * Renouveler les abonnements PayboxDirectPlus
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Genie
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');
include_spip('inc/filtres');

/**
 * Renouveler les abonnements manuels (payboxdirectplus)
 * @deprecated
 * A revoir
 */
function genie_abos_renouveler_dist(){

	// lister les abos PayboxDirectPlus a renouveler
	// il faut relancer le paiment manuellement
	$lister_renouvellements = charger_fonction('lister_renouvellements','abos');
	$abos = $lister_renouvellements(time(),"pboxdirpl",'tacite',1);
	spip_log(count($abos)." abos a renouveler",'abosrenouveler');

	if (count($abos)){

		$renouveler = charger_fonction('renouveler','abos');
		$call_directplus = charger_fonction('directplus','presta/paybox/call');

		if (defined('_TEST_BLOCK_ABOS')){
			spip_log("Renouvellements bloques par _TEST_BLOCK_ABOS",'abosrenouveler'._LOG_INFO_IMPORTANTE);
			return 1;
		}

		foreach($abos as $id_abonnement=>$infos){
			// creer la transaction de renouvellement
			$id_transaction = $renouveler($id_abonnement);
			#var_dump($id_transaction);
			$hash = $ppps = false;
			// recuperer son hash (securite)
			$trans = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));

			$payer = true;
			// ne pas re-lancer une transaction deja echouee a moins de 10h d'intervalle
			if (strncmp($trans['statut'],'echec',5)==0){
				$paiement_time = strtotime($trans['date_paiement']);
				if ($paiement_time>time()-10*3600) {
					spip_log("[Attente delai 10h] renouvellement suspendu $id_abonnement/".$infos['uid']."/$id_transaction",'abosrenouveler'._LOG_INFO_IMPORTANTE);
					$payer = false;
				}
			}
			if ($payer) {
				$hash = $trans['transaction_hash'];

				// recuperer les infos CB avec l'uid
				$ppps = sql_getfetsel("pay_id","spip_transactions","id_transaction=".intval($infos['uid']));

				// la payer
				if ($hash AND $ppps){
					spip_log("renouveler $id_abonnement/".$infos['uid']."/$id_transaction",'abosrenouveler'._LOG_INFO_IMPORTANTE);
					$out = $call_directplus($id_transaction, $hash, $infos['uid'], $ppps);
					list($id_transaction,$success) = $out;

					// si echec et que la transaction date de plus de 3 jours on resilie l'abonnement
					if (!$success) {
						$transaction_time = strtotime($trans['date_transaction']);
						if ($transaction_time<time()-3*24*3600){
							spip_log("RESILIER abonnement [echec transaction] $id_abonnement/".$infos['uid']."/$id_transaction",'abosrenouveler'._LOG_INFO_IMPORTANTE);
							$resilier = charger_fonction("resilier","abos");
							$resilier($id_abonnement,array('immediat'=>true,'message'=>"Transaction $id_transaction en echec",'notify_bank'=>false));
						}
					}
				}
			}
		}

	}

	return 1;
}
