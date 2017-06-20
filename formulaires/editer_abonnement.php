<?php
/**
 * Gestion du formulaire de d'édition de abonnement
 *
 * @plugin     Abonnements
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\Formulaires
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('inc/actions');
include_spip('inc/editer');

/**
 * Identifier le formulaire en faisant abstraction des paramètres qui ne représentent pas l'objet edité
 *
 * @param int|string $id_abonnement
 *     Identifiant du abonnement. 'new' pour un nouveau abonnement.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abonnement source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abonnement, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return string
 *     Hash du formulaire
 */
function formulaires_editer_abonnement_identifier_dist($id_abonnement = 'new', $retour = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = ''){
	return serialize(array(intval($id_abonnement)));
}

/**
 * Chargement du formulaire d'édition de abonnement
 *
 * Déclarer les champs postés et y intégrer les valeurs par défaut
 *
 * @uses formulaires_editer_objet_charger()
 *
 * @param int|string $id_abonnement
 *     Identifiant du abonnement. 'new' pour un nouveau abonnement.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abonnement source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abonnement, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Environnement du formulaire
 */
function formulaires_editer_abonnement_charger_dist($id_abonnement = 'new', $retour = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = ''){
	$valeurs = formulaires_editer_objet_charger('abonnement', $id_abonnement, '', $lier_trad, $retour, $config_fonc, $row, $hidden);

	if (!autoriser('modifier', 'abonnement', $id_abonnement)){
		return false;
	}

	$valeurs['_editer_echeance'] = ($valeurs['mode_echeance']=='tacite' ? ' ' : '');


	return $valeurs;
}

/**
 * Vérifications du formulaire d'édition de abonnement
 *
 * Vérifier les champs postés et signaler d'éventuelles erreurs
 *
 * @uses formulaires_editer_objet_verifier()
 *
 * @param int|string $id_abonnement
 *     Identifiant du abonnement. 'new' pour un nouveau abonnement.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abonnement source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abonnement, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Tableau des erreurs
 */
function formulaires_editer_abonnement_verifier_dist($id_abonnement = 'new', $retour = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = ''){

	$oblis = array('date_debut');
	if (!$row){
		$row = sql_fetsel('*', 'spip_abonnements', 'id_abonnement=' . intval($id_abonnement));
	}
	if ($row['abo_uid']){
		$oblis[] = 'abo_uid';
	}

	$erreurs = formulaires_editer_objet_verifier('abonnement', $id_abonnement, $oblis);

	include_spip('inc/date_gestion');
	$time_debut = verifier_corriger_date_saisie('debut', false, $erreurs);
	$time_echeance = verifier_corriger_date_saisie('echeance', false, $erreurs);
	$time_fin = verifier_corriger_date_saisie('fin', false, $erreurs);

	if (!isset($erreurs['date_echeance']) and !isset($erreurs['date_fin'])){
		if (!$time_echeance and !$time_fin){
			$erreurs['date_echeance'] = $erreurs['date_fin'] = _T('abonnement:erreur_date_echeance_ou_fin_obli');
		}
	}

	$verifier = charger_fonction('verifier', 'inc');

	foreach (array('prix_echeance') as $champ_prix){
		if ($err = $verifier(_request($champ_prix), 'decimal')){
			$erreurs[$champ_prix] = $err;
		}
	}

	if (!count($erreurs) and $row['mode_echeance']=='tacite'){

		$abo_uid = _request('abonne_uid');
		if ($abo_uid!=$row['abonne_uid'] and !_request('confirm_abo_uid')){
			$confirm = " <br /><input type='checkbox' class='checkbox' name='confirm_abo_uid' id='confirm_abo_uid' /> <label for='confirm_abo_uid'>" . _T('abonnement:label_confirmer_modification') . "</label>";
			$erreurs['abonne_uid'] = _T('abonnement:confirmer_changement_abonne_uid', array('presta' => $row['mode_paiement'])) . $confirm;
			$erreurs['message_erreur'] = '';
		}

		$prix_echeance = _request('prix_echeance');
		if (floatval($prix_echeance)!==floatval($row['prix_echeance']) and !_request('confirm_prix_echeance')){
			$confirm = " <br /><input type='checkbox' class='checkbox' name='confirm_prix_echeance' id='confirm_prix_echeance' /> <label for='confirm_prix_echeance'>" . _T('abonnement:label_confirmer_modification') . "</label>";
			$erreurs['prix_echeance'] = _T('abonnement:confirmer_changement_prix_echeance', array('presta' => $row['mode_paiement'])) . $confirm;
			$erreurs['message_erreur'] = '';
		}

		foreach (array('debut', 'echeance', 'fin') as $suffixe){
			$time = verifier_corriger_date_saisie($suffixe, false, $erreurs);
			if ($time){
				// on prend le nouveau jour en gardant l'heure initiale
				$d = date('Y-m-d', $time) . ' ' . end(explode(' ', $row['date_' . $suffixe]));
				if ($d!==$row['date_' . $suffixe] and !_request('confirm_date_' . $suffixe)){
					$confirm = " <br /><input type='checkbox' class='checkbox' name='confirm_date_" . $suffixe . "' id='confirm_date_" . $suffixe . "' /> <label for='confirm_date_" . $suffixe . "'>" . _T('abonnement:label_confirmer_modification') . "</label>";
					$erreurs['date_' . $suffixe] = _T('abonnement:confirmer_changement_date', array('presta' => $row['mode_paiement'])) . $confirm;
					$erreurs['message_erreur'] = '';
				}
			}
		}

	}


	return $erreurs;
}

/**
 * Traitement du formulaire d'édition de abonnement
 *
 * Traiter les champs postés
 *
 * @uses formulaires_editer_objet_traiter()
 *
 * @param int|string $id_abonnement
 *     Identifiant du abonnement. 'new' pour un nouveau abonnement.
 * @param string $retour
 *     URL de redirection après le traitement
 * @param int $lier_trad
 *     Identifiant éventuel d'un abonnement source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du abonnement, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Retours des traitements
 */
function formulaires_editer_abonnement_traiter_dist($id_abonnement = 'new', $retour = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = ''){

	$add_log = '';
	$erreurs = array();
	$row = sql_fetsel('*', 'spip_abonnements', 'id_abonnement=' . intval($id_abonnement));

	foreach (array('debut', 'echeance', 'fin') as $suffixe){
		$time = verifier_corriger_date_saisie($suffixe, false, $erreurs);
		if (!$time){
			set_request('date_' . $suffixe, $d = '0000-00-00 00:00:00');
		} else {
			// on prend le nouveau jour en gardant l'heure initiale
			set_request('date_' . $suffixe, $d = date('Y-m-d', $time) . ' ' . end(explode(' ', $row['date_' . $suffixe])));
		}
		if ($d!==$row['date_' . $suffixe]){
			$add_log .= ' date_' . $suffixe . ' (' . $row['date_' . $suffixe] . ' -> ' . $d . ')';
		}
	}

	$res = formulaires_editer_objet_traiter('abonnement', $id_abonnement, '', $lier_trad, $retour, $config_fonc, $row, $hidden);

	// si changement d'abonne_uid il faut aussi changer dans les transactions et les commandes
	// TODO le plus propre serait : faire un objet_modifier sur chaque transaction concernee
	// et les autres plugins se synchro automatiquement via pipeline pre_edition
	$abo_uid = _request('abonne_uid');
	if ($abo_uid!=$row['abonne_uid']){
		if (defined('_DIR_PLUGIN_BANK')){
			sql_updateq('spip_transactions', array('abo_uid' => $abo_uid), 'abo_uid=' . sql_quote($row['abonne_uid']));
		}
		if (defined('_DIR_PLUGIN_SOUSCRIPTION')){
			sql_updateq('spip_souscriptions', array('abonne_uid' => $abo_uid), 'abonne_uid=' . sql_quote($row['abonne_uid']));
		}
		if (defined('_DIR_PLUGIN_COMMANDES')){
			sql_updateq('spip_commandes', array('bank_uid' => $abo_uid), 'bank_uid=' . sql_quote($row['abonne_uid']));
		}
		$add_log .= ' abonne_uid (' . $row['abonne_uid'] . ' -> ' . $abo_uid . ')';
	}
	$prix_echeance = _request('prix_echeance');
	if (!is_null($prix_echeance) and floatval($prix_echeance)!==floatval($row['prix_echeance'])){
		$add_log .= ' prix_echeance (' . $row['prix_echeance'] . ' -> ' . $prix_echeance . ')';
	}

	if (strlen($add_log)){
		include_spip('inc/abos');
		$log = sql_getfetsel('log', 'spip_abonnements', 'id_abonnement=' . intval($id_abonnement));
		$log .= abos_log($add_log);
		sql_updateq('spip_abonnements', array('log' => $log), 'id_abonnement=' . intval($id_abonnement));
	}


	return $res;
}

