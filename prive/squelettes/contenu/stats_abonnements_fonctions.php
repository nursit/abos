<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

function afficher_stats_echeances_offre($mois_relatif,$id_abo_offre){

	$ref = time();
	$ref = date('Y-m-15 00:00:00',$ref);
	$ref = strtotime($ref);

	if ($mois_relatif) {
		$ref = strtotime(($mois_relatif>0?"+":"").$mois_relatif." month",$ref);
	}

	$debut = date('Y-m-01 00:00:00',$ref);
	$fin = date('Y-m-31 23:59:59',$ref);

	$abos = sql_allfetsel("id_abonnement,id_auteur","spip_abonnements",
	  "id_abo_offre=".intval($id_abo_offre)
		. " AND date_debut<".sql_quote($debut)
		. " AND date_fin>=".sql_quote($debut)
	  . " AND date_fin<=".sql_quote($fin));

	$mois = affdate_mois_annee($debut);
	$nombre = count($abos);

	// compter les reabonnements
	$id_abos = array_map('reset',$abos);
	$id_auteur = array_map('end',$abos);

	$reabos = sql_allfetsel("id_abonnement,id_auteur,id_abo_offre","spip_abonnements",
		sql_in('id_auteur',$id_auteur)
		. " AND date_debut>=".sql_quote($debut)
		. " AND (date_fin<date_debut OR date_fin>=".sql_quote($fin).")"
	);
	$pourcent_reabos = $pourcent_nonreabos = "";
	$nombre_reabos = count($reabos);
	if ($nombre_reabos>0){
		$pourcent_reabos = round(($nombre_reabos/$nombre)*100,1)."%";
	}

	$nombre_nonreabos = $nombre - $nombre_reabos;
	if ($nombre_nonreabos>0){
		$pourcent_nonreabos = round(($nombre_nonreabos/$nombre)*100,1)."%";
	}

	if (!$nombre) $nombre="";
	if (!$nombre_reabos) $nombre_reabos="";
	if (!$nombre_nonreabos) $nombre_nonreabos="";

	if (!$nombre AND !$nombre_reabos AND !$nombre_nonreabos){
		return "";
	}
	return "<tr><td>$mois</td>"
	 . "<td class='numeric'>$nombre</td>"
	 . "<td class='numeric'>$nombre_reabos</td><td class='numeric'>$pourcent_reabos</td>"
	 . "<td class='numeric'>$nombre_nonreabos</td><td class='numeric'>$pourcent_nonreabos</td>"
	 . "</tr>";

}


function labas_reporting_cadeaux($nb_mois=6) {

	if (!$nb_mois) $nb_mois=6;

	$now = $_SERVER['REQUEST_TIME'];

	$texte = "";
	$head = "<tr><th>Mois</th><th>Nb cadeaux</th><th>(dont activés)</th></tr>";
	// $nb_mois derniers mois
	$lignes = "";
	$jm1 = date('Y-m-01',$now);
	for ($i=0;$i<$nb_mois;$i++){
		$jm31 = date('Y-m-31',strtotime($jm1));

		$sous = sql_allfetsel('cadeau','spip_souscriptions','statut=\'ok\' AND cadeau!=\'\' AND date_souscription>='.sql_quote($jm1).' AND date_souscription<='.sql_quote($jm31));

		$nb_cadeaux = 0;
		$nb_actives = 0;
		foreach($sous as $s){
			if ($cadeau = unserialize($s['cadeau'])) {
				$nb_cadeaux++;
				if ($cadeau['id_auteur']>0) {
					$nb_actives++;
				}
			}
		}

		$lignes .= "<tr>"
			. "<td>".ucfirst(affdate_mois_annee($jm1))."</td>"
			. "<td>$nb_cadeaux</td>"
			. "<td>$nb_actives</td>";

		// mois precedent
		$jm1 = date('Y-m-01',strtotime("-15 day",strtotime($jm1)));
	}
	$texte .= "<h2>$nb_mois derniers mois</h2>
<table class='spip'>
$head
$lignes
</table>";

	return $texte;
}