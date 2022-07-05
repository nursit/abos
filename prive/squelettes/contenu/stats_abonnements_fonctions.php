<?php

if (!defined('_ECRIRE_INC_VERSION')) { return;
}

function afficher_stats_echeances_offre($mois_relatif, $id_abo_offre) {

	$ref = time();
	$ref = date('Y-m-15 00:00:00', $ref);
	$ref = strtotime($ref);

	if ($mois_relatif) {
		$ref = strtotime(($mois_relatif > 0 ? '+' : '') . $mois_relatif . ' month', $ref);
	}

	$debut = date('Y-m-01 00:00:00', $ref);
	$fin = date('Y-m-31 23:59:59', $ref);

	$abos = sql_allfetsel(
		'id_abonnement,id_auteur',
		'spip_abonnements',
		'id_abo_offre=' . intval($id_abo_offre)
		. ' AND date_debut<' . sql_quote($debut)
		. ' AND date_fin>=' . sql_quote($debut)
		. ' AND date_fin<=' . sql_quote($fin)
	);

	$mois = affdate_mois_annee($debut);
	$nombre = count($abos);

	// compter les reabonnements
	$id_abos = array_column($abos, 'id_abonnement');
	$id_auteur = array_column($abos, 'id_auteur');

	$reabos = sql_allfetsel(
		'id_abonnement,id_auteur,id_abo_offre',
		'spip_abonnements',
		sql_in('id_auteur', $id_auteur)
		. ' AND date_debut>=' . sql_quote($debut)
		. ' AND (date_fin<date_debut OR date_fin>=' . sql_quote($fin) . ')'
	);
	$pourcent_reabos = $pourcent_nonreabos = '';
	$nombre_reabos = count($reabos);
	if ($nombre_reabos > 0) {
		$pourcent_reabos = round(($nombre_reabos / $nombre) * 100, 1) . '%';
	}

	$nombre_nonreabos = $nombre - $nombre_reabos;
	if ($nombre_nonreabos > 0) {
		$pourcent_nonreabos = round(($nombre_nonreabos / $nombre) * 100, 1) . '%';
	}

	if (!$nombre) { $nombre = '';
	}
	if (!$nombre_reabos) { $nombre_reabos = '';
	}
	if (!$nombre_nonreabos) { $nombre_nonreabos = '';
	}

	if (!$nombre and !$nombre_reabos and !$nombre_nonreabos) {
		return '';
	}
	return "<tr><td>$mois</td>"
	 . "<td class='numeric'>$nombre</td>"
	 . "<td class='numeric'>$nombre_reabos</td><td class='numeric'>$pourcent_reabos</td>"
	 . "<td class='numeric'>$nombre_nonreabos</td><td class='numeric'>$pourcent_nonreabos</td>"
	 . '</tr>';
}


function abos_reporting_cadeaux($nb_mois = 6) {
	if (function_exists('cadeaux_reporting')) {
		return cadeaux_reporting($nb_mois);
	}
	return '';
}
