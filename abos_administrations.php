<?php
/**
 * Fichier gérant l'installation et désinstallation du plugin Abonnements
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Installation
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Fonction d'installation et de mise à jour du plugin Abonnements.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @param string $version_cible
 *     Version du schéma de données dans ce plugin (déclaré dans paquet.xml)
 * @return void
**/
function abos_upgrade($nom_meta_base_version, $version_cible) {
	$maj = array();

	$maj['create'] = array(
		array('maj_tables', array('spip_abo_offres', 'spip_abonnements', 'spip_abonnements_liens')),
	);

	$maj['2.0.0'] = array(
		array('maj_tables', array('spip_abo_offres', 'spip_abonnements', 'spip_abonnements_liens')),
	);
	$maj['2.0.1'] = array(
		array('maj_tables', array('spip_abo_offres', 'spip_abonnements', 'spip_abonnements_liens')),
	);
	$maj['2.0.2'] = array(
		array('maj_tables', array('spip_abonnements')),
	);
	$maj['2.0.3'] = array(
		array('maj_tables', array('spip_abonnements')),
	);
	$maj['2.0.4'] = array(
		array('maj_tables', array('spip_abonnements')),
	);

	// ajout des statistiques
	$maj['2.1.2'] = array(
		array('sql_alter','TABLE spip_abonnements ADD date datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\''), // ajout du champ DATE
		array('abos_rattraper_date'),
		array('maj_tables', array('spip_abo_stats')), // table des stats
		array('abos_rattraper_stats'), // compter !
	);
	$maj['2.1.5'] = array(
		array('sql_updateq','spip_abonnements',array('date'=>'0000-00-00 00:00:00')),
		array('abos_rattraper_date'),
	);
	$maj['2.1.8'] = array(
		array('sql_delete','spip_abo_stats'),
		array('abos_rattraper_stats'), // compter !
	);

	// rattraper les champs taxe/prix_ht/prix_ht_renouvellement pour les bases forkees depuis 2.1
	$maj['2.3.0'] = array(
		array('sql_alter','TABLE spip_abo_offres CHANGE taux_tva taxe decimal(4,3) default null'),
		array('sql_alter','TABLE spip_abo_offres CHANGE prix prix_ht varchar(25) NOT NULL DEFAULT \'\''),
		array('sql_alter','TABLE spip_abo_offres CHANGE prix_renouvellement prix_ht_renouvellement varchar(25) NOT NULL DEFAULT \'\''),
		array('sql_alter','TABLE spip_abonnements CHANGE commentaire log text NOT NULL DEFAULT \'\''),
		array('maj_tables', array('spip_abonnements','spip_abo_offres')),
	);

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function abos_rattraper_date(){
	// pour tous ceux qui ont un paiement lie, on prend la date de paiement
	$res = sql_select('A.id_abonnement, A.date, A.mode_paiement, T.date_paiement',
		'spip_abonnements AS A JOIN spip_abonnements_liens as L on L.id_abonnement=A.id_abonnement JOIN spip_transactions as T ON (L.objet=\'transaction\' AND L.id_objet=T.id_transaction)',
		'T.statut='.sql_quote('ok').' AND A.date='.sql_quote('0000-00-00 00:00:00'),
		'','A.id_abonnement, T.date_paiement'
	);
	while ($row = sql_fetch($res)){
		$date = $row['date_paiement'];
		if ($row['mode_paiement']=='payzen'){
			$date = date('Y-m-d H:i:s',strtotime('-9 days',strtotime($date)));
		}
		sql_updateq('spip_abonnements',array('date'=>$date),'id_abonnement='.intval($row['id_abonnement']).' AND date='.sql_quote('0000-00-00 00:00:00'));
		if (time()>_TIME_OUT) return;
	}

	// tous les autres sont des abos en commande, on les init avec la date_debut
	sql_update('spip_abonnements',array('date'=>'date_debut'),'date='.sql_quote('0000-00-00 00:00:00'));
}

function abos_rattraper_stats(){
	$compter = charger_fonction('compter','abos');
	while (!$compter()){
		if (time()>_TIME_OUT) return;
	}
}

/**
 * Fonction de désinstallation du plugin Abonnements.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @return void
**/
function abos_vider_tables($nom_meta_base_version) {

	sql_drop_table("spip_abo_offres");
	sql_drop_table("spip_abonnements");
	sql_drop_table("spip_abonnements_liens");
	sql_drop_table("spip_abo_stats");

	# Nettoyer les versionnages et forums
	sql_delete("spip_versions",              sql_in("objet", array('abooffre', 'abonnement')));
	sql_delete("spip_versions_fragments",    sql_in("objet", array('abooffre', 'abonnement')));
	sql_delete("spip_forum",                 sql_in("objet", array('abooffre', 'abonnement')));

	effacer_meta($nom_meta_base_version);
}

