<?php
/**
 * Reparer les abonnements avec des infos moisies
 * ou pas renouveles par Paybox (notif manquante)
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Genie
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

if (!defined('_ABOS_RELANCE_POOL')) define('_ABOS_RELANCE_POOL',20);

/**
 * chercher les abonnements mensuels dont la fin approche
 * et envoyer une relance
 * @param int $t
 * @return int
 */
function genie_abos_relancer($t){

	$now = time();

	$relances = abos_get_relances();
	if (!$relances) return 0;

	$premiere_relance = reset($relances);


	// marquer en premiere relance tous les abonnements ok qui ont une date de fin dans moins de N jours
	// et qui n'ont pas encore ete relance du tout
	$date_fin = abos_date_fin($premiere_relance,$now);

	sql_updateq("spip_abonnements",array('relance'=>$premiere_relance),'statut='.sql_quote('ok').' AND relance='.sql_quote('').' AND date_fin>date_debut AND date_fin<='.sql_quote($date_fin));

	// trouver tous les rappels en cours sur les statut=ok
	$rappels = sql_allfetsel("DISTINCT relance","spip_abonnements",'statut='.sql_quote('ok').' AND relance<>'.sql_quote('off').' AND relance<>'.sql_quote(''));
	if (count($rappels)){
		$rappels = array_map('reset',$rappels);

		$where = array();
		foreach($rappels as $r){
			$where[] = "(relance=".sql_quote($r,'','text')." AND date_fin>date_debut AND date_fin<".sql_quote(abos_date_fin($r,$now)).")";
		}

		$where = "(".implode(") OR (",$where).")";
		$where = "(($where) AND (statut=".sql_quote('ok')."))";

		$nb=_ABOS_RELANCE_POOL;
		$notifications = charger_fonction('notifications', 'inc');
		while($nb--){
			if ($row = sql_fetsel('id_abonnement,date_fin,relance','spip_abonnements',$where,'','date_fin','0,1')){
				spip_log("genie_abos_relancer id_abonnement=".$row['id_abonnement'].", date_fin:".$row['date_fin'].", relance:".$row['relance'],'abos_relancer');
				$notifications('relancerfinabonnement', $row['id_abonnement']);
				// noter qu'on a fait le rappel
				sql_updateq("spip_abonnements",array('relance'=>abos_prochaine_relance($row['date_fin'],$now)),'id_abonnement='.intval($row['id_abonnement']));
			}
			else $nb=0;
		}

		// si trop de relances, demander la main a nouveau
		if (($n = sql_countsel('spip_abonnements',$where))>2 * _ABOS_RELANCE_POOL){
			spip_log("Restant : $n","abos_relancer");
			return -($t-3600);
		}
	}
	return 0;
}

function abos_date_fin($relance,$now){
	$days = -$relance;
	return date('Y-m-d H:i:s',strtotime(($days>=0?"+":"")."$days days",$now));
}

function abos_get_relances(){
	include_spip('inc/config');
	// ex : -30,-15,-7,0
	$relances = lire_config('abos/relances','');
	$relances = explode(",",$relances);
	$relances = array_map("trim",$relances);
	$relances = array_map("intval",$relances);
	$relances = array_unique($relances);
	sort($relances);
	return $relances;
}

function abos_prochaine_relance($date_fin,$now=null){
	if (!$now) $now = time();

	$relances = abos_get_relances();
	rsort($relances);

	$next = 'off';
	while (count($relances)){
		$jours = array_shift($relances);
		if ($date_fin<abos_date_fin($jours,$now))
			return $next;
		$next = $jours;
	}

	return 'off'; // on n'arrive jamais la
}
