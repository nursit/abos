<?php

// Sécurité
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

function prix_abooffre_dist($id_objet, $prix_ht){
	$prix = $prix_ht;

	$abos_taux_tva = charger_fonction("abos_taux_tva", "inc");
	$taxe = $abos_taux_tva(0, $id_objet);
	$prix += $prix*$taxe;

	return $prix;
}
