<?php

// Sécurité
if (!defined('_ECRIRE_INC_VERSION')) return;

function prix_abooffre_dist($id_objet, $prix_ht){
	$prix = $prix_ht;
	
	// S'il y a une taxe de définie explicitement dans l'offre, on applique en priorité
	if (($id_abo_offre = intval($id_objet)) > 0
		and include_spip('base/abstract_sql')
		and ($taxe = sql_getfetsel('taxe', 'spip_abo_offres', 'id_abo_offre = '.intval($id_abo_offre))) !== null
	){
		$prix += $prix*$taxe;
	}
	// Sinon on applique la taxe par défaut
	else{
		include_spip('inc/config');
		$prix += $prix*lire_config('abos/taxe', 0);
	}
	
	return $prix;
}
