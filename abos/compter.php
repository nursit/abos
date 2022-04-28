<?php
/**
 * Fonctions utiles
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Inc
 */

/**
 * Compter les stats abonnement depuis la derniere fois jusqu'a aujourd'hui
 * @return bool
 *   true si on a fini, false sinon
 */
function abos_compter_dist(){

	$now = $_SERVER['REQUEST_TIME'];
	$yesterday = date('Y-m-d', strtotime('-1 day', $now));

	$last = '';
	// dernieres stats faites ?
	$last = sql_getfetsel('date', 'spip_abo_stats', 'date<=' . sql_quote($yesterday), '', 'date DESC', '0,1');
	// a moins que ca ne soit la premiere fois ?
	if (!$last){
		$last = sql_getfetsel('date', 'spip_abonnements', sql_in('statut', array('ok', 'resilie')), '', 'date', '0,1');
		if (!$last
			OR !intval($last)
			OR !strtotime($last)
		){
			// rien a faire, on a fini
			return true;
		}
		// il faut partir de la veille
		$last = date('Y-m-d', strtotime('-1 day', strtotime($last)));
	}

	// ok faisons les stats de jour en jour jusqu'a $yesterday
	$nmax = 10;
	while ($last<$yesterday AND $nmax-->0){
		$day = date('Y-m-d', strtotime('+1 day', strtotime($last)));
		abos_compter_jour($day);
		$last = $day;
	}

	return (($last==$yesterday) ? true : false);
}


function abos_compter_jour($day){

	// precaution
	$day = date('Y-m-d', strtotime($day));
	$day_start = date('Y-m-d 00:00:00', strtotime($day));
	$day_end = date('Y-m-d 23:59:59', strtotime($day));

	$set = array(
		'date' => $day,
		'nb_abonnes' => 0,
		'nb_abonnements' => 0, // nombre d'abonnements actifs
		'nb_abonnements_new' => 0, // nombre d'abonnements souscrits par de nouveaux abonnes (conquete)
		'nb_abonnements_plus' => 0, // nombre d'abonnements souscrits
		'nb_abonnements_moins' => 0, // nombre d'abonnements finis
		'ventil_abonnements' => '', // par offre : nombre d'abonnements actifs
		'ventil_abonnements_new' => '', // par offre : nombre d'abonnements souscrits par de nouveaux abonnes (conquete)
		'ventil_abonnements_plus' => '', // par offre : nombre d'abonnements souscrits
		'ventil_abonnements_moins' => '', // par offre : nombre d'abonnements finis
	);

	$where_abos = array();
	$where_abos[] = 'date_debut<=' . sql_quote($day_end);
	// les abonnements qui finissent dans la journee ne sont plus comptes, il seront comptes en abonnements perdus ce jour
	$where_abos[] = '(date_fin IS NULL OR date_fin<date_debut OR date_fin>=' . sql_quote($day_end) . ')';
	$where_abos[] = '(statut=' . sql_quote('ok') . ' OR (statut=' . sql_quote('resilie') . ' AND date_fin>date_debut))';
	// ne pas compter les abonnements gratuit ou parrain
	$where_abos[] = sql_in('mode_paiement', array('gratuit', 'parrain'), 'NOT');

	// les abonnes unique actifs
	$row = sql_fetsel('COUNT(DISTINCT id_auteur) AS N', 'spip_abonnements', $where_abos);
	$set['nb_abonnes'] = reset($row);

	// les abonnements actifs, par offre
	$rows = sql_allfetsel('id_abo_offre,count(id_abonnement) AS N', 'spip_abonnements', $where_abos, 'id_abo_offre');
	abos_compte_ventilation('', $set, $rows);

	// les abonnements en plus de ce jour : ce sont des abonnements (actifs ou resilies) souscrits ce jour
	// meme si ils commencent dans le futur par rapport a la date
	$where_abos = array();
	// les abonnements qui finissent dans la journee sont comptes, il seront aussi comptes en abonnements perdus ce jour
	$where_abos[] = '(date_fin IS NULL OR date_fin<date_debut OR date_fin>=' . sql_quote($day_start) . ')';
	$where_abos[] = '(statut=' . sql_quote('ok') . ' OR (statut=' . sql_quote('resilie') . ' AND date_fin>date_debut))';
	$where_abos[] = '(date>=' . sql_quote($day_start) . ' AND date<=' . sql_quote($day_end) . ')';
	// ne pas compter les abonnements gratuit ou parrain
	$where_abos[] = sql_in('mode_paiement', array('gratuit', 'parrain'), 'NOT');
	$rows = sql_allfetsel('id_abo_offre,count(id_abonnement) AS N', 'spip_abonnements', $where_abos, 'id_abo_offre');
	abos_compte_ventilation('plus', $set, $rows);

	// les conquetes de ce jour : ce sont des abonnements (actifs ou resilies) souscrits ce jour
	// par des abonnes qui n'avaient aucun autre abonnement souscrit avant ce jour
	$id_auteurs = sql_allfetsel('DISTINCT id_auteur', 'spip_abonnements', $where_abos);
	$id_auteurs = array_column($id_auteurs, 'id_auteur');
	// maintenant exclure les auteurs qui avaient un abonnement souscrit avant (hors gratuit/parrain)
	$exclus = sql_allfetsel('DISTINCT id_auteur', 'spip_abonnements', 'date<' . sql_quote($day_start) . ' AND ' . sql_in('statut', array('ok', 'resilie')) . ' AND ' . sql_in('id_auteur', $id_auteurs) . ' AND ' . sql_in('mode_paiement', array('gratuit', 'parrain'), 'NOT'));
	$exclus = array_column($exclus, 'id_auteur');
	$id_auteurs = array_diff($id_auteurs, $exclus);
	$where_abos[] = sql_in('id_auteur', $id_auteurs);
	$rows = sql_allfetsel('id_abo_offre,count(id_abonnement) AS N', 'spip_abonnements', $where_abos, 'id_abo_offre');
	abos_compte_ventilation('new', $set, $rows);

	// les abonnements perdus ce jour : ce sont les abonnements (actifs ou resilies)
	// dont date_fin est ce jour
	$where_abos = array();
	$where_abos[] = 'date_fin>date_debut';
	$where_abos[] = 'date_fin>=' . sql_quote($day_start);
	$where_abos[] = 'date_fin<=' . sql_quote($day_end);
	// ne pas compter les abonnements gratuit ou parrain
	$where_abos[] = sql_in('mode_paiement', array('gratuit', 'parrain'), 'NOT');
	$where_abos[] = sql_in('statut', array('ok', 'resilie'));
	/*
	// par des abonnes qui n'ont aucun autre abonnement souscrit a la suite (renouvellement)
	$id_auteurs = sql_allfetsel('DISTINCT id_auteur','spip_abonnements',$where_abos);
	$id_auteurs = array_column($id_auteurs, 'id_auteur');
	// maintenant exclure les auteurs qui ont souscrit un autre abonnement a la suite
	$exclus = sql_allfetsel('DISTINCT id_auteur','spip_abonnements','date_debut>='.sql_quote($day_start).' AND '.sql_in('statut',array('ok','resilie')).' AND '.sql_in('id_auteur',$id_auteurs));
	$exclus = array_column($exclus, 'id_auteur');
	$id_auteurs = array_diff($id_auteurs,$exclus);
	$where_abos[] = sql_in('id_auteur',$id_auteurs);
	*/
	$rows = sql_allfetsel('id_abo_offre,count(id_abonnement) AS N', 'spip_abonnements', $where_abos, 'id_abo_offre');
	abos_compte_ventilation('moins', $set, $rows);

	//var_dump($set);
	sql_insertq('spip_abo_stats', $set);

}


function abos_compte_ventilation($quoi, &$set, $rows){
	$_quoi = ($quoi ? '_' . $quoi : '');

	$set['ventil_abonnements' . $_quoi] = array();
	foreach ($rows as $row){
		$set['ventil_abonnements' . $_quoi][$row['id_abo_offre']] = $row['N'];
	}
	$set['nb_abonnements' . $_quoi] = array_sum($set['ventil_abonnements' . $_quoi]);
	$set['ventil_abonnements' . $_quoi] = json_encode($set['ventil_abonnements' . $_quoi]);
}


function abos_reporting_decompte($nb_mois = 6){
	$now = $_SERVER['REQUEST_TIME'];

	//DEBUG
	//sql_delete("spip_abo_stats","date>'2016-05-06'");
	//abos_compter_dist();

	$offres_vues = array();

	$texte = "";
	// les stats sur la derniere semaine
	$j = date('Y-m-d', $now);
	$jm7 = date('Y-m-d', strtotime("-7 day", $now));


	$head = "<tr>"
		. "<th>" . _T('public:date') . "</th>"
		. "<th>" . _T('abonnement:label_abonnes') . "</th>"
		. "<th>" . _T('abonnement:titre_abonnements') . "</th>"
		. "<th>+</th>"
		. "<th>(+" . _T('abonnement:label_nouveau_abbr') . ")</th>"
		. "<th>-</th>"
		. "</tr>";
	$head .= "<tr><td></td>"
		. "<td colspan='2' style='text-align: center'>" . _T('abonnement:label_actifs') . "</td>"
		. "<td colspan='2' style='text-align: center'>" . _T('abonnement:label_vendus') . "</td>"
		. "<td style='text-align: center'>" . _T('abonnement:label_resilies') . "</td>"
		. "</tr>";

	$jours = sql_allfetsel('*', 'spip_abo_stats', 'date>=' . sql_quote($jm7) . ' AND date<' . sql_quote($j), '', 'date DESC');
	$lignes = "";
	foreach ($jours as $jour){
		$lignes .= abos_one_line(affdate($jour['date']), $jour, $offres_vues);
	}
	$texte .= "<h2>" . _T('abonnement:derniers_jours_nb', array('nb' => 7)) . "</h2>
<table class='spip'>
$head
$lignes
</table>";

	// debut de la semaine en cours
	$off = -date('w', strtotime('-1 day', $now));

	$lignes = "";
	for ($i = 0; $i<4; $i++){
		$j = date('Y-m-d', strtotime($off . ' day', $now));
		$off -= 7;
		$jm7 = date('Y-m-d', strtotime($off . ' day', $now));
		$jours = sql_allfetsel('*', 'spip_abo_stats', 'date>=' . sql_quote($jm7) . ' AND date<' . sql_quote($j), '', 'date DESC');
		$total = abos_sum_lines($jours);
		$lignes .= abos_one_line("Semaine du " . date('d/m', strtotime($jm7)), $total, $offres_vues);
	}
	$texte .= "<h2>" . _T('abonnement:dernieres_semaines_nb', array('nb' => 4)) . "</h2>
<table class='spip'>
$head
$lignes
</table>";

	// $nb_mois derniers mois
	$lignes = "";
	$jm1 = date('Y-m-01', $now);
	for ($i = 0; $i<$nb_mois; $i++){
		$jm1 = date('Y-m-01', strtotime("-15 day", strtotime($jm1)));
		$jm31 = date('Y-m-31', strtotime($jm1));
		$jours = sql_allfetsel('*', 'spip_abo_stats', 'date>=' . sql_quote($jm1) . ' AND date<=' . sql_quote($jm31), '', 'date DESC');
		$total = abos_sum_lines($jours);
		$lignes .= abos_one_line(ucfirst(affdate_mois_annee($jm1)), $total, $offres_vues);
	}
	$texte .= "<h2>" . _T('abonnement:derniers_mois_nb', array('nb' => $nb_mois)) . "</h2>
<table class='spip'>
$head
$lignes
</table>";

	$t = "";
	ksort($offres_vues);
	foreach (array_keys($offres_vues) as $id_abo_offre){
		$t .= "#$id_abo_offre&nbsp;: " . generer_info_entite($id_abo_offre, 'abooffre', 'titre') . "<br />";
	}
	if ($t){
		$t = "<p>$t</p>";
	}

	return $t . $texte;
}


function abos_sum_lines($rows){
	$total = array(
		'nb_abonnes' => 0,
		'nb_abonnements' => 0,
		'nb_abonnements_new' => 0,
		'nb_abonnements_plus' => 0,
		'nb_abonnements_moins' => 0,
		'ventil_abonnements' => array(),
		'ventil_abonnements_new' => array(),
		'ventil_abonnements_plus' => array(),
		'ventil_abonnements_moins' => array(),
	);
	$first = reset($rows);
	$total['nb_abonnes'] = $first['nb_abonnes'];
	$total['nb_abonnements'] = $first['nb_abonnements'];
	$total['ventil_abonnements'] = $first['ventil_abonnements'];
	foreach ($rows as $row){
		foreach (array('abonnements_new', 'abonnements_plus', 'abonnements_moins') as $quoi){
			$total['nb_' . $quoi] += $row['nb_' . $quoi];
			if ($ventil = json_decode($row['ventil_' . $quoi], true)){
				foreach ($ventil as $id => $nb){
					if (!isset($total['ventil_' . $quoi])){
						$total['ventil_' . $quoi] = 0;
					}
					$total['ventil_' . $quoi][$id] += $nb;
				}
			}
		}
	}
	foreach (array('abonnements_new', 'abonnements_plus', 'abonnements_moins') as $quoi){
		$total['ventil_' . $quoi] = json_encode($total['ventil_' . $quoi]);
	}
	return $total;
}

function abos_one_line($titre, $row, &$seen){
	$ligne = "<tr><td valign='top'>$titre</td>";

	$ligne .= "<td valign='top'>" . (intval($row['nb_abonnes']) ? $row['nb_abonnes'] : '') . "</td>";
	$ligne .= "<td valign='top'>" . (intval($row['nb_abonnements']) ? $row['nb_abonnements'] : '') . "</td>";
	$ventil = json_decode($row['ventil_abonnements_plus'], true);
	ksort($ventil);
	$t = '';
	foreach ($ventil as $id => $nb){
		$t .= "<br />#$id&nbsp;: $nb";
		$seen[$id] = true;
	}
	$ligne .= "<td valign='top'>" . (intval($row['nb_abonnements_plus']) ? '+' . $row['nb_abonnements_plus'] . "<small>$t</small>" : '') . "</td>";
	$ventil = json_decode($row['ventil_abonnements_new'], true);
	ksort($ventil);
	$t = '';
	foreach ($ventil as $id => $nb){
		$t .= "<br />#$id&nbsp;: $nb";
		$seen[$id] = true;
	}
	$ligne .= "<td valign='top'>" . (intval($row['nb_abonnements_new']) ? '(<b>+' . $row['nb_abonnements_new'] . '</b>)' . "<small>$t</small>" : '') . "</td>";
	$ventil = json_decode($row['ventil_abonnements_moins'], true);
	ksort($ventil);
	$t = '';
	foreach ($ventil as $id => $nb){
		$t .= "<br />#$id&nbsp;: $nb";
		$seen[$id] = true;
	}
	$ligne .= "<td valign='top'>" . (intval($row['nb_abonnements_moins']) ? '-' . $row['nb_abonnements_moins'] . "<small>$t</small>" : '') . "</td>";
	$ligne .= "</tr>";

	return $ligne;
}

function abos_reporting_parrainages($nb_mois = 6){
	$now = $_SERVER['REQUEST_TIME'];

	$texte = "";
	$head = "<tr>"
		. "<th>" . spip_ucfirst(_T('date_un_mois')) . "</th>"
		. "<th>" . _T('abonnement:label_nombre_parrainages_abbr') . "</th>"
		. "<th>" . _T('abonnement:texte_dont_convertis_ensuite') . "</th>"
		. "</tr>";
	// $nb_mois derniers mois
	$lignes = "";
	$jm1 = date('Y-m-01',strtotime('+15 days',strtotime(date('Y-m-28', $now))));
	for ($i = 0; $i<$nb_mois; $i++){
		$jm1 = date('Y-m-01', strtotime("-15 day", strtotime($jm1)));
		$jm31 = date('Y-m-31', strtotime($jm1));

		$parrainages = sql_allfetsel('id_abonnement,id_auteur,date,date_debut', 'spip_abonnements', 'mode_paiement=\'parrain\' AND date>=' . sql_quote($jm1) . ' AND date<=' . sql_quote($jm31));
		$nb_parrainnages = count($parrainages);
		$nb_convertis = 0;
		foreach ($parrainages as $p){
			$nba = sql_countsel('spip_abonnements', 'id_auteur=' . intval($p['id_auteur']) . ' AND date>' . sql_quote($p['date'] . ' AND ' . sql_in('statut', array('ok', 'resilie'))));
			if ($nba>0){
				$nb_convertis++;
				if (!sql_countsel('spip_auteurs', 'id_auteur=' . intval($p['id_auteur']))){
					spip_log('parrainage converti en abonnement pour auteur perdu #' . $p['id_auteur'], 'filleulsperdus');
				}
			}
		}

		$lignes .= "<tr>"
			. "<td>" . spip_ucfirst(affdate_mois_annee($jm1)) . "</td>"
			. "<td>$nb_parrainnages</td>"
			. "<td>$nb_convertis</td>";
	}
	$texte .= "<h2>" . _T('abonnement:derniers_mois_nb', array('nb' => 6)) . "</h2>
<table class='spip'>
$head
$lignes
</table>";

	return $texte;
}
