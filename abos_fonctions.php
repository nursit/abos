<?php
/**
 * Filtres, balises etc necessaires au calcul de la page
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Fonctions
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

/**
 * Afficher le rapport stats abos
 * @return string
 */
function filtre_abos_reporting_decompte_dist(){
	include_spip('abos/compter');
	return abos_reporting_decompte(36); // sur 36 mois en affichage dans le site
}

function filtre_abos_reporting_parrainages(){
	include_spip('abos/compter');
	return abos_reporting_parrainages(6); // sur 12 mois en affichage dans le site
}

/**
 * Compter les abonnes vus connectes sur le mois passe
 * @return mixed
 */
function abos_compter_abonnes_recents_enligne(){

	$from = date('Y-m-d H:i:s', strtotime("-1 month"));
	//$now = date('Y-m-d H:i:s');
	$n = sql_getfetsel("count(A.id_auteur) as n",
		"spip_abonnements as A JOIN spip_auteurs as AU ON AU.id_auteur=A.id_auteur",
		"A.id_auteur>0 AND AU.en_ligne>" . sql_quote($from) . " AND A.statut=" . sql_quote('ok'));

	return $n;
}


/**
 * Traduire le combo statut+date echeance en une chaine de langue
 * @param string $statut
 * @param string $date_echeance
 * @return string
 */
function abos_statut_en_clair($statut, $date_echeance){
	static $now = null;
	if (is_null($now)){
		$now = date('Y-m-d H:i:s');
	}
	if ($statut=="resilie"){
		return "abonnement:info_statut_resilie";
	} elseif ($statut=="ok") {
		if ($date_echeance<$now){
			return "abonnement:info_statut_impaye";
		}
		return "abonnement:info_statut_ok";
	}
	if ($statut=="prepa"){
		return "abonnement:info_statut_prepa";
	}

	return "abonnement:info_statut_erreur";
}

/**
 * Traduit la duree d'abonnement en info lisible, sous forme de periode
 * tous les x mois, tous les ans...
 * @param $duree
 * @return mixed|string
 */
function abos_periode_en_clair($periodicite){
	$nb = intval($periodicite);
	$duree = trim(preg_replace(",^\d+\s+,", "", $periodicite));
	$duree = ($nb==1 ? _T('abooffre:periodicite_' . $duree) : _T('abooffre:tous_les_nb_' . $duree, array('nb' => $nb)));
	return $duree;
}

/**
 * Traduit la duree d'abonnement en info lisible, sous forme de periode
 * tous les x mois, tous les ans...
 * @param $duree
 * @return mixed|string
 */
function abos_duree_en_clair($duree){
	$nb = intval($duree);
	$duree = trim(preg_replace(",^\d+\s+,", "", $duree));
	$duree = ($nb==1 ? _T('abooffre:1_' . $duree) : _T('abooffre:nb_' . $duree, array('nb' => $nb)));
	return $duree;
}


/**
 * Affiche le nombre de mois depuis lequel un abonnement court
 *
 * @param string $date_debut
 * @return string
 */
function abos_duree_abonnement($date_debut){
	if (!$date_debut){
		return;
	}
	$decal = date("U")-date("U", strtotime($date_debut));

	if ($decal<0){
		return "";
	}

	$mois = ceil($decal/(3600*24*365/12));
	return $mois;
}

/**
 * Lister les transactions liees a un abonnement
 * @param int $id_abonnement
 * @param int $id_commande
 * @return array
 */
function abos_liste_transactions($id_abonnement, $id_commande = 0){
	$ids = sql_allfetsel("id_objet", "spip_abonnements_liens", "id_abonnement=" . intval($id_abonnement) . " AND objet=" . sql_quote('transaction'));
	$ids = array_map('reset', $ids);
	if ($id_commande){
		$id2s = sql_allfetsel("id_transaction", "spip_transactions", "id_commande=" . intval($id_commande));
		$id2s = array_map('reset', $id2s);
		$ids = array_merge($id2s, $ids);
	}
	if (!$ids){
		$ids = array(0);
	}
	return $ids;
}

/**
 * Traduit les credits d'un abonnement en info lisible
 * Les credits sont definis par des mots cles (un mot cle = un type de credits)
 * usage facultatif
 * @param string|array $credits
 * @return string
 */
function abos_credits_en_clair($credits){
	static $lib_mot = array();
	if (!is_array($credits)){
		$credits = unserialize($credits);
		if ($credits===false){
			return '';
		}
	}

	$out = array();
	foreach ($credits as $id_mot => $nb){
		if (!isset($lib_mot[$id_mot])){
			if ($row = sql_fetsel("titre", "spip_mots", "id_mot=" . intval($id_mot))){
				$lib_mot[$id_mot] = "<abbr title=\"" . attribut_html(typo($row['titre'])) . "\">" . substr($row['titre'], 0, 1) . "</abbr>";
			}
		}
		if ($nb){
			$out[] = $lib_mot[$id_mot] . ($nb==='inf' ? '' : "($nb)");
		}
	}
	return typo(implode(', ', $out));
}

function abos_couper_abbr($texte, $longueur = 20){
	$t = '<abbr title="' . attribut_html($texte) . '">' . couper($texte, $longueur) . '</abbr>';
	return $t;
}


/**
 * @param $idb
 * @param $boucles
 * @param $crit
 */
function critere_somme_echeance_dist($idb, &$boucles, $crit){
	$boucle = &$boucles[$idb];
	$boucle->select[] = "sum(prix_echeance) as somme_echeance";
}

/**
 * @param $idb
 * @param $boucles
 * @param $crit
 */
function critere_abonement_en_cours_dist($idb, &$boucles, $crit){
	$boucle = &$boucles[$idb];
	$t = $boucle->id_table;
	$_date_ref = "date('Y-m-d H:i:00',\$_SERVER['REQUEST_TIME'])";
	$boucle->where[] = "'$t.date_debut<'.sql_quote($_date_ref).' AND ($t.date_fin<$t.date_debut OR $t.date_fin>'.sql_quote($_date_ref).')'";
}


// Si on est hors d'une boucle {recherche}, ne pas "prendre" cette balise
// http://doc.spip.org/@balise_POINTS_dist
function balise_SOMME_ECHEANCE_dist($p){
	return rindex_pile($p, 'somme_echeance', 'somme_echeance');
}


/**
 * <BOUCLE(ABONNEMENTS)>
 * si il n'y a pas de critere statut, le boucle ABONNEMENTS filtre sur statut=ok
 * ET date_fin valide (date<date_fin ou date_fin null ou date_fin<date_debut)
 *
 * @param $id_boucle
 * @param $boucles
 * @return string
 */
function boucle_ABONNEMENTS_dist($id_boucle, &$boucles){
	$boucle = &$boucles[$id_boucle];
	$id_table = $boucle->id_table;
	$boucle->from[$id_table] = "spip_abonnements";
	$mstatut = $id_table . '.statut';

	// conditions de statut
	instituer_boucle($boucle);
	// Restreindre aux abonnements dont la date de fin n'est pas passee
	if (!$boucle->modificateur['criteres']['statut']){
		$_date_ref = "date('Y-m-d H:i:00',\$_SERVER['REQUEST_TIME'])";
		$boucle->where[] = array("'OR'", array("'>'", "'$id_table" . ".date_fin'", "sql_quote($_date_ref)"), array("'OR'", "'$id_table" . ".date_fin IS NULL'", array("'<'", "'$id_table" . ".date_fin'", "'$id_table" . ".date_debut'")));
	}
	return calculer_boucle($id_boucle, $boucles);
}

function critere_ABONNEMENTS_parrain_dist($idb, &$boucles, $crit){
	$boucle = &$boucles[$idb];
	$t = $boucle->id_table;

	$_parrain = !isset($crit->param[0][0]) ? "_request('parrain')" : calculer_liste(array($crit->param[0][0]), array(), $boucles, $boucles[$idb]->id_parent);

	$where = "'$t.id_abonnement IN (SELECT DISTINCT LLLL.id_abonnement FROM spip_abonnements_liens AS LLLL JOIN spip_transactions AS TTTT ON (TTTT.id_transaction = LLLL.id_objet AND LLLL.objet = \'transaction\') WHERE '.sql_in('TTTT.parrain',is_array($_parrain)?$_parrain:array($_parrain)).')'";

	if ($crit->cond){
		$where = "(is_array($_parrain)?count(array_filter($_parrain)):$_parrain)?$where:''";
	}

	$boucle->where[] = $where;
}

/**
 * Trouver les auteurs qui ont payes une transaction mais n'ont aucun abonnement
 * @return array
 */
function abos_auteur_sans_abonnement(){
	include_spip('base/abstract_sql');

	$hasabo = sql_allfetsel('id_auteur', 'spip_abonnements', "statut IN ('ok','resilie')");
	$hasabo = array_map('reset', $hasabo);
	$hastrans = sql_allfetsel('id_auteur', 'spip_transactions', "statut='ok' AND id_auteur>0 AND " . sql_in('id_auteur', $hasabo, 'NOT'));
	$hastrans = array_map('reset', $hastrans);
	if (!$hastrans){
		$hastrans = array(0);
	}
	return $hastrans;
}

/**
 * Trouver les auteurs qui ont plusieurs abonnements en cours
 * @return array
 */
function abos_auteur_plusieurs_abonnements(){
	include_spip('base/abstract_sql');

	$hasabo = sql_allfetsel('id_auteur, count(id_auteur) AS N', 'spip_abonnements', "statut IN ('ok')", "id_auteur", "", "", "N>1");
	$hasabo = array_map('reset', $hasabo);
	if (!$hasabo){
		$hasabo = array(0);
	}
	return $hasabo;
}


/**
 * Calculer un historique du CA genere par cette offre d'abonnement
 * @param $id_abo_offre
 * @return string
 */
function abos_historique_encaissements($id_abo_offres){
	if (!is_array($id_abo_offres)) {
		$id_abo_offres = array($id_abo_offres);
	}

	$id_commandes = sql_allfetsel('A.id_commande','spip_abonnements as A',sql_in('A.id_abo_offre',$id_abo_offres));
	$id_commandes = array_map('reset', $id_commandes);

	$rows = sql_allfetsel(
		"count(T.id_transaction) as nombre_mensuel, sum(T.montant_ht) as montant_mensuel_ht,sum(T.montant) as montant_mensuel,T.date_paiement",
		"spip_transactions AS T",
		"T.id_commande>0 AND T.statut='ok' AND ".sql_in('T.id_commande',$id_commandes),
		"DATE_FORMAT(T.date_paiement,'%Y-%m')",
		"T.date_paiement DESC"
	);
	$out = "";
	foreach ($rows as $row){
		$mois = affdate_mois_annee($row['date_paiement']);
		$montant = affiche_monnaie($row['montant_mensuel']);
		$montant_ht = affiche_monnaie($row['montant_mensuel_ht']);
		$nb = $row['nombre_mensuel'];
		$out .= "<tr><td>$mois</td><td class='numeric'>$nb</td><td class='numeric'>$montant_ht</td><td class='numeric'>$montant</td></tr>\n";
	}

	if ($out){
		$out = "<table class='spip'>
<thead><tr class='row_first'><th>Mois</th><th class='numeric'>Nombre</th><th class='numeric'>Montant HT</th><th class='numeric'>Montant</th></td></thead>
<tbody>$out</tbody></table>";
	}

	return $out;
}

function abos_historique_encaissements_periode($duree) {

	$id_abo_offres = sql_allfetsel('id_abo_offre','spip_abo_offres','duree='.sql_quote($duree));
	if (!$id_abo_offres) {
		return '';
	}
	$id_abo_offres = array_map('reset', $id_abo_offres);
	return abos_historique_encaissements($id_abo_offres);

}