<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2009                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
 * \***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")){
	return;
}


// chargement des valeurs par defaut des champs du formulaire
function formulaires_distribuer_abonnement_charger_dist($id_auteur){

	return
		array(
			'id_abo_offre' => '',
		);
}


// chargement des valeurs par defaut des champs du formulaire
function formulaires_distribuer_abonnement_traiter_dist($id_auteur){
	include_spip("base/abstract_sql");

	$id_abo_offre = _request('id_abo_offre');
	$abonner = charger_fonction('abonner', 'abos');
	$id_abonnement = $abonner($id_abo_offre, array('id_auteur' => $id_auteur, 'statut' => 'ok', 'prix_initial' => '0'));

	// modifier l'abonnement en mettant les echeances a prix nul
	$set = array(
		"prix_echeance" => 0,
		"mode_paiement" => 'gratuit'
	);
	if ($d = _request('date_fin')){
		list($annee, $mois, $jour, $heures, $minutes, $secondes) = recup_date($d);
		$d = mktime($heures, $minutes, $secondes, $mois, $jour, $annee);
		$d = date("Y-m-d H:i:s", $d);
		$set['date_fin'] = $d;
		$set['date_echeance'] = $d;
	} else {
		// fin a l'echeance, c'est la duree par defaut de l'abonnement
		$set['date_fin'] = sql_getfetsel("date_echeance", "spip_abonnements", "id_abonnement=" . intval($id_abonnement));
	}

	sql_updateq("spip_abonnements", $set, "id_abonnement=" . intval($id_abonnement));
	return
		array(
			'message_ok' => 'Abonnement ajouté',
			'editable' => true
		);
}
