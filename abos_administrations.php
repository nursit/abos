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

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


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
	$maj = [];

	$maj['create'] = [
		['maj_tables', ['spip_abo_offres', 'spip_abonnements', 'spip_abonnements_liens', 'spip_abo_stats']],
	];

	$maj['2.0.0'] = [
		['maj_tables', ['spip_abo_offres', 'spip_abonnements', 'spip_abonnements_liens']],
	];
	$maj['2.0.1'] = [
		['maj_tables', ['spip_abo_offres', 'spip_abonnements', 'spip_abonnements_liens']],
	];
	$maj['2.0.2'] = [
		['maj_tables', ['spip_abonnements']],
	];
	$maj['2.0.3'] = [
		['maj_tables', ['spip_abonnements']],
	];
	$maj['2.0.4'] = [
		['maj_tables', ['spip_abonnements']],
	];

	// ajout des statistiques
	$maj['2.1.2'] = [
		['sql_alter', 'TABLE spip_abonnements ADD date datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\''], // ajout du champ DATE
		['abos_rattraper_date'],
		['maj_tables', ['spip_abo_stats']], // table des stats
		['abos_rattraper_stats'], // compter !
	];
	$maj['2.1.5'] = [
		['sql_updateq', 'spip_abonnements', ['date' => '0000-00-00 00:00:00']],
		['abos_rattraper_date'],
	];
	$maj['2.1.8'] = [
		['sql_delete', 'spip_abo_stats'],
		['abos_rattraper_stats'], // compter !
	];

	// rattraper les champs taxe/prix_ht/prix_ht_renouvellement pour les bases forkees depuis 2.1
	$maj['2.3.0'] = [
		['sql_alter', 'TABLE spip_abo_offres CHANGE taux_tva taxe decimal(4,3) default null'],
		['sql_alter', 'TABLE spip_abo_offres CHANGE prix prix_ht varchar(25) NOT NULL DEFAULT \'\''],
		['sql_alter', 'TABLE spip_abo_offres CHANGE prix_renouvellement prix_ht_renouvellement varchar(25) NOT NULL DEFAULT \'\''],
		['sql_alter', 'TABLE spip_abonnements CHANGE commentaire log text NOT NULL DEFAULT \'\''],
		['maj_tables', ['spip_abonnements', 'spip_abo_offres']],
	];

	$maj['2.4.0'] = [
		['maj_tables', ['spip_abo_offres', 'spip_abonnements']],
	];
	$maj['2.4.2'] = [
		['sql_updateq', 'spip_abonnements', ['date_fin' => '0000-00-00 00:00:00'], 'date_fin IS NULL'],
		['sql_alter', 'TABLE spip_abonnements CHANGE date_fin date_fin datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\''],
	];
	$maj['2.4.3'] = [
		['sql_alter', 'TABLE spip_abonnements CHANGE mode_paiement mode_paiement varchar(25) NOT NULL DEFAULT \'\''],
	];

	$maj['2.4.4'] = [
		['sql_alter', 'TABLE spip_abonnements ADD date_fin_mode_paiement datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\''],
	];
	$maj['2.4.5'] = [
		['abos_date_fin_mode_paiement'],
	];

	$maj['2.4.6'] = [
		['maj_tables', ['spip_abo_offres']],
	];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

/**
 * Transfert des date_fin validité CB en date_fin_mode_paiement
 * @return void
 */
function abos_date_fin_mode_paiement() {
    sql_update('spip_abonnements', ['date_fin_mode_paiement' => 'date_fin', 'date_fin' => '\'0000-00-00 00:00:00\''], [
        'statut=\'ok\'',
        'date_fin like \'____-__-01 00:00:00\'',
    ]);
}

function abos_rattraper_date() {
	// pour tous ceux qui ont un paiement lie, on prend la date de paiement
	$res = sql_select(
		'A.id_abonnement, A.date, A.mode_paiement, T.date_paiement',
		'spip_abonnements AS A JOIN spip_abonnements_liens as L on L.id_abonnement=A.id_abonnement JOIN spip_transactions as T ON (L.objet=\'transaction\' AND L.id_objet=T.id_transaction)',
		'T.statut=' . sql_quote('ok') . ' AND A.date=' . sql_quote('0000-00-00 00:00:00'),
		'',
		'A.id_abonnement, T.date_paiement'
	);
	while ($row = sql_fetch($res)) {
		$date = $row['date_paiement'];
		if ($row['mode_paiement'] == 'payzen') {
			$date = date('Y-m-d H:i:s', strtotime('-9 days', strtotime($date)));
		}
		sql_updateq('spip_abonnements', ['date' => $date], 'id_abonnement=' . intval($row['id_abonnement']) . ' AND date=' . sql_quote('0000-00-00 00:00:00'));
		if (time() > _TIME_OUT) {
			return;
		}
	}

	// tous les autres sont des abos en commande, on les init avec la date_debut
	sql_update('spip_abonnements', ['date' => 'date_debut'], 'date=' . sql_quote('0000-00-00 00:00:00'));
}

function abos_rattraper_stats() {
	$compter = charger_fonction('compter', 'abos');
	while (!$compter()) {
		if (time() > _TIME_OUT) {
			return;
		}
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

	sql_drop_table('spip_abo_offres');
	sql_drop_table('spip_abonnements');
	sql_drop_table('spip_abonnements_liens');
	sql_drop_table('spip_abo_stats');

	# Nettoyer les versionnages et forums
	sql_delete('spip_versions', sql_in('objet', ['abooffre', 'abonnement']));
	sql_delete('spip_versions_fragments', sql_in('objet', ['abooffre', 'abonnement']));
	sql_delete('spip_forum', sql_in('objet', ['abooffre', 'abonnement']));

	effacer_meta($nom_meta_base_version);
}
