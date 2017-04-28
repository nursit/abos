<?php
/**
 * Déclarations relatives à la base de données
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Déclaration des alias de tables et filtres automatiques de champs
 *
 * @pipeline declarer_tables_interfaces
 * @param array $interfaces
 *     Déclarations d'interface pour le compilateur
 * @return array
 *     Déclarations d'interface pour le compilateur
 */
function abos_declarer_tables_interfaces($interfaces) {

	$interfaces['table_des_tables']['abo_offres'] = 'abo_offres';
	$interfaces['table_des_tables']['abonnements'] = 'abonnements';

	$interfaces['table_des_traitements']['INTITULE_SYNTHESE']['abo_offres']= _TRAITEMENT_TYPO;
	$interfaces['table_des_traitements']['PHRASE_ASTERISQUE']['abo_offres']= _TRAITEMENT_RACCOURCIS;

	return $interfaces;
}


/**
 * Déclaration des objets éditoriaux
 *
 * @pipeline declarer_tables_objets_sql
 * @param array $tables
 *     Description des tables
 * @return array
 *     Description complétée des tables
 */
function abos_declarer_tables_objets_sql($tables) {

	$tables['spip_abo_offres'] = array(
		'type' => 'abooffre',
		'page' => false,
		'principale' => "oui", 
		'table_objet_surnoms' => array('abooffre'), // table_objet('abooffre') => 'abo_offres' 
		'field'=> array(
			"id_abo_offre"       => "bigint(21) NOT NULL",
			"titre"              => "text NOT NULL",
			"descriptif"         => "text NOT NULL",
			"texte"              => "text NOT NULL",
			"duree"              => "varchar(10) NOT NULL DEFAULT ''",
			"prix_ht"               => "varchar(25) NOT NULL DEFAULT ''",
			"prix_ht_renouvellement" => "varchar(25) NOT NULL DEFAULT ''",
			"taxe"               => "decimal(4,3) default null",
			"mode_renouvellement"=> "varchar(10) NOT NULL DEFAULT ''",
			"wha_oid"            => "varchar(10) NOT NULL DEFAULT ''",
			"immateriel"         => "tinyint(1) NOT NULL DEFAULT 0",
			"poids"              => "bigint(21) NOT NULL DEFAULT 0", // poids en g
			"largeur"            => "bigint(21) NOT NULL DEFAULT 0", // largeur en cm
			"longueur"           => "bigint(21) NOT NULL DEFAULT 0", // longueur en cm
			"hauteur"            => "bigint(21) NOT NULL DEFAULT 0", // hauteur en cm
			"statut"             => "varchar(20)  DEFAULT '0' NOT NULL",
			"maj"                => "TIMESTAMP"
		),
		'key' => array(
			"PRIMARY KEY"        => "id_abo_offre",
			"KEY statut"         => "statut", 
		),
		'titre' => "titre AS titre, '' AS lang",
		 #'date' => "",
		'champs_editables'  => array('titre', 'descriptif', 'texte', 'duree', 'prix_ht', 'prix_ht_renouvellement','taxe','mode_renouvellement','immateriel','poids','largeur','longueur','hauteur'),
		'champs_versionnes' => array('titre', 'descriptif', 'texte', 'duree', 'prix_ht', 'prix_ht_renouvellement','taxe','mode_renouvellement','immateriel','poids','largeur','longueur','hauteur'),
		'rechercher_champs' => array('titre'=>4,'descriptif'=>2,'texte'=>2,'wha_oid'=>1),
		'tables_jointures'  => array(),
		'statut_textes_instituer' => array(
			'prepa'    => 'abooffre:texte_statut_en_cours_redaction',
			'publie'   => 'abooffre:texte_statut_publie',
			'refuse'   => 'abooffre:texte_statut_refuse',
			'poubelle' => 'abooffre:texte_statut_poubelle',
		),
		'statut'=> array(
			array(
				'champ'     => 'statut',
				'publie'    => 'publie',
				'previsu'   => 'publie,prepa',
				'exception' => array('statut','tout')
			)
		),
		'texte_changer_statut' => 'abooffre:texte_changer_statut_abooffre', 
		

	);

	$tables['spip_abonnements'] = array(
		'type' => 'abonnement',
		'page' => false,
		'principale' => "oui",
		'field'=> array(
			"id_abonnement"      => "bigint(21) NOT NULL",
			"id_abo_offre"       => "bigint(21) NOT NULL",
			"id_auteur"          => "bigint(21) NOT NULL",
			"date"               => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'", // date de creation de l'abonnement (stats)
			"id_commande"        => "bigint(21) NOT NULL",
			"date_debut"         => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			"date_fin"           => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			"date_echeance"      => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			"duree_echeance"     => "varchar(10) NOT NULL DEFAULT ''",
			"prix_echeance"      => "varchar(25) NOT NULL DEFAULT ''",
			"credits_echeance"   => "text NOT NULL DEFAULT ''",
			"mode_echeance"      => "varchar(10) NOT NULL DEFAULT ''",
			"id_transaction_echeance" => "bigint(21) NOT NULL DEFAULT '0'",
			"id_transaction_essai" => "bigint(21) NOT NULL DEFAULT '0'",
			"credits"            => "text NOT NULL DEFAULT ''",
			"mode_paiement"      => "varchar(10) NOT NULL DEFAULT ''",
			"abonne_uid"         => "varchar(50) NOT NULL DEFAULT ''",
			"confirm"            => "varchar(255) NOT NULL DEFAULT ''",
			"log"                => "text NOT NULL DEFAULT ''",
			"message"            => "text NOT NULL DEFAULT ''",
			"relance"            => "varchar(3) NOT NULL DEFAULT ''",
			"statut"             => "varchar(20)  DEFAULT '0' NOT NULL",
			"maj"                => "TIMESTAMP"
		),
		'key' => array(
			"PRIMARY KEY"        => "id_abonnement",
			"KEY statut"         => "statut", 
		),
		'titre' => "abonne_uid AS titre, '' AS lang",
		'date' => 'date',
		'champs_editables'  => array('abonne_uid','date_debut','date_echeance','date_fin','prix_echeance'),
		'champs_versionnes' => array(),
		'rechercher_champs' => array('abonne_uid'=>1,'mode_paiement'=>1),
		'rechercher_jointures' => array(
			'auteur' => array('email' => 1),
		),
		'tables_jointures'  => array('id_transaction'=>'abonnements_liens'),
		'statut_textes_instituer' => array(
			'prepa'   => 'abonnement:texte_statut_prepa',
			'ok'   => 'abonnement:texte_statut_ok',
			'resilie'   => 'abonnement:texte_statut_resilie',
		),
		'statut_images' => array(
			'abonnement-16.png',
			'prepa'=>'puce-preparer-8.png',
			'ok'=>'puce-publier-8.png',
			'resilie'=>'puce-supprimer-8.png',
		),
		'statut'=> array(
			array(
				'champ'     => 'statut',
				'publie'    => 'ok',
				'previsu'   => 'ok',
				'exception' => array('statut','tout')
			)
		),
		'texte_changer_statut' => 'abonnement:texte_changer_statut_abonnement', 
		

	);
	$tables[]['tables_jointures'][]= 'abonnements_liens';
	$tables[]['tables_jointures'][]= 'abonnements';

	return $tables;
}


/**
 * Déclaration des tables secondaires (liaisons)
 *
 * @pipeline declarer_tables_auxiliaires
 * @param array $tables
 *     Description des tables
 * @return array
 *     Description complétée des tables
 */
function abos_declarer_tables_auxiliaires($tables) {

	$tables['spip_abonnements_liens'] = array(
		'field' => array(
			"id_abonnement"      => "bigint(21) DEFAULT '0' NOT NULL",
			"id_objet"           => "bigint(21) DEFAULT '0' NOT NULL",
			"objet"              => "VARCHAR(25) DEFAULT '' NOT NULL",
			"date"                 => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'"
		),
		'key' => array(
			"PRIMARY KEY"        => "id_abonnement,id_objet,objet",
			"KEY id_abonnement"  => "id_abonnement"
		)
	);
	$tables['spip_abo_stats'] = array(
		'field' => array(
			"date"	                    => "DATE NOT NULL DEFAULT '0000-00-00'", // date du jour de stats
			"nb_abonnes"                => "bigint(21) DEFAULT '0' NOT NULL", // nombre d'abonnes uniques
			"nb_abonnements"            => "bigint(21) DEFAULT '0' NOT NULL", // nombre d'abonnements actifs
			"nb_abonnements_new"        => "bigint(21) DEFAULT '0' NOT NULL", // nombre d'abonnements souscrits par de nouveaux abonnes (conquete)
			"nb_abonnements_plus"       => "bigint(21) DEFAULT '0' NOT NULL", // nombre d'abonnements souscrits
			"nb_abonnements_moins"      => "bigint(21) DEFAULT '0' NOT NULL", // nombre d'abonnements finis
			"ventil_abonnements"        => "text NOT NULL DEFAULT ''", // par offre : nombre d'abonnements actifs
			"ventil_abonnements_new"    => "text NOT NULL DEFAULT ''", // par offre : nombre d'abonnements souscrits par de nouveaux abonnes (conquete)
			"ventil_abonnements_plus"   => "text NOT NULL DEFAULT ''", // par offre : nombre d'abonnements souscrits
			"ventil_abonnements_moins"  => "text NOT NULL DEFAULT ''", // par offre : nombre d'abonnements finis
		),
		'key' => array(
			"PRIMARY KEY"        => "date",
		)
	);

	return $tables;
}

