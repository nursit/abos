<?php
/**
 * Gestion du formulaire de d'édition de abooffre
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Formulaires
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/actions');
include_spip('inc/editer');

/**
 * Identifier le formulaire en faisant abstraction des paramètres qui ne représentent pas l'objet edité
 *
 * @param int|string $id_abo_offre
 *     Identifiant du abooffre. 'new' pour un nouveau abooffre.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abooffre source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abooffre, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return string
 *     Hash du formulaire
 */
function formulaires_editer_abooffre_identifier_dist($id_abo_offre='new', $retour='', $lier_trad=0, $config_fonc='', $row=array(), $hidden=''){
	return serialize(array(intval($id_abo_offre)));
}

/**
 * Chargement du formulaire d'édition de abooffre
 *
 * Déclarer les champs postés et y intégrer les valeurs par défaut
 *
 * @uses formulaires_editer_objet_charger()
 *
 * @param int|string $id_abo_offre
 *     Identifiant du abooffre. 'new' pour un nouveau abooffre.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abooffre source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abooffre, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Environnement du formulaire
 */
function formulaires_editer_abooffre_charger_dist($id_abo_offre='new', $retour='', $lier_trad=0, $config_fonc='', $row=array(), $hidden=''){
	$valeurs = formulaires_editer_objet_charger('abooffre',$id_abo_offre,'',$lier_trad,$retour,$config_fonc,$row,$hidden);

	$duree = explode(" ",$valeurs['duree']);
	$valeurs['duree_valeur'] = reset($duree);
	$valeurs['duree_unite'] = end($duree);

	if (strlen($valeurs['taxe'])){
		$valeurs['taxe'] = 100 * $valeurs['taxe'];
	}

	if (test_plugin_actif('accesrestreint')){
		$valeurs['acces_zones'] = array();
		if (intval($id_abo_offre)){
			$zones = sql_allfetsel("id_zone","spip_zones_liens","objet='abooffre' AND id_objet=".intval($id_abo_offre));
			$valeurs['acces_zones'] = array_map('reset',$zones);
		}
	}

	return $valeurs;
}

/**
 * Vérifications du formulaire d'édition de abooffre
 *
 * Vérifier les champs postés et signaler d'éventuelles erreurs
 *
 * @uses formulaires_editer_objet_verifier()
 *
 * @param int|string $id_abo_offre
 *     Identifiant du abooffre. 'new' pour un nouveau abooffre.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abooffre source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abooffre, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Tableau des erreurs
 */
function formulaires_editer_abooffre_verifier_dist($id_abo_offre='new', $retour='', $lier_trad=0, $config_fonc='', $row=array(), $hidden=''){

	if (intval(_request('duree_valeur')) AND _request('duree_unite'))
		set_request('duree',intval(_request('duree_valeur')).' '._request('duree_unite'));
	$erreurs = formulaires_editer_objet_verifier('abooffre',$id_abo_offre, array('titre', 'duree'));

	$verifier = charger_fonction('verifier','inc');

	foreach(array('prix_ht','prix_ht_renouvellement') as $champ_prix){
		$prix = _request($champ_prix);
		if (strpos($prix, ',') !== false) {
			$prix = str_replace(',','.',$prix);
			set_request($champ_prix, $prix);
		}
		if ($err=$verifier(_request($champ_prix),'decimal')){
			$erreurs[$champ_prix] = $err;
		}
	}

	if ($err=$verifier(_request('taxe'),'decimal',array('min' => 0,'max' => 100))){
		$erreurs['taxe'] = $err;
	}

	return $erreurs;
}

/**
 * Traitement du formulaire d'édition de abooffre
 *
 * Traiter les champs postés
 *
 * @uses formulaires_editer_objet_traiter()
 *
 * @param int|string $id_abo_offre
 *     Identifiant du abooffre. 'new' pour un nouveau abooffre.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abooffre source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abooffre, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Retours des traitements
 */
function formulaires_editer_abooffre_traiter_dist($id_abo_offre='new', $retour='', $lier_trad=0, $config_fonc='', $row=array(), $hidden=''){
	if ($taxe = _request('taxe')){
		set_request('taxe',$taxe/100);
	}

	$res = formulaires_editer_objet_traiter('abooffre',$id_abo_offre,'',$lier_trad,$retour,$config_fonc,$row,$hidden);

	if (test_plugin_actif('accesrestreint')
	  AND $id_abo_offre = $res['id_abo_offre']){
		$ins = array();
		$zones = _request('acces_zones');
		foreach($zones as $id_zone){
			if ($id_zone = intval($id_zone)){
				$ins = array('id_zone'=>$id_zone,'objet'=>'abooffre','id_objet'=>$id_abo_offre);
				sql_insertq("spip_zones_liens",$ins);
			}
		}
		sql_delete("spip_zones_liens","objet='abooffre' AND id_objet=".intval($id_abo_offre)." AND ".sql_in('id_zone',$zones,'NOT'));
	}

	return $res;
}

