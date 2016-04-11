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
	$maj['2.0.5'] = array(
		array('sql_alter','TABLE spip_abo_offres CHANGE taux_tva taxe decimal(4,3) default null'),
		array('maj_tables', array('spip_abo_offres')),
	);
	$maj['2.0.6'] = array(
		array('sql_alter','TABLE spip_abo_offres CHANGE prix prix_ht varchar(25) NOT NULL DEFAULT \'\''),
		array('sql_alter','TABLE spip_abo_offres CHANGE prix_renouvellement prix_ht_renouvellement varchar(25) NOT NULL DEFAULT \'\''),
	);
	$maj['2.0.7'] = array(
		array('sql_alter','TABLE spip_abonnements CHANGE commentaire log text NOT NULL DEFAULT \'\''),
	);

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
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

	# Nettoyer les versionnages et forums
	sql_delete("spip_versions",              sql_in("objet", array('abooffre', 'abonnement')));
	sql_delete("spip_versions_fragments",    sql_in("objet", array('abooffre', 'abonnement')));
	sql_delete("spip_forum",                 sql_in("objet", array('abooffre', 'abonnement')));

	effacer_meta($nom_meta_base_version);
}
