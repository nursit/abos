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

/**
 * Maintenance des abonnements,
 * toutes les 12h
 *
 */
function genie_abos_reparer_dist(){

	$repair = charger_fonction('repair','abos');
	$repair();
	spip_log("Maintenance des abonnements",'abos_reparer_cron');
	
	return 1;
}
